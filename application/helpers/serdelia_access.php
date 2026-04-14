<?php

class serdelia_access
{

    protected $orm, $params, $parent,$schema_name;

    public function __construct($orm, $schema_name, $params, $parent)
    {
        $this->orm = $orm;
        $this->schema_name = $schema_name;
        $this->params = $params;
        $this->parent = $parent;
    }

    /*
        Check access for given record
    */
    public function isAccessRecord($record_id)
    {
    }

    /*
        Create filters for given schema
    */
    public function getFilters()
    {
    }

    public function isAccessList()
    {
    }

    public function getAccessWrite()
    {
        return ['project'=>$this->params['nested'][1]];
    }

}
