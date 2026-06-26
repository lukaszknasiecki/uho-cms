<?php

/**
 * Serdelia built-in plugin to export model instances
 */

use Huncwot\UhoFramework\_uho_fx;
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

class serdelia_plugin_drive_export
{
    var $cms;
    var $client;
    var $service;
    var $params;
    var $config;

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
    }

    /** Main plugin-method, returns data for View module
     * @return array
     */

    public function getData()
    {
        $params = $this->params['params'];

        $errors = [];
        $schema = $this->cms->getSchema($this->params['page']);

        $fields_raw=[];

        foreach ($params['fields'] as $field)
        {
            $fields_raw[] = explode('.',$field)[0];
        }

        $records = $this->cms->get(
            [
                'schema' => $this->params['page'],
                'fields' => $fields_raw
            ]
        );

        foreach ($params['fields'] as $field)
        {
            $field=explode('.',$field);
            if (isset($field[1]))
            {
                foreach ($records as $k=>$record)
                {
                    $records[$k][$field[0]]=$record[$field[0]][$field[1]];
                }
            }
        }

    
        foreach ($records as $k => $v)
            unset($records[$k]['id']);

        $this->client = new \Google_Client();
        $this->client->setApplicationName('COH Sync');
        $this->client->setScopes([\Google_Service_Sheets::SPREADSHEETS, \Google_Service_Drive::DRIVE]);
        $this->client->setAccessType('offline');
        $this->client->setAuthConfig($_SERVER['DOCUMENT_ROOT'] . '/google_credentials.json');
        $this->service = new Google_Service_Sheets($this->client);

        $this->saveDataToSheet($params['drive']['id'],$params['drive']['tab'], $records);


        return $data;
    }

    public function saveDataToSheet($sheetId, $range, $values)
    {
        $rows = [];

        if (!empty($values)) {
            $rows[] = array_keys($values[0]);
            foreach ($values as $row) {
                $rows[] = array_values($row);
            }
        }

        $body = new Google_Service_Sheets_ValueRange(['values' => $rows]);
        $params = ['valueInputOption' => 'RAW'];
        $this->service->spreadsheets_values->update($sheetId, $range, $body, $params);
    }


    public function fetchDataFromSheet($sheetId, $range, $row_keys = false)
    {
        if (empty($this->data[$range])) {

            $response = $this->service->spreadsheets_values->get($sheetId, $range);
            $values = $response->getValues();
            $header = array_shift($values);  // remove header

            if ($row_keys) {
                $values2 = [];
                foreach ($values as $k => $v) {
                    $v2 = [];
                    $i = 0;
                    foreach ($header as $kk => $vv)

                        $v2[$vv] = @$v[$i++];
                    $values2[] = $v2;
                }
                $values = $values2;
            }
            $this->data[$range] = $values;
        }

        return $this->data[$range];
    }
}
