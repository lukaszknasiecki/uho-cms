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
        if ($this->model->clients->adminExists()) $this->route->redirect('login');
        $data['view']='create';
        $data['content']=$this->model->getContentData($this->post);
        if ($data['content']['created']) $this->route->redirect('login');
        return $data;        
    }

}

?>