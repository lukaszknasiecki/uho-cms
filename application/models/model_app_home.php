<?php

use Huncwot\UhoFramework\_uho_fx;

require_once('model_app.php');

/**
 * Model class for homepage view
 */
class model_app_home extends model_app
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
		$this->url_base = str_replace('//', '/', $url_base . '/');

		$uri = $this->cfg_folder . '/structure/dashboard.json';
		$home = file_exists($uri) ? file_get_contents($uri) : null;

		if ($home) {
			$home = json_decode($home, true);
			if (!$home) exit('dashboard.json file corrupted');
		} else {
			$home = [
				'type' => 'widgets',
				'widgets' => ['widget' => 'hello']
			];
		}

		$sections = [];

		switch ($home['type']) {
			case "widgets":
				foreach ($home['widgets'] as $k => $v) {
					if (!is_array($v)) $v = ['widget' => $v];
					$home['widgets'][$k] = $this->widgetGet($v);

					if (!$home['widgets'][$k]) {
						unset($home['widgets'][$k]);
					} elseif (!empty($home['widgets'][$k]['url'])) {
						$url = explode('/', $home['widgets'][$k]['url']);
						if (!$this->checkAuth($url[1])) {
							unset($home['widgets'][$k]);
						}
					}
				}
				break;

			case "sections":
				$sections = $home['sections'];

				// Process each section's items
				foreach ($sections as $k => $v) {
					if ($v['items']) {
						foreach ($v['items'] as $k2 => $v2) {
							$v2 = $this->updateSectionItem($v2);
							if ($v2) {
								$sections[$k]['items'][$k2] = $v2;
							} else {
								unset($sections[$k]['items'][$k2]);
							}
						}
					}
				}

				// Remove empty sections
				foreach ($sections as $k => $v) {
					if (empty($sections[$k]['items'])) {
						unset($sections[$k]);
					}
				}
				break;
		}

		return [
			'type' => $home['type'],
			'widgets' => @$home['widgets'],
			'sections' => $sections
		];
	}

	/**
	 * Recursively updates a section item, setting URLs and cleaning up invalid entries.
	 *
	 * @param array $item
	 * @return array|null
	 */
	private function updateSectionItem($item)
	{
		// Recursively process submenu items
		if (!empty($item['submenu'])) {
			foreach ($item['submenu'] as $k => $v) {
				$item['submenu'][$k] = $this->updateSectionItem($v);
				if (!$item['submenu'][$k]) unset($item['submenu'][$k]);
			}
			if (empty($item['submenu'])) return null;
		} else {
			// Set item URL if model is defined
			if (!empty($item['model']) && !empty($item['record'])) {
				$item['url'] = 'edit/' . $item['model'] . '/' . $item['record'];
			} elseif (!empty($item['model']) && $this->checkAuth($item['model'])) {
				$item['url'] = 'page/' . $item['model'];
			}

			if (empty($item['url'])) return null;
		}

		return $item;
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
		if (!isset($this->classes[$f])) {
			if (!file_exists($path . 'widget.php')) return false;

			require $path . 'widget.php';
			$widget_class = 'serdelia_widget_' . $f;
			$params['lang'] = $this->lang;

			$class = new $widget_class($this->apporm, $params, $this);
			$this->classes[$f] = $class;
		} else {
			$class = $this->classes[$f];
			$class->setParams($params);
		}

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
		if (!empty($data['result'])) {
			$html = file_exists($path . 'widget.html') 
				? file_get_contents($path . 'widget.html') 
				: file_get_contents($this->cms_folder . '/application/views/modules/widgets/_widget.html');

			if ($render) {
				$data = $this->twigger($data, $data);
				$data['url_base'] = $this->url_base;
				$data['url'] = _uho_fx::trim($data['url'], '/');
				$data['html'] = $this->getTwigFromHtml($html, $data);
			}
		}

		return $data;
	}

	/**
	 * Recursively applies Twig rendering to all strings in an array.
	 *
	 * @param array $data
	 * @param array $replace
	 * @return array
	 */
	private function twigger($data, $replace)
	{
		foreach ($data as $k => $v) {
			if (is_array($v)) {
				$data[$k] = $this->twigger($v, $replace);
			} elseif (!empty($v)) {
				// Render twice (possibly a legacy double-pass strategy)
				$data[$k] = $this->getTwigFromHtml($v, $replace);
				$data[$k] = $this->getTwigFromHtml($data[$k], $replace);
			}
		}
		return $data;
	}
}