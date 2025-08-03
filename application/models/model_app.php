<?php

/**
 * Main model class of the application
 */

use Huncwot\UhoFramework\_uho_model;
use Huncwot\UhoFramework\_uho_orm;
use Huncwot\UhoFramework\_uho_s3;
use Huncwot\UhoFramework\_uho_fx;
use Huncwot\UhoFramework\_uho_thumb;

require_once("model_app_clients.php");

class model_app extends _uho_model
{
    public $orm;
    /**
     * current _uho_orm app instance
     */
    public $appnow;
    /**
     * derived from _uho_model
     */
    public $app_structure = [];
    /**
     * mode = light|dark
     */
    public $mode = null;
    /**
     * current _uho_orm app instance
     */
    public $wysiwyg;
    public $app_menu;
    /**
     * upload path for uploads
     */
    public $upload_path;
    public $temp_path;
    public $temp_folder;
    public $logs_path;
    public $logs_folder;
    /**
     * auth array decoded from authorization.json
     */
    public $app_auth;
    /**
     * path to config.json
     */
    public $cfg_path;
    public $cfg_folder;
    public array $plugins_cfg=[];
    /**
     * _uho_s3 instance
     */
    public $s3 = null;
    /**
     * default minutes for logout expiration
     */
    public $logout_expired = 10;
    /**
 
     */
    public $cms_path = '/serdelia';

    /*
        DIR to CMS folder
        for file loading
    */
    public $cms_folder = '/root/serdelia';

    /**
     * Array of folders to clear via cache_kill
     */
    private $cache_folders;
    private $cache_plugin;
    /**
     * API keys from config section
     */
    private $api_keys;
    /**
     * PLUGIN keys from config section
     */
    private $plugins_keys;
    /**
     * Current status of authorization
     */
    private $auth_now = null;
    /**
     * Config arrays for CKEditor and TinyMCE
     */
    public $ckeditor_configs = [], $tiny_configs = [];
    /**
     * Default sitename
     * used in metatag title and automatic mailing
     */
    public $head_title = 'SiteName';
    /**
     * Current logged in user data
     */
    public $user = [];
    /**
     * Indicates if media backup is enabled
     */
    private $is_backup_media = false;
    private $is_backup = false;
    /**
     * Default metatags
     */
    var $head = array(
        'image' => '/public/og_image.jpg'
    );
    /**
     * search url variable
     */
    var $url_search = '';
    /**
     * allow schema edit
     * 
     */
    var $serdelia_schema_editor = false;
    /**
     * Init function
     * @return null
     */

    public $app_api_http;
    public $clients;
    public $apporm;

    /**
     * Initializes application configuration, paths, ORM, and app structure.
     */
    public function init(): void
    {
        // --- Set error logging for SQL ---
        if (isset($this->sql)) {
            $this->sql->setErrorFolder(folder_logs);
        }

        // --- Initialize ORM ---
        $this->apporm = new _uho_orm($this, $this->sql, null, true);
        $this->apporm->setDebug(true);
        $this->apporm->setFilesDecache(true);
        $this->apporm->setImageSizes(false);

        // --- Determine CMS root folder ---
        $dirParts = explode('/', __DIR__);
        array_pop($dirParts); // remove "/classes"
        array_pop($dirParts); // remove "/application"
        $this->cms_folder = implode('/', $dirParts);

        // --- Define paths for temp and logs ---
        $this->temp_path    = $this->config_path . '-temp';
        $this->temp_folder  = $this->config_folder . '-temp';

        $this->logs_path    = $this->config_path . '-logs';
        $this->logs_folder  = $this->config_folder . '-logs';

        // --- Set up JSON model root paths for primary ORM ---
        $this->orm->addRootPath($this->cms_folder . '/application/models/json/');

        // --- Define config paths ---
        $this->cfg_path   = $this->config_path;
        $this->cfg_folder = $_SERVER['DOCUMENT_ROOT'] . $this->config_path;

        // --- Define the main ORM root path ---
        $pagePath = $this->cfg_path . '/pages/';
        $this->apporm->removeRootPaths();
        $this->apporm->addRootPath($pagePath);
        $this->apporm->addRootPath($this->cms_folder . '/application/models/json/');

        // --- Load authorization config ---
        $authPath = $_SERVER['DOCUMENT_ROOT'] . $this->cfg_path . '/structure/authorization.json';
        $this->app_auth = @json_decode(file_get_contents($authPath), true);

        // --- Load and convert app model tree structure ---
        $modelTreePath = $this->cfg_folder . '/structure/model_tree.json';
        if (file_exists($modelTreePath)) {
            $structureJson = file_get_contents($modelTreePath);
            if ($structureJson) {
                $this->app_structure = $this->convertAppStructure($structureJson);
            }

            // Ensure placeholder entry exists
            $this->app_structure['models']['serdelia_models'] = ['serdelia_models_fields' => []];
        }

        // --- Optional image conversion engine config ---
        if (!empty($this->config_params['convert'])) {
            _uho_thumb::set_config_prefer_imagemagick(true, $this->config_params['convert']);
        }
    }

    /**
     * Sets S3 config var
     * @param array $config
     * @return null
     */

    public function setS3($config): void
    {
        $config['path_skip'] = '/public/upload';
        $this->s3 = new _uho_s3($config, _uho_fx::getGet('s3recache') == 1);
        if (!$this->s3->checkCacheFile())
            $this->s3->buildCache();
        $this->apporm->setUhoS3($this->s3);
        $this->orm->setUhoS3($this->s3);
    }

    /**
     * Forces S3 cache rebuild
     */
    public function s3recache()
    {
        if (isset($this->s3)) {
            $this->s3->buildCache();
        }
    }

    /**
     * Loads CMS menu structure into $app_menu array
     * @return null
     */

    public function loadMenu()
    {
        // app menu        
        $menu = @file_get_contents($_SERVER['DOCUMENT_ROOT'] . $this->cfg_path . '/structure/menu.json');
        if ($menu)
            $menu = json_decode($menu, true);
        if ($menu)
            $this->app_menu = $this->convertAppMenu($menu);
    }

    /**
     * Sets API url
     * @return null
     */

    private function set_api_http()
    {
        if (isset($_SERVER['HTTPS']))
            $this->app_api_http = 'https://';
        else
            $this->app_api_http = 'http://';
        $this->app_api_http .= $_SERVER['HTTP_HOST'];
        $langs = $this->appnow['langs'];
        if (count($langs) > 1)
            $this->app_api_http .= '/' . $langs[0]['lang'];
        $this->app_api_http .= '/api/';
    }

    /**
     * Sets ORM tokens
     * @param array $keys
     * @return null
     */

    public function setOrmKeys($keys)
    {
        $this->apporm->setKeys($keys);
    }

    /**
     * Gets available languages
     * @return null
     */

    public function getLangs()
    {
        return $this->appnow['langs'];
    }

    /**
     * Sets API keys from config
     * @param array $keys
     * @return null
     */

    public function setApiKeys($keys)
    {
        $this->api_keys = $keys;
    }

    /**
     * Sets Plugins keys from config
     * @param array $keys
     * @return null
     */

    public function setPluginsKeys($keys)
    {
        $this->plugins_keys = $keys;
    }

    /**
     * Gets API key
     * @param array $key
     * @return mixed
     */

    public function getApiKey($key)
    {
        return $this->api_keys[$key];
    }

    /**
     * Gets Plugin key
     * @return mixed
     */

    public function getPluginKey($key)
    {
        return $this->plugins_keys[$key];
    }

    /**
     * Sets upload path
     * @param array $path
     * @return null
     */

    public function setUploadFolder($folder)
    {
        $this->upload_path = $folder;
    }

    /**
     * Sets app languages
     * @param array $langs
     * @return null
     */

    public function setAppNowLangs($langs)
    {
        foreach ($langs as $k => $v)
            if (isset($v['suffix']))
                $langs[$k] = strtolower(substr($v['suffix'], 1));

        $this->apporm->setLangs($langs);
        foreach ($langs as $k => $v) {
            $langs[$k] = [
                'lang' => $v,
                'lang_add' => '_' . strtoupper($v),
                'active' => @$_COOKIE['serdelia_editlang_' . $v]
            ];
            if ($langs[$k]['active'])
                $one_active = true;
        }
        if (!$one_active)
            $langs[0]['active'] = true;
        $this->appnow['langs'] = $langs;
        $this->set_api_http();
    }

    /**
     * Gets Dictionary array
     * @param array $t
     * @return array
     */

    public function dictGet($t)
    {
        return $_SESSION['dict'][$t];
    }

    /**
     * Gets Translate array
     * @return array
     */

    public function getTranslate()
    {
        return $_SESSION['dict']['translate'];
    }

    /**
     * Translates string via Dictionary array
     * @param array $key
     * @return string
     */

    public function getTranslated($key)
    {
        return $_SESSION['dict']['translate'][$key];
    }


    /**
     * Sets header data
     * @param array $data
     * @return null
     */

    public function setHead($data)
    {
        $this->head_title = $data['title'];;
        $this->head['description'] = $data['description'];
    }

    /**
     * Sets _uho_client object
     * @param object $clients
     * @param boolean nosql
     * @return null
     */

    public function setClients($clients, $nosql = false)
    {
        $this->clients = $clients;
        if ($nosql)
            $this->clients->setNoSql(true);
    }

    /**
     * Gets current user data
     * @return array
     */

    public function getUser()
    {
        if ($this->clients)
            $r = $this->clients->getClientData();
        return $r;
    }

    /**
     * Check is uset has menu access
     * @return boolean
     */

    public function checkUserMenu()
    {
        $r = $this->clients->getClientData();
        if ($r && !$r['hide_menu'])
            return true;
        else
            return false;
    }


    /**
     * Gets _uho_client config
     * @param string $key
     * @return array
     */

    public function getClientsCfg($key)
    {
        $r = $this->clients->getCfg($key);
        return $r;
    }

    public function setPluginsCfg($cfg)
    {
        $this->plugins_cfg=$cfg;
    }

    public function getPluginsCfg()
    {
        return $this->plugins_cfg;
    }

    

    /**
     * Validates password format
     * @param string $pass
     * @return boolean
     */

    public function clientsValidatePasswordFormat($pass)
    {
        return $this->clients->validatePasswordFormat($pass);
    }

    /**
     * _uho_client passwordSet helper
     * @param int $user_id
     * @param string $pass
     * @return boolean
     */

