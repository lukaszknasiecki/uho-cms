<?php

/**
 * Serdelia built-in plugin to restore backup data
 */

class serdelia_plugin_cms_backup_restore
{

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

        $errors = false;;
        $success = false;

        if (isset($this->params['get']['confirm'])) {
            if ($this->parent->backupRestore($this->params['record']))
                $success = true;
            else $errors = true;
        }

        $data = ['result' => true, 'errors' => $errors, 'success' => $success];

        return $data;
    }
}
