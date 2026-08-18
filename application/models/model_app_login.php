<?php

use Huncwot\UhoFramework\_uho_load_env;

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

		$this->translate = json_decode(file_get_contents(__DIR__ . '/model_app_login.json'), true);

		$cfg = $this->getAvailableProjects();

		$logged = false;
		$error = '';

		// Login attempt
		if (
			isset($params['project'], $params['login_login'], $params['login_password']) &&
			isset($cfg['projects'][intval($params['project']) - 1])
		) {

			$result = $this->clients->login($params['login_login'], $params['login_password']);

			if (!empty($result['result'])) {
				$logged = true;
				$_SESSION['uho_cms_project'] = intval($params['project']);
				$_SESSION['uho_cms_login_time']   = time();
				$_SESSION['uho_cms_activity_time'] = time();
			} else {
				$error = 'login_error';
			}
		} elseif ($_POST) {
			// Form was submitted without selecting a valid project
			$error = 'login_error_project';
		}

		$projects=$cfg['projects'];
		$uho_cms_projects_oauth=[];
		
		foreach ($projects as $k => $p)
		{
			$token=$this->clients->client->generateToken().'_'.($k+1);
			$projects[$k]['token']=$token;
			$uho_cms_projects_oauth[$token]=$k+1;
		}

		if ($uho_cms_projects_oauth)
			setcookie('uho_cms_projects_oauth', json_encode($uho_cms_projects_oauth), time()+60, '/');

		// Response data
		$response = [
			'logged'    => $logged,
			'translate' => $this->translate[$this->lang] ?? [],
			'error'     => $error,
			'google'	=> !empty($cfg['projects'][0]['google_oauth']),
			'google_redirect' => $this->getCmsUri() . 'auth-google-callback',
			'action'    => $this->cms_path . 'login',
			'projects'  => $projects ?? []
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
	/*
	private function getAvailableProjects()
	{
		$configPath = $_SERVER['DOCUMENT_ROOT'] . '/.uho-cms.json';
		if (!file_exists($configPath))
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
			$configFolder = $_SERVER['DOCUMENT_ROOT'] . '/' . $folder . '/';
			$configFile = $configFolder . 'config.php';

			if (file_exists($configFile)) {
				require_once($configFile);
				if (!empty($cfg['cms']['title'])) {
					$name = $cfg['cms']['title'];
				}

				$envFile = $configFolder . '.env';
				if (file_exists($envFile)) {
					$env_loader = new _uho_load_env($envFile);
					$env_loader->load(['GOOGLE_OAUTH_CLIENT_ID', 'GOOGLE_OAUTH_CLIENT_SECRET']);
				}
			}

			$instances[$k] = [
				'name'   => $name,
				'folder' => $folder
			];

			if (!empty($_ENV['GOOGLE_OAUTH_CLIENT_ID']) || !empty($_ENV['GOOGLE_OAUTH_CLIENT_SECRET'])) {
				$instances[$k]['google_oauth'] = [
					'client' => $_ENV['GOOGLE_OAUTH_CLIENT_ID'],
					'secret' => $_ENV['GOOGLE_OAUTH_CLIENT_SECRET']
				];
			}
		}


		return [
			'languages'             => [$lang],
			'languages_url'         => false,
			'application_url_prefix' => $cms_prefix,
			'mode'                  => $theme,
			'projects'              => $instances
		];
	}*/

	private function getCmsUri()
	{
		if (
			isset($_SERVER['SSL_PROTOCOL'])
			|| @$_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' // ec2 ooh
			|| @$_SERVER['HTTPS'] == 'on'
			|| isset($_SERVER['SSL_TLS_SNI'])
		)
			$http = 'https';
		else $http = 'http';
		$cms_url = $http . '://' . $_SERVER['SERVER_NAME'];

		$cms_url .= $this->cms_path;
		return $cms_url;
	}
}
