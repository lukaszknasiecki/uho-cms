<?php

require_once("controller_app.php");

/**
 * Controller class for the 2Auth.
 * Inherits from controller_app.
 */
class controller_app_2auth extends controller_app
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
            $this->model->set2factor();
            $this->route->redirect('');
        }

        return [
            'view'    => '2auth',
            'content' => $content
        ];
    }
}
