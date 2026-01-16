<?php

use Vimeo\Vimeo;

/**
 * Serdelia built-in plugin to import covers from MP4/VIMEO/YOUTUBE
 * and any other metadata available like subtitles, video sources etc.
 *
 * Methods:
 * - __construct($cms, $params, $parent) - Standard Serdelia Plugin Constructor
 * 
 * - getData() - Main plugin-method, returns data for View module
 * 
 * - youtubeGet($id, $key = null) - Gets Youtube cover image via YouTube API
 * - vimeoGet($id, $keys = null, $subtitles = false) - Loads Vimeo cover via simple V2 api
 * - getVimeoFilenameAdvanced($id, $keys, $subtitles = false) - Gets MP4 filenames via Vimeo API
 * 
 * $params object structure
 *  - type          mp4|youtube|vimeo
 *  - field_mp4     json field to show video sources, mostly used for vimeo
 *  - field_video       field storing mp4 video (video)
 *  - field_duration    field storing video duration (integer)
 *  - field_poster      field storing video cover (image)
 *  - field_poster_timestamp    field storing video timestamp from which to take cover screenshot, for vimeo
 *                              with sources it will use FFMPEG not cover provided by vimeo
 *  - field_youtube     field storing youtube id (string)
 *  - field_vimeo       field storing vimeo id (string)
 *  - field_vtt         field storing vtt (file type)
 *  - field_title       field storing video title (string)
 * 
 * Example:
 *  {
 *      "type": "plugin",
 *      "plugin": "import_cover",
 *      "params":
 *       {
 *              "field_vimeo":"vimeo_id",
 *              "field_poster":"image
 *        }
 *  }
 */

use Huncwot\UhoFramework\_uho_fx;

class serdelia_plugin_import_cover
{

    /** Standard Serdelia Plugin Contructor
     * object array $cms instance of _uho_orm
     * object array $params
     * object array $parent instance of _uho_model
     * @return null
     */

    private $cms;
    private $params;
    private $parent;

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
        $params = $this->params;

        $p = @$params['get']['params'];
        if ($p) $p = json_decode($p, true);
        if ($p) $params = array_merge($params, $p);

        if (!$params['record']) return ['result' => false];
        if (isset($params['params']['page'])) $params['page'] = $params['params']['page'];

        $this->cms->setFilesDecache(false);

        $record = $this->cms->get($params['page'], ['id' => $params['record']], true, null, null, ['skip_filters' => true, 'page_update' => $params['params'], 'additionalParams' => $params['params']]);


        if (!$record) return ['result' => false];

        $schema = $this->parent->apporm->getSchema($params['page']);

        if (isset($record['uid']) && !$record['uid']) {
            $record['uid'] = uniqid();



            $this->parent->queryOut('UPDATE ' . $schema['table'] . ' SET uid="' . $record['uid'] . '" WHERE id=' . $params['record']);
        }


        $params = array_merge($params, $params['params']);

        if (!$params['field_mp4']) $params['field_mp4'] = 'source';

        $title = '';
        $cover = '';
        $sources = '';
        $duration = 0;
        $root = $_SERVER['DOCUMENT_ROOT'];
        $cover_to_remove=null;

        // ------------------------------------------------------------------------------------

