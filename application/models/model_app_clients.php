<?php

use Huncwot\UhoFramework\_uho_client;
use Huncwot\UhoFramework\_uho_mailer;
use Huncwot\UhoFramework\_uho_thumb;

/**
 * Helper class for handling client operations via _uho_client
 */
class model_app_clients
{
    // Core system components
    var $cms;                 // instance of _uho_model
    var $mailer;              // instance of _uho_mailer
    var $no_sql;              // indicates if using noSQL mode
    var $isUrlLang = false;   // whether language is part of CMS URL
    var $domain;              // current domain
    var $cfg;                 // configuration (from _uho_application)
    var $is_logged_user = null; // cache for login status

    // Public accessible properties
    public $crypto;           // legacy crypto config
    public $http;             // current http scheme and host
    public $client;           // instance of _uho_client

    /**
     * Initializes class dependencies and client instance
     */
    public function set($cms, $cfg, $smtp, $fb, $domain, $crypto = null)
    {
        $this->cms = $cms;
        $this->cfg = $cfg;
        $this->mailer = new _uho_mailer(['smtp' => $smtp]);
        $this->http = 'https://' . $_SERVER['HTTP_HOST'];

        // Load additional settings if available
        if (isset($this->cms->sql)) {
            $cfg_add = $this->cms->getJsonModel('cms_settings');
        }
        if (!empty($cfg_add)) {
            foreach ($cfg_add as $v) {
                $c[$v['slug']] = $v['value'];
            }
            $cfg['password_required'] = $c['clients_password_required'] ?? null;
            $cfg['password_expired'] = $c['clients_password_expired'] ?? null;
            $cfg['max_bad_login']     = $c['clients_login_error_max'] ?? null;
            $this->cfg = $cfg;
        }

        // Initialize _uho_client
        $this->client = new _uho_client(
            $this->cms->orm,
            [
                'models' => [
                    'client_model' => 'cms_users',
                    'client_logs_model' => 'cms_users_logs',
                    'client_logins_model' => 'cms_users_logs_logins'
                ],
                'users' => [
                    'bad_login' => 'bad_login',
                ],
                'mailer' => ['smtp' => $smtp],
                'salt' => ['type' => 'double', 'value' => $cfg['password_salt'], 'field' => 'salt'],
                'hash' => @$cfg['password_hash'],
                'settings' => [
                    'password_format' => @$cfg['password_required'],
                    'max_bad_login' => @$cfg['max_bad_login']
                ],
            ],
            $cms->lang
        );

        // Configure client
        $this->client->setModel($cms);
        $this->client->setKeys($this->cms->getKeys());
        $this->client->setFields([
            'email' => '#email',
            'password' => 'password',
            'login' => 'login',
            'uid' => 'uid',
            'other' => ['name', 'surname', 'newsletter', 'facebook_id', 'text_PL', 'text_EN', 'face'],
            'status' => 'status',
            'locked' => 'locked'
        ]);

        // Log client activity
        if ($this->client->getClientId()) {
            $this->cms->putJsonModel('cms_users_logs', [
                'user' => $this->client->getClientId(),
                'session' => intval($_SESSION['login_session_id']),
                'datetime' => date('Y-m-d H:i:s'),
                'action' => 'activity'
            ], [
                'action' => 'activity',
                'session' => intval($_SESSION['login_session_id'])
            ]);
        }

        $this->domain = $domain;
        $this->crypto = $crypto;
    }

    // ----- Utility Setters/Getters -----

    public function setNoSql() {
        $this->no_sql = true;
    }

    public function isNoSql() {
        return $this->no_sql;
    }

    public function isLogged($reload = false) {
        if (!$this->no_sql && !isset($this->is_logged_user)) {
            $this->is_logged_user = $this->client->isLogged();
        }
        return $this->is_logged_user;
    }

    public function logout() {
        return $this->client->logout();
    }

    public function login($login, $pass) {
        return $this->client->login($login, $pass);
    }

