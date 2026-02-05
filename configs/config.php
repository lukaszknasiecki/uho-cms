<?php

/*
    This is a predefined config for CMS instance
    You can override it with /cfg-folder/config.php
*/

$cfg = [

    'application_url_prefix' => getenv('CMS_CONFIG_PREFIX'),
    'application_languages' => ['en'],
    'application_languages_url' => false,
    'orm_version' =>                   getenv('UHO_ORM') ? getenv('UHO_ORM') : 1,

    'clients' =>
    [
        'password_salt'      =>  getenv('CLIENT_PASSWORD_SALT'),      // password salt
        'password_expired'    =>  365,                                // password expired in days
        'password_hash' => 'password',                                // password hash type
        'password_required'  =>  '8,1,1,1,1',                          //  password minimum length, minimum a-z letters length, minimum A-Z letters, minimum digits, minimum special chars 
        'max_bad_login' =>    5                                       //  maximum invalid logins before locking the website
    ],
    'plugins' => [
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

        'serdelia_languages_url'  =>    false,                        //  add language string in serdelia URL      		
        'debug' => filter_var(getEnv("CMS_CONFIG_DEBUG"), FILTER_VALIDATE_BOOLEAN),
        'strict_schema' => filter_var(getEnv("CMS_CONFIG_STRICT"), FILTER_VALIDATE_BOOLEAN),
        'serdelia_keys' => [
            getenv('CLIENT_KEY1'),
            getenv('CLIENT_KEY2')
        ],

        'serdelia_cache_kill'      =>  null,                    //  set cache folder of your website, so after any change in the CMS this folder will be cleared by the CMS (all files will be removed!)
        'app_languages'             => []
    ]

];
