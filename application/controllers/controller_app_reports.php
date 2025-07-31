<?php

require_once("controller_app.php");

/**
 * Controller class for the Reports debug page.
 * Inherits from controller_app.
 */
class controller_app_reports extends controller_app
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
        return [
            'view'    => 'reports',
            'content' => $this->model->getContentData([])
        ];
    }
}