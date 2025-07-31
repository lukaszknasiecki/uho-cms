<?php

require_once("controller_app.php");

/**
 * Controller class for handling record write actions.
 * Extends the base controller_app class.
 */
class controller_app_write extends controller_app
{
    /**
     * Retrieves data for the Write module view.
     *
     * @return array Returns an array with:
     *               - 'nav': boolean to show navigation
     *               - 'content': data fetched from the model for the current URL and GET parameters
     *               - 'view': the template name ('write')
     */
    public function getContentData()
    {
        // Get the current route path
        $pathNow = $this->route->getPathNow();

        // Prepare and return the data array
        return [
            'nav'     => true,
            'content' => $this->model->getContentData(['url' => $pathNow, 'get' => $this->get]),
            'view'    => 'write'
        ];
    }
}