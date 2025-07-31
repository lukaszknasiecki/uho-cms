<?php

require_once("controller_app.php");

/**
 * Controller class for handling record removal actions.
 * Inherits from controller_app.
 */
class controller_app_remove extends controller_app
{
    /**
     * Prepares data for the Remove record view.
     *
     * @return array Returns an array with:
     *               - 'nav': boolean to show navigation
     *               - 'content': data returned from the model for the current URL
     *               - 'view': the template to render ('edit' view)
     */
    public function getContentData()
    {
        // Get current path from the route
        $pathNow = $this->route->getPathNow();

        // Prepare and return the view data
        return [
            'nav'     => true,
            'content' => $this->model->getContentData(['url' => $pathNow, 'get' => $this->get]),
            'view'    => 'edit'
        ];
    }
}