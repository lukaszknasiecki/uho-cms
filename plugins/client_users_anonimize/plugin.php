<?php

/**
 * Serdelia built-in plugin to anonimize user's subscription
 */

use Huncwot\UhoFramework\_uho_fx;

class serdelia_plugin_client_users_anonimize
{

    private $cms, $params, $parent;

    /** Standard Serdelia Plugin Contructor
     * object array $cms instance of _uho_orm
     * object array $params
     * object array $parent instance of _uho_model
     * @return null
     */

    public function __construct($cms, $params, $parent = null)
    {
        $this->cms = $cms;
        $this->params = $params;
        $this->parent = $parent;
    }

    /** Main plugin-method, returns data for View module
     * @return array
     */

    public function getData()
    {
        $user = $this->params['record'];

        if (_uho_fx::getGet('anonimize')) {
            $action = 'success';
            $this->cms->put($this->params['page'], ['id' => $user, 'email' => '', 'institution' => '', 'name' => 'Anonymous', 'surname' => '', 'uid' => '', 'status' => 'anonimized']);
        }


        $data = ['result' => true, 'action' => $action];

        return $data;
    }
}