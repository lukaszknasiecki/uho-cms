<?php

use Vimeo\Vimeo;
use JamesHeinrich\GetID3;
use Huncwot\UhoFramework\_uho_fx;
use Huncwot\UhoFramework\_uho_thumb;

require_once('model_app.php');

/**
 * Model for write object action
 */

class model_app_write extends model_app
{
	private $logs_model, $logs_id;
	private $translate = [
		'en' =>
		[
			'unique_field' => 'Object with this value already exists',
		],
		'pl' =>
		[
			'unique_field' => 'Pole o tej wartości (%value%) już istnieje',
		]
	];

	/**
	 * Handles data update or sorting actions for a writeable model record.
	 *
	 * Depending on the request, this method either performs a record update
	 * or a reordering operation (via drag & drop), including plugin hooks.
	 *
	 * @param array|null $params URL or router data (typically includes the model and record ID)
	 * @return void (Outputs JSON and exits)
	 */
	public function getContentData($params = null): void
	{
		// Load language-specific translations
		$this->translate = $this->translate[$this->lang];

		// Parse model and ID from URL
		$page = explode('/', $params['url']);
		$modelParams = explode(',', $page[1]);
		$page_with_params = $page[1];

		$model = $modelParams[0];
		unset($modelParams[0]); // Remove model from param list

		$id = $page[2];

		// For logging purposes
		$this->logs_model = $model;
		$this->logs_id = $id;

		// --- Authorization check ---
		if (!$this->checkAuth($model, [2, 3])) {
			exit('auth::error::[model_app_write]');
		}

		// --- CSRF token check (unless explicitly bypassed) ---
		if (empty($_POST['update_payload_only'])) {
			if (!$this->csrf_token_verify($_POST['_srdcsrftkn'] ?? '')) {
				exit(json_encode(['result' => false]));
			}
		}

		// --- Handle sorting request ---
		if ($id === 'sort') {
			$schema = $this->getSchema($model, true, ['numbers' => $modelParams]);

			$result = $this->sortPage($schema, $_POST['field'], explode(',', $_POST['order']));
			$this->cacheKill();
			$this->logsAdd('sort');

			// Post-sort plugin hooks
			$afterHooks = _uho_fx::array_filter($schema['buttons_page'] ?? [], 'on_update', 1);

			if ($afterHooks) {
				require_once("model_app_plugin.php");

				foreach ($afterHooks as $hook) {
					$plugin = new model_app_plugin($this->sql, $this->lang);
					$plugin->setParent($this);
					$plugin->setCfgPath($this->cfg_path);

					$pluginParams = [
						'page'              => $model,
						'page_with_params' => $page_with_params,
						'params'           => $hook['params'],
						'plugin'           => $hook['plugin'],
						'orm'              => $this->apporm
					];
					$plugin->getContentData(['params' => $pluginParams, 'get' => []]);
				}
			}

			exit(json_encode($result));
		}

		// --- Handle update request ---
		$data = $_POST;

		// Flag: Only return update payload (no actual save)
		if (_uho_fx::getGet('update_payload_only')) {
			$data['update_payload_only'] = true;
		}

		$update_payload_only = isset($data['update_payload_only']);
		if ($update_payload_only) {
			unset($data['update_payload_only']);
		}

		// Handle update_fields (from GET or POST)
		$update_fields = $_POST['update_fields'] ?? [];
		if (_uho_fx::getGet('update_fields')) {
			$update_fields = explode(',', _uho_fx::getGet('update_fields'));
		}

		if (!is_array($update_fields)) {
			$update_fields = [];
		} elseif (!empty($update_fields)) {
			// Pre-fill data for auto-fields
			$existing = $this->apporm->getJsonModel($model, ['id' => $id], true, null, null, ['additionalParams' => $modelParams]);
			$data = array_merge($existing, $data);
		}

		// Perform update
		$result = $this->updateRecord($model, $id, $data, $modelParams, $update_payload_only, $update_fields);

		// Clear cache and return result
		$this->cacheKill();

		exit(json_encode($result));
	}

	/**
	 * Performs record update/create action
	 * @param string $model;
	 * @param int $id
	 * @param array $data
	 * @param array $params
	 * @param boolean $update_payload_only
	 * @param array $update_fields
	 * @return array
	 */

