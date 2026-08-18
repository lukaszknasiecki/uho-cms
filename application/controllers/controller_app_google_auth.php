<?php

require_once("controller_app.php");

/**
 * Controller class for the GppgleAuth.
 * Inherits from controller_app.
 */
class controller_app_google_auth extends controller_app
{
    /**
     * Retrieves data for the Reports page view.
     *
     * @return array Returns an array containing:
     *               - 'view': the template name ('reports')
     *               - 'content': data fetched from the model (empty parameters)
     */
    public function getContentData()
    {
        $content=$this->model->getContentData(['get' => $this->get, 'title' => $this->cfg['cms']['title']]);

        if (!empty($content['authenticated']))
        {
            $this->route->redirect('');
        }

        return [
            'view'    => 'google_auth',
            'content' => $content
        ];
    }
}
