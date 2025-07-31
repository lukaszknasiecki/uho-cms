<?php

require_once("controller_app.php");

/**
 * Controller class for handling Page view logic.
 * Inherits from controller_app.
 */
class controller_app_page extends controller_app
{
    /**
     * Prepares data for the Page view.
     *
     * @return array Array containing view information, including navigation visibility,
     *               content data fetched from the model, and the view type.
     */
    public function getContentData()
    {
        // Ensure the 'pages' session array is initialized
        if (empty($_SESSION['pages'])) {
            $_SESSION['pages'] = [];
        }

        // Get the current page key from the route (second segment in URL)
        $pageKey = $this->route->e(1);

        // Get the current full URL query string, if available
        $queryString = explode('?', $_SERVER['REQUEST_URI'])[1] ?? '';

        // Store query string under the current page key in the session
        $_SESSION['pages'][$pageKey] = ['query' => $queryString];

        // Get the current path from the router
        $currentPath = $this->route->getPathNow();

        // Return the content data needed by the view layer
        return [
            'nav'     => true, // Show navigation
            'content' => $this->model->getContentData([
                'url' => $currentPath,
                'get' => $this->get
            ]),
            'view'    => 'page'
        ];
    }
}

?>