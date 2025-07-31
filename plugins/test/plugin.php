<?php

/**
 * Serdelia built-in test/sample plugin
 */

class serdelia_plugin_test
{

    /** Standard CMS Plugin Contructor
     * object object $cms instance of _uho_orm
     * object array $params
     * object object $parent instance of _uho_model
     * @return null
     */


    public function __construct(object $cms, array $params, object $parent)
    {
    }

    /** Main plugin-method, returns data for View module
     * @return array
     */

    public function getData()
    {

        $data = ['result' => true, 'message' => 'All good!'];

        return $data;
    }
}