    public function clientsSetPassword($user_id, $password)
    {
        return $this->clients->passwordSet($user_id, $password);
    }

    /**
     * _uho_client passwordGenerate helper
     * @return string
     */

    public function clientsGeneratePassword()
    {
        return $this->clients->passwordGenerate();
    }

    /**
     * _uho_client mailingRaw helper
     * @param string $email
     * @param string $subject
     * @param string $body
     * @return boolean
     */

    public function sendEmail($email, $subject, $body)
    {
        $r = $this->clients->client->mailingRaw($email, $subject, $body);
        return $r;
    }

    /**
     * _uho_client users_mailing helper
     * @param string $slug
     * @param string $email
     * @param array $data
     * @return boolean
     */

    public function mailing($slug, $email, $data)
    {
        return $this->clients->users_mailing($slug, $email, $data);
    }

    /**
     * returns meta header values
     * @return array
     */
    public function headGet()
    {
        $t = $this->head;
        if ($t['image'])
            $t['image'] = explode('?', $t['image'])[0];
        $size = @getimagesize($this->root_path . $t['image']);
        if ($size) {
            $t['image'] = array(
                'src' => $t['image'],
                'width' => $size[0],
                'height' => $size[1]
            );
        } else
            unset($t['image']);
        if ($t['title'])
            $t['title'] .= ' - ' . $this->head_title;
        else
            $t['title'] = $this->head_title;
        return $t;
    }

    /**
     * sets meta header values
     * @param string $title
     * @param string $description
     * @param array $image
     * @return null
     */

    public function headSet($title, $description = '', $image = null)
    {
        $img = null;
        if ($image && !is_array($image))
            $img = $image;
        if (is_array($image)) {
            foreach ($image as $k => $v)
                if (!$img && file_exists($this->root_path . $v))
                    $img = $v;
        }

        if ($title)
            $this->head['title'] = strip_tags(str_replace('&nbsp;', ' ', $title));
        if ($description)
            $this->head['description'] = trim(_uho_fx::headDescription($description, true, 250));
        if ($img)
            $this->head['image'] = $img;
    }

    /**
     * adds cache to image url
     * @param string $img
     * @return null
     */

    public function updateImageCache(&$img)
    {
        if (strpos($img, '?'))
            return;
        if ($img)
            $time = @filemtime((rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $img));
        if ($time)
            $img .= '?' . $time;
        else
            $img = null;
    }

    /**
     * add cache to array of image urls
     * @param array $arr
     * @param string $field1
     * @param string $field2
     * @return null
     */

    public function updateImageCacheArray(&$arr, $field1 = null, $field2 = null)
    {
        if (is_array($arr))
            foreach ($arr as $k => $v) {
                if ($field1 == null && $field2 == null)
                    $this->updateImageCache($arr[$k]);
                elseif ($v[$field1][$field2])
                    $this->updateImageCache($arr[$k][$field1][$field2]);
                if (!$arr[$k])
                    unset($arr[$k]);
            }
    }

    /**
     * loads app structure model
     * @param array $t
     * @param string $model
     * @param array $parent
     * @return mixed
     */

    private function findAppStructureModel($t, $model, $parent = ['null'])
    {
        $result = null;
        foreach ($t as $k => $v)
            if ($result);
            elseif ($k == $model)
                $result = ['parent' => array_merge($parent, [$k]), 'children' => $v];
            elseif (is_array($v)) {
                $result = $this->findAppStructureModel($v, $model, array_merge($parent, [$k]));
            }
        return $result;
    }


    /**
     * get app structure model
     * @param string $model
     * @return array
     */

    private function getAppStructure($model)
    {
        if (isset($this->app_structure['models'])) {
            $ss = $this->findAppStructureModel($this->app_structure['models'], $model);

            if ($ss)
                $ss['parent'] = [
                    'page' => $ss['parent'][count($ss['parent']) - 1],
                    'parent_page' => $ss['parent'][count($ss['parent']) - 2],
                    'path' => $ss
                ];
        } else
            $ss = [];
        return $ss;
    }

    /**
     * extract Translation item
     * @param array $item
     * @param array $fields
     * @return array
     */

    private function extractTranslation($item, $fields = null)
    {
        foreach ($item as $k => $v) {
            if (substr($k, strlen($k) - strlen($this->lang_add)) == $this->lang_add) {
                $pre = substr($k, 0, strlen($k) - strlen($this->lang_add));

                if ((!$fields || in_array($pre, $fields)) && !$item[$pre]) {
                    $item[$pre] = $v;
                    unset($item[$v . $this->lang_add]);
                }
            } elseif (is_array($v)) {
                if (!$fields || in_array($k, $fields))
                    $item[$k] = $this->extractTranslation($item[$k]);
            }
        }
        return $item;
    }

    /**
     * Loads and updates schema from model
     * @param string $model
     * @param boolean $separate
     * @param array $params
     * @param boolean $model_updatr
     * @return array
     */

    public function getSchema($model, $separate = true, $params = null, $model_update = null)
    {
        
        if ($model_update) {
            $schema = $this->apporm->getJsonModelSchema([$model, $model_update], false, $params);
        } else
            $schema = $this->apporm->getJsonModelSchema($model, false, $params);

        $schema['structure'] = $this->getAppStructure($model);
        if (!$schema['structure'])
            $schema['structure'] = $this->getAppStructure($schema['table']);

        if (isset($schema['layouts']) && $schema['layouts']) {
            $now = intval(_uho_fx::getGet('layout'));
            if (!$now)
                $now = 1;
            $schema['layout'] = $schema['layouts'][$now - 1];
            foreach ($schema['layouts'] as $k => $v) {
                $schema['layouts'][$k]['active'] = (($k + 1) == $now);
                $schema['layouts'][$k]['url'] = ['type' => 'url_now', 'getNew' => ['layout' => ($k + 1)]];
            }
        }

        // translations
        $schema = $this->extractTranslation($schema, ['label', 'buttons_edit', 'help']);
        foreach ($schema['fields'] as $k => $v)
            $schema['fields'][$k] = $this->extractTranslation($schema['fields'][$k], ['help', 'tab', 'label']);

        // filters
        if (!isset($params['keys']) || !$params['keys'])
            $params['keys'] = [];
        $params['keys']['user'] = $this->getUser()['id'];

        if (isset($schema['filters']) && $schema['filters'] && isset($params)) {
            $params['twig'] = ['cms_user' => $this->getUser()];
            $schema['filters'] = $this->fillPattern($schema['filters'], $params);
        }
        if (isset($schema['layout']['iframe']) && $params)
            $schema['layout']['iframe'] = $this->fillPattern($schema['layout']['iframe'], $params);

        $schema['langs'] = isset($this->appnow['langs']) ? $this->appnow['langs'] : [];

        // field work
        foreach ($schema['fields'] as $k => $v) {
            if ($v['type'] == 'order')
                $schema['sortable'] = ['field' => $v['field']];
            if (in_array($v['type'], ['file']) && !$v['field'])
                $schema['fields'][$k]['field'] = 'fake_' . $v['type'] . ($k + 1);
        }

        // separating lang fields, adding cms_names
        $f = [];

        if ($separate) {

            foreach ($schema['fields'] as $k => $v) {
                if ($v['field'] && strpos($v['field'], ':lang'))
                    foreach ($schema['langs'] as $k2 => $v2) {
                        $v['field'] = explode(':lang', $schema['fields'][$k]['field'])[0] . $v2['lang_add'];
                        $v['lang'] = $v2['lang'];
                        $v['cms_field'] = 'e_' . $v['field'];
                        $v['hidden'] = (!$v2['active']);
                        if ($k2 > 0 && $v['tab'])
                            unset($v['tab']);
                        if ($k2 != 0) {
                            unset($v['help']);
                            unset($v['hr']);
                        }
                        $f[] = $v;
                    }
                else {
                    $v['cms_field'] = 'e_' . $v['field'];
                    $f[] = $v;
                }

                if (isset($v['captions'])) {

                    $c = [];
                    foreach ($v['captions'] as $k2 => $v2)
                        if (strpos($v2['field'], ':lang')) foreach ($schema['langs'] as $k3 => $v3) {
                            $vv = $v2;
                            $vv['field'] = explode(':lang', $v2['field'])[0] . $v3['lang_add'];
                            $vv['lang'] = $v3['lang'];
                            $c[] = $vv;
                        }
                        else
                            $c[] = $v2;

                    $f[count($f) - 1]['captions'] = $c;
                }
            }

            $schema['fields'] = $f;
        }


        // adding default values for CMS if not present
        foreach ($schema['fields'] as $k => $v)
            switch ($v['type']) {
                case "image":

                    if (!_uho_fx::array_filter($v['images'], 'preview')) {
                        if (isset($v['images']) && count($v['images']) > 1)
                            $nr = 1;
                        else
                            $nr = 0;
                        if (isset($schema['fields'][$k]['images'][$nr])) {
                            $schema['fields'][$k]['images'][$nr]['preview'] = true;
                        }
                    }
                    foreach ($v['images'] as $nr => $image)
                        if (empty($image['label']))
                            $schema['fields'][$k]['images'][$nr]['label'] = $image['folder'];
                    break;

                case "elements":
                case "checkboxes":



                    break;
            }

        if (isset($schema['label']['page']))
            $schema['label']['page'] = $this->fillPattern($schema['label']['page'], $params);

        return $schema;
    }

    /**
     * Update schema sources
     * @param array $schema
     * @param array $record
     * @param array $params
     * @return array
     */

    public function updateSchemaSources($schema, $record = null, $params = null)
    {
        // models mainly
        $p = [];
        if ($params)
            foreach ($params as $k => $v)
                $p['%' . $k . '%'] = $v;
        foreach ($schema['fields'] as $k => $v)
            if (isset($v['options'])) {
                foreach ($v['options'] as $kk => $vv)
                    if (empty($vv['label']) && $vv['label' . $this->lang_add])
                        $schema['fields'][$k]['options'][$kk]['label'] = $vv['label' . $this->lang_add];
            }
        $schema = $this->apporm->updateSchemaSources($schema, $record, $p);
        //print_r($schema['fields'][4]['options']);
        return $schema;
    }

    /**
     * Update schema for edit page
     * @param array $schema
     * @param string $page_with_params
     * @param array $record
     * @param array $translate
     * @param array $params
     * @return null
     */

