<?php

require_once("controller_app.php");

/**
 * Controller class for handling login functionality.
 * Inherits from controller_app.
 */
class controller_app_login extends controller_app
{
    /**
     * Prepares data for the Login view.
     * Redirects to home if login is successful.
     *
     * @return array Contains:
     *               - 'view': template name ('login')
     *               - 'content': login attempt results (if any)
     */
    public function getContentData()
    {
        // Attempt to retrieve login result from the model using POST data
        $data = [
            'view'    => 'login',
            'content' => $this->model->getContentData($this->post)
        ];

        // If login was successful, redirect to the homepage
        if (!empty($data['content']['logged'])) {
            $this->route->redirect('');
        }

        // Otherwise, return login view data
        return $data;
    }
}

?>