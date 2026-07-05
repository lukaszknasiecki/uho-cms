<?php

/**
 * Serdelia built-in plugin to sync model instances
 */

use Huncwot\UhoFramework\_uho_fx;

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

class serdelia_plugin_drive_sync
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
        $message = [];
        $schema = $this->cms->getSchema($this->params['page']);

        $fields = $params['fields'] ?? [];
        foreach ($fields as $k => $field) {
            $find = _uho_fx::array_filter($schema['fields'], 'field', $k, ['first' => true]);
            if ($find) {
                $fields[$k] = $find;
                $fields[$k]['drive'] = $field;
            } else unset($fields[$k]);
        }
        $fields = array_values($fields);

        if (isset($_POST['export'])) {
            $this->export();
        }
        if (isset($_POST['import'])) {
            $r = $this->import($schema, $fields);
            if (!$r['result']) {
                $errors[] = $r['message'];
            } else {
                $message[] = $r['message'];
            }
        }

        return ['result' => true, 'fields' => $fields, 'errors' => $errors, 'message' => $message];
    }

    private function import(array $schema, array $fields)
    {
        $this->client = new \Google_Client();
        $this->client->setApplicationName('COH Sync');
        $this->client->setScopes([\Google_Service_Sheets::SPREADSHEETS, \Google_Service_Drive::DRIVE]);
        $this->client->setAccessType('offline');
        $this->client->setAuthConfig($_SERVER['DOCUMENT_ROOT'] . '/google_credentials.json');
        $this->service = new Google_Service_Sheets($this->client);

        $params = $this->params['params'];
        $data = $this->fetchDataFromSheet($params['drive']['id'], $params['drive']['tab'], true);

        if (!$data) return ['result' => false, 'message' => 'No data found in the sheet'];

        // remove non-selected fields
        $input = [];

        foreach ($data as $k => $row) {
            $new = [];
            foreach ($fields as $field)
                if (isset($row[$field['drive']])) $new[$field['field']] = $row[$field['drive']];
            if ($new) $input[] = $new;
        }

        // change to proper format for orm

        $sources = [];

        foreach ($input as $k => $row) {
            foreach ($fields as $field) {
                switch ($field['type']) {
                    case 'elements':
                        $values = explode(';', $row[$field['field']]);

                        if (empty($sources[$field['field']])) {

                            $sources[$field['field']] = $this->cms->get(
                                [
                                    'schema' => $field['source']['model']
                                ]
                            );

                            $source_schema = $this->cms->getSchema($field['source']['model']);
                            if (isset($source_schema['cms']['output']))
                            {
                                foreach ($sources[$field['field']] as $kk => $v)
                                {
                                    $sources[$field['field']][$kk]['label'] = $this->cms->getTwigFromHtml($source_schema['cms']['output']['label'], $v);
                                }
                            }

                        }
                        $s = $sources[$field['field']];

                        foreach ($values as $kk => $v) {

                            $label = trim($v);
                            $found = _uho_fx::array_filter($s, 'label', $label, ['first' => true]);
                            if ($found) $values[$kk] = $found['id'];
                            else unset($values[$kk]);
                        }

                        $input[$k][$field['field']] = $values;
                        break;
                }
            }
        }

        if (!$input) return ['result' => false, 'message' => 'No data to import after filtering'];

        $id = $fields[0]['field'];
        
        $result = $this->cms->patch($schema['table'], $input, [], true, ['uid' => $id,'skip_id'=>true]);

        if ($result===false) return ['result' => false, 'message' => 'Error during import: '.$this->cms->getLastError()];
            return ['result' => true, 'message' => 'Imported ' . count($input) . ' records'];
    }

    private function export()
    {
        exit('TBD');
        // LOAD
        $records = $this->cms->get(
            [
                'schema' => $this->params['page']
            ]
        );

        $output = [];

        foreach ($records as $k => $record) {
            $o = [];
            foreach ($params['fields'] as $field) {
                $f = explode('.', $field);

                $field = array_shift($f);
                $field = explode(',', $field);
                $nr = $field[1] ?? "";
                $field = $field[0];

                if ($f) {
                    $val = $record[$field];
                    if ($nr) $val = $val[$nr - 1] ?? [];
                    foreach ($f as $ff) {
                        $val = $val[$ff] ?? [];
                    }
                    if (is_array($val)) $val = '';
                    $o[$field . $nr] = $val;
                } else $o[$field] = $record[$field] ?? '';
            }
            $output[] = $o;
        }


        $this->client = new \Google_Client();
        $this->client->setApplicationName('COH Sync');
        $this->client->setScopes([\Google_Service_Sheets::SPREADSHEETS, \Google_Service_Drive::DRIVE]);
        $this->client->setAccessType('offline');
        $this->client->setAuthConfig($_SERVER['DOCUMENT_ROOT'] . '/google_credentials.json');
        $this->service = new Google_Service_Sheets($this->client);

        $this->saveDataToSheet($params['drive']['id'], $params['drive']['tab'], $output);
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