    public function updateSchemaForEdit(&$schema, $page_with_params, $record, $translate, $params)
    {

        if (is_array($schema['label'])) {
            if (isset($schema['label']['edit']))
                $schema['label'] = $schema['label']['edit'];
            else
                $schema['label'] = $schema['label']['page'];
        }

        if (!$schema['label'])
            $schema['label'] = $this->getSchemaLabelFromMenu($page_with_params);
        if (!$record)
            $record = [];

        $original_record = $r = $record;
        $r['params'] = $params;
        if ($schema['label'])
            $schema['label'] = $this->getTwigFromHtml($schema['label'], $r);

        $hide = [];

        if (!@$record['id']) {
            $is_new = true;
        }


        foreach ($schema['fields'] as $k => $v)
            if (!empty($v['cms']['edit']) && $v['cms']['edit'] == 'remove')
                unset($schema['fields'][$k]);
            else {
                if (@is_array($v['label']))
                    $schema['fields'][$k]['label'] = $v['label']['edit'];
                if (isset($v['settings']) && isset($v['settings']['type']) && $v['settings']['type'] == 'tiny')
                    $this->tiny = true;
                $required = [];

                if (isset($schema['fields'][$k]['options']) && !isset($schema['fields'][$k]['source'])) {
                    $schema['fields'][$k]['options'] = _uho_fx::array_multisort($schema['fields'][$k]['options'], 'label');
                }

                // hashable
                if (isset($v['settings']['hashable']) && $v['settings']['hashable']) {
                    switch ($v['type']) {
                        case "string":
                        case "text":
                            if (substr($record[$v['field']], 0, 8) == '[HASHED]') $schema['fields'][$k]['value_hashed'] = true;
                            break;
                        case "file":
                            $filename = $_SERVER['DOCUMENT_ROOT'] . $record[$v['field']]['src'];
                            $filename = explode('?', $filename)[0];
                            $source = @file_get_contents($filename);

                            if ($source && substr($source, 0, 8) == '[HASHED]')
                                $schema['fields'][$k]['value_hashed'] = true;
                            break;
                    }
                }

                /*
                    Update schema by type
                */

                switch ($v['type']) {
                    case "image":

                        if (isset($v['images']))
                            foreach ($v['images'] as $k2 => $v2)
                                if ($v2['crop'] && $v2['width'] && $v2['height'])
                                    $schema['fields'][$k]['images'][$k2]['ratio'] = str_replace(',', '.', $v2['width'] / $v2['height']);

                        if (!empty($v['extensions']) && in_array('jpg', $v['extensions']) && !in_array('jpeg', $v['extensions'])) {
                            array_push($schema['fields'][$k]['extensions'], 'jpeg');
                        }

                        if ($this->s3 && @$record[$v['field']]['original']) {
                            $original = $record[$v['field']]['original'];
                            $original = array_shift(explode('?', $original));
                            $ext = array_pop(explode('.', $original));
                            $original_temp_filename = $this->cms_folder . '/temp/crop_' . uniqid() . '.' . $ext;
                        }
                        break;

                    case "virtual":

                        $v['value'] = $this->fillPattern($v['value'], ['keys' => $record, 'numbers' => $params, 'twig' => $record]);

                        break;

                    case "boolean":

                        if ($is_new && ($v['default'] === 1 || $v['default'] === true))
                            $record[$v['field']] = 1;

                        break;

                    case "file":
                        $src = @$record[$v['field']]['src'];
                        $src = explode('?', $src)[0];
                        $src = array_pop(explode('.', $src));
                        if ($src)
                            $record[$v['field']]['extension'] = $src;
                        break;

                    case "elements_double":

                        foreach ($v['source_double'] as $k2 => $v2) {
                            if ($v2['model']) {
                                $m = $this->apporm->getJsonModelSchema($v2['model']);
                                if ($m['model'])
                                    $v['source_double'][$k2] = array_merge($v['source_double'][$k2], $m['model']);
                                if (is_array($m['label']))
                                    $label = $m['label']['page'];
                                else
                                    $label = $m['label'];
                                $schema['fields'][$k]['source_double'][$k2]['model_label'] = $v['source_double'][$k2]['model_label'] = $label;
                            }
                            if (!isset($schema['fields'][$k]['source_double'][$k2]['model_label']))
                                $schema['fields'][$k]['source_double'][$k2]['model_label'] = $schema['fields'][$k]['source_double'][$k2]['model'];
                        }
                        $schema['fields'][$k]['source_double'] = _uho_fx::array_multisort($schema['fields'][$k]['source_double'], 'model_label', SORT_ASC, $this->lang);


                        $val = [];
                        if (is_array($record[$v['field']]))
                            foreach ($record[$v['field']] as $k2 => $v2) {
                                $m = _uho_fx::array_filter($v['source_double'], 'slug', $v2['_slug'], ['first' => true]);
                                $val[] = $v2['_slug'] . ':' . $v2['id'];
                                $record[$v['field']][$k2] = ['added' => true, 'label' => $this->getTwigFromHtml($m['label'], $v2), 'sublabel' => $m['model_label'], 'fields' => $v2, 'val' => $v2['_slug'] . ':' . $v2['id']];
                            }
                        $record[$v['field']] = ['values' => $record[$v['field']], 'value' => implode(',', $val)];

                        break;

                    case "elements":
                        if (@$v['source']['model']) {
                            $val = [];
                            $ids = [];
                            if (is_array($record[$v['field']]))
                                foreach ($record[$v['field']] as $k2 => $v2) { {
                                        $val[] = $v2['id'];
                                        $ids[$v2['id']] = true;
                                        if (!$v['source']['label'])
                                            $v['source']['label'] = '{{label}}';
                                        $record[$v['field']][$k2] = [
                                            'added' => true,
                                            'label' => $this->getTwigFromHtml($v['source']['label'], $v2),
                                            'image' => $this->getTwigFromHtml($v['source']['image'], $v2),
                                            'fields' => $v2
                                        ];
                                    }
                                }
                            $record[$v['field']] = ['values' => $record[$v['field']], 'value' => implode(',', $val), 'ids' => $ids];
                        }

                        break;
                    case "checkboxes":
                        $val = [];
                        if (is_array($record[$v['field']]))
                            foreach ($record[$v['field']] as $k2 => $v2) {
                                if (is_array($v2))
                                    $val[$v2['id']] = true;
                                else
                                    $val[$v2] = true;
                            }
                        $record[$v['field']] = $val;
                        break;

                    case "date":

                        if ((!$record[$v['field']] || $record[$v['field']] == '0000-00-00' || $record[$v['field']] == '{{now}}') && $v['default'] == '{{now}}')
                            $record[$v['field']] = date('Y-m-d');

                        break;

                    case "file":

                        $required = ['folder', ['extension', 'extensios']];
                        $val = @$record[$v['field']];
                        if ($val['src'])
                            $record[$v['field']]['size'] = _uho_fx::filesize($val['src'], true);

                        break;

                    case "datetime":

                        if ($v['default'] == '{{now}}' && (!$record[$v['field']] || $record[$v['field']] == '{{now}}'))
                            $record[$v['field']] = date('Y-m-d H:i:s');
                        break;

                    case "text":

                        if (!isset($v['settings']['rows'])) {
                            if (!$v['settings'])
                                $schema['fields'][$k]['settings'] = [];
                            $schema['fields'][$k]['settings']['rows'] = 'medium';
                        }

                        break;

                    case "media":

                        if ($v['media']['model']) {
                            $sch = $this->apporm->getJsonModelSchema($v['media']['model']);

                            $im = _uho_fx::array_filter($sch['fields'], 'type', 'image', ['first' => true]);
                            $ff = _uho_fx::array_filter($sch['fields'], 'type', 'file', ['first' => true]);

                            if (!$v['layout']) {
                                $schema['fields'][$k]['layout'] = ['folder' => $im['images'][1]['folder']];
                            }
                        }

                        foreach ($schema['fields'][$k]['media']['type'] as $k3 => $v3)
                            switch ($v3) {
                                case 'image':
                                    $schema['fields'][$k]['media']['type'][$k3] = [
                                        'type' => 'image',
                                        'extensions' => ['png', 'jpg', 'jpeg', 'gif']
                                    ];
                                    break;
                                case 'file':
                                    if ($ff)
                                        $schema['fields'][$k]['media']['type'][$k3] = [
                                            'type' => 'file',
                                            'extensions' => $ff['extensions']
                                        ];
                                    break;
                                default:
                                    break;
                            }

                        // update urls
                        if ($record[$v['field']])
                            foreach ($record[$v['field']] as $k2 => $v2)
                                switch ($v2['type']) {
                                    case "panorama":
                                        $params = json_encode(['field' => $v['field'], 'nr' => $k2], true);
                                        $record[$v['field']][$k2]['url_panorama'] = $this->cms_path . '/plugin-edit/' . $page_with_params . '/' . $record['id'] . '/panorama_init?params=' . urlencode($params);
                                        break;
                                    case "image":
                                    case "youtube":
                                    case "video":
                                    case "vimeo":
                                        $params = json_encode(['field' => $v['field'], 'nr' => $k2], true);
                                        $record[$v['field']][$k2]['url_resize'] = $this->cms_path . '/plugin-edit/' . $page_with_params . '/' . $record['id'] . '/media_resize?params=' . urlencode($params);
                                        break;
                                }

                        // update captions
                        if (isset($v['captions']))
                            foreach ($v['captions'] as $k2 => $v2)
                                if (@$v2['source']['model']) {
                                    $options = $this->apporm->getJsonModel($v2['source']['model']);
                                    foreach ($options as $k3 => $v3)
                                        if (!$v3['label'] && $v3['slug'])
                                            $options[$k3]['label'] = $v3['slug'];
                                    $schema['fields'][$k]['captions'][$k2]['options'] = $options;
                                }

                        break;

                    case "select":

                        // add null
                        if (isset($v['source']['null']) || isset($v['null'])) {
                            if (!$schema['fields'][$k]['options'])
                                $schema['fields'][$k]['options'] = [];
                            array_unshift($schema['fields'][$k]['options'], ['value' => 0, 'label' => '--- ' . $translate['choose'] . ' ---']);
                        }

                        // add full source
                        if ($v['source']['model']) {
                            if (!$v['source']['label'])
                                $v['source']['label'] = '{{label}}';
                            $val = $record[$v['field']];

                            if ($val)
                                $record[$v['field']] = ['values' => $record[$v['field']], 'label' => $this->getTwigFromHtml($v['source']['label'], $val)];
                            if ($v['source']['edit'])
                                $schema['fields'][$k]['source']['url_edit'] = 'page/' . $v['source']['model'];
                        }

                        // filter options
                        if (isset($v['options']) && isset($v['filters'])) {
                            $option_filters = $v['filters'];
                            foreach ($option_filters as $kf => $vf) {
                                $option_filters[$kf] = $this->getTwigFromHtml($vf, $original_record);
                                if (empty($option_filters[$kf])) unset($option_filters[$kf]);
                            }
                            if ($option_filters) {
                                foreach ($option_filters as $ko => $vo)
                                    foreach ($v['options'] as $kf => $vf) {
                                        if ($vf[$ko] != $vo) unset($v['options'][$kf]);
                                    }
                                $schema['fields'][$k]['options'] = $v['options'];
                            }
                        }

                        // add default
                        if ($is_new && $v['default']) {
                            $v['default'] = $this->fillPattern($v['default'], ['keys' => $record, 'numbers' => $params]);
                            $record[$v['field']] = $v['default'];
                        }

                        if ($is_new && $v['value']) {
                            $v['value'] = $this->fillPattern($v['value'], ['keys' => $record, 'numbers' => $params]);
                            $record[$v['field']] = $v['value'];
                        }

                        break;

                    case "html":
                        if (isset($v['settings']['config']))
                            $this->ckeditor_configs['e_' . $v['field']] = $v['settings']['config'];

                    case "string":
                        if ($record[$v['field']] == '{{random32}}' && $v['default'] == '{{random32}}')
                            $record[$v['field']] = md5(uniqid());
                        elseif ($is_new && $v['default']) {
                            $record[$v['field']] = $this->fillPattern($v['default'], ['keys' => $record, 'numbers' => $params]);
                        }

                        break;
                }


                if (isset($v['value']))
                    $record[$v['field']] = $v['value'];

                $errors = [];
                foreach ($required as $kk => $vv) {
                    if (is_array($vv)) {
                        $req = false;
                        foreach ($vv as $kk2 => $vv2)
                            if (isset($v[$vv2]))
                                $req = true;
                        if (!$req)
                            'error::model_app::' . $v['type'] . '.' . implode('||', $vv) . ' is required';
                    } elseif (!isset($v[$vv]))
                        $errors[] = 'error::model_app::' . $v['type'] . '.' . $vv . ' is required';
                }

                if ($errors)
                    exit(implode('<br>', $errors));

                // toggle -------------------------------------------------------
                if ($v['toggle_fields']) {
                    $val = $record[$v['field']];
                    if (is_array($val))
                        $val = $val['values']['id'];
                    $toggle = $v['toggle_fields'];

                    if (isset($toggle)) // && @isset($toggle[$val]))
                    {
                        $toggle_found = null;
                        foreach ($toggle as $kk => $vv)
                            if ($kk == $val)
                                $toggle_found = $kk;
                            else if ($kk[0] == '!' && substr($kk, 1) != $val)
                                $toggle_found = $kk;
                        if ($toggle_found !== null) {
                            $toggle = $toggle[$toggle_found];
                            if ($toggle['hide'])
                                $hide = array_merge($hide, $toggle['hide']);
                        }
                    }
                }

                // help ---------------------------------------------
                if ($v['help']) {
                    $c = 'serdelia_edit_help_' . $schema['model_name'] . '_' . $v['cms_field'];
                    $hidden = (@$_COOKIE[$c] == 1);
                    if (!is_array($v['help']))
                        $schema['fields'][$k]['help'] = ['text' => $v['help']];
                    $schema['fields'][$k]['help']['hidden'] = $hidden;
                }
                if ($schema['fields'][$k]['edit'] === false)
                    $schema['fields'][$k]['disabled'] = true;
            }

        // update HTML media

        foreach ($schema['fields'] as $k => $v)
            if ($v['type'] == 'html' && isset($v['settings']['media_field']) && (in_array($v['settings']['wysiwyg'], ['ckeditor', 'ckeditor5']))) {
                $media = $record[$v['settings']['media_field']];
                $filename = $this->cms_folder . '/public/ckeditor/plugins/uho_media/icons/uho_media.png';

                $html = $record[$v['field']];

                // clean that staff!
                // $html=strip_tags($html,'<p><b><strong><i><em><a><blockquote><img><iframe><figure><h1><h2><h3><h4><h5><h6><ol><ul><li><sup><sub>');

                $html = $this->removeTags($html, ['span', 'figcaption']);


                $max = 100;

                while ($max && strpos(' ' . $html, '<img src="' . $filename)) {
                    $max--;
                    $i1 = strpos($html, '<img src="' . $filename);
                    $i2 = strpos($html, '>', $i1);
                    $image = _uho_fx::array_filter($media, 'type', 'image', ['first' => true, 'keys' => true]);

                    if ($image !== false && $i2 > $i1) {

                        if (!empty($media[$image]['alt'])) $alt = $media[$image]['alt'];
                        else $alt = '';
                        if (!empty($media[$image]['caption'])) {
                            $caption = '<figcaption>' . $media[$image]['caption'] . '</figcaption>';
                        } else $caption = '';


                        $figure = '<figure class="image"><img src="' . $media[$image]['image']['original'] . '" alt="' . $alt . '">' . $caption . '</figure>';

                        $html = substr($html, 0, $i1) . $figure . substr($html, $i2 + 1);

                        unset($media[$image]);
                    }
                }

                $record[$v['field']] = $html;
            }


        if ($hide)
            foreach ($schema['fields'] as $k => $v)
                if (in_array($v['field'], $hide))
                    $schema['fields'][$k]['hidden'] = true;

        return $record;
    }

