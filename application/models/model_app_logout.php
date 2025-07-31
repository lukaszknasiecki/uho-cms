<?php

require_once('model_app.php');

/**
 * Model class for handling logout functionality.
 */
class model_app_logout extends model_app
{
	/**
	 * Handles user logout and returns logout-related data.
	 *
	 * @param array|null $params (Deprecated) Optional parameters, e.g., 'expired' flag
	 * @return array Response with translation strings and logout status
	 */
	public function getContentData($params = null): array
	{
		// Perform logout via client
		$this->clients->client->logout();

		// Load translations from external JSON
		$translatePath = __DIR__ . '/model_app_logout.json';
		$translations = file_exists($translatePath)
			? json_decode(file_get_contents($translatePath), true)
			: [];

		// Return translated message and logout result
		return [
			'translate' => $translations[$this->lang] ?? [],
			'result'    => true,
			'expired'   => isset($params['expired']),
		];
	}
}