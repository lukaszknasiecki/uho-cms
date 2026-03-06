# Configuration

## Configuration Structure

By default CMS is using configs defiend in `/cms/configs` folder, but you can add your
own settings, which will be merged with default config.

The `cms_config` folder contains all project-specific configurations.
It can be aby other folder, you need to define its name in `/uho-cms.json` file,
please refer to [Installation & Setup](installation.md).

Here's the recommended structure for this folder:

```
cms_config/
├── config.php                 # Main PHP configuration, merges with /cms/configs/config.php
├── assets/                    # CMS assets (logos, favicons)
│   ├── favicon.png
│   └── logotype.png
├── ckeditor5/                 # CKEditor 5 configuration
│   ├── config.js
│   └── config.css
├── pages/                     # Model/Page configurations
│   ├── menu.json
│   ├── pages.json
│   ├── media.json
│   └── modules/               # Module configurations
│       ├── m_text.json
│       ├── m_hero_home.json
│       └── ...
├── plugins/                   # Custom plugins
│   ├── clip_update/
│   │   ├── plugin.json
│   │   ├── plugin.php
│   │   └── plugin.html
│   └── ...
└── structure/                 # CMS structure files
    ├── menu.json              # Navigation menu
    ├── dashboard.json         # Dashboard widgets
    ├── authorization.json     # User permissions
    └── model_tree.json        # Model relationships
```

## config.php

By default CMS is using `cms/configs/config.php` file.
You can modify it with your own file located at `cms_config/config.php`, which will merge with default properties:

```php
<?php

$cfg = [
    'cms' =>
    [
        'title'          => 'Your CMS Title',   // CMS title visible in <title> tag
        'logotype'       => true,               // Show logotype in CMS, /cms_config/assets/logotype.png, default=FALSE
        'favicon'        => true,               // Use favicon, /cms_config/assets/favicon.png, default=FALSE

        'debug'          => filter_var(getEnv("CMS_CONFIG_DEBUG"), FILTER_VALIDATE_BOOLEAN),    // enable CMS debug mode, validates schemas
        'strict_schema'  => filter_var(getEnv("CMS_CONFIG_STRICT"), FILTER_VALIDATE_BOOLEAN),   // force strict schema, disabled depreceated support

        'app_languages'  => ['en'],            // Supported languages
        'cache' =>
        [
            [
                'folder' => '/cache',
                'extensions' => ['cache', 'sql']
            ],
            [
                'domain' => 'nature.ode.lh',
                'url' => 'https://nature.ode.lh/api/cache_kill',
            ]
        ],

        'uho_cms_logout_time' => 120,           // max CMS session in minutes, default is 60, 0 to set max time of 24H
		'uho_cms_activity_time' => 30           // max CMS non-activity time in minutes, default is 15, 0 to set max time of 24H
    ],
    'plugins' => [
        'PHP'           => getEnv("PHP"),      // PHP executable path
        'INT_API_TOKEN' => getEnv("INT_API_TOKEN")
    ]
];
```

Cache parameter is all about removing site's cache if CMS changed any data. You can remove cache
files locally by setting up folder containing cache files and files extensions, attach those actions
to a specific domain only, or choose to launch external URL call via CURL, if application is installed
on other server than the CMS.

**CMS array Configuration Options:**

* `title`: Display name for the CMS
* `logotype`: Boolean - show logotype in CMS header
* `favicon`: Boolean - use favicon
* `debug`: Enable debug mode
* `strict_schema`: Enable strict schema validation
* `app_languages`: Array of supported languages
* `cache`: Cache settings

## CKEditor Configuration

Customize the rich text editor in `cms_config/ckeditor5/config.js`:

```javascript
var CKEditor5_Config_Standard = { }
var CKEditor5_Config_Simple = { }
var CKEditor5_Configs=
{
    'default':CKEditor5_Config_Standard,
    'simple':CKEditor5_Config_Simple
};
```

Then, add CSS styles in `cms_config/ckeditor5/config.css`.

## Assets

Place CMS assets in `cms_config/assets/`:

* `logotype.png`: CMS logo
* `favicon.png`: CMS favicon
* `blank.png`: Sample placeholder image you can use in CMS for grid view

## Logs and Temp Folders

The CMS automatically creates:

* `{config_folder}-logs/`: Error logs
* `{config_folder}-temp/`: Temporary uploads

These are created automatically on first CMS access.
