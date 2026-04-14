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
		/*
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
				
				foreach ($home['widgets'] as $k => $v)
				{
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
*/
		return [
		];
	}


}