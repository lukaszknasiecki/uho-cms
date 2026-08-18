<?php

require_once("controller_app.php");

use Huncwot\UhoFramework\_uho_fx;

/**
 * Controller class for initializing and managing client (user) authentication
 * and configuration before the main application runs.
 */
class controller_app_clients
{
    public $cfg;
    public $model;
    public $route;
    public $post;
    public $get;
    public $clients;

    /**
     * Constructor to initialize config, model, and routing instances.
     *
     * @param array  $cfg   Global configuration
     * @param object $model Model instance
     * @param object $route Route handler instance
     */
    public function __construct($cfg, $model, $route)
    {
        $this->cfg   = $cfg;
        $this->model = $model;
        $this->route = $route;
        if (isset($this->cfg['cms']['debug']) && in_array($this->cfg['cms']['debug'], [1, 'true']))
            $this->model->setDebugMode(true);
    }

    /**
     * Executes logic before rendering the main application.
     * Sets up clients, manages login flow, and handles access restrictions.
     *
     * @param array $post Incoming POST data
     * @param array $get  Incoming GET data
     * @return void
     */
    public function actionBefore(array $post, array $get): void
    {
        
        $this->post = $post;
        $this->get  = $get;

        // Optional: create database schemas if SQL model is enabled
        if (isset($this->model->sql)) {
            $this->model->createSchemas();
        }

        // Initialize the clients handler
        $this->clients = new model_app_clients();

        // Set up client authentication and encryption options
        $this->clients->set(
            $this->model,
            $this->cfg['clients'],
            $this->cfg['smtp'],
            $this->cfg['fb'],
            $this->cfg['application_domain'],
            $this->cfg['client_salt'],
            $this->model->crypto
        );

        //if ($this->clients->isLogged()) exit('logged'); else exit('not-logged');

        // Inject the clients handler into the model
        $this->model->setClients($this->clients, $this->cfg['nosql']);

        // Determine the action from the route or POST data
        $action    = $this->route->e(0);
        $subaction = $this->route->e(0); // Possibly a mistake — might need to use e(1)?

        // google login
        if ($this->route->e(0) == 'auth-google-callback') {
            
            $projects = $this->model->getAvailableProjects();
            $google_cfg = $projects['projects'][0]['google_oauth'];

            $this->clients->setOAuthConfig(
                'google',
                [
                    'client_id' => $google_cfg['client'],
                    'client_secret' => $google_cfg['secret'],
                    'redirect_uri' => $this->getCmsUri(true).'/auth-google-callback'
                ]
            );

            $r = $this->clients->googleLogin(null, $this->get['code']);

            if ($r['result'])
            {
                $state=_uho_fx::getGet('state');
                $project=$_SESSION['uho_cms_projects_oauth'][$state] ?? null;

                if ($project)
                {
                    $_SESSION['uho_cms_project']=$project;
                    $this->model->setLogoutTime(
                        $this->cfg['cms']['activity_time'],
                        $this->cfg['cms']['logout_time']
                    );

                    // it's pre-login so we need to build uri other way
                    header("Refresh: 1; url=".$this->getCmsUri(false));
                    exit();
                } else
                {
                    exit('Project not found for state: '.$state);
                }
            }
        }

        // NoSQL disabled, no user logged in, and no admin created — force initial admin setup

        if (
            !$this->cfg['nosql'] &&
            $action !== 'build' &&
            $action !== 'create' &&
            !$this->clients->isLogged() &&
            !$this->clients->adminExists()
        ) {
            $_SESSION['uho_cms_project'] = $_SESSION['possible_uho_cms_project'];
            $this->route->redirect('create');
        }


        // Override action from POST if defined
        if (!empty($post['action'])) {
            $action = $post['action'];
        }

        // Require login for all routes except a few
        if (
            !$this->clients->isLogged() &&
            !in_array($action, ['login', 'create', 'build'], true)
        ) {
            if (_uho_fx::isAjax()) {
                header('HTTP/1.1 401 Unauthorized');
                echo json_encode(['login' => true, 'error' => 'You have been logged out. Please log in again.']);
                exit();
            }
            $this->route->redirect('login?source=clients');
        }

        // Force password change if client's password has expired
        if (
            $this->clients->isLogged() &&
            $action !== 'logout' &&
            $subaction !== 'password-change' &&
            isset($this->cfg['clients']['password_expired']) &&
            $this->clients->client->passwordCheckExpired($this->cfg['clients']['password_expired'])
        ) {
            $this->route->redirect('password-change?expired');
        }

        // Optionally clean up temp files (disabled)
        if ($this->clients->isLogged()) {
            // $this->model->removeTempFiles();

            if ($this->cfg['2factor'] && !$this->model->was2factor()) {
                if ($this->route->e(0) !== '2factor' && $this->route->e(0) !== 'logout') {
                    $this->route->redirect('2factor');
                }
            }
        }

        // After successful login, redirect to pre-login route if stored
        if ($this->clients->isLogged() && isset($_SESSION['prelogin_route'])) {
            $url = $_SESSION['prelogin_route'];
            unset($_SESSION['prelogin_route']);
            $this->route->setClosingSlash();
            $this->route->redirect($url);
            exit();
        }
    }

    private function getCmsUri(bool $domain)
    {
        if ($domain)
        {
            if (
                isset($_SERVER['SSL_PROTOCOL'])
                || @$_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' // ec2 ooh
                || @$_SERVER['HTTPS'] == 'on'
                || isset($_SERVER['SSL_TLS_SNI'])
            )
                $http = 'https';
            else $http = 'http';
            $cms_url=$http.'://'.$_SERVER['SERVER_NAME'];
        } else $cms_url='';
        $cms_url.='/'.$this->cfg['application_url_prefix'];
        return $cms_url;
    }
}
