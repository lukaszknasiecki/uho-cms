<?php

use Huncwot\UhoFramework\_uho_fx;

require_once('model_app.php');

/**
 * Model class for handling edit record functionality in the CMS.
 */
class model_app_edit extends model_app
{
	/**
	 * Determines if the current view is read-only.
	 */
	private $view = false;

	/**
	 * Translation strings used across UI in multiple languages.
	 */
	private $translate = [];

	/**
	 * Main method to prepare schema and data for the record editing module.
	 *
	 * @param array|null $params Input data containing 'url' and 'get'.
	 * @return array|null Returns structured data required for rendering the edit form.
	 */
	public function getContentData($params = null)
	{

		$this->translate = json_decode(file_get_contents(__DIR__ . '/model_app_edit.json'), true);

		$translate = $this->getTranslateByLang($this->lang);
		$id = $this->findIdByUrl($params['url']);
		$page = explode('/', $params['url']);
		$get = $params['get'];
		$post = $_POST;

		$params = explode(',', $page[1]);
		$page_with_params = $page[1];
		$model = $params[0];
		unset($params[0]);

		// Authorization check

		if (!$this->checkAuth($model, [2, 3])) {
			if ($this->checkAuth($model, [1])) $this->view = true;
			else exit('auth::error::[app_edit]');
		}

		$record = null;

		// Load base schema and prepare ORM

		$schema = $this->getSchema($model, false, ['numbers' => $params, 'return_error' => true]);

		if ($this->getDebugMode()) {
			if ($this->getStrictSchema()) $s = $schema;
			else $s = $this->getSchemaDepreceated($schema);
			unset($s['structure']);
			unset($s['langs']);
			unset($s['sortable']);
			$schema_validation = $this->orm->validateSchema($s, $this->getStrictSchema());
			if ($schema_validation['errors']) {
				//$schema_validation['url']=['type'=>'url_now','get'=>['rebuild_schema'=>'1']];
			}
		} else $schema_validation = null;

		$this->validateSchema($schema, $model);
		$this->apporm->creator($schema, ['create' => 'auto', 'update' => 'alert']);


		// Generate edit schema (populated with record data)

		$schema = $this->getSchemaForEdit($model, $record, $params, $id, $post, true);
		$schema = $this->getSchemaDepreceated($schema);

		// Update data with Helper Models

		if (isset($schema['helper_models'])) {
			/*
			foreach ($schema['helper_models'] as $k=>$v)
			{
				if ($params)
				foreach ($params as $kk=>$vv)
				if (is_string($v['record']))
					$v['record']=str_replace('%'.$kk.'%', $vv, $v['record']);

				$schema['helper_models'][$k]=$this->apporm->getJsonModel($v['model'], ['id'=>$v['record']],true);
			}*/
			$replace = $record;
			$replace['helper_models'] = $schema['helper_models'];
			$schema['label']['edit'] = $this->getTwigFromHtml($schema['label']['edit'], $replace);
		}

		// Execute "on_load" plugins if configured

		$before = _uho_fx::array_filter(@$schema['buttons_edit'], 'on_load', 1);
		if ($before && $record) {
			require_once("model_app_plugin.php");
			foreach ($before as $v) {
				$class = new model_app_plugin($this->sql, $this->lang);
				$class->setParent($this);
				$class->setCfgPath($this->cfg_path);

				$pluginParams = [
					'page' => $model,
					'page_with_params' => $page_with_params,
					'params' => $v['params'],
					'record' => $record['id'],
					'plugin' => $v['plugin'],
					'orm' => $this->apporm
				];

				$class->getContentData(['params' => $pluginParams, 'get' => []]);
			}
			// Refresh schema after plugin execution

			$schema = $this->getSchemaForEdit($model, $record, $params, $id, $post);
		}

		// Add backup URLs if applicable

		if (
			$this->is_backup() && $id && $this->checkAuth($model, [3]) &&
			(!isset($schema['disable']) || !in_array('backup', $schema['disable']))
		) {
			$schema['url_backup'] = "page/cms_backup?s_page={$schema['table']}&s_record={$id}";
			$schema['url_backup_media'] = "page/cms_backup_media?s_page={$schema['table']}&s_record={$id}";
		}

		// Update schema with current state and permissions

		$schema = $this->updateSchemaSources($schema, $record, $params);
		$schema = $this->updateSchemaAuth($schema);
		$schema = $this->updateSchemaRecord($schema, $record, $params);
		$record = $this->updateSchemaForEdit($schema, $page_with_params, $record, $translate, $params);

		// Enforce view-only mode if required

		if ($this->view) {
			foreach ($schema['fields'] as &$field) {
				$field['edit'] = false;
			}
		}

		// Handle POST actions for dynamic UI

		if (!$this->view && isset($post['action'])) {
			switch ($post['action']) {
				case "elements_double":
					$result = [];
					$field = _uho_fx::array_filter($schema['fields'], 'field', $post['field'], ['first' => true]);
					if ($field) {
						$modelInfo = $field['source_double'][$post['value'] - 1] ?? null;
						if ($modelInfo) {
							$result = $this->apporm->getJsonModelShort($modelInfo['model'], null, ['lang' => true]);
							foreach ($result as &$r) {
								$r['value'] = $modelInfo['slug'] . ':' . $r['id'];
								$label = is_array($r['_model_label']) ? $r['_model_label']['page'] : $r['_model_label'];
								$r['sublabel'] = $label;
								$r['image'] = $r['image'];
								if (!$r['label']) $r['label'] = '[brak tytułu, id=' . $r['id'] . ']';
							}
							$result = _uho_fx::array_multisort($result, 'label');
						}
					}
					exit(json_encode($result));
					break;

				case "search_source":
					$result = $this->apiSearchSource($schema, substr($post['field'], 2), $post['value']);
					exit(json_encode($result));
					break;
			}
		}

		// Prepare field tabs

		$tabs = [];
		foreach ($schema['fields'] as $field) {
			if ($field['cms']['tab']) {
				$tabs[] = ['id' => count($tabs) + 1, 'label' => $field['cms']['tab'], 'count' => 0];
			}
			if (!empty($tabs) && ($field['field'] || $field['type'] === 'plugin') && !$field['hidden'] && !in_array($field['type'], ['uid', 'order'])) {
				$tabs[count($tabs) - 1]['count']++;
			}
		}

		// Activate first visible tab, hide empty ones

		$iTabs = 0;
		$first = false;
		foreach ($tabs as &$tab) {
			if (!$tab['count']) {
				$tab['hidden'] = true;
			} else {
				$iTabs++;
				if (!$first) {
					$tab['active'] = true;
					$first = true;
				}
			}
		}

		// Remove tabs if there's only one

		if ($iTabs === 1) {
			$tabs = [];
			foreach ($schema['fields'] as &$field) {
				unset($field['cms']['tab']);
			}
		}

		// Set up schema navigation URLs

		$schema['url_back'] = $schema['back']['edit']
			? $this->fillPattern($schema['back']['edit'], ['keys' => $record, 'numbers' => $params])
			: ['type' => 'page', 'page' => $model, 'params' => $params];

		if (isset($_SESSION['pages'][$page_with_params]['query']) && is_array($schema['url_back'])) {
			$schema['url_back']['query'] = $_SESSION['pages'][$page_with_params]['query'];
		}

		$schema['url_back_form'] = $schema['url_back'];
		if (isset($schema['url_back_form']['query'])) {
			parse_str($schema['url_back_form']['query'], $query);
			unset($query['highlight']);
			$schema['url_back_form']['query'] = http_build_query($query);
		}

		$schema['url_write'] = [
			'type' => 'write',
			'page' => $model,
			'params' => $params,
			'record' => $id ?: 'new'
		];
		$schema['url_new'] = [
			'type' => 'edit',
			'page' => $model,
			'params' => $params,
			'record' => '%new%'
		];

		// Hide system fields like 'order'

		foreach ($schema['fields'] as &$field) {
			if (in_array($field['type'], ['order'])) {
				if (!isset($field['cms'])) $field['cms'] = [];
				$field['cms']['hidden'] = true;
			}
		}

		// Prepend back button

		if (!$schema['buttons_edit']) $schema['buttons_edit'] = [];
		array_unshift($schema['buttons_edit'], ['label' => 'back', 'url' => $schema['url_back'], 'icon' => 'back']);

		// Prepare available languages

		$langs = [];
		foreach ($this->appnow['langs'] as $lang) {
			if ($lang['active']) $langs[$lang['lang']] = true;
		}

		// Validate schema

		$validator = $this->apporm->schemaValidate($schema);
		if (!$validator['result']) {
			exit(implode('<br>', $validator['errors']));
		}

		// Final return payload

		return [
			'skip_url_update' => ['record'],
			'lang' => $this->lang,
			'langs' => $langs,
			'lightbox' => _uho_fx::getGet('mode') === 'lightbox',
			'csrf_token_value' => $this->csrf_token_value(),
			'record' => $record,
			'schema' => $schema,
			'schema_editor' => str_starts_with($schema['table'], 'serdelia_'),
			'schema_validation' => $schema_validation,
			'paging' => ['page' => 1, 'records' => ['from' => 1, 'to' => 2, 'all' => 2]],
			'translate' => $translate,
			'tabs' => $tabs,
			'view_only' => $this->view
		];
	}

