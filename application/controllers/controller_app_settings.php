<?php

require_once("controller_app.php");

/**
 * Controller class for handling the Settings page.
 * Extends the base controller_app class.
 */
class controller_app_settings extends controller_app
{
    /**
     * Retrieves data for the Settings module.
     *
     * @return array Returns an array with:
     *               - 'view': the template name ('settings')
     *               - 'nav': boolean to show navigation if user is logged in
     *               - 'content': data fetched from the model based on current URL and GET params
     */
    public function getContentData()
    {
        $data = [
            'view' => 'settings'
        ];

        // Show navigation if user is logged in
        if ($this->model->getUser()) {
            $data['nav'] = true;
        }

        // Get the current route path
        $pathNow = $this->route->getPathNow();

        // Fetch content data from model with URL and GET parameters
        $data['content'] = $this->model->getContentData([
            'url' => $pathNow,
            'get' => $this->get
        ]);

        // If model requests a redirect, send to login page
        if (!empty($data['content']['redirect'])) {
            $this->route->redirect('login');
        }

        return $data;
    }
}