    private function removeTags($html, $tags)
    {

        foreach ($tags as $k => $v) {
            $max = 100;
            while ($max && strpos(' ' . $html, '<' . $v . '>')) {
                $max--;
                $i1 = strpos($html, '<' . $v . '>');
                if ($i1 !== null) {
                    $i2 = strpos($html, '</' . $v . '>', $i1);
                    if ($i2 > $i1) {
                        $html = substr($html, 0, $i1) . substr($html, $i2 + strlen($v) + 3);
                    } else continue;
                } else continue;
            }
        }

        return $html;
    }

    /**
     * Update schema buttons for edit/page views
     * @param array $buttons
     * @param array $schema
     * @param array $record
     * @param array $params
     * @param array $get
     * @return array
     */

    public function updateSchemaButtons($buttons, $schema, $record, $params, $get = null): array
    {

        // buttons -----------------------------------------------
        if (!$buttons)
            $buttons = [];

        if ($buttons)
            foreach ($buttons as $k => $v)
                // page button
                if ($v['type'] == 'page') {
                    $v['page'] = $this->fillPattern($v['page'], ['keys' => $record, 'numbers' => $params, 'get' => $get]);

                    if ($this->checkAuth($v['page'], [2, 3]))
                        $buttons[$k]['url'] = ['type' => 'page', 'page' => $v['page']];
                    else
                        unset($buttons[$k]);
                }
                // link new windows
                elseif ($v['type'] == 'link') {
                    $u = $this->fillPattern($v['params']['url'], ['keys' => $record, 'numbers' => $params]);

                    $buttons[$k]['url'] = ['type' => 'external', 'link' => $u];
                    $buttons[$k]['target'] = "_blank";
                    $buttons[$k]['class'] = "warning";
                }
                // plugins
                elseif ($v['type'] == 'plugin' || isset($v['plugin'])) {

                    $buttons[$k]['type'] = 'plugin';
                    if ($v['params']) {
                        $v['params'] = $this->fillPattern($v['params'], ['keys' => $record, 'numbers' => $params]);
                    }

                    $v = $this->fillPattern($v, ['keys' => $record, 'numbers' => $params], true);
                    if ($this->checkAuth($schema['model_name'], [2, 3])) {

                        if ($record) {
                            $v['params'] = $this->fillPattern($v['params'], ['keys' => $record]);

                            if ($v['params'])
                                foreach ($v['params'] as $k2 => $v2)
                                    $buttons[$k]['params'][$k2] = $this->getTwigFromHtml($v2, $record);
                        } else
                            $buttons[$k]['params'] = $v['params'];

                        if ($schema['model_url_name'])
                            $m_name = $schema['model_url_name'];
                        else
                            $m_name = $schema['model_name'];

                        $buttons[$k]['url'] = ['type' => 'plugin', 'page' => $m_name, 'page_params' => $params, 'plugin' => $v['plugin'], 'record' => $record['id'], 'params' => $buttons[$k]['params'], 'get' => $get];
                        // let's find plugin's JSON

                        $json = @file_get_contents($this->cms_folder . '/plugins/' . $v['plugin'] . '/plugin.json');
                        if ($json)
                            $json = json_decode($json, true);
                        if (!$buttons[$k]['label'] && $json[$this->lang]['label'])
                            $buttons[$k]['label'] = $json[$this->lang]['label'];
                        if (!$buttons[$k]['icon'] && $json['icon'])
                            $buttons[$k]['icon'] = $json['icon'];
                    } else {
                        unset($buttons[$k]);
                    }
                }

        // remove disputed "?" 

        foreach ($buttons as $k => $v)
            if (!empty($v['url']['params']['url'])) {
                $buttons[$k]['url']['params']['url'] = str_replace('?', '¿', $v['url']['params']['url']);
            }



        return $buttons;
    }

    /**
     * Simple array template function using %template% notation
     * @param array $array
     * @param array $params
     * @return array
     */

    public function fillPattern($array, $params)
    {
        if (!is_array($array)) {
            $string = true;
            $array = [$array];
        }

        foreach ($array as $k => $v)
            if ($v) {
                $null = false;
                if ($params['numbers'])
                    foreach ($params['numbers'] as $k2 => $v2)
                        if ($v == ('%' . $k2 . '%') && $v2 === null)
                            $null = true;
                        elseif ($k2 && is_string($array[$k])) {
                            //  echo($array[$k]);
                            if (!$v2) $v2 = '';
                            $array[$k] = str_replace('%' . $k2 . '%', $v2, $array[$k]);
                            //echo('-->'.$array[$k].'
                            //');
                        }

                if ($params['keys'])
                    foreach ($params['keys'] as $k2 => $v2)
                        if (is_string($v2) && is_string($array[$k])) {
                            $array[$k] = str_replace('%' . $k2 . '%', $v2, $array[$k]);
                            $array[$k] = str_replace('{{' . $k2 . '}}', $v2, $array[$k]);
                            if (is_string($array[$k]) && isset($params['twig'])) {
                                $array[$k] = $this->getTwigFromHtml($array[$k], $params['twig']);
                            }
                        }


                if ($null)
                    $array[$k] = null;

                if ($params['get']) {
                    $array[$k] = $this->getTwigFromHtml($array[$k], ['get' => $params['get']]);
                }
            }
        if ($string)
            $array = $array[0];
        return $array;
    }


