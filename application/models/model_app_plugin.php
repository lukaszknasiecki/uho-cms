<?php

use Huncwot\UhoFramework\_uho_fx;

require_once('model_app.php');

/**
 * Model class for handling plugin execution and rendering.
 */
class model_app_plugin extends model_app
{
	/**
	 * Optional parent model for access control delegation.
	 * @var object|null
	 */
	private $parent;

	/**
	 * Sets the parent object used for access control.
	 *
	 * @param object $parent
	 * @return void
	 */
	public function setParent($parent): void
	{
		$this->parent = $parent;
	}

	/**
	 * Sets the plugin configuration path.
	 *
	 * @param string $cfg Configuration path
	 * @return void
	 */
	public function setCfgPath($cfg): void
	{
		$this->cfg_path = $cfg;
	}

	/**
	 * Loads and executes the plugin based on parameters.
	 *
	 * @param array|null $input Input data (params, get, etc.)
	 * @param bool $twig Whether to render Twig templates
	 * @return array|null Plugin data output
	 */
	public function getContentData($input = null, bool $twig = true): ?array
	{
		$params = $input['params'] ?? [];

		// Access control
		$page = $params['page'] ?? '';			
		$page_single=explode(',', $page)[0];
		$plugin = $params['plugin'] ?? '';
		$plugin = preg_replace('/[^a-zA-Z0-9_-]/', '', $plugin); // Only alphanumeric, dash, underscore
		
	
		if (empty($plugin)) {
			exit("Invalid plugin name");
		}

		if ($this->parent && !$this->parent->checkAuth($page, [2, 3])) {
			exit("auth::error::0::app_plugin::{$page}::{$plugin}");
		}
		if (!$this->parent && !$this->checkAuth($page, [2, 3])) {
			exit("auth::error::1::app_plugin::{$page}::{$plugin}");
		}

		$schema=$this->apporm->getJsonModelSchemaWithPageUpdate($page_single);

		if (isset($params['record'])) $buttons=$schema['buttons_edit'];
			else $buttons=$schema['buttons_page'];
		
		if (!_uho_fx::array_filter($buttons,'plugin',$plugin))
			exit("auth::error::2::app_plugin::{$page}::{$plugin}");

		// Translations
		$defaultTranslate = [
			'pl' => ['back' => 'Powrót', 'error' => 'Wystąpił błąd'],
			'en' => ['back' => 'Back', 'error' => 'Error occurred']
		];
		$translate = $defaultTranslate[$this->lang] ?? [];

		if (!empty($params['orm'])) {
			$this->apporm = $params['orm'];
		}

		if (empty($plugin)) {
			return null;
		}

		// Determine plugin path

		$customPath = rtrim($this->cfg_path, '/') . '/plugins/' . $plugin . '/';
		$defaultPath = rtrim($this->cms_path, '/') . '/plugins/' . $plugin . '/';

		$pluginPath = _uho_fx::file_exists($customPath) ? $customPath : $defaultPath;
		$pluginPath = str_replace('//', '/', $pluginPath);
		$pluginFolder = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'] . $pluginPath);

		$jsFile = _uho_fx::file_exists($pluginPath . 'plugin.js') ? $pluginPath . 'plugin.js' : null;

		// Load plugin translation file if exists
		$pluginTrans = @file_get_contents($pluginFolder . 'plugin.json');
		$pluginTrans = $pluginTrans ? json_decode($pluginTrans, true) : [];
		$pluginTrans = $pluginTrans[$this->lang] ?? [];

		// Load plugin PHP class
		$pluginFile=$pluginFolder . 'plugin.php';
		if (!file_exists($pluginFile) || !is_readable($pluginFile)) {
			exit("Plugin file not found");
		}

		require_once $pluginFile;
		$pluginClass = 'serdelia_plugin_' . $plugin;

		// Parse page and parameters
		if (empty($params['page_with_params'])) {
			$params['page_with_params'] = $page;
			$pageParts = explode(',', $page);
			$params['page'] = array_shift($pageParts);
			$params['params'] = array_merge($params['params'] ?? [], $pageParts);
		}

		// Add query string if GET params present
		$params['page_with_params_and_query'] = $params['page_with_params'];
		if (!empty($input['get'])) {
			$params['page_with_params_and_query'] .= '?' . http_build_query($input['get']);
			$params['get'] = $input['get'];
		}

		// Set default parent
		if (!$this->parent) {
			$this->parent = $this;
		}

		// Instantiate plugin and get data
		$class = new $pluginClass($this->apporm, $params, $this->parent);
		$data = $class->getData();

		// Merge translations
		$data['translate'] = array_merge($translate, $pluginTrans);

		// Render Twig templates in translate strings
		if (is_array($data['translate']) && $twig) {
			foreach ($data['translate'] as $k => $v) {
				if (is_string($v)) {
					$data['translate'][$k] = $this->getTwigFromHtml($v, $data);
				}
			}
		}

		// Handle back button data
		if (!empty($data['back']['type']) && $data['back']['type'] === 'page') {
			$params['url_back'] = $params['url_back_page'] ?? null;
		}
		if (!empty($data['url_back'])) {
			$params['url_back'] = $data['url_back'];
		}

		$data['buttons'] = [[
			'label' => 'back',
			'icon' => 'back',
			'url'  => $params['url_back'] ?? null
		]];

		// Final HTML rendering
		if (!empty($data['result'])) {
			$htmlPath = $pluginFolder . 'plugin.html';
			$data['html'] = $twig && file_exists($htmlPath)
				? $this->getTwigFromHtml(file_get_contents($htmlPath), $data)
				: null;

			$data['url_back'] = $params['url_back'] ?? '';
			$data['url_back_string'] = $params['url_back_string'] ?? '';
			$data['url_back_page_string'] = $params['url_back_page_string'] ?? '';
			$data['js'] = $jsFile;
		}

		// Clear any plugin-related cache
		$this->cacheKill();

		return $data;
	}
}
