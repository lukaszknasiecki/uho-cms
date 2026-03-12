<?php

/*
    This is a predefined config for CMS instance
    You can override it with /cfg-folder/config.php
*/

$cfg = [

    'application_url_prefix' => getenv('CMS_CONFIG_PREFIX'),
    'application_languages' => ['en'],
    'application_languages_url' => false,
    'orm_version' =>getenv('UHO_ORM') ? getenv('UHO_ORM') : 1,

    'clients' =>
    [
        'password_salt'      =>  getenv('CLIENT_PASSWORD_SALT'),      // password salt
        'password_expired'    =>  365,                                // password expired in days
        'password_hash' => 'password',                                // password hash type
        'password_required'  =>  '8,1,1,1,1',                          //  password minimum length, minimum a-z letters length, minimum A-Z letters, minimum digits, minimum special chars 
        'max_bad_login' =>    5                                       //  maximum invalid logins before locking the website
    ],
    'plugins' => [

		'PHP' => getEnv("PHP") ?? null,
		'INT_API_TOKEN' => getEnv("INT_API_TOKEN") ?? null,

        'client' =>
        [
            'client_model' => 'client_users',
            'client_logs_model' => '',
            'client_logins_model' => '',
            'password_salt' => getenv('CLIENT_PASSWORD_SALT'),
            'password_required' => '8,1,1,1,1'
        ]
    ],

    'cms' =>
    [
        'title'          =>  'CMS',
        'logotype'       =>   false,
        'favicon'       =>   false,

        'debug' => empty(getEnv("CMS_CONFIG_STRICT")) ? false : filter_var(getEnv("CMS_CONFIG_STRICT"), FILTER_VALIDATE_BOOLEAN),
        'strict_schema' => empty(getEnv("CMS_CONFIG_STRICT")) ? true : filter_var(getEnv("CMS_CONFIG_STRICT"), FILTER_VALIDATE_BOOLEAN),

        'logout_time' => getEnv("CMS_LOGOUT_TIME") ? getenv("CMS_LOGOUT_TIME") : 60,
		'activity_time' => getEnv("CMS_ACTIVITY_TIME") ? getenv("CMS_ACTIVITY_TIME") : 15,

        'serdelia_keys' => [
            getenv('CLIENT_KEY1'),
            getenv('CLIENT_KEY2')
        ],
        'cache' => null,
        'app_languages'             => []
    ]

];