	private function updateRecord($model, $id, $data, $params, $update_payload_only, $update_fields)
	{

		$page_with_params = $model;
		if ($params) $page_with_params = $model . ',' . implode(',', $params);
		$errors = [];

		// transform values from Form fields to model fields
		$d = [];
		foreach ($data as $k => $v) {
			if (substr($k, 0, 2) == 'e_') $d[substr($k, 2)] = $v;
			else $d[$k] = $v;
		}


		$data_payload = $data = $data_depp = $d;

		// schema update

		$schema = $this->getSchema($model, true, ['numbers' => $params]);

		if ($schema['page_update']) {
			$schema = $this->updateSchemaSources($schema);
			foreach ($schema['fields'] as $k => $v)
				if (isset($v['options']) && $d[$v['field']]) {
					$exists = _uho_fx::array_filter($v['options'], 'value', $d[$v['field']], ['first' => true]);
					if ($exists && $exists['values']) {
						$data_deep[$v['field']] = $exists['values'];
					}
				}

			if (!is_array($schema['page_update']))
				$schema['page_update'] = ['file' => $schema['page_update']];

			if ($data_deep)
				$schema['page_update']['file'] = $this->getTwigFromHtml($schema['page_update']['file'], $data_deep);

			$schema = $this->getSchema($model, true, ['numbers' => $params], ['model' => $schema['page_update']['file']]);
		}

		// update values by sources, for pattern fills etc.
		
		$data_deep = $this->apporm->updateRecordSources($schema, $data);
		$old_value = $this->apporm->getJsonModel($schema, ['id' => $id], true);
		$update_fields_even_empty = [];

		// $update_fields --> add UID if image exists
		if ($update_fields)
			foreach ($update_fields as $k => $v) {
				$f = _uho_fx::array_filter($schema['fields'], 'field', $v, ['first' => true]);
				if ($f && $f['type'] == 'image' && !in_array('uid', $update_fields)) $update_fields[] = 'uid';
				if ($f && $f['type'] == 'image' && !empty($f['settings']['sizes']) && !in_array($f['settings']['sizes'], $update_fields)) $update_fields_even_empty[] = $update_fields[] = $f['settings']['sizes'];
			}


			
		// value updates
		foreach ($schema['fields'] as $k => $v) {
			if ($v['auto'] && $v['type'] != 'file' && (!@$v['auto']['on_null'] || !$data[$v['field']])) {

				$data[$v['field']] = $this->updateAutoValue($v, $schema, $data_deep, $params);
			}

			switch ($v['type']) {

				case "float":

					break;


				case "variable":
					$data[$v['field']] = $this->getAutoVariable($v['variable']);
					break;

				case "elements":

					if ($data[$v['field']]) {
						if (is_array($data[$v['field']])) {
							$vv = [];
							foreach ($data[$v['field']] as $k5 => $v5)
								$vv[] = $v5['id'];
						} else $vv = explode(',', $data[$v['field']]);
					} else $vv = [];

					$iDigits = 8;
					if ($v['output'] == '4digits') $iDigits = 4;
					if ($v['output'] == '6digits') $iDigits = 6;
					if ($v['output'] == 'string') $iDigits = 0;

					// extract new
					if (isset($data[$v['field'] . '_new'])) $new = json_decode($data[$v['field'] . '_new'], true);
					else $new = null;
					if ($new)
						foreach ($vv as $k2 => $v2)
							if (substr($v2, 0, 4) == 'new_' && $new[$v2]) {
								$add = [$v['settings']['add'] => $new[$v2]];

								$this->apporm->postJsonModel($v['source']['model'], $add);
								$vv[$k2] = $this->apporm->getInsertId();
								if (!$vv[$k2]) unset($vv[$k2]);
							}

					foreach ($vv as $k2 => $v2)
						if ($vv) {
							if ($iDigits > 0) $vv[$k2] = _uho_fx::dozeruj($v2, $iDigits);
						} else unset($vv[$k2]);

					$data[$v['field']] = implode(',', $vv);

					break;

				case "boolean":
					
					if ($data[$v['field']] == 'off') $data[$v['field']] = 0;
					elseif ($data[$v['field']] == 'on') $data[$v['field']] = 1;

					break;

				case "checkboxes":

					$iDigits = 8;
					if ($v['output'] == '4digits') $iDigits = 4;
					if ($v['output'] == '6digits') $iDigits = 6;
					if ($v['output'] == 'string') $iDigits = 0;

					$val = [];
					if ($iDigits && $data[$v['field']])
						foreach ($data[$v['field']] as $k2 => $v2)
							if (is_numeric($v2))
								$val[] = _uho_fx::dozeruj($v2, $iDigits);
							elseif ($v2 != 'off') $val[] = $v2;
					$data[$v['field']] = implode(',', $val);

					break;

				case "uid":
					if (in_array($v['field'], $update_fields) && $old_value[$v['field']])
						$data[$v['field']] = $old_value[$v['field']];
					else if (!$data[$v['field']]) $data[$v['field']] = uniqid();
					break;

				case "table":

					if ($update_fields && !in_array($v['field'], $update_fields));
					else {

						$t = [];
						$vv = $data[$v['field']];

						if (is_array($vv))
							foreach ($vv as $k2 => $v2) {
								foreach ($v2 as $k3_row => $col)
									if (is_array($col) && is_numeric($k3_row))	// avoid fake placeholder field
										foreach ($col as $k4_row => $value) {
											if (!$t[$k3_row]) $t[$k3_row] = [];
											$t[$k3_row][$k4_row] = $value;
										}
							}

						$t = array_values($t);

						foreach ($t as $k2 => $v2) {
							$t[$k2] = array_values($v2);
							$on = false;
							foreach ($t[$k2] as $k3 => $v3) {
								$t[$k2][$k3] = trim($v3);
								$on = $on || $t[$k2][$k3];
							}
							if ($k2 == count($t) - 1 && !$on) unset($t[$k2]);
						}

						if (!$v['outside']) $data[$v['field']] = $t;
						else {
							$this->writeOutside($v, $t, $data);
							unset($data[$v['field']]);
						}
					}

					break;
			}
		}


		foreach ($schema['fields'] as $k => $v)
			if (
				($v['write'] === false) ||
				($update_payload_only && (
					!isset($data_payload[$v['field']])
					|| ($update_fields && !in_array($v['field'], $update_fields))
				)
				)
			) {

				if (in_array($v['field'], $update_fields_even_empty));
				else {
					unset($schema['fields'][$k]);
					unset($schema['filters']);
				}
			}

		// unique values
		foreach ($schema['fields'] as $k => $v)
			if (isset($v['unique']) && $v['unique']) {
				$exists = $this->apporm->getJsonModel($model, ['id' => ['operator' => '!=', 'value' => $id], $v['field'] => $data[$v['field']]], true, null, null, ['additionalParams' => $params]);
				if ($exists)
					return ['result' => false, 'message' => $v['label'] . ' - ' . str_replace('%value%', $data[$v['field']], $this->translate['unique_field'])];
			}

		$backup_record_data = [
			'page' => $schema['table'],
			'model' => $schema['model_name'],
			'record' => $id,
			'field' => ''
		];


		// upload handling etc.
		$additional_post = [];
		$additional_put = [];
		$additional_delete = [];


		foreach ($schema['fields'] as $k => $v)
		{

			$backup_record_data['field'] = $v['field'];
			switch ($v['type']) {

				case "html":
					if (@$v['settings']['media']) {
						$r = $this->htmlMediaUpdate($data[$v['field']], $schema['model_name'], $v['settings']['media'], $v);

						$data[$v['field']] = $r['html'];
						$additional_post = array_merge($additional_post, $r['post']);
						$additional_put = array_merge($additional_put, $r['put']);
						$additional_delete = array_merge($additional_delete, $r['delete']);
					}

					break;

				case "image":

					$filename=$data[$v['field']];
					if ($filename && !empty($v['settings']['filename_field']))
					{
						$data[$v['settings']['filename_field']]=$filename;
					}

					if (!empty($v['settings']['change_uid_on_upload']))
					{
						$data['uid']=uniqid();
					}
					

					// for plugin (refresh), let's set rescale only
					if (in_array($v['field'], $update_fields)) {
						$data[$v['field'] . '_rescale'] = 'on';
						if (!$data['uid']) {
							$data['uid'] = $old_value['uid'];
						}
					}

					// remove
					if ($data[$v['field'] . '_remove'] == 'on')
						$r = $this->imageRemove($v, $data, true, $backup_record_data);

					// normalize original
					if ($data[$v['field'] . '_normalize'] == 'on') {
						$r = $this->imageNormalize($v, $data);
						if (!$r['result']) $errors = array_merge($errors, $r['errors']);
					}

					// rotate 
					if ($data[$v['field'] . '_rotate']) {
						$r = $this->imageRotate($v, $data, $data[$v['field'] . '_rotate']);
						if (!$r['result']) $errors = array_merge($errors, $r['errors']);
					}

					// rescale
					if ($data[$v['field'] . '_rescale'] == 'on') {
						$filename = $data[$v['field']];
						if (is_array($filename)) $filename = ''; // refresh plugin only
						$r = $this->imageUpload($v, $data, $filename, true, null, 'image', $backup_record_data);
						if (!$r['result']) $errors = array_merge($errors, $r['errors']);
					}
					// recompress
					elseif ($data[$v['field'] . '_recompress'] == 'on') {
						$r = $this->imageUpload($v, $data, $data[$v['field']], true, ['q' => 95, 'filters' => ['usm'], 'use_native' => true], 'image', $backup_record_data);
						if (!$r['result']) $errors = array_merge($errors, $r['errors']);
					}
					// standard
					elseif ($data[$v['field']]) {
						$r = $this->imageUpload($v, $data, $data[$v['field']], false, null, 'image', $backup_record_data);
						if (!$r['result'] && $r['errors']) $errors = array_merge($errors, $r['errors']);
						elseif ($r['extension'] && isset($v['extension_field'])) {
							$data[$v['extension_field']] = $r['extension'];
						}
					}


					// image resize

					foreach ($v['images'] as $k2 => $v2)
						if ($data[$v['field'] . '_crop_data_' . $k2]) {
							$crop = explode(',', $data[$v['field'] . '_crop_data_' . $k2]);
							$r = $this->imageUpload(
								$v,
								$data,
								$filename,
								true,
								['folder' => $v2['folder'], 'crop' => [$v2['folder'] => $crop]],
								'image',
								$backup_record_data
							);
							if (!$r['result']) $errors = array_merge($errors, $r['errors']);
						}

					// image - sizes
					if (!empty($v['settings']['sizes'])) {
						$data[$v['settings']['sizes']] = $this->getImageSizes($v, $data);
					}

					break;

				case "file":

					$source = $data[$v['field']];


					if ($data['file'] && isset($data['filename_original']))
						$data['filename_original'] = $data['file'];

					if ($v['extension']) $extension = $v['extension'];
					else
							if ($data['extension']) $extension = $data['extension'];
					elseif ($source) {
						$extension = strtolower(array_pop(explode('.', $source)));
					} else $extension = '';

					if (!empty($v['settings']['size'])) $size = $v['settings']['size'];
					else $size = '';

					if ($extension && $v['extension_field'])
						$data[$v['extension_field']] = $extension;

					if ($data[$v['field'] . '_remove'] == 'on') {
						$r = $this->fileRemove($v, $data, null, true);
					} elseif ($data[$v['field']]) {
						$r = $this->fileUpload($v, $data, $data[$v['field']], $extension);
						if ($size) $data[$size] = $r['size'];
						if (!$r['result']) $errors = array_merge($errors, $r['errors']);
						elseif ($v['auto']) {
							foreach ($v['auto'] as $k2 => $v2) {
								$r0 = $this->getFileAuto($v2['type'], $r['file']);
								if (isset($r0['value'])) $data[$v2['field']] = $r0['value'];
							}
						}
					}

					break;


				case "video":

					$video_uploaded = false;

					if ($data[$v['field'] . '_remove'] == 'on') {
						$r = $this->fileRemove($v, $data, 'mp4', true);
					} elseif ($data[$v['field']]) {

						$r = $this->fileUpload($v, $data, $data[$v['field']], 'mp4');
						if (!$r['result']) $errors = array_merge($errors, $r['errors']);
						else $video_uploaded = true;
					}

					if ($data[$v['field'] . '_cover'] == 'on' || $video_uploaded) {
						$position = floatval($data[$v['field'] . '_video']);
						$cover_field = _uho_fx::array_filter($schema['fields'], 'field', $v['field_cover'], ['first' => true]);
						if ($cover_field) $r = $this->videoCover($v, $cover_field, $data, $position);
					}

					break;

				// --------------------------------------------------------

				case "media":

					$field = $v['field'];

					if ($data[$v['field']]['filename'])
						$media = explode(';', $data[$v['field']]['filename']);
					else $media = [];

					foreach ($media as $kk => $vv)
						if ($vv === '') unset($media[$kk]);
					$media = array_values($media);

					$new = [];
					$media_model = $this->getSchema($v['source']['model']);

					$image_field = _uho_fx::array_filter($media_model['fields'], 'field', 'image', ['first' => true]);
					$file_field = _uho_fx::array_filter($media_model['fields'], 'field', 'file', ['first' => true]);
					$audio_field = _uho_fx::array_filter($media_model['fields'], 'field', 'audio', ['first' => true]);
					$video_field = _uho_fx::array_filter($media_model['fields'], 'field', 'video', ['first' => true]);

					foreach ($media as $k2 => $v2)

						if ($v2 && $data[$field . '_filename_' . $v2]) {
							$image_type = $data[$v['field']][$v2]['type'];
							if ($image_type == 'file') $image_field = $file_field;

							if (!$image_field) exit('media::no image/file field found');

							$source = $data[$field . '_filename_' . $v2];
							$extension = strtolower(array_pop(explode('.', $source)));
							$uid = uniqid();

							if ($image_type == 'file') {
								$r = $this->fileUpload($file_field, ['uid' => $uid], $source, null, ['remove' => true]);
								$new[$v2] = [
									'uid' => $uid,
									'filename_original' => $r['filename'],
									'extension' => $extension
								];
								if (isset($image_field['settings']['size']))
									$new[$v2][$image_field['settings']['size']] = $r['size'];
							} else
							if ($image_type == 'audio') {
								$im = $image_field;
								if ($im['folder_audio']) $im['folder'] = $im['folder_audio'];
								if (@$audio_field['folder']) $im['folder'] = $audio_field['folder'];
								$r = $this->fileUpload($im, ['uid' => $uid], $source);
								$new[$v2] = ['uid' => $uid, 'filename_original' => $r['filename']];
							} else
							if ($image_type == 'video') {
								$im = $image_field;
								if ($im['folder_video']) $im['folder'] = $im['folder_video'];
								if (@$video_field['folder']) $im['folder'] = $video_field['folder'];
								$r = $this->fileUpload($im, ['uid' => $uid], $source);
								$new[$v2] = ['uid' => $uid, 'filename_original' => $r['filename']];
								$this->videoCover($video_field, $image_field, $new[$v2], 0);
							} else
							// image
							{
								$r = $this->imageUpload($image_field, ['uid' => $uid], $source, false, null, $image_type);
								if ($r) {

									$new[$v2] = ['uid' => $uid, 'extension' => $r['extension']];
									$new[$v2]['checksum'] = $r['checksum'];
									$new[$v2]['width'] = $r['width'];
									$new[$v2]['height'] = $r['height'];
									$new[$v2]['images_sizes'] = $r['images_sizes'];
								} else exit('error upload image');
							}
						}

					if (!is_array($data[$v['field']]['order']) && $data[$v['field']]['order'])
						$val = explode(',', $data[$v['field']]['order']);
					else $val = [];

					foreach ($val as $kk => $vv)
						if ($vv === '') unset($val[$kk]);
					$val = array_values($val);

					$add = [];
					$existing = [];
					$cover_uid = '';

					foreach ($val as $k2 => $v2) {
						$new_uid = null;
						if (substr($v2, 0, 3) != 'new')
							$old = $this->apporm->getJsonModel($v['source']['model'], ['id' => $v2], true);
						else $old = null;

						$cap = [];
						$captions = $data[$v['field']][$v2];
						$label = '';

						if ($captions)
							foreach ($captions as $k3 => $v3)
								if ($k3 == 'youtube') {
									$cap['youtube'] = $data[$v['field']][$v2]['youtube'];
									if ($cap['youtube'] && (!$old || $old['youtube'] != $cap['youtube'])) {
										$yt = $this->getYouTubeData($cap['youtube']);
										if ($yt) {
											if ($yt['title']) $label = $yt['title'];
											if (substr($v2, 0, 3) == 'new' || !$old['uid']) $uid = uniqid();
											else $uid = $old['uid'];
											if (!$image_field) exit('media::no image field found');
											$r = $this->imageUpload($image_field, ['uid' => $uid], $yt['cover']);
											if ($r) $new[$v2] = ['uid' => $uid];
										}
									}
								} elseif ($k3 == 'vimeo') {
									$cap['vimeo'] = $data[$v['field']][$v2]['vimeo'];
									$cap['vimeo'] = str_replace('https://vimeo.com/', '', $cap['vimeo']);

									if ($cap['vimeo'] && (!$old || $old['vimeo'] != $cap['vimeo'])) {
										$yt = $this->getVimeoData($cap['vimeo']);
										if ($yt) {
											if (is_string($yt)) $yt = ['cover' => $yt];
											if ($yt['title']) $label = $yt['title'];
											if (substr($v2, 0, 3) == 'new' || !$old['uid']) $uid = uniqid();
											else $uid = $old['uid'];
											if (!$image_field) exit('media::no image field found');

											$r = $this->imageUpload($image_field, ['uid' => $uid], $yt['cover']);
											if ($r) {
												$new[$v2] = ['uid' => $uid, 'extension' => 'jpg'];
												if ($yt['sources']) $cap['vimeo_sources'] = json_encode($yt['sources']);
											}
										}
									}
								} elseif ($k3 == 'url')
									$cap['url'] = $data[$v['field']][$v2]['url'];
								elseif (is_numeric($k3) && $v['captions'][$k3 - 1])
									$cap[$v['captions'][$k3 - 1]['field']] = $data[$v['field']][$v2][$k3];

						if (isset($cap['label']) && $label) $cap['label'] = $label;
						elseif (isset($cap['caption']) && $label) $cap['caption'] = $label;
						elseif (isset($cap['label_PL']) && $label) $cap['label_PL'] = $label;

						if (substr($v2, 0, 3) == 'new') {
							$element = $data[$v['field']][$v2];
							$add = [
								'model' => $schema['model_name'] . @$v['media']['suffix'],
								'model_id' => $id,
								'model_id_order' => $k2 + 1,
								'uid' => $new[$v2]['uid'],
								'filename_original' => $new[$v2]['filename_original'],
								'extension' => $new[$v2]['extension'],
								'type' => $element['type'],
								'date' => date('Y-m-d H:i:s'),
								'width' => $new[$v2]['width'],
								'height' => $new[$v2]['height'],
								'size' => $new[$v2]['size']
							];

							if (!empty($image_field['settings']['sizes']))
								$add[$image_field['settings']['sizes']] = $new[$v2]['images_sizes'];

							if ($new[$v2]['checksum'] && @$v['checksum'])
								$add[$v['checksum']] = $new[$v2]['checksum'];

							$add = array_merge($add, $cap);

							$r = $this->apporm->postJsonModel($v['source']['model'], $add);
							if (!$r) return (['result' => false, 'message' => 'Last error on POST: ' . $this->apporm->getLastError()]);

							$existing[] = $new_uid = $this->apporm->getInsertId();
							unset($val[$k2]);
							if ($k2 == 0) $cover_uid = $add['uid'];
						} else {
							$val[$k2] = ['id' => $v2, 'model_id_order' => $k2 + 1];
							$val[$k2] = array_merge($val[$k2], $cap);
							$existing[] = $v2;
							if ($k2 == 0) $cover_uid = $old['uid'];
						}
					}

					if ($cover_uid && isset($v['settings']['cover_uid'])) {
						$data[$v['settings']['cover_uid']] = $cover_uid;
					}

					if ($val) {
						$r = $this->apporm->putJsonModel($v['source']['model'], $val, ['model' => $schema['model_name'] . @$v['media']['suffix'], 'model_id' => $id], true);
						if (!$r) return (['result' => false, 'message' => 'Last error on PUT: ' . $this->apporm->getLastError()]);
					}

					$this->apporm->deleteJsonModel(
						$v['source']['model'],
						[
							'id' => ['operator' => '!=', 'value' => $existing],
							'model' => $schema['model_name'] . @$v['media']['suffix'],
							'model_id' => $id
						],
						true
					);

					break;
			}
		}


		if ($schema['filters']) $data = array_merge($schema['filters'], $data);

		/*
		** adding new record
		*/

		if ($id == 'new')
		{
			// setting up proper order value

			foreach ($schema['fields'] as $k => $v)
				if ($v['type'] == 'order')
				{
					if (@$v['default'] == 'first') {
						$query = $this->apporm->getJsonModelFiltersQuery($schema);
						if ($query) $query = ' WHERE ' . implode(' && ', $query);
						$this->queryOut('UPDATE ' . $schema['table'] . ' SET `' . $v['field'] . '`=`' . $v['field'] . '`+1 ' . $query);
						$data[$v['field']] = 1;
					} else {
						$last = $this->apporm->getJsonModel($schema, $schema['filters'], true, $v['field'] . ' DESC');
						if ($last) $data[$v['field']] = $last[$v['field']] + 1;
					}
				} elseif (isset($v['default']) && $v['default'] === '%variable%') {
					$data[$v['field']] = $this->getAutoVariable($v['variable']);
				}


			$result = $this->apporm->postJsonModel($schema, $data);

			$id_new = $id = $result;
			if ($data['id']) $id_new = $id = $data['id'];

			$is_new_now = true;
			$result = true;
			$this->logs_id = $id_new;
			$this->logsAdd('add');
		} else

		/*
		** updating new record
		*/ {
			$this->backupAdd($schema['table'], $id);
			$data['id'] = $id;
			$this->logsAdd('edit');

			$result = $this->apporm->putJsonModel($schema, $data);
			if (!$result) $errors[] = 'Error on PUT UPDATE ' . $this->apporm->getLastError();

			$is_new_now = false;
		}

		if ($additional_post)
			foreach ($additional_post as $k => $v) {
				foreach ($v['value'] as $kk => $vv)
					if (is_string($vv)) $v['value'][$kk] = str_replace('%record_id%', $id, $vv);
				$this->apporm->postJsonModel($v['model'], $v['value']);
			}

		if ($additional_put)
			foreach ($additional_put as $k => $v)
				$this->apporm->putJsonModel($v['model'], $v['value'], $v['filter']);

		if ($additional_delete)
			foreach ($additional_delete as $k => $v) {
				foreach ($v['value'] as $kk => $vv)
					if (is_string($vv)) $v['value'][$kk] = str_replace('%record_id%', $id, $vv);
				$this->apporm->deleteJsonModel($v['model'], $v['value'], true);
			}

		/*
		** run auto-plugins		
		*/

		$plugins1 = _uho_fx::array_filter(@$schema['buttons_edit'], 'on_update', 1);
		if ($is_new_now) $plugins2 = _uho_fx::array_filter(@$schema['buttons_edit'], 'on_add', 1);
		else $plugins2 = [];

		$plugins = array_merge($plugins1, $plugins2);

		if ($plugins && !$update_fields)
		{
			require_once("model_app_plugin.php");

			$class = new model_app_plugin($this->sql, $lang);
			$class->setParent($this);
			$class->setCfgPath($this->cfg_path);

			foreach ($plugins as $k => $v)
			{
				$p = ['page' => $model, 'page_with_params' => $page_with_params, 'params' => $v['params'], 'record' => $id, 'plugin' => $v['plugin'], 'orm' => $this->apporm];
				$output = $class->getContentData(array('params' => $p, 'get' => []));
			}

			$data = $this->apporm->getJsonModel($model, ['id' => $id], true, null, null, ['additionalParams' => $params]);
			$new = [];
			foreach ($schema['fields'] as $k => $v) {
				if ($v['auto'] && (!@$v['auto']['on_null'] || !$data[$v['field']])) {
					$auto = $this->updateAutoValue($v, $schema, $data, $params);
					if ($auto) $new[$v['field']] = $auto;
				}
			}
			if ($new) {
				$new['id'] = $id;
				$result = $this->apporm->putJsonModel($schema, $new, false, false, false);
				if (!$result) $errors[] = 'Error on PUT';
			}
		}

		if ($errors) return (['result' => false, 'message' => implode('<br>Errors: ', $errors)]);
		elseif (!$result) return (['result' => false, 'message' => 'Last error: ' . $this->apporm->getLastError()]);
		elseif ($id == 'new') return (['result' => true, 'id' => $id_new]);
		else return (['result' => true, 'id' => $id]);
	}


