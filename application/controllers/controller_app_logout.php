<?php

require_once("controller_app.php");

/**
 * Controller class for handling logout functionality.
 * Inherits from controller_app.
 */
class controller_app_logout extends controller_app
{
    /**
     * Prepares data for the Logout view.
     *
     * @return array Contains:
     *               - 'view': the template to render ('logout')
     *               - 'content': logout-related data returned by the model
     */
    public function getContentData()
    {
        // Fetch any additional logout-related content from the model (if needed)
        $logoutContent = $this->model->getContentData($this->get);

        // Return data for rendering the logout view
        return [
            'view'    => 'logout',
            'content' => $logoutContent
        ];
    }
}

?>