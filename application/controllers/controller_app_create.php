<?php

require_once("controller_app.php");

/**
 * Controller class for creating new Admin
 */

class controller_app_create extends controller_app
{

    /**
     * Gets data for Create Admin module controller
     * @return array
     */

    public function getContentData()
    {
        if ($this->model->clients->adminExists()) $this->route->redirect('login?source=create');
        $initialized = $_SERVER['DOCUMENT_ROOT'] . $this->cfg['config_path'] . '/.initialized';
        if (file_exists($initialized))
            exit('Error. CMS initialized with empty table.');
        $data['view'] = 'create';
        $data['content'] = $this->model->getContentData($this->post);
        if ($data['content']['created']) {
            file_put_contents($initialized, date('Y-m-d H:i:s'));
            $this->route->redirect('login?source=create');
        }
        return $data;
    }
}