	/**
	 * Performs page sort action
	 * @param array $schema
	 * @param string $field
	 * @param array $data
	 * @return boolean
	 */

	private function sortPage($schema, $field, $data)
	{
		$query = $this->apporm->getJsonModelFiltersQuery($schema);
		if ($query) $query = ' && ' . implode(' && ', $query);

		$data = array_values($data);
		foreach ($data as $k => $v) {
			$q = 'UPDATE ' . $schema['table'] . ' SET `' . $field . '`=' . ($k + 1) . ' WHERE id=' . intval($v) . ' ' . $query;
			echo ($q . chr(13) . chr(10));
			$this->queryOut($q);
		}

		return true;
	}

	/**
	 * Creates auto-variable for use with templates
	 * @param string $variable
	 * @return mixed
	 */

	private function getAutoVariable($variable)
	{
		if ($variable)
			switch ($variable) {
				case "user":
					$value = $this->clients->getClientId();
					break;

				default:
					exit('Variable::getAutoVariable::not defined or unknown::' . $variable);
			}
		return $value;
	}

	/**
	 * Generates and updates the value of an auto-generated field.
	 *
	 * Supports dynamic patterns, transliteration, URL-friendly formatting,
	 * and uniqueness enforcement within a model.
	 *
	 * @param array $field   Field configuration (must contain 'auto' key)
	 * @param array $schema  Schema definition of the current model
	 * @param array $data    Data used for pattern substitution and auto generation
	 * @param array $params  (Deprecated) Unused
	 * @return mixed         The computed auto value, or null if no pattern is defined
	 */
	private function updateAutoValue(array $field, array $schema, array $data, array $params)
	{
		// Simple case: direct auto-variable reference
		if (is_string($field['auto'])) {
			return $this->getAutoVariable($field['auto']);
		}

		// Pattern-based auto value
		$value = $field['auto']['pattern'] ?? null;
		if (!$value) {
			return null;
		}

		// Ensure type array is set
		if (!isset($field['auto']['type'])) {
			$field['auto']['type'] = isset($field['auto']['url']) ? ['translit', 'url'] : [];
		}

		// Sanitize input values in $data
		foreach ($data as $k => $v) {
			if (is_string($v)) {
				$v = str_replace(['&', "'", '"'], ['and', '', ''], $v);
				$data[$k] = $v;
			}
		}

		// Inject CMS user if needed in pattern
		$data['cms_user'] = $this->clients->getClientId();

		// Apply pattern using templating engine (e.g., Twig)
		$value = $this->getTwigFromHtml($value, $data);

		// Transliterate value if needed
		if (in_array('translit', $field['auto']['type'])) {
			$value = _uho_fx::removeLocalChars($value, true);
		}

		// Convert to URL-friendly format if needed
		if (in_array('url', $field['auto']['type'])) {
			$value = preg_replace('/\s+/', '-', $value);                  // Replace spaces with hyphens
			$value = preg_replace('/[^a-zA-Z0-9\-]/', '', $value);        // Remove non-URL-safe characters
			$value = strtolower(str_replace('_', '-', $value));           // Lowercase and replace underscores
			$value = str_replace(['--', '--'], '-', $value);              // Collapse double hyphens
		}

		// Enforce max length for URL slugs (60 chars max)
		if (in_array('url', $field['auto']['type']) && strlen($value) > 60) {
			$parts = explode('-', $value);
			while (strlen(implode('-', $parts)) > 60) {
				array_pop($parts);
			}
			$value = _uho_fx::trim(implode('-', $parts), '-');
		}

		// Enforce uniqueness if required
		if (!empty($field['auto']['unique']) && $value !== '') {
			$i = 0;
			$exists = false;
			$original = $value;

			// Loop until unique value is found
			do {
				$candidate = $original;
				if ($i > 0) {
					$candidate .= '-' . $i;
				}

				$filter = [$field['field'] => $candidate];
				$exists = $this->apporm->getJsonModel($schema['model_name'], $filter, true);
				$i++;
			} while ($exists);

			$value = $candidate;
		}

		return $value;
	}


