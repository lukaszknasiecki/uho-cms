<?php

use Huncwot\UhoFramework\_uho_fx;

require_once('model_app.php');

/**
 * Model class for maintaining page (list) views
 */

class model_app_page extends model_app
{
	/**
	 * default paging count
	 */
	private $paging = ['count' => 20];


	/**
	 * Loads and prepares content data for a page based on schema, parameters, filters, and user authorization.
	 *
	 * Handles:
	 * - URL parsing
	 * - Authorization and schema loading
	 * - Plugin on-load execution
	 * - Filtering (simple, advanced, and global)
	 * - Record fetching with paging
	 * - Record formatting and field rendering
	 * - Button generation, schema updates, and translation loading
	 *
	 * @param array|null $params Input parameters (e.g., URL, GET data)
	 * @return array Final page data including records, schema, buttons, filters, and UI flags
	 */
	public function getContentData($params = null)
	{
		// Extract page info and GET parameters
		$page = explode('/', $params['url']);
		$get = $params['get'];

		$page_with_filters = $page_with_params = $page[1];

		// Build filters from GET keys prefixed with "s_"
		$f = [];
		foreach ($get as $k => $v) {
			if (str_starts_with($k, 's_')) {
				$f[$k] = $v;
			}
		}

		// Append filters to the URL if they exist
		if (!empty($f)) {
			$query = http_build_query($f);
			$page_with_filters .= (strpos($page_with_filters, '?') !== false ? '&' : '?') . $query;
		}

		// Parse model and parameters from URL
		$params = explode(',', $page[1]);
		$model = $params[0];
		unset($params[0]);

		// Check authorization
		$auth = $this->checkAuth($model);
		if (!in_array($auth, [1, 2, 3])) exit('auth::error::[app_page]');

		// Load and validate schema
		$schema = $this->getSchema($model, false, ['numbers' => $params, 'return_error' => true]);
		if (isset($schema['result']) && $schema['result']===false)
			exit('<pre>'.$schema['message'].'</pre>');
		$schema=$this->getSchemaDepreceated($schema);		
		$this->validateSchema($schema, $model);
		$this->apporm->sqlCreator($schema, ['create' => 'auto', 'update' => 'alert']);
		$schema = $this->updateSchemaAuth($schema);

		// Execute plugin "on_load" hooks
		$before = _uho_fx::array_filter($schema['buttons_page'] ?? [], 'on_load', 1);
		if ($before) {
			require_once "model_app_plugin.php";
			foreach ($before as $plugin) {
				$pluginModel = new model_app_plugin($this->sql, $this->lang);
				$pluginModel->setParent($this);
				$pluginModel->setCfgPath($this->cfg_path);

				$pluginModel->getContentData([
					'params' => [
						'page' => $model,
						'page_with_params' => $page_with_params,
						'params' => $plugin['params'],
						'plugin' => $plugin['plugin'],
						'orm' => $this->apporm
					],
					'get' => []
				]);
			}
		}

		$this->url_search = ['type' => 'url_now'];



		// Filter and transform schema
		$schema = $this->removeNonListedFieldsFromSchema($schema, $page_with_params, $params, $auth);


		$buttons = $this->getSchemaButtons($schema, $params);
		$schema['fields'] = $this->updateSchemaLanguages($schema);
		$schema = $this->updateSchemaSorting($schema, $get['sort'] ?? null, $page_with_filters);		
		$schema = $this->updateSchemaRowWidth($schema);

		// Determine current page number
		$page_nr = isset($get['page']) ? max(1, (int)$get['page']) : 1;

		/*
     	* Handle filtering (global and advanced)
     	*/

		$filters = [];
		$filters_virtual = [];
		$filters_stack = [];
		$first = true;
		$global_search = isset($get['query']);

		foreach ($schema['fields'] as $k => $field)
			if (
				!in_array($field['type'], ['image', 'checkboxes', 'temp-elements']) &&
				(!empty($field['field_search']) && isset($get[$field['field_search']]) || $get['query'])
			) {				
				$searchKey = $field['field_search'] ?? null;
				$queryVal = $searchKey ? ($get[$searchKey] ?? null) : null;
				$value = $global_search ? $get['query'] : $queryVal;
	
				if (!$global_search) {
					$schema['fields'][$k]['searched'] = $value;
				}

				$vv = null;

				// Type-specific filter logic

				switch ($field['type']) {
					case 'uid':
						$vv = $value ?: "";
						break;
					case 'boolean':
					case 'integer':
						$vv = $value ?: 0;
						break;
					case 'select':
					case 'elements':
						/*
						$vv = [];
						if (!$global_search && !empty($field['source']['model'])) {
							$searchParams = [];
							if (!empty($field['source']['search']) && $field['source']['search_strict']) {
								foreach ($field['source']['search'] as $srcField) {
									$searchParams[$srcField] = $value;
								}
							}
							$ids = $this->apporm->get($field['source']['model'], $searchParams, false);
							$vv = _uho_fx::array_extract($ids, 'id') ?: 0;
						} else*/
							if ($global_search && !empty($field['options']))
							{
								foreach ($field['options'] as $opt)
								{
									if (isset($opt['label']) && str_contains($opt['label'], $value)) {
										$vv[] = $opt['value'];
									}
								}
							} else $vv=$value;
							
						break;

					case 'string':
					case 'text':
						$words = explode(' ', trim($value));
						if ($field['cms']['search'] === 'strict') {
							$vv = (count($words) === 1)
								? $value
								: ['type' => 'custom', 'join' => ' && ', 'value' => array_map(fn($w) => "`{$field['field']}`=\"{$this->sqlSafe($w)}\"", $words)];
						} else {
							$vv = (count($words) === 1)
								? ['operator' => '%LIKE%', 'value' => $value]
								: ['type' => 'custom', 'join' => ' && ', 'value' => array_map(fn($w) => "`{$field['field']}` LIKE \"%{$this->sqlSafe($w)}%\"", $words)];
						}
						break;
				}

				// Apply filters based on field type
				if ($field['type'] === 'virtual') {
					$filters_virtual[$field['field']] = $vv;
				} elseif ($field['type'] === 'date') {
					$filters[$field['field']] = ['operator' => '%LIKE%', 'value' => $vv];
				} elseif ($field['type'] === 'integer' && is_numeric($vv)) {
					$filters[$field['field']] = (int)$vv;
				} elseif ($vv !== null) {
					$filters[$field['field']] = $vv;
				}


				// Build filter label stack
				if (!$global_search || $first) {
					
					$first = false;

					if (!empty($field['options']) && !$global_search) {
						if ($value === '[not_null]') {
							$filters[$field['field']] = ['operator' => '!=', 'value' => ''];
							$label_value = $field['label'];
						} else {
							$value = _uho_fx::array_change_keys($field['options'], 'value', 'label')[$value] ?? $value;
						}
					}

					if ($field['type'] == 'boolean')
					{
						if ($this->lang == 'en') $no = 'No'; else $no = 'Nie';
						if (!$label_value) $label_value = $field['label'];
						if (!$value && $field['label_not']) $label_value = $field['label_not'];
						elseif (!$value) $label_value = $no . '-' . $field['label'];
					} elseif (!$label_value)  $label_value = $value;


					$filters_stack[] = [
						'label' => $field['label'],
						'label_value' => $label_value,
						'value' => $value,
						'url' => ['type' => 'url_now', 'getRemove' => ['query', $searchKey]]
					];
				}
			}


		// Convert global search to unified custom query
		if ($global_search) {
			$searchSchema = $schema;
			$searchSchema['filters'] = $filters;
			$filterSet = $this->apporm->getFiltersQueryArray($searchSchema);
			$filters = $filterSet
				? ['search' => ['type' => 'custom', 'join' => '||', 'value' => $filterSet]]
				: [];
			$filters_stack = [];
		}

		// Merge with schema-defined filters
		if (!empty($schema['filters'])) {
			$filters = array_merge($schema['filters'], $filters);
		}


		// Fetch records
	
		$all = $this->apporm->get($schema, $filters, false, null, null, ['count' => true]);
		$_SESSION['page_filters'][$model] = $filters;

		$offset = ($page_nr - 1) * $this->paging['count'];
		$limit = $this->paging['count'];
		
		$records = $this->apporm->get($schema, $filters, false, null, "$offset,$limit");

		if (!$global_search) {
			$records = $this->apporm->filterResults($schema, $records, $filters_virtual, false);
		}

		// Format each record

		foreach ($records as $i => $record) {
			$records[$i] = [
				'id' => $record['id'],
				'values' => $record,
				'highlighted' => ($get['highlight'] ?? null) == $record['id'],
				'url_edit' => ['type' => 'edit', 'page' => $model, 'params' => $params, 'record' => $record['id']],
				'url_view' => ['type' => 'view', 'page' => $model, 'params' => $params, 'record' => $record['id']],
				'url_remove' => ['type' => 'remove', 'page' => $model, 'record' => $record['id'], 'params' => $params]
			];

			if (!empty($schema['layout']['link'])) {
				$records[$i]['url_click'] = $this->getTwigFromHtml($schema['layout']['link'], $record);
			}

			// Apply action restrictions
			if (!empty($schema['disable']) && in_array('edit', $schema['disable'])) {
				unset($records[$i]['url_edit']);
			}
			if (!empty($schema['disable']) && in_array('remove', $schema['disable'])) {
				unset($records[$i]['url_remove']);
			}
			if (empty($schema['enable']) || !in_array('view', $schema['enable'])) {
				unset($records[$i]['url_view']);
			}
		}

		// Render source labels for certain fields
		foreach ($schema['fields'] as $field) {
			if (isset($field['source']['label'])) {
				foreach ($records as $i => $rec) {
					$val = $rec['values'][$field['field']] ?? null;
					if (in_array($field['type'], ['elements', 'checkboxes'])) {
						$val = array_map(fn($v) => $this->getTwigFromHtml($field['source']['label'], $v), (array) $val);
						$records[$i]['values'][$field['field']] = implode(', ', $val);
					} else {
						$records[$i]['values'][$field['field']] = $this->getTwigFromHtml($field['source']['label'], $val ?: []);
					}
				}
			}
		}

		// Paging
		$paging = $this->getPaging($this->paging['count'], $page_nr, $all);
		// record HTML reformatting
		$records = $this->updateRecordsValues($schema, $records);

		// Load translation JSON (if available)
		$translatePath = __DIR__ . '/model_app_page.json';
		$translate = file_exists($translatePath)
			? json_decode(file_get_contents($translatePath), true)
			: [];

		// Schema output URLs
		$schema['url_write'] = ['type' => 'write', 'page' => $model, 'params' => $params, 'record' => '%id%'];
		$schema['url_sort'] = ['type' => 'write', 'page' => $model, 'params' => $params, 'record' => 'sort'];

		// Skip update for certain keys
		foreach ($records as &$rec) {
			$rec['skip_url_update'] = ['values'];
		}

		// Return the final data structure
		return [
			'records' => $records,
			'filters' => $filters_stack,
			'schema' => $schema,
			'schema_editor' => str_starts_with($schema['table'], 'serdelia_'),
			'buttons' => $buttons,
			'paging' => $paging,
			'schema_edit' => true,
			'csrf_token_value' => $this->csrf_token_value(),
			'translate' => $translate[$this->lang] ?? [],
			'lang' => $this->lang
		];
	}