    public function loginCookie() {
        return $this->client->cookieLogin();
    }

    public function facebookLogin($token = null) {
        return $this->client->loginFacebook($token);
    }

    public function createAdmin($login, $pass) {
        return $this->client->createAdmin($login, $pass);
    }

    public function anyUserExists() {
        return $this->client->anyUserExists();
    }

    public function adminExists() {
        return $this->client->adminExists();
    }

    public function validatePasswordFormat($pass) {
        return $this->client->passwordValidateFormat($pass);
    }

    public function passwordSet($user, $pass) {
        if (is_numeric($user) && $user > 0 && $this->validatePasswordFormat($pass)) {
            return $this->client->passwordChange($pass, $user);
        }
    }

    public function passwordReminder($email) {
        $result = $this->cms->getJsonModel('users', ['email' => strtolower($email)], true);

        if ($result) {
            if (!$result['email_key']) {
                $result['email_key'] = md5(uniqid());
                $this->cms->queryOut('UPDATE users SET email_key="' . $result['email_key'] . '" WHERE id=' . $result['id']);
            }

            $this->users_mailing('password_remind', $email, [
                'http' => $this->http . '/' . $this->cms->lang . '/login-reset?key=' . $result['email_key'],
            ]);
        }

        return ['result' => true];
    }

    public function passwordGenerate() {
        return $this->client->passwordGenerate();
    }

    public function passwordChange($key, $pass, $pass_old = null) {
        if ($key && $pass) return $this->client->passwordChange($key, $pass);
        elseif ($pass && $pass_old) return $this->client->passwordChangeByOldPassword($pass_old, $pass);
    }

    public function checkEmailKey($key) {
        return $this->client->checkEmailKey($key);
    }

    public function register($email, $password, $newsletter, $name = null, $surname = null, $image = null, $face = null) {
        $uid = uniqid();
        $r = $this->client->register([
            'email' => $email,
            'password' => $password,
            'newsletter' => $newsletter,
            'uid' => $uid,
            'name' => $name,
            'surname' => $surname,
            'face' => $face
        ], '');

        if ($image) {
            $image = urldecode($image);
            $source_filename = basename($image);
            $filename = $uid . '.jpg';
            $folder = '/public/upload/users/';
            $root = $_SERVER['DOCUMENT_ROOT'];

            uho_thumb::convert($source_filename, $root . $image, $root . $folder . 'thumb/' . $filename, ['width' => 320, 'height' => 320, 'cut' => 1]);
            @rename($root . $image, $root . $folder . 'original/' . $filename);
            @unlink($root . str_replace('/files/', '/files/thumbnail/', $image));
        }

        return $r;
    }

    public function registerConfirm($key) {
        return $this->client->registerConfirm($key);
    }

    public function profileSet($data, $image, $allowEmptyCheckboxes = true) {
        $user = $this->getClient();
        if (!$user) return;

        $root = $_SERVER['DOCUMENT_ROOT'];

        if ($image === 'remove') {
            @unlink($root . $user['cover']['oryginalne']);
            @unlink($root . $user['cover']['thumb']);
        } elseif ($image) {
            $image = urldecode($image);
            @rename($root . $image, $root . $user['cover']['oryginalne']);
            _uho_thumb::convert($user['cover']['oryginalne'], $root . $user['cover']['oryginalne'], $root . $user['cover']['thumb'], ['width' => 320, 'height' => 320, 'cut' => 1]);
            $data['face'] = 0;
        }

        return $this->client->update($data, $allowEmptyCheckboxes);
    }

    public function getId() {
        return $this->client->getId();
    }

    public function getClient() {
        if (!$this->no_sql) {
            $data = $this->client->getData(false);
            $data['text'] = $data['text' . $this->cms->lang_add];
        }
        return $data;
    }

    public function getCfg($key) {
        return @$this->cfg[$key];
    }

    public function getClientName() {
        return @$this->client->getData()['name'];
    }