	/**
	 * Removes image
	 * @param array $field
	 * @param array $data
	 * @param boolean $backup
	 * @param array $record
	 * @return null
	 */

	private function imageRemove($field, $data, $backup = false, $record = false)
	{
		$dest = $this->updateImageDest($field, $data);
		$webp = @$field['settings']['webp'];
		foreach ($dest['images'] as $k => $v) {
			if ($k == 0 && $backup) $this->backup_media_add($v['destination'], $record);
			$this->unlink($v['destination']);
			if ($webp) $this->unlink($this->jpg2webp($v['destination']));
			$this->unlink($v['destination_x2']);
			if ($webp) $this->unlink($this->jpg2webp($v['destination_x2']));
		}
	}

	/**
	 * Normalizes original image
	 * @param array $field
	 * @param array $data
	 * @return array
	 */

	private function imageNormalize(array $field, $data)
	{
		$dest = $this->updateImageDest($field, $data);
		$original = $dest['images'][0]['destination'];
		if ($this->s3) $original = $this->s3->getFilenameWithHost($original);

		$source = imagecreatefromjpeg($original);
		if (!$source) $source = imagecreatefrompng($original);

		if ($source) {
			$this->imagejpeg($source, $original);
			return ['result' => true];
		} else {
			return ['result' => false, 'errors' => ['Cannot create source image']];
		}
	}

	/**
	 * Rotates the image
	 * @param array $field
	 * @param array $data
	 * @param float $angle
	 * @return array
	 */

	private function imageRotate(array $field, $data, $angle)
	{
		$dest = $this->updateImageDest($field, $data);
		$images = [];

		foreach ($dest['images'] as $k => $v) {
			$images[] = $v['destination'];
			if ($v['folder'] != 'original' && !empty($v['destination_x2'])) $images[] = $v['destination_x2'];
		}
		$webp = (!empty($field['settings']['webp']));

		foreach ($images as $k => $v) {
			$original = $v;
			if ($this->s3) $original = $this->s3->getFilenameWithHost($original);

			$source = imagecreatefromjpeg($original);
			if (!$source) $source = imagecreatefrompng($original);

			if ($source) {

				$source = imagerotate($source, $angle, 0);
				$this->imagejpeg($source, $original);
				if ($webp && $k > 0) $this->imagewebp($source, str_replace('.jpg', '.webp', $original));
			} else {
				return ['result' => false, 'errors' => ['Cannot create source image']];
			}
		}
		return ['result' => true];
	}