	/**
	 * Updates model schema for the Page View module.
	 * 
	 * - Applies label and help text transformations
	 * - Adjusts field configurations based on type
	 * - Reorders fields based on 'position_after' setting
	 * - Adds a schema editor button for authorized editors
	 * 
	 * @param array $schema           Current model schema
	 * @param string $page_with_params Full page identifier with parameters
	 * @param array $params           Parameter values passed in the request
	 * @return array                  Modified schema
	 */

	public function updateSchemaForPage($schema, $page_with_params, $params)
	{
		// Save page identifier for use in rendering or labeling
		$schema['page_with_params'] = $page_with_params;

		// Update label from nested 'label.page' or fallback to label from menu
		if (isset($schema['label']['page'])) {
			$schema['label'] = $schema['label']['page'];
		}
		if (!$schema['label']) {
			$schema['label'] = $this->getSchemaLabelFromMenu($page_with_params);
		}

		// Format and expand help section if defined
		if (!empty($schema['help'])) {
			if (!is_array($schema['help'])) {
				$schema['help'] = ['label' => $schema['help']];
			}

			// Use cookie to check if help should be hidden
			$hidden = (@$_COOKIE['serdelia_page_help_' . $schema['page_with_params']] == 1);

			// Convert help text using Twig-like template engine
			$schema['help']['label'] = $this->getTwigFromHtml($schema['help']['label'], ['params' => $params]);
			$schema['help']['hidden'] = $hidden;
		}

		/**
		 * Shortcuts
		*/

		if (isset($schema['shortcuts']))
		{
			foreach ($schema['shortcuts'] as $k=>$v)
			{
				$schema['shortcuts'][$k]['url']=['type'=>'url_now','get'=>@$v['link']['query']];
			}
		}

		/**
		 * Loop through all fields and normalize configuration
		 */
		foreach ($schema['fields'] as $k => $v) {
			// Override multilingual label
			if (@is_array($v['label'])) {
				$schema['fields'][$k]['label'] = $v['label']['page'];
			}

			// convert list to object if string

			if (!empty($v['cms']['list']) && is_string($v['cms']['list']))
			{
				$schema['fields'][$k]['cms']['list'] = ['type' => $v['cms']['list']];				
			}

			switch ($v['type']) {

				case "select":
					// Normalize option values for selects if source ID is present
					if (!empty($v['source']['id'])) {
						foreach ($v['options'] as $k2 => $v2) {
							$schema['fields'][$k]['options'][$k2]['value'] = $v2['values'][$v['source']['id']];
						}
					}
					break;

				case "image":
					
					// Ensure list view for images is well-formed
					if (isset($v['cms']['list']))
					{
						$list=@$v['cms']['list'];
						if (!$list) $list=$v['cms']['list'] = [];
						if (empty($list['folder']))
						{
							if (!is_array($v['cms']['list']))
								$v['cms']['list']=[];
							$v['cms']['list']['folder'] = 
								$v['images'][1]['folder'];
						}
						if (!empty($list['src_blank']))
						{
							$v['cms']['list']['src_blank'] = $this->cfg_path . '/assets/' . $list['src_blank'];
						}
						$schema['fields'][$k]['cms']['list'] = $v['cms']['list'];

					}
					break;

				case "file":
					// Similar handling for file-type images (e.g., PNG thumbnails)
					if (isset($v['list']) && $v['extension'] == 'png') {
						if (!is_array($v['list'])) $v['list'] = [];
						if (empty($v['list']['folder'])) $v['list']['folder'] = $v['images'][1]['folder'];
						if (empty($v['list']['width'])) $v['list']['width'] = 100;
						if (empty($v['list']['height'])) $v['list']['height'] = 100;
						$schema['fields'][$k]['list'] = $v['list'];
					}
					break;
			}

			// If any field has search enabled, set schema search flag
			if (!empty($v['cms']['search'])) {
				$schema['search'] = true;
			}



		}

		

		/*
     * Reorder fields if 'position_after' is defined
     */
		/*
		$reordered_fields = [];

		foreach ($schema['fields'] as $field) {
			if (isset($field['list']['position_after'])) {
				$target = $field['list']['position_after'];

				// Try to insert after the specified field
				$pos = _uho_fx::array_filter($reordered_fields, 'field', $target, ['first' => true, 'keys' => true]);

				if (!$target) {
					// Place at the beginning
					$reordered_fields = array_merge([$field], $reordered_fields);
				} elseif (isset($pos) && isset($reordered_fields[$pos])) {
					// Insert at specific position
					$reordered_fields = array_merge(
						array_slice($reordered_fields, 0, $pos + 1),
						[$field],
						array_slice($reordered_fields, $pos + 1)
					);
				} else {
					// Fallback: append to end
					$reordered_fields[] = $field;
				}
			} else {
				$reordered_fields[] = $field;
			}
		}

		$schema['fields'] = $reordered_fields;
		*/

		/*
		     * Add schema editor button if user is allowed
     	*/
		if (!isset($schema['buttons_page'])) {
			$schema['buttons_page'] = [];
		}

		$is_serdelia_table = substr($schema['table'], 0, 9) == 'serdelia_';

		if ($this->serdelia_schema_editor && !$is_serdelia_table) {
			array_unshift(
				$schema['buttons_page'],
				[
					'icon' => 'settings',
					'url' => 'edit/serdelia_models/' . $schema['model_name'],
					'class' => 'default serdelia-button-schema-edit'
				]
			);
		}



		if (!$this->serdelia_schema_editor && $is_serdelia_table) {
			exit('Schema edit not allowed on this domain');
		}

		return $schema;
	}