        switch ($params['type']) {

            case "mp4":


                $video = $root . explode('?', $record[$params['field_video']]['src'])[0];
                $image = $record[$params['field_poster']];

                $image_original = $root . $image['original'];
                $position = 20;

                if ($video && $image_original) {

                    $cmd = "-i $video -vframes 1 -y -ss " . str_replace(',', '.', $position) . " $image_original";
                    $this->parent->ffmpeg($cmd);

                    $result = file_exists($image_original);
                    if ($result) {
                        $r = $this->parent->imageResizeModel($params['page'], $params['field_poster'], $record, $image['original']);
                        if (!$r['result']) {
                            $errors[] = 'errors_poster_failed'; //Poster import failed: '.$r['message'];
                        } else {
                            $added[] = 'poster_added';
                        }
                    } else return ['result' => false, 'message' => 'ffmpeg failed'];
                }

                break;

            case "youtube":

                $youtube = $this->youtubeGet($record[$params['field_youtube']], $this->parent->getApiKey('youtube'));
                if (@$youtube['title']) $title = $youtube['title'];
                if (@$youtube['image']) $cover = $youtube['image'];

                break;

            // ------------------------------------------------------------------------------------

            case "vimeo":

                $vimeo_id = @$record[$params['field_vimeo']];

                if (empty($params['params'])) $params['params'] = $params;

                if ($vimeo_id) $vimeo = $this->vimeoGet($vimeo_id, $this->parent->getApiKey('vimeo'), isset($params['params']['field_vtt']));

                if (@$vimeo['error']) $errors[] = $vimeo['error'];
                elseif ($vimeo) {

                    /*
                        title
                    */
                    if ($params['field_title'] && $vimeo['title'] && !$record[$params['field_title']])
                        $title = $vimeo['title'];

                    /*
                        vimeo poster
                    */
                    if ($vimeo['image']) $cover = $vimeo['image'];

                    /*
                        vimeo poster via ffmpeg
                    */

                    if (isset($params['field_poster_timestamp']) && $record[$params['field_poster_timestamp']] && isset($vimeo['mp4'][0]['src']))
                    {
                        $image_temp = '/cms_config-temp/'.uniqid().'.jpg';
                        $mp4=$vimeo['mp4'][0]['src'];
                        $cmd = "-ss " . $record[$params['field_poster_timestamp']] . " -i \"".$mp4."\" -frames:v 1 ".$root.$image_temp;
                        $this->parent->ffmpeg($cmd);
                        if (_uho_fx::file_exists($image_temp))
                            {
                                $cover = $image_temp;
                                $cover_to_remove=$root.$image_temp;
                            }
                            else $cover=null;

                    }

                    /*
                        sources
                    */
                    if ($params['field_mp4'] && $vimeo['mp4']) {
                        $sources = $vimeo['mp4'];
                        if (is_array($sources) && !empty($sources[0]) && !empty($sources[0]['src'])) {
                            $sources = json_encode($sources, true);
                        } else {
                            $errors[] = 'Vimeo Sources not found';
                            $sources = null;
                        }
                    }

                    if (!empty($vimeo['duration'])) {
                        $duration = $vimeo['duration'];
                    }

                    if ($params['params']['field_vtt'] && !empty($vimeo['subtitles'])) {
                        $r = $this->parent->fileUploadModel(
                            $params['page'],
                            $params['params']['field_vtt'],
                            $record,
                            $vimeo['subtitles']
                        );
                        if (!$r || !$r['result']) $errors[] = 'errors::vimeo::vtt_copy_error from ' . $vimeo['subtitles'];
                        else $added[] = 'subtitles_added';
                    }
                } else $errors[] = 'errors::vimeo_not_found::id=' . $record[$params['field_vimeo']];
                break;

            // ------------------------------------------------------------------------------------


            default:
                exit('import_cover Plugin: TYPE NOT FOUND: ' . $params['type']);
        }


        // ------------------------------------------------------------------------------------

        if ($title || $cover || $sources) {

            $data = ['id' => $record['id']];
            if ($params['field_title'] && $title && !$record[$params['field_title']]) {
                $data[$params['field_title']] = $title;
                $added[] = 'title_added';
            }
            if ($params['field_poster'] && $cover) {

                $r = $this->parent->imageResizeModel($params['page'], $params['field_poster'], $record, $cover);

                if (!$r['result']) {
                    $errors[] = 'poster_failed'; //Poster import failed: '.$r['message'];
                } else {
                    $data[$params['field_poster']] = $cover;
                    $added[] = 'poster_added';
                }
            }
            if ($params['field_poster_url'] && $cover) {
                $data[$params['field_poster_url']] = $cover;
                $added[] = 'poster_added';
            }
            if ($sources) {
                $data[$params['field_mp4']] = $sources;
                $added[] = 'sources_added';
            }
            if ($duration && @$params['field_duration']) {
                $data[$params['field_duration']] = $duration;
                $added[] = 'duration_added';
            }

            $this->cms->put($params['page'], $data);
            if ($cover_to_remove) unlink($cover_to_remove);
        }

        // ----------------------------------------------------------------


        if (isset($schema['model_all']))
            $this->cms->put($schema['model_all'], $data);
        else
            $this->cms->put($params['page'], $data);

