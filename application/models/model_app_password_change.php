<?php

require_once('model_app.php');

/**
 * Model class for handling password change functionality.
 */
class model_app_password_change extends model_app
{
	/**
	 * Handles the password change process and returns status and translation data.
	 *
	 * @param array $post POST data from the request
	 * @param array $get  GET data from the request
	 * @return array Response data including errors, translation, and redirect flag
	 */
	public function getContentData($post, $get): array
	{
		// Load password format requirements
		$required = $this->getClientsCfg('password_required');
		$required = explode(',', $required);

		if (!$required) {
			exit('model_app_password_change::cfg::password_required missing');
		}

		$errors = [];

		// Handle form submission
		if (!empty($post)) {
			if ($post['new_password1'] !== $post['new_password2']) {
				$errors[] = 'error_match';
			} elseif ($post['new_password1'] === $post['old_password']) {
				$errors[] = 'error_same';
			} else {
				// Validate password format
				$validation = $this->clientsValidatePasswordFormat($post['new_password1']);

				if (!empty($validation['errors'])) {
					$errors = array_merge($errors, $validation['errors']);
				} elseif (!$this->clients->client->passwordCheck($post['old_password'])) {
					$errors[] = 'error_oldpass';
				} elseif ($this->clients->client->passwordChange($validation['password'])) {
					// On success, log user out and redirect
					$this->clients->client->logout();
					return ['redirect' => true];
				} else {
					$errors[] = 'error_system';
				}
			}
		}

		// Load translations
		$translatePath = __DIR__ . '/model_app_password_change.json';
		$translations = file_exists($translatePath)
			? json_decode(file_get_contents($translatePath), true)
			: [];

		return [
			'translate' => $translations[$this->lang] ?? [],
			'required'  => $required,
			'expired'   => isset($get['expired']),
			'errors'    => $errors
		];
	}
}