	/**
	 * Updates the schema with sorting configuration and generates sort URLs for fields.
	 *
	 * @param array $schema The schema array to update.
	 * @param string|null $sort Comma-separated sort field and direction (e.g., "title,ASC").
	 * @param string $page_with_filters The page identifier including any filters for sorting links.
	 * @return array Updated schema with sorting information.
	 */
	private function updateSchemaSorting(array $schema, ?string $sort, string $page_with_filters): array
	{
		// If sorting is provided (e.g., "field,ASC"), set it into the schema.
		if ($sort)
		{
			[$field, $direction] = explode(',', $sort);
			if (empty($direction)) $direction='ASC';
			$schema['order'] = ['field' => $field, 'sort' => $direction];
		}
		

		// Default sorting fallback to the first field if not defined.
		if (empty($schema['order'])) {
			$schema['order'] = ['field'=>$schema['fields'][0]['field'],'sort'=>'ASC'];
		}


		// Ensure the order format is consistent (associative array).
		if (!is_array($schema['order'])) {
			$schema['order'] = [
				'field' => $schema['order']['field'],
				'sort'  => $schema['order']['sort']
			];
		}

		// Add URL sorting links and modify field names with ",DESC" where needed.
		foreach ($schema['fields'] as $k => $v) {
			if ($v['field'] === $schema['order']['field'] && $schema['order']['sort'] !== 'DESC') {
				$v['field'] .= ',DESC';
			}

			if (isset($v['hash'])) {
				unset($schema['fields'][$k]['url_sort']);
			} elseif ($v['sort'] !== false) {
				$schema['fields'][$k]['url_sort'] = [
					'type' => 'page',
					'page' => $page_with_filters,
					'sort' => $v['field']
				];
			}
		}

		return $schema;
	}

