<?php

require_once("controller_app.php");

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
        if (isset($this->cfg['cms']['debug']) && in_array($this->cfg['cms']['debug'],[1,'true']))
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
    public function actionBefore(array $post, array $get) : void
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

        // Inject the clients handler into the model
        $this->model->setClients($this->clients, $this->cfg['nosql']);

        // Determine the action from the route or POST data
        $action    = $this->route->e(0);
        $subaction = $this->route->e(0); // Possibly a mistake — might need to use e(1)?

        // NoSQL disabled, no user logged in, and no admin created — force initial admin setup
        if (
            !$this->cfg['nosql'] &&
            $action !== 'build' &&
            $action !== 'create' &&
            !$this->clients->isLogged() &&
            !$this->clients->adminExists()
        ) {
            $_SESSION['serdelia_project'] = $_SESSION['possible_serdelia_project'];
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
            $this->route->redirect('login');
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
}
?>