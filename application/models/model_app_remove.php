<?php

use Huncwot\UhoFramework\_uho_fx;

require_once('model_app.php');

/**
 * Model class for handling record removal.
 */
class model_app_remove extends model_app
{

	/**
	 * Handles the record removal request and returns a JSON response.
	 *
	 * @param array|null $params Request parameters
	 * @return void Outputs JSON and exits
	 */

	public function getContentData($params = null): void
	{
		$page = explode('/', $params['url'] ?? '');
		$pageParams = explode(',', $page[1] ?? '');
		$model=$pageParams[0];
		unset($pageParams[0]);

		// Extract record ID
		$id = $page[2] ?? null;
		if (is_numeric($id)) {
			$id = (int)$id;
		}

		// Log the removal attempt
		$this->clients->logsAdd("{$model}::{$id}::remove");

		// Attempt record removal
		$result = $this->removeRecord($model, $pageParams, $id);

		// Return JSON response and exit
		exit(json_encode($result));
	}

	/**
	 * Removes a record from the database and performs cleanup.
	 *
	 * @param string $model Model name
	 * @param array $params Page parameters
	 * @param int|string|null $id Record ID
	 * @return array Result array
	 */
	public function removeRecord($model, $params, $id): array
	{
		if (empty($id)) {
			return ['result' => false];
		}

		$schema = $this->getSchema($model);
		$schema['filters'] = $schema['filters'] ?? [];

		// Apply filters for record existence check
		$filters = _uho_fx::fillPattern($schema['filters'], ['numbers' => $params]);
		$filters['id'] = $id;

		$exists = $this->apporm->get($model, $filters, true);

		// Backup record before deletion
		$this->backupAdd($schema['table'], $id);

		$result = false;
		if ($exists) {
			$result = $this->apporm->delete($model, $id);
		}

		// Run any post-deletion plugins (if defined)
		if (!empty($schema['buttons_page'])) {
			require_once("model_app_plugin.php");

			$plugins = _uho_fx::array_filter($schema['buttons_page'], 'on_update', 1);
			foreach ($plugins as $pluginConfig) {
				$class = new model_app_plugin($this->sql, $this->lang);
				$class->setParent($this);
				$class->setCfgPath($this->cfg_path);

				$page_with_params = implode(',', $params);

				$pluginParams = [
					'page' => $model,
					'page_with_params' => $page_with_params,
					'params' => $pluginConfig['params'],
					'plugin' => $pluginConfig['plugin'],
					'orm' => $this->apporm,
				];

				$class->getContentData(['params' => $pluginParams, 'get' => []]);
			}
		}

		// Reorder if "order" field is defined
		$orderField = _uho_fx::array_filter($schema['fields'], 'type', 'order', ['first' => true]);
		if ($result && $orderField) {
			$orderKey = $orderField['field'] ?? null;
			unset($filters['id']);

			$records = $this->apporm->get($model, $filters);
			foreach ($records as $k => $record) {
				if (($k + 1) !== (int)$record[$orderKey]) {
					$this->apporm->put($schema, [
						$orderKey => $k + 1,
						'id' => $record['id']
					]);
				}
			}
		}

		if (!$result) {
			return ['result' => false, 'message' => $this->apporm->getLastError()];
		}

		return ['result' => true];
	}
}