	/**
	 * Removes fields not marked for listing, adjusts schema based on context, and enforces auth-based permissions.
	 *
	 * @param array $schema The schema array to clean.
	 * @param string $page_with_params The page identifier with parameters.
	 * @param array $params Additional parameters affecting schema.
	 * @param int $auth Authorization level of the current user.
	 * @return array Updated schema with cleaned fields and adjusted layout/auth settings.
	 */
	private function removeNonListedFieldsFromSchema(array $schema, string $page_with_params, array $params, int $auth): array
	{
		// Remove fields that are not marked to be listed.
		foreach ($schema['fields'] as $k => $v) {
			if (empty($v['cms']['list'])) {
				unset($schema['fields'][$k]);
			}
		}

		// Apply schema transformations based on sources and page context.
		$schema = $this->updateSchemaSources($schema);
		$schema = $this->updateSchemaForPage($schema, $page_with_params, $params);
		if (empty($schema['fields'])) {
			exit('<pre>No fields to display in the list view. Please add fields[].cms.list in the schema configuration.</pre>');
		}

		// Adjust paging count based on field types or layout configuration.
		if (_uho_fx::array_filter($schema['fields'], 'type', 'order')) {
			$this->paging = ['count' => 1000];
		} elseif (!empty($schema['layout']['type']) && $schema['layout']['type'] === 'grid') {
			$this->paging = ['count' => 50];
		}

		if (!empty($schema['layout']['count'])) {
			$this->paging = ['count' => $schema['layout']['count']];
		}

		// Restrict actions for unauthorized users.
		if (!in_array($auth, [2, 3])) {
			if ($auth==1) $remove=['add', 'remove'];
				else $remove=['add', 'remove', 'edit'];
			$schema['disable'] = array_merge($schema['disable'] ?? [], $remove);
			$schema['enable'] = array_merge($schema['enable'] ?? [], ['view']);
		}

		// Remove editable fields in list
		if (!in_array($auth, [2, 3])) {
			foreach ($schema['fields'] as $k=>$v)
			{
				if (isset($v['cms']['list']) && $v['cms']['list']=='edit')
					$schema['fields'][$k]['cms']['list']='show';
				if (!empty($v['list']['type']) && $v['list']['type']=='edit')
					$schema['fields'][$k]['list']['type']='show';
			}
		}

		return $schema;
	}

