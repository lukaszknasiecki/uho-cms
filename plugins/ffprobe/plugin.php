<?php

/**
 * Serdelia built-in plugin to get media metadata via FFPROBE
 */

use Huncwot\UhoFramework\_uho_fx;

class serdelia_plugin_ffprobe
{

    /** Standard Serdelia Plugin Contructor
     * object array $cms instance of _uho_orm
     * object array $params
     * object array $parent instance of _uho_model
     * @return null
     */

    private $cms,$params,$parent;


    public function __construct($cms, $params, $parent)
    {
        $this->cms = $cms;
        $this->params = $params;
        $this->parent = $parent;
    }

    /** Main plugin-method, returns data for View module
     * @return array
     * You need to define FFPROBE full exec path in .ENV, i.e. FFPROBE_PATH=/opt/homebrew/bin/ffprobe
     * Input params = {
     *                  "audio":{"src":"field_audio"}
     *                  "video":{"src":"field_video"}
     *                  "duration":"field_duration"
     */

    public function getData()
    {
        $errors = [];
        $added = [];
        $params = $this->params;
        $p = $params['params'];
        if (isset($p['page'])) $params['page'] = $p['page'];


        if (!$params['record']) return ['result' => false];
        $this->cms->setFilesDecache(false);
        $record = $this->cms->getJsonModel($params['page'], ['id' => $params['record']], true, null, null, ['skip_filters' => true, 'additionalParams' => $params['params']]);

        if (!$record) return ['result' => false];
        $params = array_merge($params, $params['params']);

        if (isset($record[$p['video']]['src'])) $src = $record[$p['video']]['src'];
        elseif (isset($record[$p['audio']]['src'])) $src = $record[$p['audio']]['src'];
        else return ['result' => false];

        $stream = $this->getStream($_SERVER['DOCUMENT_ROOT'] . $src);
        

        if ($stream) {
            $output = [];
            if (isset($p['width']) && isset($stream['width'])) $output[$p['width']] = $stream['width'];
            if (isset($p['height']) && isset($stream['height'])) $output[$p['height']] = $stream['height'];
            if (isset($p['duration']) && isset($stream['duration']) && $stream['duration'] > 0) $output[$p['duration']] = $stream['duration'];
            if ($output) {
                $this->parent->putJsonModel($params['page'], $output, ['id' => $record['id']]);
                $added[] = 'Well done!';
            }
        } else return ['result' => false, 'message' => 'Stream not found of FFPROBE undefined'];



        $data = ['result' => true, 'fields' => $fields, 'errors' => $errors, 'success' => $added];

        return $data;
    }

    /** Gets metadada
     * @param string $file
     * @return array
     */

    private function getStream($file)
    {
        $stream = null;
        $cmd = '-v quiet -print_format json -show_format -show_streams ' . $file;        
        $json = $this->parent->ffprobe($cmd);
        
        if ($json) $json = json_decode($json, true);
        if ($json) {
            if (isset($json['streams']))
            {
                $stream = _uho_fx::array_filter($json['streams'], 'codec_type', 'video', ['first' => true]);
                if (!$stream) $stream = _uho_fx::array_filter($json['streams'], 'codec_type', 'audio', ['first' => true]);
                //$stream=$json['streams'][0];
                $stream = [
                    'width' => $stream['coded_width'],
                    'height' => $stream['coded_height'],
                    'duration' => round($stream['duration'])
                ];
            }
        }
        return $stream;
    }
}
