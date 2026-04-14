<?php

use Huncwot\UhoFramework\_uho_fx;

require_once('model_app.php');

/**
 * Model class for dashboard view
 */
class model_app_dashboard extends model_app
{
	/**
	 * Stores instances of loaded widget classes.
	 * @var array
	 */
	public $classes = [];

	/**
	 * Base URL prefix (e.g., /serdelia/).
	 * @var string
	 */
	public $url_base;

	/**
	 * Loads content data for the homepage, including widgets or sections.
	 *
	 * @param array|null $params (Deprecated)
	 * @param string $url_base URL base path
	 * @return array|null Structured homepage data
	 */
	public function getContentData($params = null, $url_base = '')
	{
		$path = explode('/', $params['url']);
		$params['record'] = intval(array_pop($path));
		$dashboard_name = array_pop($path);

		$this->url_base = str_replace('//', '/', $url_base . '/');

		$uri = $this->cfg_folder . '/dashboards/'.$dashboard_name.'.json';
		$home = file_exists($uri) ? file_get_contents($uri) : null;
		$home = json_decode($home, true);

		foreach ($home['widgets'] as $k => $v) {
			if (!is_array($v)) $v = ['widget' => $v];
			if (empty($v['params'])) $v['params'] = [];
			$v['params'] = array_merge($v['params'], $params);
			$home['widgets'][$k] = $this->widgetGet($v, $params);

			if (!$home['widgets'][$k]) {
				unset($home['widgets'][$k]);
			} elseif (!empty($home['widgets'][$k]['url'])) {
				$url = explode('/', $home['widgets'][$k]['url']);
				if (!$this->checkAuth($url[1])) {
					unset($home['widgets'][$k]);
				}
			}
		}

		return [
			'widgets' => @$home['widgets']
		];
	}

	/**
	 * Loads and optionally renders a widget.
	 *
	 * @param array $widget Widget config (must include 'widget' key)
	 * @param bool $render Whether to render HTML
	 * @return array|false Widget data, or false if loading failed
	 */
	public function widgetGet($widget, $render = true)
	{
		$f = $widget['widget'];
		$params = $widget['params'] ?? [];
		$params['user'] = $this->getUser();

		// Widget path lookup
		$path = $this->cms_folder . '/widgets/' . $f . '/';
		if (!file_exists($path . '/widget.php')) {
			$path = $this->cfg_folder . '/widgets/' . $f . '/';
		}

		// Load and instantiate widget class
		if (!file_exists($path . 'widget.php')) return false;

		require_once $path . 'widget.php';
		$widget_class = 'serdelia_widget_' . $f;

		$params['lang'] = $this->lang;
		$params['record'] = 1;

		$class = new $widget_class($this->apporm, $params, $this);
		$this->classes[$f] = $class;

		// Load translations (widget.json)
		$translate = [];
		if (file_exists($path . 'widget.json')) {
			$json = file_get_contents($path . 'widget.json');
			$translateData = json_decode($json, true);
			if ($translateData && isset($translateData[$this->lang])) {
				$lang = $translateData[$this->lang];
				unset($translateData['pl'], $translateData['en']);
				$translate = array_merge($translateData, $lang);
			}
		}

		// Get widget data
		$data = $class->getData();
		$data = array_merge($translate, $data);

		// Render widget HTML if required
		if (!empty($data['result']))
		{
			$html = file_exists($path . 'widget.html')
				? file_get_contents($path . 'widget.html')
				: file_get_contents($this->cms_folder . '/application/views/modules/widgets/_widget.html');

			if ($render) {
				$data['url_base'] = $this->url_base;
				$data['url'] = _uho_fx::trim($data['url'], '/');
				$data['text'] = $this->getTwigFromHtml($data['text'], $data);
				$data['html'] = $this->getTwigFromHtml($html, $data);
			}
		}

		return $data;
	}
}