	/**
	 * Calculates and assigns appropriate widths for visible, listable fields in the schema.
	 *
	 * @param array $schema The schema to update.
	 * @return array Schema with calculated widths for each field.
	 */
	private function updateSchemaRowWidth(array $schema): array
	{
		$totalFixedWidth = 0;
		$dynamicFieldCount = 0;

		// Determine total width and count dynamic fields.
		foreach ($schema['fields'] as $k => $v)
			if (in_array($v['list']['type'], ['show', 'order', 'edit'])) {

				if (empty($v['list']['width'])) {
					// Assign default width based on field type if none set.
					if (in_array($v['type'], ['integer', 'boolean', 'date', 'order'])) {
						$schema['fields'][$k]['list']['width'] = 10;
					} elseif ($v['type'] === 'datetime') {
						$schema['fields'][$k]['list']['width'] = 25;
					}
				}

				if (!empty($schema['fields'][$k]['list']['width'])) {
					$totalFixedWidth += $schema['fields'][$k]['list']['width'];
				} else {
					$dynamicFieldCount++;
				}
			}

		// Distribute remaining width evenly among dynamic fields.
		$availableWidth = max(10, intval((100 - $totalFixedWidth) / max(1, $dynamicFieldCount)));

		foreach ($schema['fields'] as $k => $v)
			if (in_array($v['list']['type'], ['show', 'order', 'edit']) && empty($v['list']['width'])) {
				$schema['fields'][$k]['list']['width'] = $availableWidth;
			}

		return $schema;
	}