    /**
     * Converts (recursively) main menu to use with views
     * adding urls
     * @param array $t
     * @return array
     */

    private function convertAppMenu($t)
    {
        $t = $this->extractTranslation($t);
        $hr = false;
        foreach ($t as $k => $v) {
            // Depreciated
            if (isset($v['page']) && empty($v['model'])) {
                $v['model'] = $v['page'];
                unset($v['page']);
            }


            // Divider line
            if (empty($v)) {
                unset($t[$k]);
                $hr = true;
            }
            // Hidden element
            elseif ($v['hidden'])
                unset($t[$k]);
            // Submenu
            elseif (!empty($v['submenu'])) {
                $t[$k]['submenu'] = $this->convertAppMenu($v['submenu']);
                if ($hr)
                    $t[$k]['hr'] = true;
                $hr = false;
                if (empty($t[$k]['submenu']))
                    unset($t[$k]);
            }
            // Edit item     
            elseif (!empty($v['edit'])) {
                $page = $v['edit'];
                $page = explode('/', $page);
                $page = $page[0];
                $page = explode(',', $page);
                $page = $page[0];
                if ($this->checkAuth($page)) {
                    $t[$k]['url'] = ['type' => 'edit', 'page' => $v['edit']];
                    if ($hr)
                        $t[$k]['hr'] = true;
                    $hr = false;
                } else
                    unset($t[$k]);
            }
            // Standard item
            elseif (!empty($v['model']) && $this->checkAuth($v['model'])) {
                $t[$k]['url'] = ['type' => 'page', 'page' => $v['model']];
                if ($hr)
                    $t[$k]['hr'] = true;
                $hr = false;
            } else {
                unset($t[$k]);
            }
        }
        return $t;
    }

    /**
     * Convert app structure models to to use with views
     * @param array $t
     * @return array
     */

    private function convertAppStructureModels($t)
    {
        $tt = [];
        if ($t)
            foreach ($t as $k => $v) {
                if (is_numeric($k) && is_array($v)) {
                    foreach (array_slice($v, -1, 1, true) as $key => $value);
                    $tt[$key] = $this->convertAppStructureModels($v[$key]);
                } else if (is_array($v))
                    $tt[$k] = $this->convertAppStructureModels($v);
                else if (is_numeric($k) && is_string($v))
                    $tt[$v] = [];
                else
                    $tt[] = $v;
            }

        return $tt;
    }

    /**
     * Convert app structure object to to use with views
     * @param array $t
     * @return array
     */

    private function convertAppStructure($t)
    {
        $t = json_decode($t, true);
        $t['models'] = $this->convertAppStructureModels($t['models']);
        return $t;
    }

    /**
     * TWIG template util
     * @param string $html
     * @param array $data
     * @return string
     */

