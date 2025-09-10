<?php

/**
 * Serdelia built-in plugin to set authorization for user
 */

class serdelia_plugin_cms_users_auth
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
        $user = $this->params['record'];
        $auth_list = $this->parent->getAuthList();
        $auth_presets = $this->parent->getAuthPresets();

        if ($_POST) {
            $set = [];
            $val = [];
            foreach ($_POST as $k => $v)
                if (substr($k, 0, 6) == 'allow_' && $v) {
                    $val[] = substr($k, 6) . '=' . $v;
                    $set[substr($k, 6)] = $v;
                }
            $val = implode(',', $val);
            $set_preset = '';

            if ($auth_presets)
                foreach ($auth_presets as $k => $v)
                    if ($v['auth'] == $set) $set_preset = $v['label'];


            $this->cms->putJsonModel('cms_users', ['auth' => $val, 'id' => $user, 'auth_label' => $set_preset]);
            $success = true;
        }

        $auth = $this->cms->getJsonModel('cms_users', ['id' => $user], true);
        $auth = explode(',', $auth['auth']);
        foreach ($auth as $k => $v) {
            $v = explode('=', $v);
            $auth[$v[0]] = $v[1];
        }

        $errors = [];
        $items = $auth_list;
        foreach ($items as $k => $v) {
            if ($auth[$v['id']]) $items[$k]['value'] = $auth[$v['id']];
            else $items[$k]['value'] = 0;
        }

        $levels = [
            ['label' => 'off', 'class' => 'default'],
            ['label' => 'read', 'class' => 'success'],
            ['label' => 'write', 'class' => 'warning'],
            ['label' => 'admin', 'class' => 'danger']
        ];

        $presets = $auth_presets;


        $data = ['result' => true, 'presets' => $presets, 'errors' => $errors, 'required' => $required, 'success' => $success, 'items' => $items, 'levels' => $levels];

        return $data;
    }
}