	/**
	 * Expands language fields in the schema by duplicating them for each language supported.
	 *
	 * @param array $schema The schema containing fields and language definitions.
	 * @return array Schema fields array with language-specific duplicates.
	 */
	private function updateSchemaLanguages(array $schema): array
	{
		$result = [];

		foreach ($schema['fields'] as $field) {
			// If the field includes a :lang placeholder, create entries per language.
			if (strpos($field['field'], ':lang') !== false) {
				foreach ($schema['langs'] as $langData) {
					$localizedField = $field;
					$localizedField['field'] = str_replace(':lang', $langData['lang_add'], $field['field']);
					$localizedField['lang'] = $langData['lang'];

					if (!empty($field['list']['languages']) || $langData === reset($schema['langs'])) {
						$result[] = $localizedField;
					}
				}
			} else {
				$result[] = $field;
			}
		}

		// Add search field name if applicable.
		foreach ($result as $k => $v) {
			if (!empty($v['cms']['search'])) {
				$result[$k]['field_search'] = 's_' . $v['field'];
			}
		}

		return array_values($result);
	}

	/**
	 * Generates a list of buttons (e.g. back, add) based on the schema and current parameters.
	 *
	 * @param array $schema The current schema definition.
	 * @param array $params Parameters passed for context (e.g. path/record keys).
	 * @return array Array of buttons (label, icon, url).
	 */
	private function getSchemaButtons(array $schema, array $params): array
	{
		$buttons = [];

		// Determine the 'back' button URL based on schema structure
		$url = null;

		if (($schema['structure']['parent']['parent_page'] ?? '') === 'null') {
			$url = '';
		} elseif (!empty($schema['structure']['parent']['page'])) {
			// Construct parent page URL from params
			$p = $params;
			$record = array_pop($p); // last param is considered the record ID
			$url = [
				'type'   => 'edit',
				'page'   => $schema['structure']['parent']['parent_page'],
				'params' => $p,
				'record' => $record
			];
		}

		// Override with explicit back page, if defined
		if (!empty($schema['back']['page'])) {
			$url = $schema['back']['page'];
		}

		// Add "back" button if URL is defined
		if (isset($url)) {
			$buttons[] = [
				'label' => 'back',
				'icon'  => 'back',
				'url'   => $url
			];
		}

		// Append any schema-defined buttons
		if (!empty($schema['buttons_page']) && is_array($schema['buttons_page'])) {
			$buttons = array_merge($buttons, $schema['buttons_page']);
		}

		// Add "add" button if it's not disabled
		if (empty($schema['disable']) || !in_array('add', $schema['disable'], true))
		{
			
			$addLabel = $schema['buttons_page_labels']['add'] ?? 'add';
			$buttons[] = [
				'label' => $addLabel,
				'icon'  => 'add',
				'url'   => [
					'type'   => 'add',
					'page'   => $schema['model_name'] ?? $schema['table'],
					'params' => $params
				]
			];
		}
		
		// Final update using plugin/customization hook
		return $this->updateSchemaButtons($buttons, $schema, null, $params, $_GET ?? []);
	}