    public function getTwigFromHtml($html, $data)
    {
        if ($html && is_string($html) && is_array($data)) {
            $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(array()));

            // --- declination
            $twig_filter_declination = new \Twig\TwigFilter('declination', function ($context, $string, $params) {
                $result = $params[_uho_fx::utilsNumberDeclinationPL($string) - 1];
                if ($params[3])
                    $result = $string . ' ' . $result;
                return $result;
            }, ['needs_context' => true]);

            $twig->addFilter($twig_filter_declination);


            $template = $twig->createTemplate($html);
            $html = $template->render($data);
        }
        return $html;
    }

    /**
     * Set cache folders
     * @param array $folders
     * @return null
     */

    public function setCacheKills($folders)
    {
        $this->cache_folders = $folders;
    }

    public function setCacheKillPlugin($plugin)
    {
        $this->cache_plugin = $plugin;
    }

    /**
     * Removes cache files
     * @return null
     */

    public function cacheKill()
    {
        $arrContextOptions = array(
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            ),
        );

        if ($this->cache_folders) {
            foreach ($this->cache_folders as $k => $v) {
                if (substr($v, 0, 4) == 'http') {
                    $r = json_decode(_uho_fx::fileCurl($v), true);
                    if (!$r['result'])
                        $r = json_decode(_uho_fx::fileCurl(str_replace('https', 'http', $v), true));
                    if (!$r['result'])
                        $r = json_decode(file_get_contents($v, false, stream_context_create($arrContextOptions)), true);
                    if (!$r['result'])
                        exit('Error perfoming cache clean at: ' . $v);
                } else
                    $this->sql->cacheKill($v, ['cache', 'sql']);
            }
        }

        if ($this->cache_plugin) {
            $path = $_SERVER['DOCUMENT_ROOT'] . $this->cfg_path . '/plugins/' . $this->cache_plugin . '/';
            require_once $path . 'plugin.php';
            try {
            } catch (Exception $e) {
                echo 'Caught exception: ', $e->getMessage(), "\n";
            }
            $plugin_class = 'serdelia_plugin_' . $this->cache_plugin;
            $class =    new $plugin_class($this->apporm, $params, $this);
            $data = $class->getData();
            if ($data && isset($data['result']) && $data['result']);
            else {
                echo ('CACHE PLUGIN ERROR');
            }
        }
    }

    /**
     * Loads model label from menu file
     * @param string page_with_params
     * @param array menu
     * @return string
     */

    public function getSchemaLabelFromMenu($page_with_params, $menu = null)
    {
        if (!$menu)
            $menu = $this->app_menu;
        $label = '';
        foreach ($menu as $k => $v)
            if ($label);
            elseif ($v['submenu'])
                $label = $this->getSchemaLabelFromMenu($page_with_params, $v['submenu']);
            elseif ($v['page'] == $page_with_params)
                $label = $v['label'];

        return $label;
    }

    /**
     * Starts FFMPEG
     * @param string $cmd
     * @return mixed
     */

    public function ffmpeg($cmd)
    {
        if ($this->config_params['ffmpeg'])
            $ffmpeg = $this->config_params['ffmpeg'];
        else
            $ffmpeg = 'ffmpeg';

        if (isset($this->config_params['exec']) && $this->config_params['exec'] == 'exec')
            return exec($ffmpeg . ' ' . $cmd);
        else return shell_exec($ffmpeg . ' ' . $cmd);
    }

    /**
     * Starts FFPROBE
     * @param string $cmd
     * @return mixed
     */

    public function ffprobe($cmd)
    {
        if ($this->config_params['ffprobe'])
            $ffprobe = $this->config_params['ffprobe'];
        else
            $ffprobe = 'ffprobe';
        return shell_exec($ffprobe . ' ' . $cmd);
    }

    public function fileUploadModel($model, $field, $data, $source)
    {
        $result = ['result' => false];
        $schema = $this->apporm->getJsonModelSchemaWithPageUpdate($model);
        if (!$schema)
            return ['result' => false, 'message' => 'Image schema not found'];

        $field_schema = _uho_fx::array_filter($schema['fields'], 'field', $field, ['first' => true]);

        if ($field_schema) {
            $dest = $_SERVER['DOCUMENT_ROOT'] . $field_schema['folder'];
            if (!file_exists($dest)) mkdir($dest, 0775, true);
            $dest .= '/';

            if (!empty($field_schema['filename']))
                $dest .= $field_schema['filename'];
            else
                $dest .= $data['uid'];
            $dest .= '.' . $field_schema['extension'];
            $dest = $this->getTwigFromHtml($dest, $data);

            $r = copy($source, $dest);
            if ($r)
                $result = ['result' => true];
        } else
            $result = ['result' => false, 'message' => 'Image field [' . $field . '] in schema not found'];
        return $result;
    }

    /**
     * Resizes uploaded image based on model/field
     * @param string $model
     * @param string $field
     * @param array $data
     * @param string $source
     * @param string $type
     * @param boolean $mkdir
     * @return array
     */

    public function imageResizeModel($model, $field, $data, $source, $type = 'image', $mkdir = false)
    {

        $result = ['result' => false];
        $schema = $this->apporm->getJsonModelSchema($model);
        if (!$schema)
            return ['result' => false, 'message' => 'Image schema not found'];

        $field_schema = _uho_fx::array_filter($schema['fields'], 'field', $field, ['first' => true]);

        if ($field_schema) {
            if ($type == 'panorama') {
                $field_schema['images'] = $field_schema['images_panorama'];
            }

            $params = ['mkdir' => $mkdir];
            if (@$field_schema['settings']['webp'])
                $params['webp'] = true;

            $result = $this->imageResize($field_schema, $data, $source, false, $params);
            if ($result['result'])
                return ['result' => true];
            else
                return ['result' => false, 'message' => implode(', ', $result['errors'])];
        } else
            return ['result' => false, 'message' => 'Image field [' . $field . '] in schema not found'];
    }

    /**
     * Updates image sizes field
     */
    public function imageUpdateResize($model, $field, $record)
    {
        $schema = $this->apporm->getJsonModelSchema($model);
        if ($schema)
            $image_field = _uho_fx::array_filter($schema['fields'], 'field', $field, ['first' => true]);
        else
            return false;
        if ($image_field && !empty($image_field['settings']['sizes']))
            $sizes = $image_field['settings']['sizes'];
        else
            return false;

        $item = $this->apporm->getJsonModel($model, ['id' => $record], true);
        if ($item && !empty($item[$field])) {
            $image = $item[$field];
            foreach ($image as $k => $v) {
                if (is_array($v))
                    $v = @$v['src'];
                if ($v && (substr($k, strlen($k) - 5) != '_webp')) {
                    $s = @getimagesize($v);
                    if (isset($s[0]))
                        $image[$k] = [$s[0], $s[1]];
                    else
                        unset($image[$k]);
                } else
                    unset($image[$k]);
            }

            if ($image) {
                $this->apporm->putJsonModel($model, [$sizes => $image], ['id' => $record]);
                return true;
            }
        }
    }

    /**
     * Resizes uploaded image based on field schema
     * @param string $field
     * @param array $data
     * @param string $filename
     * @param boolean $rescale_only
     * @param array $params
     * @return array
     */

    public function imageResize($field, $data, $filename = 'filename.jpg', $rescale_only = true, $params = null)
    {

        $result = true;
        $errors = [];
        $unlink = false;
        $full_filename = $filename;
        $filename = explode('?', $filename);
        $filename = $filename[0];

        $dir = rtrim(root_doc, '/') . $field['folder'] . '/';
        $dir = $this->getTwigFromHtml($dir, $data);

        $destination_filename = $data['uid'] . '.jpg';

        if ($field['filename']) {
            $destination_filename = str_replace('%uid%', $data['uid'], $field['filename']) . '.jpg';
        }

        if (isset($field['images'][0]['filename'])) {
            $destination_filename = $this->fillPattern($field['images'][0]['filename'], ['keys' => $data]) . '.jpg';
            $original = $dir . '/' . $destination_filename;
        }

        if ($rescale_only) {
            $original = $dir . $field['images'][0]['folder'] . '/' . $destination_filename;
            if (!file_exists($original))
                $errors[] = 'model_app_write::source file not found::' . $source;
        } else {
            if (substr($filename, 0, 4) == 'http') {
                $source = $full_filename;
            } else {
                if ($filename[0] == '/')
                    $source = root_doc . $filename;
                else {
                    $unlink = true;
                    $source = $this->upload_path . $filename;
                }
                if (!file_exists($source))
                    $errors[] = 'model_app_write::source file not found::' . $source;
            }
        }

        if (!$errors)
            foreach ($field['images'] as $k => $v) {
                // folder
                $folder = $dir . $v['folder'];
                $folder = $this->getTwigFromHtml($folder, $data);

                if (!$this->s3 && !file_exists($folder))
                    mkdir($folder, 0755, true);

                // copy or convert
                $destination = $folder . '/' . $destination_filename;
                $destination_retina = $folder . '_x2/' . $destination_filename;

                if (!$this->s3 && @$params['mkdir'] && !is_dir($folder))
                    mkdir($folder, 0755, true);
                if (!$this->s3 && @$params['mkdir'] && !is_dir($folder . '_x2'))
                    mkdir($folder . '_x2', 0755, true);

                if (!$v['width'] && !$v['height']) {
                    if (!$rescale_only) {
                        // 
                        $this->uploadCopy($source, $destination);
                        if (!$this->file_exists($destination))
                            return (['result' => false, 'errors' => ['Could not copy [' . $source . ' to ' . $destination . ']']]);
                        $original = $destination;
                    }
                } else {
                    if ($v['crop'])
                        $v['cut'] = $v['crop'];
                    unset($v['crop']);
                    if ($params)
                        $v = array_merge($v, $params);

                    if ($v['filename'] && $v['folder']) {
                        $destination = $folder . '/' . $this->fillPattern($v['filename'], ['keys' => $data]) . '.jpg';
                    }

                    if ($this->s3) $temp_destination = $this->s3GetTempFilename();
                    else $temp_destination = $destination;

                    if (@$v['webp']) {
                        $webp_destination = $this->jpg2webp($destination);
                    } else $webp_destination = '';

                    $r = _uho_thumb::convert($filename, $original, $temp_destination, $v, true);
                    if (!$r['result'])
                        $errors = array_merge($errors, $r['errors']);
                    elseif ($this->s3) {
                        $this->s3->copy($temp_destination, $destination);
                        if ($v['webp'] && @$r['webp']) $this->s3->copy($r['webp'], $webp_destination);
                    }

                    if ($v['retina']) {
                        if ($v['width'])
                            $v['width'] = $v['width'] * 2;
                        if ($v['height'])
                            $v['height'] = $v['height'] * 2;

                        if ($this->s3) $temp_destination = $this->s3GetTempFilename();
                        else $temp_destination = $destination_retina;
                        $r = _uho_thumb::convert($filename, $original, $temp_destination, $v, true);
                        if (!$r['result'])
                            $errors = array_merge($errors, $r['errors']);
                        elseif ($this->s3) {
                            $this->s3->copy($temp_destination, $destination_retina);
                            $webp_destination_retina = $this->jpg2webp($destination_retina);
                            if ($v['webp'] && @$r['webp']) $this->s3->copy($r['webp'], $webp_destination_retina);
                        }
                    }
                }
            }
        if ($source && $unlink)
            @unlink($source);
        if (!$errors)
            return (['result' => true]);
        else
            return (['result' => false, 'errors' => $errors]);
    }

    /**
     * Returns authorization list
     * @return array
     */

    public function getAuthList()
    {
        return $this->app_auth;
    }

    /**
     * Returns authorization presets list
     * @return array
     */

    public function getAuthPresets()
    {
        $src = $this->cfg_folder . '/structure/authorization_presets.json';
        if (file_exists($src)) {
            $data = file_get_contents($src);
            if ($data) $data = json_decode($data, true);
            return $data;
        }
    }

    /**
     * Checks user access to the model
     * @param string $model
     * @param array $levels
     * @return boolean
     */

    public function checkAuth($model, $levels = null)
    {

        $result = 0;
        $user = $this->getUser();

        if ($model) {
            $model = explode('?', $model);
            $model = $model[0];
            $model = explode(',', $model);
            $model = $model[0];
        }

        // home
        if ($model == '' && $user)
            return 1;

        if ($user && $user['edit_all'])
            $result = 3;
        else
            if ($user) {
            if (!$this->auth_now) {
                $auth = $this->apporm->getJsonModel('cms_users', ['id' => $user['id']], true);

                if (!empty($auth['auth']))
                    $auth = explode(',', $auth['auth']);
                $a = [];

                if ($auth)
                    foreach ($auth as $k => $v) {

                        if (is_string($v)) $v = explode('=', $v);

                        $a = _uho_fx::array_filter($this->app_auth, 'id', $v[0], ['first' => true]);

                        if ($a['models'])
                            foreach ($a['models'] as $k2 => $v2)
                                if (!$this->auth_now[$v2] || $this->auth_now[$v2] < $v[1])
                                    $this->auth_now[$v2] = $v[1];
                    }
            }



            foreach ($this->app_auth as $k => $v)
                if ($v['models']) foreach ($v['models'] as $k2 => $v2)
                    if ($this->auth_now[$model] && $this->auth_now[$model] > $result) {
                        $result = $this->auth_now[$model];
                    }
        }



        if ($levels)
            return in_array($result, $levels);
        else
            return $result;
    }

    /**
     * Checks user access to the model by authorization
     * @param string $model
     * @param array $auth
     * @return boolean
     */

    private function checkAuthModelSet($model, $auth)
    {
        if (!is_array($auth))
            $auth = [$auth];
        $swap = ['admin' => 3, 'write' => 2, 'read' => 1];
        // auth = [admin,...]
        foreach ($auth as $k => $v)
            if (isset($swap[$v]))
                $auth[$k] = $swap[$v];

        $r = $this->checkAuth($model, $auth);
        return $r;
    }

    /**
     * Updates schema by authorization access
     * @param array $schema
     * @return array
     */

    public function updateSchemaAuth($schema)
    {
        foreach ($schema['fields'] as $k => $v)
            if ($v['auth'] && !$this->checkAuthModelSet($schema['table'], $v['auth'])) {
                unset($schema['fields'][$k]);
            }
        $schema['fields'] = array_values($schema['fields']);
        return $schema;
    }

    /**
     * Performs API call
     * @param string $action
     * @param string $result_field
     * @return mixed
     */

    public function app_api($action, $result_field)
    {
        $url = $this->app_api_http . $action;
        $json = file_get_contents($url);
        if ($json)
            $json = json_decode($json, true);
        if ($json[$result_field])
            return $json[$result_field];
    }

    /**
     * Get variable from variables.json
     * @param string $section
     * @param string $var
     * @return mixed
     */

    public function getVariable($section, $var)
    {
        $vars = @json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'] . $this->cfg_path . '/structure/variables.json'), true);
        return @$vars[$section][$var];
    }

    /**
     * Updates model schema for Edit view
     * @param array $model
     * @param array $record
     * @param array $params
     * @param int $id
     * @param array $post
     * @return null
     */

    public function getSchemaForEdit($model, &$record, $params, $id, $post = null, $validate = false)
    {

        $schema = $this->getSchema($model, true, ['numbers' => $params]);

        // on_create defaults
        $plugins = _uho_fx::array_filter($schema['buttons_edit'], 'on_create', 1);
        if ($plugins) {
            require_once("model_app_plugin.php");
            $create_defaults = [];
            foreach ($plugins as $k => $v) {
                $class = new model_app_plugin($this->sql, $lang);
                $class->setParent($this);
                $class->setCfgPath($this->cfg_path);
                $p = ['page' => $model, 'page_with_params' => $page_with_params, 'params' => $params, 'record' => $id, 'plugin' => $v['plugin'], 'orm' => $this->apporm];
                $output = $class->getContentData(array('params' => $p, 'get' => []));
                if ($output['defaults'])
                    $create_defaults = array_merge($create_defaults, $output['defaults']);
            }

            foreach ($create_defaults as $k => $v)
                $record[$k] = $v;
        }


        // ------------------------------------------------------------------
        // getting record

        if ($id) {
            if ($post['action'] == 'field_value')
                $replace = [substr($post['field'], 2) => $post['value']];

            if (!empty($schema['filters']))
                foreach ($schema['filters'] as $k => $v)
                    if ($v[0] == '%') unset($schema['filters'][$k]);


            $record = $this->apporm->getJsonModel($schema, ['id' => $id], true, null, null, ['replace_values' => $replace]);

            if (!$record)
                exit('model_app_edit::getSchemaForEdit::record not found [' . $id . ']');
        }

        // setting up defaults
        if (!$record) {
            $record = [];
            foreach ($schema['fields'] as $k => $v)
                if ($v['default']) {
                    $record[$v['field']] = $this->fillPattern($v['default'], ['keys' => $record, 'numbers' => $params]);
                }
        }

        // plugins on create
        $plugins = _uho_fx::array_filter($schema['buttons_edit'], 'on_create', 1);

        // changing schema according to record values ------------------------
        if ($schema['page_update']) {
            if (!is_array($schema['page_update']))
                $schema['page_update'] = ['file' => $schema['page_update']];
            // fields which cause update
            $fields = _uho_fx::excludeTagsFromText($schema['page_update']['file'], '{{', '}}');

            foreach ($fields as $k => $v)
                $fields[$k] = array_shift(explode('.', $v));

            // updating page_update
            if ($record)
                $schema['page_update']['file'] = $this->getTwigFromHtml($schema['page_update']['file'], $record);

            if ($schema['page_update']['file'] && $record) {
                $schema = $this->getSchema($model, true, ['numbers' => $params], ['model' => $schema['page_update']['file'], 'position_after' => $schema['page_update']['position_after']]);
                if ($validate) {
                    $this->validateSchema($schema, $model);
                    $this->orm->creator($schema, ['create' => 'auto', 'update' => 'alert'], $record);
                }

                if ($id)
                    $record = $this->apporm->getJsonModel($schema, ['id' => $id], true);
            }


            // marking fields which cause update to launch askSaveGo popup after change
            if ($fields)
                foreach ($schema['fields'] as $k => $v)
                    if (in_array($v['field'], $fields))
                        $schema['fields'][$k]['page_update'] = true;
        } elseif ($validate) {
            $this->validateSchema($schema, $model);
            $this->orm->creator($schema, ['create' => 'auto', 'update' => 'alert'], true);
        }

        // getting new schema updated with record values  ------------------------

        $schema['model_url_name'] = $model;

        // updating on_create defaults

        foreach ($schema['fields'] as $k => $v) {
            if ($create_defaults[$v['field']])
                $schema['fields'][$k]['default'] = $create_defaults[$v['field']];
            if (isset($v['edit']) && $v['edit'] === 'add' && $id)
                $schema['fields'][$k]['edit'] = false;

            switch ($v['type']) {
                case "html":
                    if (!isset($v['settings']))
                        $schema['fields'][$k]['settings'] = [];
                    if (!isset($v['settings']['wysiwyg']))
                        $schema['fields'][$k]['settings']['wysiwyg'] = $this->wysiwyg['type'];
                    if (!isset($v['settings']['config']))
                        $schema['fields'][$k]['settings']['config'] = 'standard';
                    break;
            }
        }

        if (!empty($schema['filters']))
            foreach ($schema['filters'] as $k => $v)
                if ($v[0] == '%') unset($schema['filters'][$k]);


        return $schema;
    }

    public function setWysiwyg($cfg)
    {
        $this->wysiwyg = $cfg;
    }

    /**
     * Get model filters
     * @param array $schema
     * @param array $get
     * @return array
     */

    public function getPageQueryFilters($schema, $get)
    {
        $filters = [];
        $filters_stack = [];
        $first = true;

        $s = $schema['fields'];
        foreach ($s as $k => $v)
            if ($v['search'])
                $s[$k]['field_search'] = 's_' . $v['field'];

        $schema['fields'] = array_values($s);

        foreach ($schema['fields'] as $k => $v)
            if (
                !in_array($v['type'], ['image', 'uid']) &&
                ($v['field_search'] && isset($get[$v['field_search']]) || $get['query'])
            ) {
                $val = $get[$v['field_search']];

                // global search
                if ($get['query']) {
                    $val = $get['query'];
                } else
                // advanced search
                {
                    $schema['fields'][$k]['searched'] = $val;
                }

                if (in_array($v['type'], ['boolean']))
                    $vv = intval($val);
                else {
                    $v0 = explode(' ', trim($val));
                    if (count($v0) == 1)
                        $vv = ['operator' => '%LIKE%', 'value' => $val];
                    else {
                        foreach ($v0 as $kk => $vv)
                            $v0[$kk] = $v['field'] . ' LIKE "%' . $this->sqlSafe($vv) . '%"';
                        $vv = ['type' => 'custom', 'join' => ' && ', 'value' => $v0];
                    }
                }

                if ($v['type'] == 'virtual')
                    $filters_virtual[$v['field']] = $vv;
                elseif (in_array($v['type'], ['image', 'file']));
                else {
                    $filters[$v['field']] = $vv;
                }

                // remove these types from global search
                if (in_array($v['type'], ['boolean']) && $get['query'])
                    unset($filters[$v['field']]);

                $label_value = '';

                if ($v['options']) {
                    if ($val == '[not_null]') {
                        $filters[$v['field']] = ['operator' => '!=', 'value' => ''];
                        $label_value = $v['label'];
                    } else {
                        $val = _uho_fx::array_change_keys($v['options'], 'value', 'label')[$val];
                    }
                }

                if (!$get['query'] || $first) {
                    $first = false;
                    if ($v['type'] == 'boolean') {
                        if (!$label_value)
                            $label_value = $v['label'];
                        if (!$val && $v['label_not'])
                            $label_value = $v['label_not'];
                        elseif (!$val)
                            $label_value = 'Nie-' . $v['label'];
                    } elseif (!$label_value)
                        $label_value = $val;
                    $filters_stack[] = ['label' => $v['label'], 'label_value' => $label_value, 'value' => $val, 'url' => ['type' => 'url_now', 'getRemove' => ['query', $v['field_search']]]];
                }
            }

        if ($get['query']) {
            $s = $schema;
            if (!$s['filters'])
                $s['filters'] = [];
            $s['filters'] = $filters;
            $filters = $this->apporm->getJsonModelFiltersQuery($s);
            $filters = ['search' => ['type' => 'custom', 'join' => '||', 'value' => $filters]];
        }

        if ($schema['filters'])
            $filters = array_merge($filters, $schema['filters']);

        return $filters;
    }

    /**
     * Adds data backup
     * @param array $page
     * @param array $record
     * @return boolean
     */

    public function backupAdd($page, $record)
    {
        $data = $this->query('SELECT * FROM ' . $page . ' WHERE id="' . $record . '"', true);

        $this->postJsonModel('cms_backup', [
            'data' => json_encode($data),
            'session' => @intval($_SESSION['login_session_id']),
            'page' => $page,
            'record' => $record
        ]);
    }

    /**
     * Restores data from backup
     * @param int $id
     * @param boolean backup_current
     * @return boolean
     */

    public function backupRestore($id, $backup_current = true)
    {
        $backup = $this->getJsonModel('cms_backup', ['id' => $id], true);
        if ($backup && $backup['data']) {
            if ($backup_current)
                $this->backupAdd($backup['page'], $backup['record']);
            $data = $backup['data'];
            foreach ($data as $k => $v)
                $data[$k] = '`' . $k . '`="' . $this->apporm->sqlSafe($v) . '"';
            $query = 'UPDATE ' . $backup['page'] . ' SET ' . implode(', ', $data) . ' WHERE id=' . $backup['record'];
            $result = $this->queryOut($query);
            return $result;
        }
    }

    /**
     * Sets/unsets media backup
     * @param boolean $q
     * @return null
     */

    public function backupMediaSet($q)
    {
        $this->is_backup_media = $q;
    }

    public function backupSet($q)
    {
        $this->is_backup = $q;
    }
    public function is_backup()
    {
        return $this->is_backup;
    }

    /**
     * Adds media backup record
     * @param string $file
     * @param array $record
     * @return mixed
     */

    public function backup_media_add($file, $record = [])
    {
        if ($this->is_backup_media && $file && file_exists($file)) {
            $filename = explode('.', $file);
            $uid = uniqid();
            $extension = '';
            if (count($filename) == 1)
                $filename = $uid;
            else {
                $extension = array_pop($filename);
                $filename = $uid . '.' . $extension;
            }

            $backup_folder = $_SERVER['DOCUMENT_ROOT'] . '/public/upload/cms_backup';
            if (!file_exists($backup_folder))
                mkdir($backup_folder);

            $new = $backup_folder . '/' . $filename;

            $f = ['path' => $file, 'checksum' => md5_file($file)];
            if (isset($record['model']))
                $f['model'] = $record['model'];
            if (isset($record['record']))
                $f['record'] = $record['record'];
            if (isset($record['field']))
                $f['field'] = $record['field'];

            $exists = $this->getJsonModel('cms_backup_media', $f, true, 'date DESC');

            if ($exists) {
                $exists = $this->putJsonModel('cms_backup_media', ['date' => date('Y-m-d H:i:s')], ['id' => $exists['id']]);
            } elseif (!$exists && @copy($file, $new) && file_exists($new)) {
                $checksum = md5_file($new);
                $r = $this->postJsonModel(
                    'cms_backup_media',
                    [
                        'path' => $file,
                        'extension' => $extension,
                        'uid' => $uid,
                        'checksum' => $checksum,
                        'session' => intval($_SESSION['login_session_id']),
                        'page' => @$record['page'],
                        'model' => @$record['model'],
                        'record' => @$record['record'],
                        'field' => @$record['field']
                    ]
                );
                if (!$r)
                    exit($this->orm->getLastError());
            }
        }
    }

    /**
     * Restores media from backup
     * @param string $from
     * @param string $to
     * @param array $record
     * @return boolean
     */

    public function backup_media_copy($from, $to, $record = [])
    {
        if ($this->is_backup_media)
            $this->backup_media_add($to, $record);
        return $this->uploadCopy($from, $to);
    }

    /**
     * Copy function extened by S3 support
     * @param string $source
     * @param string $to
     * @return boolean
     */

    public function uploadCopy($source, $dest, $download = false)
    {
        if ($this->s3) {
            $unlink = false;
            $middle = 'i.vimeocdn.com';
            if (strpos($source, $middle) || substr($source, 0, 4) == 'http') {
                $temp = $this->temp_folder . '/' . uniqid();
                copy($source, $temp);
                $source = $temp;
                $unlink = true;
            }
            $result = $this->s3->copy($source, $dest, $download);
            if ($unlink) unlink($source);
            return $result;
        } else
            return copy($source, $dest);
    }

    /**
     * Restore media from backup
     * @param int $id
     * @param boolean $backup_current
     * @param array $record
     * @return array
     */

    public function backupMediaRestore($id, $backup_current = true, $record = [])
    {

        $backup = $this->getJsonModel('cms_backup_media', ['id' => $id], true);
        $message = '';
        $result = false;

        if ($backup) {
            if ($backup_current) {
                $this->backup_media_add($backup['path'], $record);
            }

            $filename = $_SERVER['DOCUMENT_ROOT'] . '/public/upload/cms_backup/' . $backup['uid'];
            if ($backup['extension'])
                $filename .= '.' . $backup['extension'];
            $result = copy($filename, $backup['path']);

            if ($result && $backup['model'] && $backup['record'] && $backup['field']) {
                // get full schema
                $schema = $this->apporm->getJsonModelSchemaWithPageUpdate($backup['model']);
                // find a field
                $field_schema = _uho_fx::array_filter($schema['fields'], 'field', $backup['field'], ['first' => true]);
                if ($field_schema) {
                    //$data=$this->apporm->getJsonModel($backup['model'],['id'=>$backup['record']],true,null,null,['skipSchemaFilters'=>true,'page_update'=>true]);
                    $data = $this->apporm->getJsonModel($schema, ['id' => $backup['record']], true, null, null, ['skipSchemaFilters' => true]);
                } else
                    $data = null;

                if ($data) {
                    $this->imageResize($field_schema, $data);
                    $message = 'resized';
                }
            }


            return ['result' => $result, 'message' => $message];
        }
    }

    /**
     * Check if TinyMCE is availabel
     * @return boolean
     */
    /*

    /**
     * Sets new AppOrm instance
     * @param object $orm
     */
    public function setAppOrm($orm)
    {
        $this->apporm = $orm;
    }

    /**
     * Gets JSON for schema page
     */
    public function schemaGetJson($model, $json_file, $help, $fields)
    {
        $json_filename = array_pop(explode('/', $json_file));
        $json = @file_get_contents($_SERVER['DOCUMENT_ROOT'] . $this->cfg_path . $json_file);
        if ($json)
            $json = json_decode($json, true);
        if (!$json)
            return false;

        $schema = [
            'label' => '<pre>' . $json_filename . '<pre>',
            'help' => ['label' => $help],
            'fields' => $fields
        ];

        foreach ($json as $k => $v) {
            $json[$k] =
                [
                    'url_edit' => 'schema-edit/' . $model . '/' . $v['id'],
                    'values' => $v
                ];
        }

        $buttons = [
            //['label'=>'Add',"type"=>"page","icon"=>"add","url"=>"schema-edit/"]
        ];

        return ['schema' => $schema, 'filename' => $json_filename, 'records' => $json, 'buttons' => $buttons];
    }

    /**
     * Gets JSON for schema edit
     */

    private function schemaGetAllModels()
    {
        $files = scandir($_SERVER['DOCUMENT_ROOT'] . $this->cfg_path . '/structure');
        foreach ($files as $k => $v)
            if (!strpos($v, '.json') || $v == 'model_tree.json')
                unset($files[$k]);
            else {
                $v = str_replace('.json', '', $v);
                $files[$k] = ['value' => $v, 'label' => $v];
            }

        return array_values($files);
    }

    public function schemaGetJsonEdit($model, $record, $json_file, $fields)
    {
        $json_filename = array_pop(explode('/', $json_file));
        $json = @file_get_contents($_SERVER['DOCUMENT_ROOT'] . $this->cfg_path . $json_file);
        if ($json)
            $json = json_decode($json, true);
        if (!$json)
            return false;
        $url_back = "schema-page/" . $model;
        foreach ($fields as $k => $v)
            $fields[$k]['cms_field'] = $v['field'];
        $schema = [
            'label' => '<pre>' . $json_filename . '<pre>',
            'help' => ['label' => $help],
            'fields' => $fields,
            'url_back' => 'dupa',
            'url_back_form' => $url_back,
            'url_write' => "schema-write/" . $model . '/' . $record
        ];

        if (is_array($record))
            $record = _uho_fx::array_filter($json, $record[0], $record[1], ['first' => true]);
        else
            exit('unsupported');

        foreach ($schema['fields'] as $k => $v) {
            switch ($v['type']) {
                case "checkboxes":
                    if ($v['source'] == '#models') {
                        $schema['fields'][$k]['options'] = $this->schemaGetAllModels();
                        $record[$v['field']] = array_flip($record['models']);
                        foreach ($record[$v['field']] as $k2 => $v2)
                            $record[$v['field']][$k2] = 1;
                    }
                    break;
            }
        }

        $schema['buttons_edit'] = [
            ['label' => 'Back', "type" => "page", "icon" => "keyboard-backspace", "url" => $url_back]
        ];

        return ['schema' => $schema, 'filename' => $json_filename, 'record' => $record, 'buttons' => $buttons];
    }

    /**
     * Deletes temporary files in a directory based on given extensions.
     *
     * @param string $dir       Relative path to the directory (e.g., 'cache', 'temp/upload')
     * @param array  $extension List of allowed file extensions to delete
     * @return int              Number of deleted files
     */
    private function tempKill(string $dir = 'cache', array $extension = ['cache']): int
    {
        $deleted = 0;
        if ($dir) {
            $dirPath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . trim($dir, '/');
            $files = @scandir($dirPath);

            if ($files) {
                foreach ($files as $file) {
                    $pathInfo = pathinfo($file);
                    if (
                        $file === '.' || $file === '..' ||
                        empty($pathInfo['extension']) ||
                        !in_array($pathInfo['extension'], $extension, true)
                    ) {
                        continue;
                    }

                    if (@unlink($dirPath . DIRECTORY_SEPARATOR . $file)) {
                        $deleted++;
                    }
                }
            }
        }
        return $deleted;
    }

    /**
     * Removes temporary files from known upload/temp folders.
     */
    public function removeTempFiles(): void
    {
        $this->tempKill('serdelia/temp', ['jpg', 'png', 'gif', 'jpeg']);
        $this->tempKill('serdelia/temp/upload', ['jpg', 'png', 'gif', 'jpeg', 'mp4', 'mp3', 'pdf', 'doc', 'docx']);
        $this->tempKill('serdelia/temp/upload/thumbnail', ['jpg', 'png', 'gif', 'jpeg']);
    }

    /**
     * Sets internal flag for whether current user is a schema editor.
     */
    public function setSerdeliaSchemaEditor(): void
    {
        $user = $this->getUser();
        $this->serdelia_schema_editor = !empty($user['edit_all']);
    }

    /**
     * Checks if the current user has admin privileges.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        $user = $this->getUser();
        return !empty($user['edit_all']);
    }

    /**
     * Extends file_exists function to support S3.
     *
     * @param string $filePath
     * @return bool
     */

    public function file_exists(string $filePath): bool
    {
        return $this->s3 ? $this->s3->file_exists($filePath) : file_exists($filePath);
    }

    /**
     * Generates a temporary filename, optionally with an extension.
     *
     * @param string|null $ext
     * @return string
     */
    private function s3GetTempFilename(?string $ext = null): string
    {
        $file = $this->cms_folder . '/temp/' . uniqid();
        if ($ext) $file .= '.' . $ext;
        return str_replace('//', '/', $file);
    }

    /**
     * Converts a .jpg/.png/.gif filename to .webp.
     *
     * @param string $filename
     * @param array  $exts Allowed source extensions
     * @return string|null
     */

    private function jpg2webp(string $filename, array $exts = ['jpg', 'gif', 'png']): ?string
    {
        $parts = explode('.', $filename);
        $ext = array_pop($parts);

        if (in_array(strtolower($ext), $exts, true) && $parts) {
            return implode('.', $parts) . '.webp';
        }

        return null;
    }

    /*
        Set visual theme
    */
    public function setMode($default)
    {
        if (isset($_COOKIE['serdelia_mode'])) $this->mode = $_COOKIE['serdelia_mode'];
        else $this->mode = $default;
    }

    /*
        Get visual theme mode, store it if not set
    */
    public function getMode($default)
    {
        if (!$this->mode) $this->mode = _uho_fx::getGet('mode');
        if (!$this->mode && isset($_SESSION['serdelia_mode'])) $this->mode = $_SESSION['serdelia_mode'];
        if (!$this->mode && isset($_COOKIE['serdelia_mode'])) $this->mode = $_COOKIE['serdelia_mode'];
        if (!$this->mode && $default) $this->mode = $default;
        if (!$this->mode) $this->mode = 'light';
        $_SESSION['serdelia_mode'] = $this->mode;
        setcookie('serdelia_mode', $this->mode, time() + time() + 60 * 60 * 24 * 30, "/", $_SERVER['HTTP_HOST']);
        return $this->mode;
    }

    private function halt($message)
    {
        exit('<pre>' . $message . '</pre>');
    }

    /*
        Validate all models if they match ORM schema
    */

    public function validateSchema($schema, $name = null)
    {

        if (!$schema || !is_array($schema)) {
            if (!$name) $name = 'unknown';
            $this->halt('No schema found for model: ' . $name);
        }

        if (empty($schema['table'])) $this->halt('No table defined for model: ' . $name);
        if (empty($schema['fields'])) $this->halt('No fields array defined for model: ' . $name);

        $types = [
            'string',
            'text',
            'order',
            'boolean',
            'date',
            'datetime',
            'integer',
            'float',
            'image',
            'select',
            'uid',
            'html',
            'elements',
            'checkboxes',
            'json',
            'file',
            'table',
            'media',
            'plugin'
        ];

        foreach ($schema['fields'] as $k => $v) {
            $type = isset($v['type']) ? $v['type'] : "string";
            $field = isset($v['field']) ? $v['field'] : null;

            if (!in_array($type, $types)) $this->halt('Unknown type for item# ' . ($k + 1) . ': ' . $type);
            if ($type != 'plugin' && !$field) $this->halt('No field specified for item# ' . ($k + 1));

            switch ($type) {
                case "image":
                    if (empty($v['folder'])) $this->halt('No .folder specified image type field: ' . $field);
                    if (empty($v['images']) || !is_array($v['images'])) $this->halt('No .images array for image type field: ' . $field);

                    break;
            }
        }
    }

    /*
        Create schems for all models if they don't exist
        Usually done on the first run of the application
    */

    public function createSchemas()
    {
        if (empty($_SESSION['schemas_checked'])) $_SESSION['schemas_checked']=0;
        if ($_SESSION['schemas_checked']<3)
        {
            $tables = [
                'cms_users',
                'cms_users_logs',
                'cms_users_logs_logins',
                'cms_backup',
                'cms_backup_media',
                'cms_settings'
            ];

            foreach ($tables as $k => $v) {
                $schema = $this->getSchema($v, false);
                $this->apporm->creator($schema, ['create' => "auto"]);
            }

            $_SESSION['schemas_checked']++;
        }
    }
}
