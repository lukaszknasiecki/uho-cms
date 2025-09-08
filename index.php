<?php

require_once __DIR__ . '/vendor/autoload.php';

use Huncwot\UhoFramework\_uho_application;
use Huncwot\UhoFramework\_uho_fx;

class cms_sunship
{

    private function getAvailableProjects()
    {

        /*
            Default URL prefix, i.e. https://mysite.com/cms        
            Default Project folder i.e. /cms_config
            Default Project language i.e. en
            Default Theme, bright|dark
            Default Debug false
        */

        $cfg_src = $_SERVER['DOCUMENT_ROOT'] . '/sunship-cms.json';
        if (file_exists($cfg_src)) {
            $cfg = file_get_contents($cfg_src);
            if ($cfg) $cfg = json_decode($cfg, true);
            if (!$cfg) return;
        }

        $instances = !empty($cfg['CMS_CONFIG_FOLDERS']) ? explode(',', $cfg['CMS_CONFIG_FOLDERS']) : ["cms_config"];
        $lang = !empty($cfg['CMS_CONFIG_LANG']) ? $cfg['CMS_CONFIG_LANG'] : "en";

        $cms_prefix = !empty($cfg['CMS_CONFIG_PREFIX']) ? $cfg['CMS_CONFIG_PREFIX'] : "cms";
        if (substr($cms_prefix,0,4)=='ENV.')
        {
            $cms_prefix=getenv(substr($cms_prefix,4));
            $cms_prefix = !empty($cms_prefix) ? $cms_prefix : "cms";
        }
        
        $debug = !empty($cfg['CMS_CONFIG_DEBUG']) ? $cfg['CMS_CONFIG_DEBUG'] : false;
        $theme = !empty($cfg['CMS_CONFIG_THEME']) ? $cfg['CMS_CONFIG_THEME'] : "light";

        if ($instances) {
            foreach ($instances as $k => $v) {
                $instances[$k] = [
                    'folder' => $v,
                    'folder_logs' => $v . '-logs',
                    'folder_temp' => $v . '-temp'
                ];
            }

            $cfg =
                [
                    'languages' => [$lang],
                    'languages_url' => false,
                    'application_url_prefix' => $cms_prefix,
                    'debug' => $debug,
                    'mode' => $theme,
                    'projects' => $instances
                ];
        } else $cfg = null;
        return $cfg;
    }

    /*
        Create necessary folders
    */

    private function createFolders($root, $cfg_project)
    {
        
        if (!empty($cfg_project['folder_logs']) && !is_dir($root . $cfg_project['folder_logs'])) {
            mkdir($root . $cfg_project['folder_logs']);
            file_put_contents($root . $cfg_project['folder_logs'] . '/.htaccess', 'Deny: all');
        }

        if (!empty($cfg_project['folder_temp']) && !is_dir($root . $cfg_project['folder_temp']))
        {
            mkdir($root . $cfg_project['folder_temp']);
            mkdir($root . $cfg_project['folder_temp'] . '/upload');
            mkdir($root . $cfg_project['folder_temp'] . '/upload/thumbnail');
            $htaccess = 'ForceType application/octet-stream
            Header set Content-Disposition attachment
            <FilesMatch "(?i)\.(gif|jpe?g|png)$">
                ForceType none
                Header unset Content-Disposition
            </FilesMatch>
            Header set X-Content-Type-Options nosniff';

            file_put_contents($root . $cfg_project['folder_temp'] . '/.htaccess', $htaccess);
            file_put_contents($root . $cfg_project['folder_temp'] . '/upload/.htaccess', $htaccess);
            file_put_contents($root . $cfg_project['folder_temp'] . '/upload/thumbnail/.htaccess', $htaccess);
        }

    }

