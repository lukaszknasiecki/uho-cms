<?php


/**
 * Model class for Worker widget
 */

class serdelia_widget_worker
{

    var $params;
    var $orm;
    var $lang_add;
    var $parent;

    /**
     * Constructor
     * @param object $orm instance of _uho_orm class
     * @param array $params
     */

    public function __construct($orm, $params)
    {
        $this->orm = $orm;
        $this->params = $params;
    }

    /**
     * Loads model data to be rendered by View
     * @return array
     */

    public function getData()
    {
        $logs = $this->orm->query('SELECT status FROM uho_worker GROUP BY status');
        $pending=0;
        foreach ($logs as $k=>$log)
        {
            $logs[$k]['stats'] = $this->orm->query('SELECT COUNT(*) AS count FROM uho_worker WHERE status="'.$log['status'].'"');
            if ($log['status'] === 'waiting') {
                $pending = $logs[$k]['stats'][0]['count'];
            }
        }

        
        if ($pending > 0) {
            $params=["bg-warning"];
        }   
        

        return ['result' => true, 'logs' => $logs,'params'=>$params];
    }
}
