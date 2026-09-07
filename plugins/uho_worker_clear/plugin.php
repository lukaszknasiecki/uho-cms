<?php

/**
 * Serdelia built-in plugin to remove all uho_worker_queries
 */

use Huncwot\UhoFramework\_uho_fx;

class serdelia_plugin_uho_worker_clear
{

    var $cms, $params;

    /** Standard Serdelia Plugin Contructor
     * object array $cms instance of _uho_orm
     * object array $params
     * object array $parent instance of _uho_model
     * @return null
     */


    public function __construct($cms, $params, $parent)
    {
        $this->cms = $cms;
        $this->params = $params;
    }

    /** Main plugin-method, returns data for View module
     * @return array
     */

    public function getData()
    {
        $removed = 0;
        $action = '';

        $schema = $this->cms->getSchema('uho_worker');
        $field = _uho_fx::array_filter($schema['fields'], 'field', 'status', ['first' => true]);
        $types = $field['options'];


        foreach ($types as $k => $v) {
            $types[$k] = ['value' => $v['value'], 'label' => $v['label' . $this->parent->lang_add], 'count' => $this->cms->get('uho_worker', ['status' => $v['value']], false, null, null, ['count' => true])];
        }


        if (@$_POST['submit']) {
            foreach ($types as $k => $v)
                if ($_POST[$v['value']]) {
                    $removed += $v['count'];
                    $this->cms->delete('uho_worker', ['status' => $v['value']]);
                }
        }

        foreach ($types as $k => $v) {
            $types[$k]['count'] = $this->cms->get('uho_worker', ['status' => $v['value']], false, null, null, ['count' => true]);
        }

        /*
        $errors = [];
        $added = [];
        
        $items=$this->cms->getJsonModel('uho_worker',['status'=>'error'],false,null);

        $update=[];
        if ($items)
        {
            foreach ($items as $k=>$v)
            if ($v['action'])
            {
                //$exists=$this->cms->getJsonModel('uho_worker',['status'=>'waiting','action'=>$v['action']],true);
                //if (!$exists) 
                $update[]=$v['id'];
            }
        }
        if ($update)
        {
            $this->cms->queryOut('UPDATE uho_worker SET status="waiting" WHERE id IN('.implode(',',$update).')');

            

            
        }

        // dupllicates
        $removed=0;
        $query='SELECT id FROM uho_worker WHERE status="waiting" GROUP BY action HAVING COUNT(action) > 1';
        $items=$this->cms->query($query);
        if ($items)
        {
            foreach ($items as $k=>$v)
                $items[$k]=$v['id'];
            $removed=count($items);
            $this->cms->queryOut('UPDATE uho_worker SET status="disabled" WHERE id IN ('.implode(',',$items).')');
        }
         */

        $data = ['result' => true, 'action' => $action, 'types' => $types, 'removed' => $removed];

        return $data;
    }
}
