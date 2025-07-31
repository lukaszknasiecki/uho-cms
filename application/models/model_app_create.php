<?php

require_once('model_app.php');

use Huncwot\UhoFramework\_uho_fx;

/**
 * Model for creating a new Admin account.
 */
class model_app_create extends model_app
{
    /**
     * Provides data for the admin creation page.
     * Handles form submission and validation.
     *
     * @param array|null $params
     * @return array
     */
    public function getContentData($params = null)
    {
        $cfg = $this->getAvailableProjects();

        if (!$cfg) {
            _uho_fx::halt('model_app_create::serdelia_config not found');
        }

        $created = false;
        $errors = [];

        // Form submission validation
        if (!empty($params['login_login'])) {
            $params['login_password'] = trim(str_replace(' ', '', $params['login_password']));

            // Validate project
            if (
                empty($params['project']) ||
                !isset($cfg['projects'][intval($params['project']) - 1])
            ) {
                $errors[] = 'project-missing';
            }

            // Password validation
            if (strlen($params['login_password']) < 8) {
                $errors[] = 'password-min-8-chars';
            }

            if ($params['login_password'] !== $params['login_password2']) {
                $errors[] = 'password-mismatch';
            }

            // CSRF token check
            if (!$this->clients->client->validateToken($params['token'])) {
                $errors[] = 'invalid-token';
            }

            // Attempt to create account
            if (empty($errors)) {
                if ($this->create($params['login_login'], $params['login_password'])) {
                    $created = true;
                } else {
                    $errors[] = 'system-error-creating';
                }
            }
        }

        // Simple UI translation
        $translate = [
            'pl' => [
                'header' => 'Cześć Nowy!',
                'cta' => 'utwórz konto',
                'your_login' => 'twój login',
                'password' => 'hasło (min. 8 znaków)',
                'password2' => 'powtórz hasło',
            ],
            'en' => [
                'header' => 'Hello Dear!',
                'cta' => 'create account',
                'your_login' => 'your login',
                'password' => 'password (min. 8 chars)',
                'password2' => 'repeat password',
            ]
        ];

        return [
            'created'   => $created,
            'translate' => $translate[$this->lang],
            'token'     => $this->clients->client->getToken(),
            'projects'  => $cfg['projects'],
            'errors'    => $errors,
        ];
    }

    /**
     * Creates an admin user and clears existing logs.
     *
     * @param string $login
     * @param string $password
     * @return bool
     */
    public function create($login, $password)
    {
        // Create necessary DB schemas (CMS tables)
        $this->createSchemas();

        // Create new admin account
        $result = $this->clients->createAdmin($login, $password);

        // Clean up user logs
        $this->sql->queryOut('TRUNCATE TABLE cms_users_logs');
        $this->sql->queryOut('TRUNCATE TABLE cms_users_logs_logins');

        return $result;
    }

    /**
     * Loads available CMS project configurations from disk.
     *
     * @return array|null
     */
    private function getAvailableProjects()
    {
        $cfg_src = $_SERVER['DOCUMENT_ROOT'] . '/sunship-cms.json';

        if (!file_exists($cfg_src)) return null;

        $cfg = json_decode(file_get_contents($cfg_src), true);
        if (!$cfg) return null;

        // Defaults
        $instances   = !empty($cfg['CMS_CONFIG_FOLDERS']) ? explode(',', $cfg['CMS_CONFIG_FOLDERS']) : ["cms_config"];
        $lang        = $cfg['CMS_CONFIG_LANG'] ?? "en";
        $cms_prefix  = $cfg['CMS_CONFIG_PREFIX'] ?? "cms";
        $debug       = $cfg['CMS_CONFIG_DEBUG'] ?? false;
        $theme       = $cfg['CMS_CONFIG_THEME'] ?? "light";

        // Discover project titles
        foreach ($instances as $k => $folder) {
            $name = 'Project #' . ($k + 1);
            $cfg_filename = $_SERVER['DOCUMENT_ROOT'] . '/' . $folder . '/config.php';

            if (file_exists($cfg_filename)) {
                require_once($cfg_filename);
                if (!empty($cfg['cms']['title'])) {
                    $name = $cfg['cms']['title'];
                }
            }

            $instances[$k] = [
                'name'   => $name,
                'folder' => $folder
            ];
        }

        return [
            'languages'             => [$lang],
            'languages_url'         => false,
            'application_url_prefix'=> $cms_prefix,
            'mode'                  => $theme,
            'projects'              => $instances
        ];
    }
}