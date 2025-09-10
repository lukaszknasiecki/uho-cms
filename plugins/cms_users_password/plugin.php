<?php

/**
 * Serdelia built-in plugin to set user's password
 */

class serdelia_plugin_cms_users_password
{

    private $cms,$params,$parent;

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
        $errors = [];
        $required = $this->parent->getClientsCfg('password_required');
        if (!$required) exit('cms_users_password::password_required cfg missing');
        $required = explode(',', $required);

        if ($_POST['password'] || $_POST['generate']) {
            $rr = ['min_length', 'min_lower', 'min_upper', 'min_numbers', 'min_special'];
            if ($_POST['generate']) $generated = $password = $this->parent->clientsGeneratePassword();
            else {
                if ($_POST['password'] != $_POST['password2']) $errors[] = 'mismatch';
                $password = $_POST['password'];
            }

            $r = $this->parent->clientsValidatePasswordFormat($password);
            //print_r($r['errors']);
            if ($r['errors'])
                foreach ($r['errors'] as $k => $v) {
                    $v[0] = array_search($v[0], $rr);
                    $errors[] = $v;
                }
            elseif (!$errors) {
                $success = $this->parent->clientsSetPassword($this->params['record'], $r['password']);
                if (!$success) $errors[] = 'system';
            }
        }

        $data = ['result' => true, 'errors' => $errors, 'required' => $required, 'success' => $success, 'generated' => $generated];

        return $data;
    }
}