	/**
	 * Extends PHP unlink function to handle S3
	 * @param string $filename
	 * @return boolean
	 */

	private function unlink($filename)
	{
		if ($this->s3) return $this->s3->unlink($filename);
		else return @unlink($filename);
	}

	/**
	 * Handles image upload, resizing, and optional S3 synchronization.
	 *
	 * Supports cropping, retina images, webp conversion, and rescaling-only mode.
	 *
	 * @param array       $field         Field definition (must contain folder and image variants)
	 * @param array       $data          Record data (must include UID)
	 * @param string|null $filename      Uploaded filename or remote URL
	 * @param bool        $rescale_only  If true, only rescales an existing image
	 * @param array|null  $params        Optional image processing parameters (e.g., crop, rotate)
	 * @param string      $image_type    Optional image type (e.g., 'panorama', defaults to 'image')
	 * @param array       $record        Optional record data for backup logic
	 * @return array                     Result array with status, paths, sizes, and errors if any
	 */
	private function imageUpload($field, $data, $filename, $rescale_only = false, $params = null, $image_type = 'image', $record = []): array
	{

		// --- Handle remote image upload (for S3)
		if ($this->s3 && is_string($filename) && str_starts_with($filename, 'http')) {
			$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'jpg';
			if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) $ext = 'jpg';
			$temp_filename = uniqid() . '.' . $ext;
			copy($filename, $this->upload_path . $temp_filename);
			$filename = $temp_filename;
		}

		$images = [];
		$images_sizes = [];
		$errors = [];
		$source_image_size = null;
		$checksum = '';
		$source_filename = null;
		$source_to_remove = null;

		// Use alternate image variants for other image types
		if ($image_type !== 'image' && !empty($field['images_' . $image_type])) {
			$field['images'] = $field['images_' . $image_type];
		}

		// Prepare destination info
		$dir = rtrim(root_doc, '/') . $field['folder'] . '/';
		$dest = $this->updateImageDest($field, $data, $filename);
		$field['images'] = $dest['images'];
		$extension = $dest['extension'];

		if (is_array($filename)) {
			return ['result' => false, 'errors' => ['Invalid filename']];
		}

		// If source is a URL or passed in via params
		if (is_string($filename) && str_starts_with($filename, 'http')) {
			$source_filename = $filename;
			$filename = basename($filename);
		} elseif (!empty($params['source'])) {
			$filename = basename($params['source']);
		}

		// --- Handle rescale-only mode
		if ($rescale_only) {
			$original = $field['images'][0]['destination'];

			if ($this->s3) {
				$source = $source_to_remove = $this->s3GetTempFilename();
				copy($this->s3->getFilenameWithHost($original), $source);
				$original = $source;
			} elseif ($source_filename) {
				$this->backup_media_copy($source_filename, $original, $record);
			}

			if (!file_exists($original)) {
				$errors[] = "model_app_write::rescale::source file not found::{$original}";
			} else {
				if (!empty($params['rotate'])) {
					$img = imagecreatefromjpeg($original);
					$rotated = imagerotate($img, $params['rotate'], 0);
					$this->imagejpeg($rotated, $original);
				}
				$checksum = md5_file($original);
				$source_image_size = getimagesize($original);
			}
		}

		// --- Normal image upload mode
		else {
			$source = $source_to_remove = $this->upload_path . $filename;

			if ($source_filename) {
				$this->backup_media_copy($source_filename, $source, $record);
			}

			if (!file_exists($source)) {
				$errors[] = "model_app_write::imageUpload2::source file not found::{$source}";
			} else {
				$checksum = @md5_file($source);
				$source_image_size = getimagesize($source);
			}
		}

		// --- Process each image size variant
		if (empty($errors)) {
			foreach ($field['images'] as $variant) {
				if (!empty($params['folder']) && $variant['folder'] !== $params['folder']) continue;

				// Destination folder
				$folder = $variant['folder'] ? $dir . $variant['folder'] : $dir;
				$folder = $this->getTwigFromHtml($folder, $data);

				// Ensure folder exists
				if (!$this->folder_exists($folder)) {
					mkdir($folder, 0755, true);
					if (!file_exists($folder)) {
						return ['result' => false, 'errors' => ["Cannot create folder: {$folder}"]];
					}
				}

				$destination = $variant['destination'];
				$destination_retina = $variant['destination_x2'];
				$original = $source ?? $field['images'][0]['destination'];

				// No resize — just copy original
				if (empty($variant['width']) && empty($variant['height']) && !$rescale_only) {
					$this->backup_media_copy($source, $destination, $record);
					if ($this->s3) $source_to_remove = $source;
					continue;
				}

				// Prepare conversion options
				if ($extension !== 'jpg') $variant['output'] = $extension;
				if (!empty($field['mask'])) $variant['mask'] = $field['mask'];
				if (!empty($variant['crop'])) {
					$variant['cut'] = $variant['crop'];
					unset($variant['crop']);
				}
				if ($params) $variant = array_merge($variant, $params);

				$crop = null;
				if (!empty($params['crop'][$variant['id']])) {
					[$x1, $y1, $w, $h] = $params['crop'][$variant['id']];
					$crop = ['x1' => $x1, 'y1' => $y1, 'width' => $w, 'height' => $h];
				}

				// WebP support
				$variant['webp'] = $field['settings']['webp'] ?? false;
				if ($variant['webp']) {
					$webp_destination = $this->jpg2webp($destination);
				}

				// --- Perform image conversion ---
				if ($extension !== 'svg') {
					if ($this->s3) {
						$temp_destination = $this->s3GetTempFilename();
						$r = _uho_thumb::convert($filename, $source, $temp_destination, $variant, true, 1, $crop);
						if ($r['result']) {
							$this->s3->copy($temp_destination, $destination);
							if ($variant['webp'] && !empty($r['webp'])) {
								$this->s3->copy($r['webp'], $webp_destination);
							}
						}
					} else {
						$r = _uho_thumb::convert($filename, $original, $destination, $variant, true, 1, $crop);
					}

					if (empty($r['result'])) {
						$errors = array_merge($errors, $r['errors']);
					}

					// --- Handle retina (@2x) variant ---
					if (!empty($variant['retina'])) {
						$variant['width']  = $variant['width'] ? $variant['width'] * 2 : null;
						$variant['height'] = $variant['height'] ? $variant['height'] * 2 : null;

						if ($this->s3) {
							$temp_destination = $this->s3GetTempFilename();
							$r = _uho_thumb::convert($filename, $source, $temp_destination, $variant, true, 1, $crop);
							if ($r['result']) {
								$this->s3->copy($temp_destination, $destination_retina);
								$webp_dest_x2 = $this->jpg2webp($destination_retina);
								if ($variant['webp'] && !empty($r['webp'])) {
									$this->s3->copy($r['webp'], $webp_dest_x2);
								}
							}
						} else {
							$r = _uho_thumb::convert($filename, $original, $destination_retina, $variant, true, 1, $crop);
						}

						if (empty($r['result'])) {
							$errors = array_merge($errors, $r['errors']);
						}
					}
				}

				// --- Collect successful image paths and sizes ---
				foreach ([$destination => $variant['folder'], $destination_retina => $variant['folder'] . '_x2'] as $path => $key) {
					if ($this->file_exists($path)) {
						$im = $this->s3 ? $this->s3->getFilenameWithHost($path) : str_replace(root_doc, '/', $path);
						$images[] = $im;
						if ($sizes = @getimagesize($im)) {
							$images_sizes[$key] = [$sizes[0], $sizes[1]];
						}
					}
				}
			}
		}

		// Cleanup temp file if needed
		if ($source_to_remove) {
			@unlink($source_to_remove);
		}

