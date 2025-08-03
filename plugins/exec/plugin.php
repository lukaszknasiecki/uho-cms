<?php

/**
 * Serdelia built-in plugin to exec shell command
 * from website root directory. Expects JSON reponse
 * with .result=true. Shows .message or full object
 * as an output.
 * You can use variables defined in cfg.plugins[]
 * Sample json:
 * 
 * {
 *           "plugin": "exec",
 *           "params":
 *           {
 *               "command":"{{PHP}} command.php token={{TOKEN}}"
 *           }
 *       }
 */

use Huncwot\UhoFramework\_uho_fx;

class serdelia_plugin_exec
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
        $this->input = _uho_fx::sanitize_input($params['params'],['command'=>'string']);
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

        if (empty($this->input['command'])) $errors[]='error_no_action';
        else
        {

            // construct final url to call
            $command_primary=$command=$this->input['command'];            
            $cfg=$this->parent->getPluginsCfg();            
            if ($cfg) $command=$this->parent->getTwigFromHtml($command,$cfg);
            $command='cd '.$_SERVER['DOCUMENT_ROOT'].' ; '.$command;

            // execute
            exec($command, $r, $retval);

            // handle JSON response
            if ($r) $r=implode('',$r);
            if ($r) $r=@json_decode($r,true);

            if (!$r)
            {
                $errors[]='Empty answer, no data.';
            }
            elseif (!empty($r['result']) && $r['result']=='true')
            {                
                if (!empty($r['message'])) $success[]=$r['message'];
                    else
                    {
                        unset($r['result']);
                        $success[]='<pre>'.json_encode($r,JSON_PRETTY_PRINT).'</pre>';
                    }
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
            'url'=>$command_primary,
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