	/**
	 * Adds/updates field metadata and plugins in the schema.
	 */
	private function updateSchemaRecord($schema, $record, $params)
	{
		if (!$record) {
			$schema['buttons_edit'] = [];
		}

		if ($schema['buttons_edit']) {
			$schema['buttons_edit'] = $this->updateSchemaButtons($schema['buttons_edit'], $schema, $record, $params);
		}

		foreach ($schema['fields'] as $k => $v) {
			// Handle dynamic filters
			if ($v['source']['filters']) {
				foreach ($v['source']['filters'] as $filterField => $filterVal) {
					$i = _uho_fx::array_filter($schema['fields'], 'field', $filterField, ['first' => true]);
					if ($i && $filterField !== $v['field']) {
						$schema['fields'][$k]['source']['parent'] = $i['cms_field'];
					}
				}
			}

			// Bind plugin metadata
			if ($v['type'] === 'plugin') {
				$plugin = null;
				if (!empty($v['settings']['plugin'])) {
					$plugin = _uho_fx::array_filter($schema['buttons_edit'], 'plugin', $v['settings']['plugin']);
				} elseif (!empty($v['settings']['page'])) {
					$plugin = _uho_fx::array_filter($schema['buttons_edit'], 'page', $v['settings']['page']);
				}

				if ($plugin) {
					$plugin = array_values($plugin);
					$nr = ($v['plugin_nr'] ?? 1) - 1;
					$plugin = $plugin[$nr];

					$schema['fields'][$k]['url'] = $plugin['url'];
					if (!isset($v['cta']) && isset($plugin['label'])) {
						$schema['fields'][$k]['cta'] = $plugin['label'];
					}
					if (!isset($v['icon']) && isset($plugin['icon'])) {
						$schema['fields'][$k]['icon'] = $plugin['icon'];
					}
				}
			}
		}

		return $schema;
	}

