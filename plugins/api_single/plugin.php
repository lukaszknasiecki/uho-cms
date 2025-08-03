<?php

/**
 * Serdelia built-in plugin to manage single API endpoint execution
 */

use Huncwot\UhoFramework\_uho_fx;

class serdelia_plugin_api_single
{

    /** Standard Serdelia Plugin Contructor
     * object array $cms instance of _uho_orm
     * object array $params
     * object array $parent instance of _uho_model
     * @return null
     */

     var $cms,$parent,$input;

    public function __construct($cms, $params, $parent = null)
    {
        $this->cms = $cms;
        $this->parent = $parent;
        $this->input = _uho_fx::sanitize_input($params['params'],['url'=>'string','method'=>'string']);
    }

    /** Main plugin-method, returns data for View module
     * @return array
     */

    public function getData()
    {

        $errors = [];
        $success=[];

        $time = $this->microtime_float();
        $this->parent->logout_expired = 300000;
        set_time_limit(120);

        if (empty($this->input['url'])) $errors[]='error_no_action';
        else
        {

            // construct final url to call
            $url=$this->input['url'];
            $url_primary=$url=str_replace('&iquest;','?',$url);
            
            $cfg=$this->parent->getPluginsCfg();
            if ($cfg) $url=$this->parent->getTwigFromHtml($url,$cfg);

            if (substr($url, 0, 4) != 'http') {
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')  || $_SERVER['SERVER_PORT'] == 443;
                $https= $isHttps ? 'https' :'http';
                $url = $https . '://'.$_SERVER['HTTP_HOST'].$url;                
            }

            // method
            $method=empty($this->input['method']) ? "GET" : $this->input['method'];

            switch ($method)
            {
                case "GET":

                $r = @file_get_contents($url);
                if ($r)
                {
                    if (is_string($r)) $r = @json_decode($r, true); else $r=null;
                }
                else $r = _uho_fx::fileCurl($url,['json'=>true,'decode'=>true]);
                break;

                case "POST":

                $r = _uho_fx::fileCurl($url,['post'=>true,'json'=>true,'decode'=>true]);
                break;

                case "PUT":

                $r = _uho_fx::fileCurl($url,['put'=>true,'json'=>true,'decode'=>true]);
                break;

                default:
                $errors[]='Method not supported';
                $r=null;

            }

            if (!$r)
            {
                $errors[]='Empty answer, no data.';
            }
            elseif (!empty($r['result']))
            {
                unset($r['result']);
                if (!empty($r['message'])) $success[]=$r['message'];
                    else $success[]='<pre>'.json_encode($r,JSON_PRETTY_PRINT).'</pre>';
            } else
            {                
                if (!empty($r['message'])) $errors[]=$r['message'];
                    else $errors[]='<pre>'.json_encode($r,JSON_PRETTY_PRINT).'</pre>';
            }
        }

        $time = ($this->microtime_float() - $time);
        if ($time < 0.001) $time = 0;

        $data = [
            'result' => true,
            'url'=>$method.' '.$url_primary,
            'errors' => $errors,
            'success'=>$success,
            'time' => $time
        ];


        return $data;
    }

    /** Timestamp utiliy
     * @return float
     */

    private function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
}
