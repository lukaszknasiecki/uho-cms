<?php

use Huncwot\UhoFramework\_uho_controller;
use Huncwot\UhoFramework\_uho_fx;

require_once("controller_app_clients.php");

/**
 * Main controller class of the application, extending base _uho_controller.
 */
class controller_app extends _uho_controller
{
    public $clients;
    private $js_array = [];

    /**
     * Loads and updates data from the model and prepares data for the view.
     *
     * @return void
     */
    public function getData() : void
    {        
        // Set CMS base path
        $this->model->cms_path = $this->route->getUrl('');
        if (!empty($this->cfg['plugins'])) $this->model->setPluginsCfg($this->cfg['plugins']);        
        if (isset($this->cfg['cms']['debug']) && $this->cfg['cms']['debug']==1) $this->model->setDebugMode(true);
        if (isset($this->cfg['cms']['strict_schema']) && $this->cfg['cms']['strict_schema']==1) $this->model->setStrictSchema(true);

        // Load additional CMS settings if using non-NoSQL clients
        if (!$this->model->clients->isNoSql()) {
            $cfg_add = $this->model->get('cms_settings');
        } else {
            $cfg_add = [];
        }

        // Apply additional config settings if present
        if ($cfg_add) {
            $c = [];
            foreach ($cfg_add as $v) {
                $c[$v['slug']] = $v['value'];
            }
            if (isset($c['clients_password_required'])) {
                $this->cfg['clients']['password_required'] = $c['clients_password_required'];
            }
            if (isset($c['clients_password_expired'])) {
                $this->cfg['clients']['password_expired'] = $c['clients_password_expired'];
            }
            if (isset($c['clients_login_error_max'])) {
                $this->cfg['clients']['login_error_max'] = $c['clients_login_error_max'];
            }
            if (isset($c['session_time'])) {
                $this->cfg['cms']['serdelia_logout_time'] = $c['session_time'];
            }
        }

        // Backup and caching configurations
        if (isset($this->cfg['cms,slia']['backup_media'])) {
            $this->model->backupMediaSet(true);
        }
        if (isset($this->cfg['cms']['backup'])) {
            $this->model->backupSet($this->cfg['cms']['backup']);
        } else {
            $this->model->backupSet(true);
        }

        if (!empty($this->cfg['cms']['serdelia_cache_kill'])) {
            $caches = [$this->cfg['cms']['serdelia_cache_kill']];
            if (!empty($this->cfg['cache_kill'])) {
                $caches = array_merge($caches, $this->cfg['cache_kill']);
            }
            $this->model->setCacheKills($caches);
        }

        if (!empty($this->cfg['cms']['serdelia_cache_kill_plugin'])) {
            $this->model->setCacheKillPlugin($this->cfg['cms']['serdelia_cache_kill_plugin']);
        }

        if (!empty($this->cfg['cms']['keys'])) {
            $this->model->setAppOrmKeys($this->cfg['cms']['keys']);            
        }
        if (!empty($this->cfg['cms']['serdelia_keys'])) {
            $this->model->setSerdeliaOrmKeys($this->cfg['cms']['serdelia_keys']);            
        }

        if (!empty($this->cfg['api_keys'])) {
            $this->model->setApiKeys($this->cfg['api_keys']);
        }

        if (!empty($this->cfg['plugins'])) {
            $this->model->setPluginsKeys($this->cfg['plugins']);
        }

        // Default editor type is CKEDITOR5
        if (empty($this->cfg['cms']['wysiwyg'])) {
            $this->cfg['cms']['wysiwyg'] = ['type' => 'ckeditor5'];
        }

        // Check authentication for all actions except login/logout/create
        $action = $this->route->e(0);
        if (!in_array($action, ['login', 'logout', 'create'])) {
            $auth = $this->model->checkAuth($this->route->e(1));
            if (!$auth) exit('auth-needed=' . $auth);
        }

        // Set application language(s)
        if (isset($this->cfg['cms']['app_languages'])) {
            $this->model->setAppNowLangs($this->cfg['cms']['app_languages']);
        } else {
            $this->model->setAppNowLangs($this->cfg['application_languages']);
        }

        // Set upload folder path
        $this->model->setUploadFolder($this->cfg['temp_folder'] . '/upload/');

        // Load menu if client is logged in
        if ($this->model->clients->isLogged()) {
            $this->model->loadMenu();
        }

        // Enable Serdelia schema editor if configured
        if (!empty($this->cfg['serdelia_schema_editor'])) {
            $this->model->setSerdeliaSchemaEditor();
        }

        // Configure WYSIWYG editor settings
        /*
        switch ($this->cfg['cms']['wysiwyg']['type']) {
            case "ckeditor5":
                
                $this->cfg['cms']['wysiwyg']['configs'] = $this->model->ckeditor_configs;
                
                break;
        }*/
        $this->model->setWysiwyg($this->cfg['cms']['wysiwyg']);

        if (empty($this->cfg['cms']['serdelia_logout_time']) && $this->cfg['cms']['serdelia_logout_time']!==0)
            $this->cfg['cms']['serdelia_logout_time']=60;
        if (empty($this->cfg['cms']['serdelia_activity_time']) && $this->cfg['cms']['serdelia_activity_time']!==0)
            $this->cfg['cms']['serdelia_activity_time']=15;

        /*
            Logout if time TOTAL time have passed
        */
        if (isset($this->cfg['cms']['serdelia_logout_time']))
        {
            $this->model->setLogoutTime($this->cfg['cms']['serdelia_logout_time']);
            if (!$this->model->checkLogoutTime())
            {
                $uri=$this->route->getUrl('logout?action=max_time_expired');
                header('Location: '.$uri);
                exit();
            }
        }
        /*
            Logout if time ACTIVITY time have passed
        */
        if (isset($this->cfg['cms']['serdelia_activity_time']))
        {
            $this->model->setActivityTime($this->cfg['cms']['serdelia_activity_time']);
            if (!$this->model->checkActivityTime())
            {
                $uri=$this->route->getUrl('logout?action=activity_time');
                header('Location: '.$uri);
                exit();
            }
        }
        
        // Fetch content data for current controller/action
        $this->data = $this->getContentData();

        // Enable lightbox mode if requested
        if (!empty($this->get['mode']) && $this->get['mode'] === 'lightbox') {
            $this->data['lightbox'] = true;
        }

        // General page data
        $this->data['version'] = date('Y-m-d');
        $this->data['development'] = false;
        $this->data['head'] = $this->model->headGet();
        $this->data['head']['http'] = $this->route->getDomain();
        $this->data['serdelia_path'] = rtrim($this->model->cms_path, '/');
        $this->data['translate'] = $this->model->getTranslate();

        // Scaffold setup (page layout, CSS, menus, etc.)
        $this->data['scaffold'] = [];
        $this->data['scaffold']['logged'] = $this->model->clients->isLogged();
        $this->data['scaffold']['title'] = $this->cfg['cms']['title'] . ' Admin';
        $this->data['scaffold']['cfg'] = $this->cfg['cms'];
        $this->data['scaffold']['cfg']['cfg_path'] = $this->model->cfg_path;
        $this->data['scaffold']['css'] = $this->model->cfg_path . '/assets/styles.css';
        if (!_uho_fx::file_exists($this->data['scaffold']['css'])) {
            unset($this->data['scaffold']['css']);
        }
        $this->data['scaffold']['popup'] = 1;

        // Mode (light/dark/etc.)
        $this->cfg['cms']['mode'] = $this->data['scaffold']['mode'] = $this->model->getMode($this->cfg['cms']['mode']);
        

        // Navigation and menu
        $this->data['scaffold']['nav'] = $this->data['nav'];
        if ($this->data['scaffold']['nav'] && $this->model->checkUserMenu()) {
            $this->data['scaffold']['menu'] = $this->model->app_menu;
        }

        if (!empty($this->data['lightbox'])) {
            $this->data['scaffold']['nav'] = false;
            $this->data['scaffold']['menu'] = false;
        }

        // Add CKEditor5 specific assets if configured
        if ($this->cfg['cms']['wysiwyg']['type'] === 'ckeditor5')
        {
            $srcCss = $this->model->cfg_path . '/ckeditor5/config.css';
            if (_uho_fx::file_exists($srcCss))
                $this->data['scaffold']['cfg']['wysiwyg']['css'] = $srcCss;
            
            $srcJs = $this->model->cfg_path . '/ckeditor5/config.js';
            if (_uho_fx::file_exists($srcJs))
            {
                $this->data['scaffold']['cfg']['wysiwyg']['js'] = $srcJs;
            }
            
        }

        // Control rendering root HTML
        $this->view->setRenderHtmlRoot(!$this->data['fullscreen'] && !$this->route->isAjax());

        // Search URL and logout URL
        if ($this->model->url_search) {
            $this->data['scaffold']['url_search'] = $this->model->url_search;
        }
            
        $this->data['scaffold']['url_logout'] = ['type' => 'logout'];


        // Handle logout expiration time
        
        if (strpos($_SERVER['HTTP_HOST'], '.lhh') !== false)
        {
            $this->data['scaffold']['logout_expired'] = null;
        }
        elseif (!empty($this->cfg['cms']['serdelia_activity_time']))
        {
            $this->data['scaffold']['logout_expired'] = $this->cfg['cms']['serdelia_activity_time']-2;
        }

        /*
            if max logout time is sooner than activity time
        */
        if ($this->cfg['cms']['serdelia_logout_time'])
        {
            $time=$this->model->getLeftLogoutTime($this->cfg['cms']['serdelia_logout_time']);
            if ($time>0)
            {
                $time=$time-2;
                if ($time<1) $time=1;
            }
            if ($time>0 && $time<$this->data['scaffold']['logout_expired'])
                    $this->data['scaffold']['logout_expired']=$time;

        }

        // Various URLs for scaffold
        $this->data['scaffold']['url_logout_expired'] = ['type' => 'logout_expired'];
        $this->data['scaffold']['url_settings'] = ['type' => 'settings'];
        $this->data['scaffold']['url_mode_dark'] = ['type' => 'url_now', 'get' => ['mode' => 'dark']];
        $this->data['scaffold']['url_mode_light'] = ['type' => 'url_now', 'get' => ['mode' => 'light']];
        $this->data['scaffold']['url_password_change'] = ['type' => 'password_change'];
        $this->data['scaffold']['js_array'] = $this->js_array;

        $this->data['url_base'] = '/';

        // Set 404 output type if applicable
        if ($this->model->is404) {
            $this->outputType = '404';
        }

        // Prepare language selection data
        $lang = [];
        if (!empty($this->cfg['application_languages'])) {
            foreach ($this->cfg['application_languages'] as $k => $v) {
                $lang[] = [
                    'label'  => strtoupper($v),
                    'url'    => ['type' => 'url_now', 'lang' => $v, 'setlang' => true],
                    'active' => ($this->model->lang === $v)
                ];
            }
        }
        $this->data['langs'] = $lang;

        // Update URLs with routing info        
        $this->data = $this->urlUpdate($this->data);

        $this->data['head']['url'] = rtrim($this->route->getUrlNow(), '/');
    }

