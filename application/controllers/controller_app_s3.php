<?php

require_once("controller_app.php");

/**
 * Controller class for the S3 debug page.
 * Extends the base controller_app class.
 */
class controller_app_s3 extends controller_app
{
    /**
     * Retrieves data for the S3 page view.
     *
     * @return array Returns an array containing:
     *               - 'view': the template name ('s3')
     *               - 'content': data fetched from the model (no parameters)
     */
    public function getContentData()
    {
        return [
            'view'    => 's3',
            'content' => $this->model->getContentData([])
        ];
    }
}