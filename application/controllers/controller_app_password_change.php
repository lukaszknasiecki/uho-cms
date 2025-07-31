<?php

require_once("controller_app.php");

/**
 * Controller class for handling password change functionality.
 * Inherits from controller_app.
 */
class controller_app_password_change extends controller_app
{
    /**
     * Prepares data for the Password Change view.
     * Redirects to login page if password change is complete.
     *
     * @return array Contains:
     *               - 'view': template to render ('password_change')
     *               - 'content': result of the password change attempt
     *               - 'nav': optional flag to show navigation (if user is logged in)
     */
    public function getContentData()
    {
        // Fetch password change results from the model using POST and GET data
        $data = [
            'view'    => 'password_change',
            'content' => $this->model->getContentData($this->post, $this->get)
        ];

        // If a user is logged in, enable navigation display
        if ($this->model->getUser()) {
            $data['nav'] = true;
        }

        // If the model requests a redirect after password change, go to login
        if (!empty($data['content']['redirect'])) {
            $this->route->redirect('login');
        }

        // Return view data
        return $data;
    }
}

?>