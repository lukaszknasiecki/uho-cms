<?php

/**
 * Model class for sample widget
 */

class serdelia_widget_boilerplate
{

    /**
     * Constructor
     * @param object $orm instance of _uho_orm class
     * @param array $params
     */

    private $orm;
    private $params;

    public function __construct($orm, $params)
    {
        $this->orm = $orm;
        $this->params = $params;
    }

    /**
     * Retrieves widget data
     * @return array
     */
    
    public function getData(): array
    {
        // in any widget you can retrieve any data you want from the ORM
        // and then use them in the output
        $logs = $this->orm->getJsonModel('cms_users_logs_logins', ['success' => 1]);
        if (isset($logs)) $amount = count($logs);
        else $amount = 0;

        // you can pass any variabled to the View, one is required
        // to properly render widget - result=true

        return ['result' => true, 'amount' => $amount, 'name' => $this->params['user']['name']];
    }
}
