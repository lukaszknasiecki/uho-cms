<?php

use Huncwot\UhoFramework\_uho_fx;

require_once("controller_app.php");

/**
 * Controller class for handling plugin-related functionality.
 * Inherits from controller_app.
 */
class controller_app_plugin extends controller_app
{
    /**
     * Prepares data for the Plugin module view.
     * Handles different plugin routes, builds navigation and URLs, and optionally returns Ajax responses.
     *
     * @return array View and content data for rendering.
     */
    public function getContentData()
    {
        // Parse current path from the route
        $pathNow = explode('/', $this->route->getPathNow());
        $params  = [];

        // Determine plugin context based on the route
        switch ($pathNow[0]) {
            case "plugin-edit":
                $params['page']   = $pathNow[1] ?? '';
                $params['record'] = $pathNow[2] ?? '';
                $params['plugin'] = $pathNow[3] ?? '';
                break;

            case "plugin-page":
                $params['page']   = $pathNow[1] ?? '';
                $params['plugin'] = $pathNow[2] ?? '';
                break;
        }

        // Decode JSON parameters from GET query string
        $gets=_uho_fx::getGetArray();
        if (isset($gets['params'])) $p=$gets['params']; else $p=null;
        
        if ($p) {
            $p = json_decode(urldecode($p), true);
        }
        if ($p) {
            $params['params'] = $p;
        }

        // Determine back URL for navigation
        if (!empty($params['record'])) {
            $url_back = [
                'type'   => 'edit',
                'page'   => $params['page'],
                'record' => $params['record']
            ];
        } else {
            $url_back = [
                'type' => 'page',
                'page' => $params['page']
            ];
        }

        $params['url_back'] = $url_back;
        $params['url_serdelia'] = '';

        // Update URLs based on current parameters
        $updatedUrls = $this->urlUpdate($params);
        $params['url_back_string']     = $updatedUrls['url_back'];
        $params['url_serdelia']        = $updatedUrls['url_serdelia'];
        $params['url_back_page']       = ['type' => 'page', 'page' => $params['page']];

        // Build a back URL string for navigation (to the page view)
        $p0 = explode('/', $updatedUrls['url_back']);
        array_pop($p0);
        $p0 = implode('/', $p0);
        $p0 = str_replace('/edit/', '/page/', $p0);
        $params['url_back_page_string'] = $p0;

        // Set config and filesystem paths
        $params['config_path']     = $this->cfg['config_folder'];
        $params['serdelia_path']   = $_SERVER['DOCUMENT_ROOT'] . $updatedUrls['url_serdelia'];

        // Fetch content data using the prepared parameters
        $data = [
            'nav'     => true,
            'content' => $this->model->getContentData([
                'params' => $params,
                'get'    => $this->get
            ])
        ];

        // If this is an Ajax request, return JSON response immediately
        if (_uho_fx::isAjax()) {
            exit(json_encode($data['content']));
        }

        // Prevent URL rewriting for certain dynamic fields
        $data['content']['skip_url_update'] = ['message', 'translate'];

        // Load JavaScript if specified in the content
        if (!empty($data['content']['js'])) {
            $this->addJs($data['content']['js']);
        }

        // Set the view template
        $data['view'] = 'plugin';

        // If available, set a translated page title
        if (!empty($data['content']['page_label'])) {
            $data['content']['translate']['title'] = $data['content']['page_label'];
        }

        // Optional display flags
        if (!empty($data['content']['fullscreen'])) {
            $data['fullscreen'] = true;
        }

        if (!empty($data['content']['lightbox'])) {
            $data['lightbox'] = true;
        }

        // Return the final structured data for rendering
        return $data;
    }
}
?>