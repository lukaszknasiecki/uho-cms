<?php

/**
 * Serdelia built-in plugin to export model instances
 */

use Huncwot\UhoFramework\_uho_fx;

class serdelia_plugin_export
{
    var $cms;
    var $params;

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
        $errors = [];
        $schema = $this->cms->getSchema($this->params['page']);

        $fields = $schema['fields'];
        $submitted = [];
        foreach ($fields as $k => $v)
            if (in_array($v['type'], ['string', 'boolean','date','integer','datetime','text','media'])) {
                if ($_POST['f_' . $v['field']])
                    $submitted[] = $v['field'];
            } else unset($fields[$k]);

        if ($_POST && !$submitted)
            $errors[] = 'nothing_checked';

        if (!empty($_SESSION['page_filters'][$this->params['page']])) $filters=$_SESSION['page_filters'][$this->params['page']];
            else $filters=[];

        if (!$submitted)
        {
            $count = $this->cms->get($this->params['page'],$filters,false,null,null,['count'=>true]);
        }
        elseif ($submitted) {
            $data = $this->cms->get($this->params['page'],$filters);
            $count=count($data);
            
            if ($data)
            {
                    
                foreach ($data as $k=>$v)
                foreach ($v as $kk=>$vv)
                if (empty($_POST['f_'.$kk]))
                    unset($data[$k][$kk]);

                $path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->params['serdelia_path']);
                $f = '/temp/' . uniqid() . '.csv';
                $exported =  count($data);
                $download = [
                    'source' => $path . $f,
                    'filename' => $this->params['page'] . '-' . date('Y-m-d-H:i:s') . '.csv'
                ];

                $dest=$this->params['serdelia_path'] . $f;
                
                $f = fopen($dest, 'w');
                

                header("Content-type: text/csv");
header("Cache-Control: no-store, no-cache");
header('Content-Disposition: attachment; filename="data.csv"');
                $f = fopen('php://output', 'w');
                
                if (!$f)
                {
                    $errors[]='Cannot write to: '.$dest;
                    $exported=0;
                }
                else
                {
                    // header
                    $header = [];
                    foreach ($data[0] as $k => $v)
                        $header[] = $k;
                    fputcsv($f, $header);
                    $http = 'https://' . $_SERVER['HTTP_HOST'];

                foreach ($data as $fields) {
                    foreach ($fields as $k => $v) {
                        $field = _uho_fx::array_filter($schema['fields'], 'field', $k, ['first' => true]);
                        if ($field && $field['type']) {
                            switch ($field['type']) {
                                case "select":
                                    if (is_array($v))
                                    {
                                        if (isset($v['label'])) $v=$v['label'];
                                            else $v=array_shift($v);
                                    }
                                    
                                    break;
                                case "media":
                                    $val = [];
                                    foreach ($v as $k2 => $v2) {
                                        switch ($v2['type']) {
                                            case "file":
                                                $val[] = $http . $v2['file']['src'];
                                                break;
                                        }
                                    }
                                    $v = implode(chr(13).chr(10), $val);
                                    break;
                            }
                            $fields[$k] = $v;
                        }
                    }
                    
                    fputcsv($f, $fields);
                }


                fclose($f);
                exit();
                }
            } else $errors[] = 'no_data';
        }


        $data = ['result' => true, 'count'=>$count,'fields' => $fields, 'errors' => $errors, 'exported' => $exported, 'download' => $download];

        return $data;
    }

}