    public function start()
    {

        header('X-Frame-Options: SAMEORIGIN');
        date_default_timezone_set('Europe/Berlin');
        ini_set("session.cookie_httponly", 1);
        session_start();

        /*
            Get available projects (if not logged in)
        */

        $cfg_root = $this->getAvailableProjects();

        if (empty($cfg_root)) {
            header('Content-Type: text/plain; charset=utf-8');
            echo ("503 Service Unavailable: Application configuration error - no projects found.");
            exit();
        }

        putenv("CMS_CONFIG_PREFIX=" . $cfg_root['application_url_prefix']);

        /*
            Primary variables
        */

        $index = [            
            'development' => isset($cfg_root['debug']) && $cfg_root['debug'] == 'true',
            'root_path' => __DIR__ . '/',
            'root_doc' => rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/',
            'cache_salt' => 'fya'
        ];

        /*
            If already logged in or at least project selected
        */

        $blank = true;
        $cfg_folder = null;
        $cfg_project = null;

        if (isset($_SESSION['serdelia_project']) || (isset($_POST['login_login']) && isset($_POST['project'])))
        {
            
            $blank = false;

            // get current project array index
            if (!empty($_SESSION['serdelia_project'])) $project = $_SESSION['serdelia_project'] - 1;
            else $project = intval($_POST['project']) - 1;

            // check if project per domain defined
            if (!empty($cfg_root['projects'][$project]['domains']))
            {
                if (!in_array($_SERVER['HTTP_HOST'], $cfg_root['projects'][$project]['domains'])) {
                    $blank = true;
                }
            }

            if (!$blank)
            {
                if ($cfg_root['projects'][$project])
                {
                    $cfg_project = $cfg_root['projects'][$project];
                    $cfg_folder = $_SERVER['DOCUMENT_ROOT'] . '/' . $cfg_root['projects'][$project]['folder'];
                    $cfg_file = [
                        'main' => $cfg_folder,
                        'pre' => [__DIR__ . '/configs']
                    ];
                    $_SESSION['possible_serdelia_project'] = $project + 1;
                }
            }
        }

        /*
            Create required folders
        */

        $root = $_SERVER['DOCUMENT_ROOT'] . '/';
        $this->createFolders($root, $cfg_project);


        /*
            Set error handling
        */

        $cfg_project['folder_logs'] = isset($cfg_project['folder_logs']) ? $cfg_project['folder_logs'] : $cfg_project['folder_logs'] = '';

        if ($index['development']) {
            define("debug", true);
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);
            $s = sprintf('%s/php-errors-%s.txt', $root . $cfg_project['folder_logs'], date('Ymd'));
            define('folder_logs', $root . $cfg_project['folder_logs']);
            ini_set('error_log', $s);
            ini_set('error_reporting', E_ALL ^ E_NOTICE ^ E_WARNING);
        } else {
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
            $s = sprintf('%s/php-errors-%s.txt', $root . $cfg_project['folder_logs'], date('Ymd'));
            ini_set('error_log', $s);
            ini_set('error_reporting', E_ALL ^ E_NOTICE ^ E_WARNING);
            define('folder_logs', $root . $cfg_project['folder_logs']);
        }


        /*
            Not logged in
        */

        if ($blank) {
            unset($_SESSION['serdelia_project']);
            unset($_SESSION['possible_serdelia_project']);

            $cfg_file = [
                'application_title' => 'app',
                'application_class' => 'app',
                'nosql' => true,
                'application_languages' => $cfg_root['languages'],
                'application_languages_url' => ((count($cfg_root['languages']) > 1) || @$cfg_root['languages_url'] ? true : false),
                'application_url_prefix' => $cfg_root['application_url_prefix']
            ];
        }

        /*
            Build application
        */

        try {
            $app = new _uho_application($index['root_path'], $index['development'], $cfg_file);
        } catch (Exception $e) {
            header('Content-Type: text/plain; charset=utf-8');
            echo ("503 Service Unavailable: Uho framework not found.");
            exit();
        }

        /*
            Output
        */

        if (_uho_fx::getGet('output')) $type = _uho_fx::getGet('output');

        return $app->getOutput($type);
    }
}

$app = new cms_sunship();
$result = $app->start();

$output = $result['output'];
$header = $result['header'];

switch ($header) {
    case "json":
        header('Content-Type: application/json');
        break;
    case "404":
        header("HTTP/1.0 404 Not Found");
        break;
}

header_remove('Server');
header_remove('X-Powered-By');

echo ($output);
