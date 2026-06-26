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
 *  - field_date        field video date
 *  - field_date_updated timestamp date updated
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

        $this->cms->fileSetCacheBuster(false);

        $record = $this->cms->get(
            $params['page'],
            ['id' => $params['record']],
            true,
            null,
            null,
            [
                'skipSchemaFilters' => true,
                'page_update' => $params['params'],
                'additionalParams' => $params['params']
            ]
        );

        if (!$record) return ['result' => false];

        $schema = $this->parent->apporm->getSchema($params['page']);

        if (empty($record['uid'])) {
            $record['uid'] = uniqid();
            $this->parent->queryOut('UPDATE ' . $schema['table'] . ' SET uid="' . $record['uid'] . '" WHERE id=' . $params['record']);
        }

        $params = array_merge($params, $params['params']);

        //if (!$params['field_mp4']) $params['field_mp4'] = 'source';

        $title = '';
        $cover = '';
        $sources = '';
        $date = null;
        $sources_progressive = '';
        $duration = 0;
        $root = $_SERVER['DOCUMENT_ROOT'];
        $cover_to_remove = null;

        if (isset($params['field_poster']) && isset($params['poster_if_exists']) && $params['poster_if_exists'] === false) {
            $image = $record[$params['field_poster']];

            if ($image && _uho_fx::file_exists(array_shift($image))) {
                unset($params['field_poster']);
            }
        }

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

                $youtube = $this->youtubeGet($record[$params['field_youtube']], $this->parent->getApiKeys('youtube'));
                if (@$youtube['title']) $title = $youtube['title'];
                if (@$youtube['image']) $cover = $youtube['image'];

                break;

            case "spotify":

                $spotify = $this->spotifyGet($record[$params['field_spotify']], $this->parent->getApiKeys('spotify'));

                if (!$spotify) $errors[] = 'spotify_not_found';
                else {
                    if (!empty($spotify['date'])) $date = $spotify['date'];
                    if (!empty($spotify['duration'])) $duration = $spotify['duration'];
                }
                break;

            // ------------------------------------------------------------------------------------

            case "vimeo":

                $vimeo_id = @$record[$params['field_vimeo']];

                if (empty($params['params'])) $params['params'] = $params;

                if ($vimeo_id) $vimeo = $this->vimeoGet(
                    $vimeo_id,
                    $this->parent->getApiKeys('vimeo'),
                    isset($params['params']['field_vtt'])
                );

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



                    if (isset($params['field_poster_timestamp']) && $record[$params['field_poster_timestamp']] && isset($vimeo['static'][0]['src'])) {
                        $image_temp = '/cms_config-temp/' . uniqid() . '.jpg';
                        $mp4 = $vimeo['static'][0]['src'];
                        $cmd = "-ss " . $record[$params['field_poster_timestamp']] . " -i \"" . $mp4 . "\" -frames:v 1 " . $root . $image_temp;
                        $this->parent->ffmpeg($cmd);
                        if (_uho_fx::file_exists($image_temp)) {
                            $cover = $image_temp;
                            $cover_to_remove = $root . $image_temp;
                        } else $cover = null;
                    }

                    /*
                        sources
                    */

                    if ($params['field_mp4'] && (!empty($vimeo['static']) || !empty($vimeo['play']))) {
                        $sources = ['static' => $vimeo['static'] ?? [], 'play' => $vimeo['play'] ?? []];
                    } else {
                        if ($params['field_mp4'])  $errors[] = 'Vimeo Sources not found';
                        $sources = null;
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

        if ($title || $cover || $sources || $sources_progressive || $duration || $date) {

            $data = ['id' => $record['id']];

            if ($params['field_title'] && $title && !$record[$params['field_title']]) {
                $data[$params['field_title']] = $title;
                $added[] = 'title_added';
            }

            if ($params['field_poster'] && $cover)
            {

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
 

            if ($sources && !empty($params['field_mp4'])) {
                $data[$params['field_mp4']] = $sources;
                $added[] = 'sources_added';
                if (isset($params['field_mp4_timestamp'])) {
                    $data[$params['field_mp4_timestamp']] = date('Y-m-d H:i:s');
                    $added[] = 'sources_timestamp_added';
                }
            }

            if ($sources_progressive && !empty($params['field_mp4_play'])) {
                $data[$params['field_mp4_play']] = $sources_progressive;
                $added[] = 'sources_play_added';
            }

            if ($duration && @$params['field_duration']) {
                $data[$params['field_duration']] = $duration;
                $added[] = 'duration_added';
            }

            if ($date && isset($params['field_date'])) {
                $data[$params['field_date']] = $date;
                $added[] = 'date_added';
            }

            if (isset($params['field_date_updated'])) {
                $data[$params['field_date_updated']] = date('Y-m-d H:i:s');
            }

            // no need to write to "image" field
            if (isset($params['field_poster']) && $data[$params['field_poster']])
                unset($data[$params['field_poster']]);

            // if more than ID
            if ($data && count($data) > 1) {
                $r = $this->cms->put($params['page'], $data);
                if ($r === false) $errors[] = 'Database update failed';
            } else
                $added[] = 'record_updated';
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
        $id = explode('/', $id)[0];
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
            $video_progressive = [];

            // progressive sources

            $vv = @$data['body']['play']['progressive'];
            if ($vv)
                foreach ($vv as $k => $v)
                    if ($v['type'] == 'video/mp4' && $v['width']) {
                        $video_progressive[] = [
                            'width' => $v['width'],
                            'height' => $v['height'],
                            'src' => $v['link'],
                            'expire' => $v['link_expiration_time']
                        ];
                    }

            // hls

            if (!empty($data['body']['play']['hls'])) {
                $hls = [
                    'src' => $data['body']['play']['hls']['link'],
                    'expire' => $data['body']['play']['hls']['link_expiration_time']
                ];
            } else $hls = null;

            // standard (full) sources

            $vv = @$data['body']['files'];
            if ($vv)
                foreach ($vv as $k => $v)
                    if ($v['type'] == 'video/mp4' && isset($v['width'])) {
                        $video[] = ['width' => $v['width'], 'height' => $v['height'], 'src' => $v['link']];
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
            $r = ([
                'image' => $image,
                'title' => $title,
                'author' => $author,
                'static' => $video,
                'play' => [
                    'mp4' => $video_progressive,
                    'hls' => $hls
                ],
                'subtitles' => $subtitles,
                'duration' => $duration
            ]);

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

    private function spotifyGet(string $spotify_id, array $keys)
    {
        $token = $this->getSpotifyToken($keys['client'], $keys['secret']);
        if ($token) {
            $url = "https://api.spotify.com/v1/episodes/" . $spotify_id;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode === 200) {
                $data = json_decode($response, true);

                $title = $data['name'] ?? null;
                $author = $data['show']['publisher'] ?? null;
                $image = $data['images'][0]['url'] ?? null;
                $duration = $data['duration_ms'] ?? null;

                return [
                    'title' => $title,
                    'date' => $data['release_date'] ?? null,
                    'author' => $author,
                    'image' => $image,
                    'duration' => intval($duration / 1000)
                ];
            }
        }
    }

    private function getSpotifyToken($clientId, $clientSecret)
    {
        // 1. Spotify's token endpoint
        $url = 'https://accounts.spotify.com/api/token';

        // 2. Prepare the payload 
        // The payload requires 'grant_type' and can optionally include client credentials here or via Basic Auth headers
        $postFields = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret
        ]);

        // 3. Setup cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        // 4. Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            return null;
        }

        // 5. Read the Token
        if ($httpCode === 200) {
            $data = json_decode($response, true);

            $accessToken = $data['access_token'];
            return $accessToken;
        } else {
            return null;
        }
    }
}
