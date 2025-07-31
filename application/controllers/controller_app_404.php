<?php

/**
 * Controller class for handling the 404 (Not Found) page.
 * Inherits from controller_app.
 */

require_once("controller_app.php");

class controller_app_404 extends controller_app
{
    /**
     * Prepares data for the 404 view.
     *
     * @return array Array containing the view information for the 404 error page.
     */
    public function getContentData()
    {
        // Return only the view name for rendering the 404 page.
        return [
            'view' => '404' // Indicates that the '404' view/template should be loaded.
        ];
    }
}

?>