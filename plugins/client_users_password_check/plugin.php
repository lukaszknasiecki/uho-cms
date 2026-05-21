<?php

/**
 * Serdelia built-in plugin to set user's password checker
 */

use Huncwot\UhoFramework\_uho_client;
use Huncwot\UhoFramework\_uho_auth;

class serdelia_plugin_client_users_password_check
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

        $cfg = $this->parent->getPluginKey('client');

        $required = !empty($cfg['password_required']);
        if (!$required) exit('client_users_password::password_required cfg missing');
        else $required = explode(',', $cfg['password_required']);

        $client = new _uho_client(
            $this->parent->apporm,
            [
                'models' =>
                [
                    'client_model' => $cfg['client_model'],
                    'client_logs_model' => $cfg['client_logs_model'],
                    'client_logins_model' => $cfg['client_logins_model']
                ],
                'users' =>
                [
                    'bad_login' => 'bad_login',
                ],
                'salt' => ['type' => 'double', 'value' => $cfg['password_salt'], 'field' => 'salt'],
                'hash' => @$cfg['password_hash'],
                'settings' =>
                [
                    'password_format' => isset($cfg['password_required']) ? $cfg['password_required'] : null
                ]
            ]
        );

        $auth = new _uho_auth(
            $this->parent->apporm,
            [
                'salt' => $cfg['password_salt']
            ],
            false
        );

        $errors = [];


        if ($_POST['password']) {
            //$check=$client->getClient(['id' => $this->params['record'],'password' => $_POST['password']]);

            $user = $auth->getUserByParams(['id' => $this->params['record'], 'password' => $_POST['password']]);


            if ($user) $success = 'Password is correct';
            else $errors = 'Password incorrect';
        }

        $data = ['result' => true, 'errors' => $errors, 'required' => $required, 'success' => $success, 'generated' => $generated];

        return $data;
    }

    public function setCfg($cfg) {}
}
