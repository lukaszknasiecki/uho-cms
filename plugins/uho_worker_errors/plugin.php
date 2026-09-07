<?php

/**
 * Serdelia built-in plugin to reactivate error uho_worker_queries
 */

class serdelia_plugin_uho_worker_errors
{

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
        $this->parent = $parent;
    }

    /** Main plugin-method, returns data for View module
     * @return array
     */

    public function getData()
    {
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
         

        $data = ['result' => true, 'updated' => count($update),'removed'=>$removed];

        return $data;
    }

}