	/**
	 * Generates a paging structure with navigation URLs and record range indicators.
	 *
	 * @param int $per_page  Number of records per page.
	 * @param int $page_nr   Current page number (1-based).
	 * @param int $all       Total number of records available.
	 * @return array         Paging structure including record range and navigation links.
	 */
	private function getPaging(int $per_page, int $page_nr, int $all): array
	{
		// Return empty if there are no records to paginate.
		if ($all <= 0) {
			return [];
		}

		// Calculate the last record number for the current page.
		$to = min($per_page * $page_nr, $all);

		// Calculate total number of pages.
		$total_pages = (int) ceil($all / $per_page);

		// Base paging structure
		$paging = [
			'page'    => $page_nr,
			'all'     => $total_pages,
			'records' => [
				'from' => ($per_page * ($page_nr - 1)) + 1,
				'to'   => $to,
				'all'  => $all
			],
			'nav' => [
				'url_first' => ['type' => 'url_now', 'get' => ['page' => 1]],
				'url_prev'  => ['type' => 'url_now', 'get' => ['page' => max(1, $page_nr - 1)]],
				'url_next'  => ['type' => 'url_now', 'get' => ['page' => min($total_pages, $page_nr + 1)]],
				'url_last'  => ['type' => 'url_now', 'get' => ['page' => $total_pages]]
			]
		];

		// If there’s more than one page, build pagination links (sliding window of 9 pages max).
		if ($total_pages > 1) {
			$range_start = max(1, $page_nr - 4);
			$range_end   = min($total_pages, $range_start + 8);

			// Adjust start if end is near total and range is too short
			$range_start = max(1, $range_end - 8);

			$pages = [];
			for ($i = $range_start; $i <= $range_end; $i++) {
				$pages[] = [
					'nr'  => $i,
					'url' => ['type' => 'url_now', 'get' => ['page' => $i]]
				];
			}

			$paging['nav']['pages'] = $pages;
		}

		return $paging;
	}

	/**
	 * Updates the record values in the schema with rendered HTML based on virtual or list field definitions.
	 *
	 * - For 'virtual' fields with an 'html' template, renders custom HTML per record.
	 * - For fields with a 'list' HTML template, replaces raw value with a rendered label and HTML.
	 *
	 * @param array $schema  The schema containing field definitions.
	 * @param array $records The data records to update.
	 * @return array         The updated records with rendered values.
	 */

	private function updateRecordsValues(array $schema, array $records): array
	{
		foreach ($schema['fields'] as $field)
			if (!empty($field['list']['value'])) {
				foreach ($records as $k => $record) {
					$records[$k]['values'][$field['field']] = $this->getTwigFromHtml($field['list']['value'], $record['values']);
				}
			}


		return $records;
	}
}
