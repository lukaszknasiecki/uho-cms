<?php

require_once("controller_app.php");

/**
 * Controller class for handling record editing functionality.
 * Inherits from controller_app.
 */
class controller_app_edit extends controller_app
{
    /**
     * Prepares data for the Edit view.
     *
     * @return array Returns an array containing:
     *               - 'nav': whether to show navigation
     *               - 'content': content data from the model
     *               - 'view': name of the view template
     */
    public function getContentData()
    {
        // Get the current route path (used to identify the record or section)
        $currentPath = $this->route->getPathNow();

        // Fetch content data using the model with the current path and GET parameters
        $contentData = $this->model->getContentData([
            'url' => $currentPath,
            'get' => $this->get
        ]);

        // Return data to be used by the view
        return [
            'nav'     => true,       // Enable navigation in the view
            'content' => $contentData,
            'view'    => 'edit'      // Use the 'edit' view template
        ];
    }
}
