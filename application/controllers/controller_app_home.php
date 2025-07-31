<?php

/**
 * Controller class for handling the homepage view.
 * Inherits from controller_app.
 */

require_once("controller_app.php");

class controller_app_home extends controller_app
{
    /**
     * Prepares data for the Homepage view.
     *
     * @return array Array containing:
     *               - 'view': the template name
     *               - 'nav': whether navigation is shown
     *               - 'content': data fetched from the model for rendering
     */
    public function getContentData()
    {
        // Get the current base URL for the homepage
        $homeUrl = $this->route->getUrl('');

        // Retrieve content data using posted data and URL
        $contentData = $this->model->getContentData($this->post, $homeUrl);

        // Return data to be used in the view rendering
        return [
            'view'    => 'home',     // View template to use
            'nav'     => true,       // Show navigation
            'content' => $contentData
        ];
    }
}

?>