    public function getClientData() {
        $data = $this->client->getData();
        return [
            'id' => $data['id'],
            'login' => $data['login'],
            'name' => $data['name'],
            'hide_menu' => $data['hide_menu'],
            'edit_all' => $data['edit_all']
        ];
    }

    public function getClientCoverFace() {
        $v = $this->client->getData();
        return $v['face'] ? '/public/faces/' . $v['face'] . '.png' : '/public/faces/2.png';
    }

    public function getClientId() {
        return @$this->client->getData()['id'];
    }

    public function users_mailing($slug, $email, $params = null)
    {
        $mailing = $this->cms->query('SELECT subject:lang,message:lang,slug FROM client_mailing WHERE slug="' . $this->cms->sqlSafe($slug) . '"', true);

        if ($mailing) {
            $footer = $this->cms->query('SELECT message:lang FROM client_mailing WHERE slug="footer"', true);
            if ($footer) {
                $mailing['message'] .= "\r\n\r\n" . $footer['message'];
            }

            $this->mailer->addEmail($email, true);
            $this->mailer->addSubject($mailing['subject']);

            if ($params) {
                foreach ($params as $k => $v) {
                    $mailing['message'] = str_replace(['%' . $k . '%', '{{' . $k . '}}'], $v, $mailing['message']);
                }
            }

            if ($mailing['message'][0] === '<') {
                $this->mailer->addMessageHtml($mailing['message']);
            } else {
                $this->mailer->addMessage($mailing['message']);
            }

            $this->mailer->send();
        } else {
            exit('mailing not found: ' . $slug);
        }
    }

    public function newsletterConfirm($key, $status = "confirmed") {
        $key = $this->cms->sqlSafe($key);
        return $this->cms->queryOut('UPDATE newsletter_users SET status="' . $this->cms->sqlSafe($status) . '" WHERE key_confirm="' . $key . '"');
    }

    public function newsletterRemove($key) {
        $key = $this->cms->sqlSafe($key);
        $result = false;
        if ($key) {
            $t = $this->cms->query('SELECT id FROM newsletter_users WHERE key_remove="' . $key . '"');
            if ($t) {
                $this->cms->queryOut('DELETE FROM newsletter_users WHERE key_remove="' . $key . '"');
                $result = true;
            }
        }
        return $result;
    }

    public function newsletterAdd($email, $source = 'www') {
        $domain = 'http://' . $_SERVER['HTTP_HOST'];
        $data = [];

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = $this->cms->sqlSafe(strtolower($email));
            $email_hashed = $this->client->hash($email);
            $exists = $this->cms->getJsonModel('newsletter_users', ['email' => $email], true);

            $mail = false;
            if (!$exists) {
                $key_confirm = md5(uniqid());
                $this->cms->queryOut('INSERT INTO newsletter_users (lang,source,key_confirm,status,date_signup,email) VALUES ("' . $this->cms->lang . '","' . $source . '","' . $key_confirm . '","submitted","' . date('Y-m-d H:i:s') . '","' . $email_hashed . '")');
                $mail = true;
            } else if ($exists['status'] != 'confirmed') {
                $key_confirm = md5(uniqid());
                $this->cms->queryOut('UPDATE newsletter_users SET key_confirm="' . $key_confirm . '", status="submitted" WHERE email="' . $email_hashed . '"');
                $mail = true;
            }

            if ($mail) {
                $url = $domain . '/' . $this->cms->lang . '/newsletter-confirm/' . $key_confirm;
                $this->users_mailing('newsletter_confirmation', $email, ['url' => $url]);
            }

            $data['result'] = true;
            $data['message'] = $this->cms->lang == 'en' ? 'Thank You!' : 'Dziękujemy!';
        } else {
            $data = ['result' => false, 'message' => 'Nieprawidłowy adres e-mail'];
        }

        return $data;
    }

    public function logsAdd($action) {
        if ($action) $this->client->logsAdd($action);
    }
}