<?php

/**
 * CMS built-in plugin for page preview using IFRAME
 */

class serdelia_plugin_preview
{

    /** Standard CMS Plugin Contructor
     * object array $cms instance of _uho_orm
     * object array $params
     * @return null
     */

    private $params, $parent;

    public function __construct(object $cms, array $params, object $parent = null)
    {
        $this->params = $params;
        $this->parent = $parent;
    }

    /** Main plugin-method, returns data for View module
     * @return array
     */

    public function getData()
    {

        $url = @$this->params['params']['url'];

        if (!$url) {
            $url = json_decode($this->params['get']['params'], true);
            $url = $url['url'];
        }

        $url = explode(';', $url);
        $url = array_shift($url);
        $url = str_replace('%', '', $url);
        $url = str_replace('¿', '?', $url);

        if (!$url) return ['result' => false, 'message' => 'url_empty'];
        return [
            'result' => true,
            'src' => $url,
            'fullscreen' => true,
            'new_window' => $url,
            'cms_path' => $this->parent->cms_path,
            'root_path' => $this->params['url_serdelia']
        ];
    }
}