    /**
     * Update URL fields based on routing settings.
     *
     * @param array $t Data array to update URLs for.
     * @return array Updated data array.
     */
    public function urlUpdate(array $t) : array
    {
        $record=isset($t['content']['record']) ? $t['content']['record'] : null;

        $t=$this->route->updatePaths($t);
        
        if (isset($record)) $t['content']['record'] = $record;
        
        return $t;
    }

    /**
     * Actions to run BEFORE the page is rendered.
     *
     * @param array $post POST parameters.
     * @param array $get GET parameters.
     * @return void
     */
    public function actionBefore(array $post, array $get) : void
    {
        $this->cfg['config_path'] = $this->model->config_path;
        $this->cfg['temp_folder'] = $this->model->temp_folder;
        $this->cfg['temp_path'] = $this->model->temp_path;
        $this->cfg['logs_folder'] = $this->model->logs_folder;
        $this->cfg['logs_path'] = $this->model->logs_path;

        $this->post = $post;
        $this->get = $get;

        if (isset($get['setlang'])) {
            $this->route->setCookieLang($this->model->lang);
        }

        // Instantiate and call actionBefore on clients controller
        $this->clients = new controller_app_clients($this->cfg, $this->model, $this->route);
        $this->clients->actionBefore($post, $get);

        // Early return for no_sql flag
        if ($this->no_sql) return;

        if ($this->route->e(0) === 'login') {
            // $this->model->s3recache();
        }

        $last = $this->route->getUrlNow();

        // Save last URL for navigation except some paths
        if (!strpos($last, '/register') &&
            !strpos($last, '/login-facebook') &&
            !strpos($last, '/login') &&
            !strpos($last, '/logout') &&
            !strpos($last, '/api') &&
            !strpos($last, '404')
            ) {
            $_SESSION['last_url'] = $last;
        }
    }

    /**
     * Add a JavaScript file to the render queue.
     *
     * @param string $js JS source path or URL.
     * @return void
     */
    public function addJs(string $js) : void
    {
        $this->js_array[] = $js;
    }

    /**
     * Return a JSON response and exit immediately.
     *
     * @param mixed $data Data to JSON encode and return.
     * @return never
     */
    public function returnJson($data)
    {
        header('Content-Type: application/json');
        header_remove('Server');
        header_remove('X-Powered-By');
        exit(json_encode($data, JSON_PRETTY_PRINT));
    }
}