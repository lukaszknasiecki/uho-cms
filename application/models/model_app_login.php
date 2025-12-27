<?php

require_once('model_app.php');

/**
 * Model class for handling the login functionality.
 */
class model_app_login extends model_app
{
	/**
	 * ORM object used by the application (inherited).
	 * @var mixed
	 */
	public $apporm;

	/**
	 * Language translation strings.
	 * @var array
	 */
	private $translate = [];

	/**
	 * Returns login-related data and performs authentication.
	 *
	 * @param array|null $params Login request parameters
	 * @return array Response object with login result and metadata
	 */
	public function getContentData($params = null)
	{

		$this->translate = json_decode(file_get_contents(__DIR__.'/model_app_login.json'),true);

		$cfg = $this->getAvailableProjects();
		$logged = false;
		$error = '';

		// Login attempt
		if (
			isset($params['project'], $params['login_login'], $params['login_password']) &&
			isset($cfg['projects'][intval($params['project']) - 1])
		) {
			$result = $this->clients->login($params['login_login'], $params['login_password']);
			if (!empty($result['result']))
			{
				$logged = true;
				$_SESSION['serdelia_project'] = intval($params['project']);
				$_SESSION['serdelia_login_time']   = time();
				$_SESSION['serdelia_activity_time']= time();

			} else {
				$error = 'login_error';
			}
		} elseif ($_POST) {
			// Form was submitted without selecting a valid project
			$error = 'login_error_project';
		}

		// Response data
		$response = [
			'logged'    => $logged,
			'translate' => $this->translate[$this->lang] ?? [],
			'error'     => $error,
			'action'    => $this->cms_path . 'login',
			'projects'  => $cfg['projects'] ?? []
		];

		// Token and login attempt metadata (if client object exists)
		if ($this->clients) {
			$response['token'] = $this->clients->client->getToken();

			if (!empty($params['login_login'])) {
				$response['tries'] = $this->clients->client->getRemainingLoginAttempts($params['login_login']);
				$response['tries_all'] = $this->clients->client->getMaxLoginAttempts();
			}
		}

		return $response;
	}

	/**
	 * Loads the list of available projects from sunship-cms.json.
	 *
	 * @return array|null Configuration array containing available projects
	 */
	private function getAvailableProjects()
	{
		$configPath = $_SERVER['DOCUMENT_ROOT'] . '/uho-cms.json';
		if (!file_exists($configPath))
			$configPath = $_SERVER['DOCUMENT_ROOT'] . '/sunship-cms.json';

		// Load base CMS config
		if (!file_exists($configPath)) {
			return null;
		}

		$configContent = file_get_contents($configPath);
		$cfg = $configContent ? json_decode($configContent, true) : null;

		if (!$cfg) {
			return null;
		}

		// Configuration defaults
		$instances   = !empty($cfg['CMS_CONFIG_FOLDERS']) ? explode(',', $cfg['CMS_CONFIG_FOLDERS']) : ['cms_config'];
		$lang        = $cfg['CMS_CONFIG_LANG']    ?? 'en';
		$cms_prefix  = $cfg['CMS_CONFIG_PREFIX']  ?? 'cms';
		$theme       = $cfg['CMS_CONFIG_THEME']   ?? 'light';

		// Build project list
		foreach ($instances as $k => $folder) {
			$name = 'Project #' . ($k + 1);
			$configFile = $_SERVER['DOCUMENT_ROOT'] . '/' . $folder . '/config.php';

			if (file_exists($configFile)) {
				require_once($configFile);
				if (!empty($cfg['cms']['title'])) {
					$name = $cfg['cms']['title'];
				}
			}

			$instances[$k] = [
				'name'   => $name,
				'folder' => $folder
			];
		}

		return [
			'languages'             => [$lang],
			'languages_url'         => false,
			'application_url_prefix'=> $cms_prefix,
			'mode'                  => $theme,
			'projects'              => $instances
		];
	}
}