	/**
	 * Extracts record ID from the given URL string.
	 */
	public function findIdByUrl($url)
	{
		$page = explode('/', $url);
		return $page[2] ?? null;
	}

	/**
	 * Returns the language translation array.
	 */
	public function getTranslateByLang($lang)
	{
		return $this->translate[$lang] ?? [];
	}

	/**
	 * Handles live source searching (e.g. AJAX for dropdowns).
	 */
	private function apiSearchSource($schema, $field, $value)
	{
		$value = trim($value);
		$field = _uho_fx::array_filter($schema['fields'], 'field', $field, ['first' => true]);

		if (!$field) return ['result' => false, 'message' => 'field not found'];

		$items = [];
		$searchSchema = $this->apporm->getJsonModelSchema($field['source']['model']);
		$searchFields = $field['source']['search'] ?? ['label'];
		$filter = [];

		if (isset($field['source']['search_strict'])) {
			foreach ($searchFields as $f) {
				$filter[$f] = $value;
			}
		} else {
			$values = explode(' ', $value);
			$searchClauses = [];

			foreach ($searchFields as $f) {
				$subClauses = array_filter(array_map(fn($v) => "$f LIKE \"%$v%\"", $values));
				$searchClauses[] = implode(' && ', $subClauses);
			}

			$filter = ['search' => ['type' => 'custom', 'join' => '||', 'value' => $searchClauses]];
		}

		$items = $this->apporm->getJsonModel($field['source']['model'], $filter, false, null, '0,10');
		foreach ($items as &$item) {
			$item['label'] = $this->getTwigFromHtml($field['source']['label'], $item);
			if (isset($searchSchema['model']['image'])) {
				$item['image'] = ['thumb' => $this->getTwigFromHtml($searchSchema['model']['image'], $item)];
				if ($this->s3) $item['image']['thumb'] = $this->s3->getFilenameWithHost($item['image']['thumb']);
			}
		}

		return ['result' => true, 'items' => $items];
	}
}