		// --- Return result ---
		if (empty($errors)) {
			return [
				'result'        => true,
				'checksum'      => $checksum,
				'extension'     => $extension,
				'images'        => $images,
				'images_sizes'  => $images_sizes,
				'width'         => $source_image_size[0] ?? null,
				'height'        => $source_image_size[1] ?? null,
			];
		} else {
			return [
				'result' => false,
				'errors' => $errors,
			];
		}
	}

	/**
	 * Generates an image cover (thumbnail) from a video file using ffmpeg.
	 *
	 * This function extracts a frame at the specified time position from a video,
	 * saves it as a JPEG image, and uploads it to the destination path configured
	 * in the image field (supports local and S3 storage).
	 *
	 * @param array  $video_field  Field configuration for the video file (e.g. folder, filename)
	 * @param array  $image_field  Field configuration for the generated image (e.g. folder, filename, image variants)
	 * @param array  $data         Data record used for filename and path templating (must include UID)
	 * @param float  $position     Time position (in seconds) from which to extract the image
	 * @return boolean             
	 */
	private function videoCover($video_field, $image_field, $data, $position)
	{
		// Resolve video folder path (local or S3)
		$folder_video = $this->s3
			? $this->s3->getFilenameWithHost($video_field['folder'])
			: rtrim(root_doc, '/') . $video_field['folder'];

		// Resolve image folder path
		$folder_image = rtrim(root_doc, '/') . $image_field['folder'];

		// Apply templating to paths
		$folder_video = $this->getTwigFromHtml($folder_video, $data);
		$folder_image = $this->getTwigFromHtml($folder_image, $data);

		// Build video filename
		if (!empty($video_field['filename'])) {
			$filename = str_replace('%uid%', $data['uid'], $video_field['filename']);
			$filename = $this->getTwigFromHtml($filename, $data);
		} else {
			$filename = $data['uid'] . '.mp4';
		}

		// Ensure .mp4 extension
		if (!str_contains($filename, '.')) {
			$filename .= '.mp4';
		}

		$video = $folder_video . '/' . $filename;

		// Build default image filename
		if (!empty($image_field['filename'])) {
			$image_filename = str_replace('%uid%', $data['uid'], $image_field['filename'] . '.jpg');
			$image_filename = $this->getTwigFromHtml($image_filename, $data);
		} else {
			$image_filename = $data['uid'] . '.jpg';
		}

		// Fallback to original folder if no image variant defined
		$image = $folder_image . '/original/' . $image_filename;

		// Check for configured image variant (e.g., thumb)
		if (!empty($image_field['images'][0]['filename'])) {
			$image = $folder_image . '/';
			if (!empty($image_field['images'][0]['folder'])) {
				$image .= $image_field['images'][0]['folder'] . '/';
			}
			$image .= $this->getTwigFromHtml($image_field['images'][0]['filename'], $data) . '.jpg';
			$image = $this->fillPattern($image, ['keys' => $data]);
		}

		// Destination file (temporary if using S3)
		$image_dest = $this->s3 ? $this->s3GetTempFilename('jpg') : $image;

		// Create folder if needed (local only)
		if (!$this->s3 && !is_dir(dirname($image_dest))) {
			mkdir(dirname($image_dest), 0755, true);
		}

		// Build ffmpeg command to extract frame
		$cmd = sprintf(
			'-i "%s" -vframes 1 -y -ss %s "%s"',
			$video,
			str_replace(',', '.', $position),
			$image_dest
		);

		// Execute ffmpeg
		$this->ffmpeg($cmd);

		// If thumbnail was created, upload it
		if (file_exists($image_dest)) {
			if ($this->s3) {
				$this->s3->copy($image_dest, $image);
			}
			return $this->imageUpload($image_field, $data, null, true);
		}

		return false;
	}

	/**
	 * Handles a file upload and stores it under a generated destination path.
	 *
	 * This method builds the destination path using the field config and UID,
	 * handles name collisions for original filenames, and copies the file
	 * from the upload path to its final location.
	 *
	 * @param array       $field     Field configuration (e.g. folder, filename pattern)
	 * @param array       $data      Record data (must contain UID)
	 * @param string      $filename  Name of the uploaded file in the temp upload directory
	 * @param string|null $extension Optional extension override; auto-detected if not provided
	 * @param array|null  $params    Optional additional parameters (currently unused)
	 * @return array                 Upload result including: success status, path, size, and filename
	 */
	private function fileUpload($field, $data, $filename, $extension = null, $params = null): array
	{
		$errors = [];

		// Resolve folder and source path
		$folder = rtrim(root_doc, '/') . $field['folder'] . '/';
		$source = $this->upload_path . $filename;

		// Detect extension from source filename if not provided
		$sourceExtParts = explode('.', $source);
		$source_ext = strtolower(end($sourceExtParts));
		if (!$extension) {
			$extension = $source_ext;
		}

		// --- Generate destination filename ---
		if (!empty($field['filename'])) {
			$dest_filename = $field['filename'];

			// Handle original filename collisions (if pattern includes %filename_original%)
			if (str_contains($field['filename'], '%filename_original%')) {
				$nr = 0;
				$originalFilename = $filename;
				$testPath = str_replace('%filename_original%', $filename, $dest_filename);

				while (file_exists($folder . $testPath)) {
					$nr++;
					$nameParts = explode('.', $originalFilename);
					$nameParts[0] .= '-' . $nr;
					$filename = implode('.', $nameParts);
					$testPath = str_replace('%filename_original%', $filename, $dest_filename);
				}
			}

			// Replace tokens in filename
			$dest_filename = str_replace('%filename_original%', $filename, $dest_filename);
			$dest_filename = str_replace('%uid%', $data['uid'], $dest_filename);
			if ($extension && !str_contains($dest_filename, '.')) {
				$dest_filename .= '.' . $extension;
			}
			$dest_filename = str_replace('%extension%', $extension, $dest_filename);
		} else {
			$dest_filename = $data['uid'] . '.' . $extension;
		}

		// Resolve folder and filename using template logic (if dynamic)
		$folder = $this->getTwigFromHtml($folder, $data);
		$dest_filename = $this->getTwigFromHtml($dest_filename, $data);

		// Final destination path
		$destination = str_replace('//', '/', $folder . '/' . $dest_filename);
		$size = 0;

		// --- Validate and copy ---
		if (!file_exists($source)) {
			$errors[] = "model_app_write::source file not found::{$field['field']}::{$source}";
		}

		if (empty($errors)) {
			// Create destination folder if it doesn't exist
			if (!$this->file_exists($folder)) {
				mkdir($folder, 0755, true);
			}

			$size = filesize($source);

			// Copy uploaded file to final destination
			$this->uploadCopy($source, $destination, true);
			@unlink($source); // Clean up source file

			// Verify success
			if (!$this->file_exists($destination)) {
				$errors[] = "model_app_write::file copy error::{$source} -> {$destination}";
			}
		}

		// --- Return result ---
		if (empty($errors)) {
			return [
				'result'   => true,
				'file'     => $destination,
				'size'     => $size,
				'filename' => $dest_filename
			];
		} else {
			return [
				'result' => false,
				'errors' => $errors
			];
		}
	}

	/**
	 * Removes a file based on the field configuration and record data.
	 *
	 * Constructs the expected file path using the provided field info and deletes it if found.
	 *
	 * @param array       $field     Field configuration (e.g. folder, filename template, extensions)
	 * @param array       $data      Data record with UID and other variables used in the path
	 * @param string|null $ext       Optional extension override; if null, it is auto-detected
	 * @return bool                  True if the file was successfully deleted, false otherwise
	 */
	private function fileRemove(array $field, array $data, ?string $ext = null): bool
	{
		// Determine the file extension
		if ($ext) {
			// Provided externally
		} elseif (!empty($field['extension_field']) && !empty($data[$field['extension_field']])) {
			$ext = $data[$field['extension_field']];
		} elseif (!empty($field['extension'])) {
			$ext = $field['extension'];
		} elseif (!empty($data['extension'])) {
			$ext = $data['extension'];
		}

		// Build destination filename
		if ($ext) {
			if (!empty($field['filename'])) {
				$destinationFilename = str_replace('%uid%', $data['uid'], $field['filename']);
				$destinationFilename = $this->getTwigFromHtml($destinationFilename, $data);

				// Append extension if not templated
				if (!str_contains($destinationFilename, '{{extension}}')) {
					$destinationFilename .= '.' . $ext;
				}
			} else {
				$destinationFilename = $data['uid'] . '.' . $ext;
			}
		} else {
			// Extension-less fallback path
			if (empty($field['filename'])) {
				$destinationFilename = $data['uid'] . '.' . ($field['extension'] ?? '');
			} else {
				$destinationFilename = $field['filename'];
				if (!empty($data['uid'])) {
					$destinationFilename = str_replace('%uid%', $data['uid'], $destinationFilename);
				}
				if (!empty($data['filename_original'])) {
					$destinationFilename = str_replace('%filename_original%', $data['filename_original'], $destinationFilename);
				}
				$destinationFilename = $this->getTwigFromHtml($destinationFilename, $data);
			}
		}

		// Final file path
		$folder = rtrim(root_doc, '/') . $field['folder'];
		$file = $folder . '/' . $destinationFilename;
		$file = $this->getTwigFromHtml($file, $data);

		// Attempt to remove the file
		return $this->unlink($file);
	}

	/**
	 * Loads YouTube metadata via API
	 * @param string $id
	 * @return mixed
	 */

	private function getYouTubeData($id)
	{
		$key = $this->getApiKey('youtube');
		if (!$key || !$id) return;

		$url = 'https://www.googleapis.com/youtube/v3/videos?id=' . $id . '&key=' . $key . '&part=snippet';

		$json = $this->fileCurl($url);
		if ($json) {
			$json = json_decode($json, true);

			if (!@$json['items']) {
				$result = false;
			} else {

				$title = @$json['items'][0]['snippet']['title'];
				$result = $json['items'][0]['snippet']['thumbnails'];

				$result = array_pop($result);
				$result = array(
					'title' => $title,
					'cover' => $result['url']
				);
			}
		} else {
			$result = false;
		}
		return $result;
	}

	/**
	 * Loads Vimeo file metadata via public API
	 * @param string $id
	 * @return mixed
	 */

	private function getVimeoData($id)
	{
		$id = explode('/', $id)[0];
		$key = $this->getApiKey('vimeo');
		if ($key) return $this->getVimeoFilenameAdvanced($id, $key);
		else {
			$json = $this->fileCurl('http://vimeo.com/api/v2/video/' . $id . '.json');
			if ($json) {
				$json = json_decode($json);
				$result = @$json[0]->thumbnail_large;
			} else $result = false;
		}
		return $result;
	}

	/**
	 * Loads Vimeo file metadata via private token
	 * @param string $id
	 * @param array $keys
	 * @return array
	 */

	private function getVimeoFilenameAdvanced($id, $keys): array
	{
		require_once($this->cms_folder . '/plugins/import_cover/vimeo/autoload.php');
		$lib = new \Vimeo\Vimeo($keys['client'], $keys['secret']);

		$lib->setToken($keys['token']);
		$data = $lib->request('/videos/' . $id);

		if (!empty($data['body'])) {
			$title = @$data['body']['name'];
			$author = @$data['body']['user']['name'];
			$sources = @$data['body']['files'];
			$data = @$data['body']['pictures']['sizes'];
			if ($data) $data = array_pop($data);
			if ($data) $filename = $data['link'];
		} else return [];

		return (['cover' => $filename, 'title' => $title, 'sources' => $sources, 'author' => $author]);
	}

	/**
	 * Gets video file ID3 tags
	 * @param string $type
	 * @param string $file
	 * @return mixed
	 */

	private function getFileAuto($type, $file)
	{
		switch ($type) {
			case "duration":
				$getID3 = new JamesHeinrich\GetID3\GetID3();
				$ThisFileInfo = $getID3->analyze($file);
				$result = @$ThisFileInfo['playtime_seconds'];
				break;
		}
		if ($result) return ['value' => $result];
	}

	/**
	 * Performs a write operation on an external (related) model.
	 *
	 * This is typically used for a "table" type field that stores multiple rows in
	 * an external model (e.g., sub-items of a parent record).
	 *
	 * @param array  $field  The field definition array, including 'outside' config.
	 * @param array  $data   Array of rows (usually from a form or editor) to write.
	 * @param array  $record The parent record data, used to reference parent ID.
	 * @return void
	 */

	private function writeOutside(array $field, array $data, array $record): void
	{
		// Only applicable for fields of type 'table'
		if ($field['type'] !== 'table') {
			return;
		}

		// Prepare deletion filter for existing records linked to the parent
		$parentKey = $field['outside']['parent'];
		$recordIdKey = $field['outside']['id'];
		$parentId = $record[$recordIdKey] ?? null;

		if ($parentId === null) {
			return; // Cannot proceed without parent ID
		}

		$filter = [$parentKey => $parentId];

		// Delete existing related rows
		$this->apporm->deleteJsonModel($field['outside']['model'], $filter);

		// Prepare new rows to insert
		foreach ($data as $index => $row) {
			$newRow = [$parentKey => $parentId];

			foreach ($row as $columnIndex => $value) {
				$outsideField = $field['settings']['outside_fields'][$columnIndex] ?? null;
				if ($outsideField) {
					$newRow[$outsideField] = $value;
				}
			}

			// Optionally assign order field
			if (!empty($field['outside']['order'])) {
				$newRow[$field['outside']['order']] = $index + 1;
			}

			// Replace original row with mapped row
			$data[$index] = $newRow;
		}

		// Insert the new data rows into the external model
		$this->apporm->postJsonModel($field['outside']['model'], $data, true);
	}

	/**
	 * Get temporary S3 bucket filename
	 * @param string $ext optional
	 * @return string
	 */

	private function s3GetTempFilename($ext = null)
	{
		$f = $this->temp_folder . '/' . uniqid();
		$f = str_replace('//', '/', $f);
		if ($ext) $f .= '.' . $ext;
		return $f;
	}

	/**
	 * Enhances m5_files function to handle S3
	 * @param string $f
	 * @return string
	 */

	public function md5_file($f)
	{
		if ($this->s3) return $this->s3->file_time($f);
		else return md5_file($f);
	}

	/**
	 * Enhances folder_exists function to handle S3
	 * @param string $f
	 * @return boolean
	 */

	public function folder_exists($f)
	{
		if ($this->s3) return true;
		else return file_exists($f);
	}

	/**
	 * Enhances file_exists function to handle S3
	 * @param string $f
	 * @return boolean
	 */

	public function file_exists($f) :bool
	{
		if ($this->s3) return $this->s3->file_exists($f);
		else return file_exists($f);
	}

	/**
	 * Updates imagejpeg function with S3 support
	 * @param mixed $image
	 * @param string $filename
	 * @return null
	 */

	private function imageJpeg($image, $filename)
	{
		if ($this->s3) {
			$f = $this->s3GetTempFilename();
			imagejpeg($image, $f);
			$this->s3->copy($f, $filename);
		} else
			imagejpeg($image, $filename);
	}


	/**
	 * Updates HTML content from CKEditor to handle media uploads and metadata syncing.
	 *
	 * This function processes embedded images from the temp folder in CKEditor HTML,
	 * uploads them to the target model, and synchronizes associated media data (e.g. alt, caption).
	 * It supports integration with a custom "uho_media" plugin and replaces image paths accordingly.
	 *
	 * @param string $html             The HTML content from CKEditor
	 * @param object $parent_model     The parent model object (used for relation)
	 * @param string $media_model_name The name of the media model to use
	 * @param array  $field            Field configuration, may include media plugin settings
	 * @return array                   An array containing:
	 *                                 - 'html': updated HTML content
	 *                                 - 'post': list of new media entries to create
	 *                                 - 'put': list of media entries to update
	 *                                 - 'delete': list of media entries to delete
	 */
	private function htmlMediaUpdate($html, $parent_model, $media_model_name, $field): array
	{
		$uho_media_replacer = !empty($field['settings']['media_field']);
		$uho_media_filename = $this->cms_folder . '/public/ckeditor/plugins/uho_media/icons/uho_media.png';

		$new_media = [];     // New images to upload
		$updated_media = []; // Existing media metadata to update
		$deleted_media = []; // Media records to remove

		$media_model = $this->getSchema($media_model_name);
		$image_field = _uho_fx::array_filter($media_model['fields'], 'field', 'image', ['first' => true]);

		// Normalize upload path
		$html = str_replace('../../../public/upload/', '/public/upload/', $html);

		// --- STEP 1: Process newly uploaded temp images ---

		$max = 100;
		while (strpos(' ' . $html, $this->temp_folder . '/upload') && $max-- > 0) {
			$i1 = strpos($html, $this->temp_folder . '/upload');
			$i2 = strpos($html, '"', $i1 + 10);

			$uid = uniqid();
			$source = $_SERVER['DOCUMENT_ROOT'] . substr($html, $i1, $i2 - $i1);
			$image_type = 'image';

			$upload_result = $this->imageUpload($image_field, ['uid' => $uid], $uid, false, ['source' => $source], $image_type);

			if (!empty($upload_result['result'])) {
				$new_media[] = [
					'model' => $media_model_name,
					'value' => [
						'model'    => $parent_model,
						'model_id' => '%record_id%',
						'uid'      => $uid,
						'extension' => $upload_result['extension']
					]
				];
			}

			// Replace src with a placeholder or plugin icon
			$replacement = $uho_media_replacer ? 'new_' . $uid : $uho_media_filename;
			$html = substr($html, 0, $i1) . $replacement . substr($html, $i2);
		}

		// --- STEP 2: Replace placeholder src and extract metadata (alt, caption) ---

		if ($uho_media_replacer) {
			$max = 100;
			$order = 0;
			$uids = [];

			while (strpos(' ' . $html, '<img ') && $max-- > 0) {
				$i1 = strpos($html, '<img ');
				$i2 = strpos($html, '>', $i1 + 1);

				$j1 = strpos($html, 'src="', $i1);
				$j2 = strpos($html, '"', $j1 + 5);

				// Extract alt attribute
				$alt1 = strpos($html, 'alt="', $i1);
				$alt2 = strpos($html, '"', $alt1 + 5);
				$alt = ($alt1 && $alt2 > $alt1) ? substr($html, $alt1 + 5, $alt2 - $alt1 - 5) : '';

				// Extract figcaption content
				$fig1 = strpos($html, '<figcaption>', $i1);
				$fig2 = strpos($html, '</figcaption>', $fig1);
				$fig = ($fig2 > $fig1 && ($fig1 - $i2) < 10) ? substr($html, $fig1 + 12, $fig2 - $fig1 - 12) : '';

				$order++;
				$src = substr($html, $j1 + 5, $j2 - $j1 - 5);

				if (str_starts_with($src, 'new_')) {
					$uid = substr($src, 4);
					$uids[] = $uid;

					foreach ($new_media as &$media) {
						if ($media['value']['uid'] === $uid) {
							$media['value']['model_id_order'] = $order;
							$media['value']['alt'] = $alt;
							$media['value']['caption'] = $fig;
							break;
						}
					}
				} else {
					// For existing images: extract UID from filename
					$parts = explode('.', explode('?', basename($src))[0]);
					$uid = $parts[0] ?? '';
					$uids[] = $uid;

					$updated_media[] = [
						'model' => $media_model_name,
						'value' => [
							'model_id_order' => $order,
							'alt'     => $alt,
							'caption' => $fig
						],
						'filter' => ['uid' => $uid]
					];
				}

				// Temporarily replace <img> to avoid reprocessing
				if ($i2 > $i1) {
					$html = substr($html, 0, $i1) . '<igm src="' . $uho_media_filename . '">' . substr($html, $i2 + 1);
				} else {
					break;
				}
			}

			// Restore <img> tags from <igm>
			$html = str_replace('<igm', '<img', $html);

			// Identify deleted images by excluding current UIDs
			$deleted_media[] = [
				'model' => $media_model_name,
				'value' => [
					'model_id' => '%record_id%',
					'uid' => ['operator' => '!=', 'value' => $uids]
				]
			];

			// --- STEP 3: Flatten <figure class="image"> to just the <img> tag ---

			$max = 100;
			while ($max-- > 0 && strpos($html, '<figure class="image">') !== false) {
				$i1 = strpos($html, '<figure class="image">');
				$i2 = strpos($html, '</figure>', $i1);

				$inside = substr($html, $i1, $i2 - $i1 + 9);
				$j1 = strpos($inside, '<img');
				$j2 = strpos($inside, '>', $j1);

				$imgOnly = substr($inside, $j1, $j2 - $j1 + 1);
				$html = substr($html, 0, $i1) . $imgOnly . substr($html, $i2 + 9);
			}
		}

		return [
			'html'   => $html,
			'post'   => $new_media,
			'put'    => $updated_media,
			'delete' => $deleted_media
		];
	}

	/**
	 * Loads a remote file via cURL.
	 *
	 * Performs a simple HTTP GET request and returns the response body.
	 *
	 * @param string $url The URL to request.
	 * @return string|false The response body on success, or false on failure.
	 */
	private function fileCurl(string $url)
	{
		$ch = curl_init();

		// Set cURL options
		curl_setopt($ch, CURLOPT_URL, $url);                // Target URL
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);     // Return result instead of printing
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);        // Timeout after 5 seconds

		$response = curl_exec($ch); // Execute the request
		curl_close($ch);            // Close the handle

		return $response;           // Return the fetched content or false on error
	}

	/**
	 * Adds _uho_client object log enrtry
	 * @param string $action
	 * @return null
	 */

	private function logsAdd($action)
	{
		$this->clients->logsAdd($this->logs_model . '::' . $this->logs_id . '::' . $action);
	}

	/**
	 * Converts filename to webp extension if applicable.
	 * @param string $filename
	 * @param array $exts Optional array of valid extensions to convert
	 * @return null
	 */

	private function jpg2webp($filename, $exts = ['jpg', 'gif', 'png'])
	{
		$f = explode('.', $filename);
		$ext = array_pop($f);
		if (in_array($ext, $exts) && $f) {
			array_push($f, 'webp');
			return implode('.', $f);
		}
	}

	/**
	 * Enhances imagewebp function with S3 support
	 * @param mixed $image
	 * @param string $filename
	 * @return null
	 */

	private function imageWebp($image, $filename)
	{
		if ($this->s3) {
			$f = $this->s3GetTempFilename();
			imagewebp($image, $f);
			$this->s3->copy($f, $filename);
		} else
			imagewebp($image, $filename);
	}


	/**
	 * Updates the destination paths for image files based on field configuration and data.
	 *
	 * This method builds the destination file paths for original and high-resolution (x2) images.
	 * It supports optional S3-based path generation and handles dynamic folder/filename resolution.
	 *
	 * @param array  $field    Field configuration array (must contain 'folder', 'images', etc.)
	 * @param array  $data     Data record containing keys used in path patterns and filename substitution
	 * @param string $filename Optional filename to use instead of generating from UID
	 * @param bool   $s3       If true and S3 is configured, returns destination using S3 path
	 * @return array           Associative array with keys: 'images' (list of image paths) and 'extension'
	 */
	private function updateImageDest(array $field, array $data, $filename = '', bool $s3 = false): array
	{
		// Resolve dynamic folder path using templating and data
		if (!empty($field['folder'])) {
			$field['folder'] = $this->fillPattern($field['folder'], ['keys' => $data]);
			$field['folder'] = $this->getTwigFromHtml($field['folder'], $data);
		}

		// Base directory path (local or S3)
		$dir = rtrim(root_doc, '/') . $field['folder'] . '/';
		if ($this->s3 && $s3) {
			$dir = rtrim($this->s3->getFilenameWithHost($field['folder']), '/') . '/';
		}

		// Determine file extension
		$extension = 'jpg';
		if (!empty($field['extension_field']) && !empty($field['extensions'])) {
			if (!$filename) {
				$extension = $data[$field['extension_field']] ?? 'jpg';
			} else {
				$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
				if ($extension === 'jpeg') {
					$extension = 'jpg';
				}
			}

			// Validate extension against allowed list
			if (!in_array($extension, $field['extensions'], true)) {
				$extension = 'jpg';
			}
		} elseif (!empty($field['extensions']) && count($field['extensions']) === 1) {
			$extension = $field['extensions'][0];
		}

		// Generate the base filename
		if (empty($field['filename'])) $destinationFilename=$data['uid'] . '.' . $extension;
		else
		{
			if (isset($data['id'])) $destinationFilename =  str_replace('%id%', $data['id'], $field['filename']) . '.' . $extension;
				else $destinationFilename =  $field['filename'] . '.' . $extension;
			if (isset($data['uid'])) $destinationFilename =  str_replace('%uid%', $data['uid'], $destinationFilename);
		}
		
		

		// Build destination paths for each image size (e.g., thumb, preview, etc.)
		foreach ($field['images'] as $k => $imageConfig) {
			$dest     = $dir;
			$destX2   = $dir;

			// Append subfolders if defined
			if (!empty($imageConfig['folder'])) {
				$dest   .= $imageConfig['folder'] . '/';
				$destX2 .= $imageConfig['folder'] . '_x2/';
			}

			// Determine filename for this image variant
			if (!empty($imageConfig['filename'])) {
				$filledFilename = $this->fillPattern($imageConfig['filename'], ['keys' => $data]) . '.' . $extension;
				$dest   .= $filledFilename;

				// If no folder defined, add fallback x2 folder
				if (empty($imageConfig['folder'])) {
					$destX2 .= 'x2/';
				}
				$destX2 .= $filledFilename;
			} else {
				$dest   .= $destinationFilename;
				$destX2 .= $destinationFilename;
			}

			// Save calculated paths back to the field config
			$field['images'][$k]['destination']     = $dest;
			$field['images'][$k]['destination_x2']  = $destX2;
		}

		return [
			'images'    => $field['images'],
			'extension' => $extension,
		];
	}


	/**
	 * Retrieves the dimensions (width and height) of image files defined by a field configuration.
	 *
	 * @param mixed $field Field definition or configuration used to locate images.
	 * @param mixed $data  Data related to the record that may affect image destination paths.
	 * @return array Associative array of image sizes, e.g., ['thumb' => [width, height], 'thumb_x2' => [width, height]]
	 */

	private function getImageSizes($field, $data): array
	{
		$dest = $this->updateImageDest($field, $data, '', true);
		$result = [];

		foreach ($dest['images'] as $image) {
			$folder = $image['folder'] ?? null;
			$destination = $image['destination'] ?? null;

			// Check and get size of the standard resolution image
			if ($folder && $destination && is_file($destination)) {
				$size = getimagesize($destination);
				if (!empty($size[0]) && !empty($size[1])) {
					$result[$folder] = [$size[0], $size[1]];
				}
			}

			// Check and get size of the high-resolution (@2x) image, if available
			if (!empty($image['destination_x2']) && is_file($image['destination_x2'])) {
				$sizeX2 = getimagesize($image['destination_x2']);
				if (!empty($sizeX2[0]) && !empty($sizeX2[1])) {
					$result[$folder . '_x2'] = [$sizeX2[0], $sizeX2[1]];
				}
			}
		}

		return $result;
	}
}