        $data = ['result' => true, 'fields' => $fields, 'errors' => $errors, 'success' => $added];

        return $data;
    }


    /** Loads Vimeo cover via simple V2 api
     * @param string $id
     * @param array $keys
     * @return array
     */

    private function vimeoGet($id, $keys = null, $subtitles = false)
    {
        //echo('<!-- vimeoGet -->');
        
        if ($keys) return $this->getVimeoFilenameAdvanced($id, $keys, $subtitles);
        else {
            $json = _uho_fx::fileCurl('http://vimeo.com/api/v2/video/' . $id . '.json');
            if (is_string($json)) {
                $json = @json_decode($json);
                $image = @$json[0]->thumbnail_large;
                if ($image) $result = ['image' => $image];
            } else $result = false;
        }
        return $result;
    }

    /** Gets MP4 filenames via Viemo API
     * @param string $id
     * @param array $keys
     * @return array
     */

    public function getVimeoFilenameAdvanced($id, $keys, $subtitles = false)
    {

        $lib = new Vimeo($keys['client'], $keys['secret']);
        $lib->setToken($keys['token']);
        $id=explode('/',$id)[0];
        $data = $lib->request('/videos/' . $id);

        if (isset($data['body']['error'])) {
            return ['error' => 'Error: ' . $data['body']['error'] . ' ' . $data['body']['developer_message']];
        } elseif ($data) {

            $title = @$data['body']['name'];
            $author = @$data['body']['user']['name'];
            $image = @$data['body']['pictures']['sizes'];
            $duration = @$data['body']['duration'];
            if ($image) $image = array_pop($image);  // largest thumbnail
            if ($image) $image = $image['link'];
            $video = [];
            $progressive = false;
            if ($progressive) {
                $vv = @$data['body']['play']['progressive'];
                if ($vv)
                    foreach ($vv as $k => $v)
                        if ($v['type'] == 'video/mp4' && $v['width']) {
                            $video[] = ['width' => $v['width'], 'height' => $v['height'], 'src' => $v['link']];
                        }
            } else {
                $vv = @$data['body']['files'];
                if ($vv)
                    foreach ($vv as $k => $v)
                        if ($v['type'] == 'video/mp4' && isset($v['width'])) {
                            $video[] = ['width' => $v['width'], 'height' => $v['height'], 'src' => $v['link']];
                        }
            }
        }

        // subtitles
        if ($subtitles) {
            $data = $lib->request('/videos/' . $id . '/texttracks');
            if (!empty($data['body']['data'])) {
                // looking for non-auto
                $i = 0;
                foreach ($data['body']['data'] as $k5 => $v5)
                    if (!$i && !strpos($v5['language'], 'autogen'))  $i = $k5;
                $subtitles = $data['body']['data'][$i]['link'];
            }
        }
        if (!$video) return  ['error' => 'ERROR OCCURED: No video sources have been found'];
        else {
            $r = (['image' => $image, 'title' => $title, 'author' => $author, 'mp4' => $video, 'subtitles' => $subtitles, 'duration' => $duration]);
            return $r;
        }
    }

    /** Gets Youtube cover image via YouTube API
     * @param string $id
     * @param string $key
     * @return array
     */

    private function youtubeGet($id, $key = null)
    {
        $id = str_replace('https://www.youtube.com/watch?v=', '', $id);
        if ($key) {
            $url = 'https://www.googleapis.com/youtube/v3/videos?id=' . $id . '&key=' . $key . '&part=snippet';
            $t = file_get_contents($url);
            if ($t) $t = json_decode($t, true);
            if ($t) $t = @$t['items'][0];
            if ($t) {
                $author = @$t['snippet']['channelTitle'];
                $title = @$t['snippet']['title'];
                $image = @$t['snippet']['thumbnails']['maxres'];
                if (!$image) $image = @$t['snippet']['thumbnails']['high'];
                if (!$image) $image = @$t['snippet']['thumbnails']['medium'];
            }
            if ($image) $image = $image['url'];
        } else $image = 'http://img.youtube.com/vi/' . $id . '/maxresdefault.jpg';

        $result = ['title' => $title, 'image' => $image, 'author' => $author];
        return $result;
    }
}
