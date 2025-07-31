<?php

require_once('model_app.php');

/**
 * Model class for handling 404 (Not Found) page data.
 */
class model_app_404 extends model_app
{
    /**
     * Returns data for the 404 page.
     *
     * @param array|null $params Optional parameters (currently unused).
     * @return array
     */
    public function getContentData($params = null): array
    {
        return [
            'message' => 'Page not found',
            'code' => 404
        ];
    }
}