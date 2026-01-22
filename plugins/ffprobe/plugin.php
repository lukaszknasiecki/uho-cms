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
        $this->cms->fileSetCacheBuster(false);
        $record = $this->cms->get($params['page'], ['id' => $params['record']], true, null, null, ['skipSchemaFilters' => true, 'additionalParams' => $params['params']]);

        if (!$record) return ['result' => false];
        $params = array_merge($params, $params['params']);

        if (isset($record[$p['video']]['src'])) $src = $record[$p['video']]['src'];
        elseif (isset($record[$p['audio']]['src'])) $src = $record[$p['audio']]['src'];
        else return ['result' => false];

        if (substr($src,0,4)!='http')
            $src=$_SERVER['DOCUMENT_ROOT'].$src;

        $stream = $this->getStream($src);

        if ($stream) {
            $output = [];
            if (isset($p['width']) && isset($stream['width'])) $output[$p['width']] = $stream['width'];
            if (isset($p['height']) && isset($stream['height'])) $output[$p['height']] = $stream['height'];
            if (isset($p['duration']) && isset($stream['duration']) && $stream['duration'] > 0) $output[$p['duration']] = $stream['duration'];
            if ($output) {
                $this->parent->put($params['page'], $output, ['id' => $record['id']]);
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

    private function getFFProbe($cmd)
    {
        $json=$this->parent->ffprobe($cmd);
        if ($json) $json = json_decode($json, true);
        return $json;
    }

    private function getStream($file)
    {
        $json = $this->getFFProbe('-v quiet -print_format json -show_format -show_streams ' . $file);
                
        if ($json) {
            if (isset($json['streams']))
            {
                if (!empty($json['format']['duration']))
                    $duration=$json['format']['duration']; else $duration=0;
                $stream = _uho_fx::array_filter($json['streams'], 'codec_type', 'video', ['first' => true]);
                if (!$stream) $stream = _uho_fx::array_filter($json['streams'], 'codec_type', 'audio', ['first' => true]);

                if (isset($stream['duration'])) $duration=$stream['duration'];

                // alternative duration with packets
                if (!$duration)
                    {
                        $data = $this->getFFProbe('-v quiet -print_format json -show_format -show_entries packet=pts_time -show_streams ' . $file);
                        if (!empty($data['packets']))
                            {
                                $item=array_pop($data['packets']);
                                if (!empty($item['pts_time'])) $duration=$item['pts_time'];
                            }
                    }
                
                $stream = [
                    'width' => $stream['coded_width'],
                    'height' => $stream['coded_height'],
                    'duration' => round($duration)
                ];
            }
        }
        return $stream;
    }
}
