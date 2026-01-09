# UHO-CMS Documentation

Complete guide to creating and configuring uho-cms instances.

## Table of Contents

 1. [Overview](#overview)
 2. [Installation](#installation)
 3. [Configuration Structure](#configuration-structure)
 4. [Core Configuration Files](#core-configuration-files)
 5. [Page/Model Configuration](#pagemodel-configuration)
 6. [Field Types](#field-types)
 7. [Plugins](#plugins)
 8. [Structure Files](#structure-files)
 9. [Best Practices](#best-practices)
10. [Examples](#examples)

## Overview

UHO-CMS is a content management system built on the UHO-MVC framework, utilizing Bootstrap and Twig templating. The CMS follows a configuration-driven architecture where all CMS behavior is defined through JSON and PHP configuration files located in a `cms_config` folder.

### Key Concepts

* **CMS Core**: Located in `/cms` folder, contains the framework and core functionality
* **Configuration Folder**: Located in `/cms_config` folder, contains all project-specific configurations
* **Multi-Instance Support**: Can manage multiple CMS instances from a single core installation
* **Model-Driven**: Each content type is defined as a "model" with fields, layouts, and behaviors


## Installation

### 1. Core Installation

The CMS core is located in the `/cms` folder. To set up a new CMS instance:









1. Ensure the `/cms` folder contains the core CMS files
2. Run `composer install` in the `/cms` directory to install dependencies
3. Create a configuration folder (e.g., `/cms_config`) in your project root

### 2. Root Configuration File

Create a configuration file in your project root. The CMS looks for either:

* `uho-cms.json` (preferred)
* `sunship-cms.json` (fallback)

**Example** `uho-cms.json`:

```json
{
  "CMS_CONFIG_DEBUG": true,
  "CMS_CONFIG_PREFIX": "cms",
  "CMS_CONFIG_FOLDERS": "cms_config",
  "CMS_CONFIG_LANG": "en",
  "CMS_CONFIG_THEME": "light"
}
```

**Configuration Options:**

* `CMS_CONFIG_DEBUG`: Enable/disable debug mode (boolean)
* `CMS_CONFIG_PREFIX`: URL prefix for CMS access (default: "cms")
* `CMS_CONFIG_FOLDERS`: Comma-separated list of configuration folder names (default: "cms_config")
* `CMS_CONFIG_LANG`: Default language code (default: "en")
* `CMS_CONFIG_THEME`: Theme mode - "light" or "dark" (default: "light")

**Environment Variable Support:**

You can use environment variables by prefixing values with `ENV.`:

```json
{
  "CMS_CONFIG_PREFIX": "ENV.CMS_PREFIX",
  "CMS_CONFIG_DEBUG": "ENV.CMS_DEBUG"
}
```

### 3. Environment Variables

Set up the following environment variables for database and security:

```bash
# Database Configuration
SQL_HOST=mysql_host
SQL_USER=mysql_user
SQL_PASS=mysql_password
SQL_BASE=mysql_dbname

# Security Keys
CLIENT_PASSWORD_SALT=xxx
CLIENT_KEY1=xxxxxxxxxxxxxxxx
CLIENT_KEY2=xxxxxxxxxxxxxxxx

# CMS Configuration (optional, can be in JSON)
CMS_CONFIG_DEBUG=true
CMS_CONFIG_STRICT=false
PHP=/usr/bin/php
INT_API_TOKEN=your_token_here
```

### 4. Access the CMS

After installation, access the CMS at:

```
https://yoursite.com/cms
```

On first access, you'll be prompted to:









1. Select a project (if multiple configuration folders are defined)
2. Set up the admin password


## Configuration Structure

The `cms_config` folder contains all project-specific configurations. Here's the recommended structure:

```
cms_config/
├── config.php                 # Main PHP configuration
├── assets/                    # CMS assets (logos, favicons)
│   ├── blank.png
│   ├── blank169.png
│   └── logotype.png
├── ckeditor/                  # CKEditor configuration (legacy)
│   ├── config.js
│   └── contents.css
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


## Core Configuration Files

### config.php

The main PHP configuration file located at `cms_config/config.php`:

```php
<?php

$cfg = [
    'cms' => [
        'title'          => 'Your CMS Title',
        'logotype'       => true,              // Show logotype in CMS
        'favicon'        => true,              // Use favicon
        'debug'          => filter_var(getEnv("CMS_CONFIG_DEBUG"), FILTER_VALIDATE_BOOLEAN),
        'strict_schema'  => filter_var(getEnv("CMS_CONFIG_STRICT"), FILTER_VALIDATE_BOOLEAN),
        'app_languages'  => ['en'],            // Supported languages
        'serdelia_cache_kill' => '/cache'      // Cache invalidation path
    ],
    'plugins' => [
        'PHP'           => getEnv("PHP"),      // PHP executable path
        'INT_API_TOKEN' => getEnv("INT_API_TOKEN")
    ]
];
```

**Configuration Options:**

* `title`: Display name for the CMS
* `logotype`: Boolean - show logotype in CMS header
* `favicon`: Boolean - use favicon
* `debug`: Enable debug mode (from environment variable)
* `strict_schema`: Enable strict schema validation
* `app_languages`: Array of supported language codes


## Page/Model Configuration

Each content type in the CMS is defined as a "model" using a JSON configuration file in the `pages/` folder. The filename (without `.json`) becomes the model name.

### Basic Structure

```json
{
    "table": "database_table_name",
    "label": {
        "page": "Display Name (List View)",
        "edit": "Edit View Title (Twig template)"
    },
    "order": "field_name",
    "fields": [
        {
            "field": "field_name",
            "type": "string",
            "cms": {
                "label": "Field Label",
                "required": true,
                "list": "show"
            }
        }
    ]
}
```

### Configuration Properties

#### Top-Level Properties

* `table`: Database table name (required)
* `label`: Object with `page` (list view title) and `edit` (edit view title, supports Twig)
* `order`: Default sort field(s), comma-separated for multiple fields
* `model`: Object defining how records of this schema are displayed (supports Twig templates) in other models
* `layout`: List view layout configuration
* `fields`: Array of field definitions
* `buttons_page`: Array of buttons shown in list view
* `buttons_edit`: Array of buttons shown in edit view

#### Page title (label) configuration

You can define separate title for list and edit views. Moreover you can use `helper_models` object to get properties from other models, especially useful
for nested structures. In the following case CMS is using `params.1` value to get
the parent model from the URL.

```json
{
    "label": {
        "page": "<code>/{{helper_models.page.path}}</code> Modules",
        "edit": "<code>/{{helper_models.page.path}}</code>: {% if not type %}New module{% else %}{{type.label}}{% endif %}"
    },
    "helper_models": {
        "page": {
            "model": "pages",
            "parent": "{{params.1}}"
        }
    }
}
```

#### List View - Layout Configuration

The standard layout is in the form of a list. An additional layout type (grid) is available. You can use built-on HTML for grid cells (based on `list` properties) or define custom HTML using values record values stored in `record.values` object.
You can enhabce `grid` layout with `cards` settings, showing a more structured view.

```json
{
    "layout": {
        "type": "grid",              // "grid" or "table" (default)
        "count": 100,                // Items per page
        "html": "Twig template: {{record.values.title}}",     // Custom HTML for grid items
        "settings": {
            "cards": true,           // Use grid card layout
            "card_title": "title"    // Field to use as card title
        }
    }
}
```

#### Buttons Configuration

Each page can have custom buttons which can execute custom (or CMS-based) pugins or simply move users to other CMS pages. Please note that you can use button icons from https://mervick.github.io/material-design-icons/.

**Page Buttons (List View):**

```json
{
    "buttons_page": [
        {
            "label": "Plugin Label",
            "icon": "icon-name",
            "type": "plugin",         // "plugin" or "page"
            "plugin": "plugin_name",
            "hidden": false,
            "params": {
                "key": "value"
            }
        },
        {
            "label": "Page Label",
            "icon": "icon-name",
            "type": "page",        
            "page": "items"
        }
    ]
}
```

**Edit Buttons:**

```json
{
    "buttons_edit": [
        {
            "label": "Related Items",
            "type": "page",
            "icon": "reorder",
            "page": "related_model,{{id}}"    // you can use current record's id
        },
        {
            "type": "plugin",
            "plugin": "preview",
            "params": {
                "url": "/{{url}}?preview=true"
            }
        }
    ]
}
```

### Field Configuration

Each field in the `fields` array defines a database column and its CMS behavior:

```json
{
    "field": "field_name",
    "type": "string",    
    "settings": {                   // additional field settings
        "length": 256
    },
    "cms": {                        // cms-only settings
        "label": "Field Label",
        "label": "Display Label",
        "code": false,
        "rows": 5
        "required": true,
        "list": "show",              // "show", "read", "edit", "order", or object
        "search": true,              // Include in search filters
        "tab": "Tab Name",           // Group fields in tabs
        "hr": true,                  // Show divider above field
        "header": "Section Header",  // Section header text
        "on_demand": true,           // Only show when requested
        "edit": {
            "remove": true           // Hide from edit form
        },
        "help": {
            "text": "Help text",
            "size": "full"           // "full" or "small"
        },
        "auto": {
            "on_null": true,
            "pattern": "{{title}}",
            "unique": true,
            "url": true
        }
    }
}
```

**Field** `cms` Properties:

* `label`: Display label for the field
* `required`: Field is required
* `list`: Visibility in list view - `"show"`, `"read"`, `"edit"`, `"order"`, or object with `type`, `value`, `width`
* `search`: Include in search/filter panel
* `tab`: Group field in a named tab
* `hr`: Show horizontal divider above field
* `header`: Show section header above field
* `on_demand`: Only display when explicitly requested
* `edit.remove`: Hide field from edit form
* `help`: Show help text with field
* `auto`: Auto-generate field value (see Auto Fields section)

**List View Options:**

```json
{
    "cms": {
        "list": {
            "type": "show",
            "value": "{{title}} - {{status}}",
            "width": 30,
            "height": 110,
            "src_blank": "blank.png"
        }
    }
}
```


## Field Types

Each field consists of at least two parameters - `field` and `type`. Every field can have additional objects of `settings` (advanced parameters connected with field's type) and `cms` - object describing CMS-only parameters of this field, usually connected with its appearance in the CMS.

```json
{
    "field": "title",
    "type": "string",
    "settings": {        
    },
    "cms": {     
        "label": "Field Label", // field label visible next to input
        "default": "draft",     // default value for empty fields
        "placeholder" :"Enter something",   // placeholder for input, use boolean to use label value as placeholder
        "required" : true,      // field is required        
        "on_demand" : true,     // field is initially disabled for input   
        "edit" : false         // field is disabled for input        
    }
}
```

### Basic Types

#### `string`

Default type, Text field, 256 characters by default.

```json
{
    "field": "title",
    "type": "string",
    "settings": {
        "length": 128      // Custom length, 256 default        
    },
    "cms":
    {
        "rows": 5,          // multi-row input field, you can use also "medium" value
        "style" : "json",   // Use monospace, smaller font for multi-row edit
        "max": 100,         // maximum length of input (chars)
        "wide" : true,      // Use wide text input for multi-row edit
        "code": true        // Use monospace font for standard edit
    }
}
```

#### `boolean`

Checkbox/boolean field.

```json
{
    "field": "active",
    "type": "boolean",
    "cms": {
        "label": "Publish",
        "list": "edit"         // Show as toggle in list view
    }
}
```

#### `text`

Multi-line text field (no HTML).

```json
{
    "field": "description",
    "type": "text",
    "cms":
    {
        "style" : "json",   // Use monospace, smaller font for multi-row edit
        "wide" : true,      // Use wide text input for multi-row edit
        "rows": 5,          // multi-row input field, you can use also "medium" value
        "max": 100,         // maximum length of input (chars)
    }
}
```

#### `json`

Multi-line text field in JSON format.

```json
{
    "field": "description",
    "type": "text",
    "cms":
    {
        "pretty" : false,   // Prettifies input
        "rows": "default",  // default|large|small
    }
}
```

#### `html`

Rich text editor field (CKEditor). It's based on CKEditor5, which you can enhance
with your own toolbars, classes and styles in `/cms_config/ckeditor5` folder.

```json
{
    "field": "content",
    "type": "html",
    "cms": {
        "width": 8,          // Bootstrap column width (1-12)
        "tall": true,        // Tall Edit Area
        "style": "standard"   // One of available toolbar styles
    },
    "settings": {
        "wysiwyg": "ckeditor5", // ckeditor5 is a default editor
    }
}
```

#### `integer`

Integer number (INT(11) in database).

```json
{
    "field": "count",
    "type": "integer"
}
```

#### `float`

Floating-point number.

```json
{
    "field": "price",
    "type": "float",
    "cms": {
        "step": 0.1,          // increase/decrease step
        "blank_on_zero": true // show empty input on "0" value        
    }
}
```

#### `date`

Date field (YYYY-mm-dd format).

```json
{
    "field": "publish_date",
    "type": "date",
    "cms":
    {
        "default":"{{now}}"     // shows current date for blank fields
    }
}
```

#### `datetime`

Date and time field (YYYY-mm-dd H:i:s format).

```json
{
    "field": "created_at",
    "type": "datetime",
    "settings":
    {
        "format":"ISO8601"      // ISO8601|UTC - reformats YYYY-MM-DD to ISO8601
    }
}
```

#### `uid`

Automatically generated unique identifier (varchar(13)).

```json
{
    "field": "uid",
    "type": "uid",
    "cms": {
        "list": "read"          // "read" is useful for list view, where uid is needed to create image paths
    }
}
```

### Selection Types

#### `select`

Single selection from options or another model.

**Static Options:**

```json
{
    "field": "status",
    "type": "select",
    "options": [                // for enum SQL fields, you can skip options as they will ve derived from field definition
        {
            "value": "draft",
            "label": "Draft"
        },
        {
            "value": "published",
            "label": "Published"
        }
    ],
    "cms": {
        "default": "draft"
    }
}
```

**From Another Model:**

```json
{
    "field": "category_id",
    "type": "select",
    "source": {
        "model": "categories"        
    },
    "settings": {
        "output": "value"            // Return value instead of strict integer id (default)
    }
}
```

**Search Input (for large models):**

```json
{
    "field": "author_id",
    "type": "select",
    "cms":
    {
        "input": "search"
    },
    "source": {
        "model": "authors",                 // search in this model
        "search_strict": false,             // don't use strict 1:1 search
        "search": ["first_name", "last_name"],  // search those fields in this model
        "label":"{{label}}"                 // show results as this Twig template
    }
}
```

#### `checkboxes`

Multiple selection from another model (no fixed order).

```json
{
    "field": "tags",
    "type": "checkboxes",
    "source": {
        "model": "tags",
        "model_fields": ["label"],    // Fields to read/display
        "filters": {                  // Filter source
            "active": 1
        }
    },
    "cms": {
        "cols": 2,                   // Number of columns, default is 3
        "small": true                // Compact display, useful for many options
    }
}
```

#### `elements`

Multiple selection from another model (with fixed order).

```json
{
    "field": "related_items",
    "type": "elements",
    "cms":
    {
        "layout" : "simple",    // both settings mean the same
        "small" : true,
        "input" : "standard"        // use standard drop-down select, not "search"
    },
    "source": {
        "model": "items",
        "model_fields": ["title", "description"],
        "filters": {
            "active": 1
        }
    }
}
```

### Media Types

#### `image`

Image upload with multiple sizes. Creates virtual schema (no SQL field).
CMS is using phpThumb library and by default `uid` field to create unique filenames.
By default all images are converted do JPG and WEBP (optional).

```json
{
    "field": "image",
    "type": "image",
    "settings": {
        "folder": "/public/upload/images",  // root folder for uploads
        "folder_preview": "thumb",          // preview folder to be used in CMS for thumbnails
        "filename": "%uid%",                // filename template
        "filename_field": "filename",       // field to store original filename
        "change_uid_on_upload": false,      // change UID on every image change/upload
        "sizes": "image_sizes",             // json field to store JSON with all image sizes
        "webp": true                        // add webp format
    },
    "images": [                             // list of sizes/folders
        {
            "folder": "original",           // first folder - to store original image
            "label": "Original image"
        },
        {
            "folder": "desktop",            // subfolder name
            "label": "Desktop",             // cms label
            "width": 1200,                  // max width
            "height": 800,                  // max height
            "crop": true,               // use to force fixed ratio
            "retina": true              // create retina (x2) images, in desktop_x2 folder
        },
        {
            "folder": "mobile",
            "label": "Mobile",
            "width": 640,
            "height": 480,
            "crop": true
        }
    ],
    "cms": {
        "list": {
            "height": 110,              // image height in CMS preview
            "src_blank": "blank169.png" // blank file to be shown if no image is present, uses /cms_config/assets folder as a root
        }
    }
}
```

You can also keep original image types, which is convenient for transparent images (like PNG).

```json
{
    "field": "image",
    "type": "image",
    "settings": {
        "folder": "/public/upload/images",  // root folder for uploads
        "folder_preview": "thumb",          // preview folder to be used in CMS for thumbnails
        "filename": "%uid%",                // filename template
        "filename_field": "filename",       // field to store original filename
        "extensions": [ "png"],             // allowed extensions
        "extension_field": "extension",     // field to store original extension
        "webp": true                        // add webp format
    },
    "images": [                             // list of sizes/folders
        {
            "folder": "original",           // first folder - to store original image
            "label": "Original image"
        },
        {
            "folder": "desktop",            // subfolder name
            "label": "Desktop",             // cms label
            "width": 1200,                  // max width
            "height": 800,                  // max height
            "crop": true,               // use to force fixed ratio
            "retina": true              // create retina (x2) images, in desktop_x2 folder
        },
        {
            "folder": "mobile",
            "label": "Mobile",
            "width": 640,
            "height": 480,
            "crop": true
        }
    ],
    "cms": {
        "list": {
            "height": 110,              // image height in CMS preview
            "src_blank": "blank169.png" // blank file to be shown if no image is present, uses /cms_config/assets folder as a root
        }
    }
}
```

#### `file`

File upload - by default filename is created from uid field with added extension.

```json
{
    "field": "file",
    "type": "file",
    "settings": {
        "folder": "/public/upload/files",       // folder to upload files
        "size": "size",                          // field to store file size
        "hashable": false,                      // if you want to enable option to hash/dehash files
        "extensions": ["docx", "pdf"],          // list of supported extensions for multi-extension fields
        "extension_field": "ext"                // field to store file's extension,        
    },
    "cms":
    {
        "auto":
        [
            {"type":"duration","field":"file_duration"} // fill other filed with file's calues, only duration (GetID3.playtime_seconds) is supported now
        ]

    }
},
{
    "field":"ext",
    "type":"string"
}
```

You can also store files with their original filenames, you will need additional field to store that filename. Additionally you can specify just one extenstion, then you won’t need separate extension field.

```json
{
    "field": "file",
    "type": "file",
    "settings": {
        "folder": "/public/upload/files",       // folder to upload files
        "filename_original": "filename_org",        // field to store original filename
        "filename":"{{filename_org}}",              // pattern to create filename
        "extension": "docx"                    // extension for single-extension fields
     }
},
{
    "field":"filename_org",
    "type":"string"
}
```

#### 

#### `video`

Video file upload (MP4).

```json
{
    "field": "video",
    "type": "video",
    "settings": {
        "folder": "/public/upload/videos",
        "field_cover": "image"                  // make video's cover and save for this field
    }
}
```

Files are stored as: `{folder}/{uid}.mp4`

#### `media`

Attach media from another model.

```json
{
    "field": "gallery",
    "type": "media",
    "source": {
        "model": "media",
        "types": [
            "image",
            "video",
            "audio",
            {
                "type": "file",
                "extensions": ["pdf", "doc", "docx"]
            },
            "vimeo",
            "youtube"
        ]
    },
    "layout": {
        "folder": "desktop"
    },
    "captions": [
        {
            "label": "Caption",
            "field": "label"
        },
        {
            "label": "Description",
            "field": "description",
            "field_type": "text"            // youtube|vimeo|url|text
        }
    ]
}
```

**Media Types:**

* `image`: Image files
* `video`: Video files
* `audio`: Audio files
* `file`: Other files
* `youtube`/`vimeo`: Stores video's UIDs and thumbnails if available

For YouTube and Vimeo you need to specify access keys in ENVs to get covers
and/or sources (Vimeo):

VIMEO_CLIENT
VIMEO_SECRET
VIMEO_TOKEN
YOUTUBE_TOKEN

Fields used to store the data:
`youtube`
`vimeo`
`vimeo_sources`

### Other Types

#### `hidden`

Fiels is not shown in the CMS, but it's value is available for other fields
as a possible source of patter fills.

```json
{
    "field": "unused",
    "type": "hiddenr"
}

#### `order`
Defines record ordering (INT(11) in database). Enables drag-and-drop sorting.

```json
{
    "field": "nr",
    "type": "order",
    "cms": {
        "label": "Order",
        "list": "order"
    }
}
```

#### `plugin`

Creates plugin button as a mirror of any plugin defined in `buttons_edit` array.
Useful if you want to visually attach plugin to some field and hide top plugin button.

```json
{
    "type": "plugin",
    "plugin": "convert",        // plugin name, must match .plugin from buttonS_edit
    "plugin_nr": 1,             // if there is more than 1 plugin of this type, choose which one to use
    "page": "items"              // plugin name, if it's of page type, must match .page from buttonS_edit
}

#### `table`
Table view with multiple columns and rows.

```json
{
    "field": "schedule",
    "type": "table",
    "settings": {
        "cols": 3,
        "counter": true,
        "height": 300,
        "wide": false,
        "style": "distinct",
        "header": [
            {
                "label": "Time",
                "width": 30,
                "placeholder": "HH:MM"
            },
            {
                "label": "Event",
                "width": 70
            }
        ]
    }
}
```

#### `url`

String for storing URLs.

```json
{
    "field": "link",
    "type": "url",
    "cms": {
        "label": "Website link"
    }
}
```

#### `virtual`

Field used for copy to clipboard.

```json
{
    "type": "virtual",
    "cms": {
        "label": "Copy link"
    },
    "settings":
    {
        "clipboard"
    }
}
```

### Auto Fields

Field values can be automatically generate values using the `auto` property:

```json
{
    "field": "slug",
    "cms": {
        "auto": {
            "on_null": true,         // Only if field is empty
            "pattern": "{{title}}",  // Twig template
            "unique": true,          // Ensure uniqueness
            "url": true              // Convert to URL-safe string
        }
    }
}
```


## Plugins

Plugins extend CMS functionality with custom PHP code. Each plugin is a folder in `cms_config/plugins/` containing:

* `plugin.json`: Plugin metadata
* `plugin.php`: PHP class implementation
* `plugin.html`: Optional HTML template

### Plugin Structure

**plugin.json:**

```json
{
    "icon": "video-settings",
    "en": {
        "label": "Import Media"
    },
    "pl": {
        "label": "Importuj media"
    }
}
```

**plugin.php:**

```php
<?php

class serdelia_plugin_your_plugin_name
{
    private $cms, $params, $parent;

    public function __construct($cms, $params, $parent)
    {
        $this->cms = $cms;
        $this->params = $params;
        $this->parent = $parent;
    }

    public function getData()
    {
        // Your plugin logic here
        
        $data = [
            'result' => true,
            'message' => 'Operation completed'
        ];
        
        return $data;
    }
}
```

**Plugin Class Naming:**

Class name must follow pattern: `serdelia_plugin_{folder_name}`

Replace hyphens and spaces with underscores. Example:

* Folder: `clip-update` → Class: `serdelia_plugin_clip_update`
* Folder: `my_plugin` → Class: `serdelia_plugin_my_plugin`

### Using Plugins

Plugins can be called from:









1. **Page buttons** (list view)
2. **Edit buttons** (edit view)
3. **Field actions**

**Example in page configuration:**

```json
{
    "buttons_page": [
        {
            "label": "Update Index",
            "icon": "index",
            "plugin": "index_update"
        }
    ],
    "buttons_edit": [
        {
            "type": "plugin",
            "plugin": "preview",
            "params": {
                "url": "/{{slug}}?preview=true"
            }
        }
    ]
}
```

**Plugin Parameters:**

Parameters are passed to the plugin constructor in `$params`:

```json
{
    "plugin": "my_plugin",
    "params": {
        "record": "{{id}}",
        "action": "update"
    }
}
```

Access in PHP:

```php
$record_id = $this->params['record'];
$action = $this->params['action'];
```

### Available CMS Methods

Plugins have access to the CMS instance with methods like:

```php
// Query database
$results = $this->cms->query('SELECT * FROM table WHERE id = ?', [$id]);

// Execute query (no return)
$this->cms->queryOut('UPDATE table SET field = ? WHERE id = ?', [$value, $id]);

// Get JSON model (with relationships)
$item = $this->cms->getJsonModel('model_name', ['id' => $id], true);

// Put JSON model (update JSON cache)
$this->cms->putJsonModel('model_name', ['active' => 1], ['id' => $id]);
```


## Structure Files

Structure files in `cms_config/structure/` define CMS navigation, permissions, and relationships.

### menu.json

Defines the CMS navigation menu.

```json
[
    {
        "label": "Content",
        "submenu": [
            {
                "label": "Pages",
                "page": "pages"
            },
            {
                "label": "Media",
                "page": "media"
            }
        ]
    },
    {
        "label": "Settings",
        "submenu": [
            {
                "label": "Users",
                "page": "cms_users"
            }
        ]
    }
]
```

**Menu Properties:**

* `label`: Menu item label
* `page`: Model/page to link to (format: `"model_name"` or `"model_name,id"`)
* `submenu`: Array of nested menu items
* `auth`: Required authorization preset (see authorization.json)

### dashboard.json

Defines dashboard widgets shown on CMS home page.

```json
{
    "type": "widgets",
    "widgets": [
        "hello",
        {
            "widget": "page",
            "params": {
                "page": "pages",
                "icon": "article",
                "image": "image"
            }
        },
        {
            "widget": "page",
            "params": {
                "page": "interviews",
                "icon": "videocam"
            }
        }
    ]
}
```

**Widget Types:**

* `"hello"`: Welcome message widget
* `{"widget": "page", ...}`: Link to a model/page

**Page Widget Parameters:**

* `page`: Model name
* `icon`: Material icon name
* `image`: Field name to use as widget image

### authorization.json

Defines user permission presets.

```json
[
    {
        "id": "system",
        "label": "System admin",
        "models": ["settings", "cms_users"]
    },
    {
        "id": "content",
        "label": "Content editor",
        "models": ["pages", "media", "news"]
    },
    {
        "id": "author",
        "label": "Author",
        "models": ["articles"]
    }
]
```

**Authorization Properties:**

* `id`: Unique identifier for the preset
* `label`: Display name
* `models`: Array of model names this preset can access

**Using Authorization:**

Reference in menu.json:

```json
{
    "label": "Settings",
    "page": "settings",
    "auth": "system"
}
```

### model_tree.json

Defines parent-child relationships between models.

```json
{
    "models": {
        "pages": [
            "pages_modules"
        ],
        "interviews": [
            "sessions"
        ],
        "topics": [
            "subtopics"
        ]
    }
}
```

This creates navigation relationships where child models are accessible from parent model edit pages.


## Best Practices

### 1. Naming Conventions

* **Model files**: Use lowercase with underscores: `my_model.json`
* **Database tables**: Match model names exactly
* **Field names**: Use snake_case: `first_name`, `publish_date`
* **Plugin folders**: Use lowercase with hyphens: `my-plugin`

### 2. Field Organization

* Use `tab` to group related fields
* Use `hr: true` to visually separate sections
* Use `header` for section titles
* Place most important fields first

### 3. Image Handling

* Always use `uid` field for image filenames
* Define appropriate sizes for your use case
* Enable WebP generation for better performance
* Use `src_blank` for list view placeholders

### 4. Performance

* Use `list: "read"` for fields needed in templates but not displayed
* Use `on_demand: true` for rarely-edited fields
* Define appropriate `order` for list views
* Use `search: true` only for fields that need filtering

### 5. Security

* Never expose sensitive data in list views
* Use `hidden: true` for admin-only buttons
* Validate plugin inputs
* Use prepared statements in plugin queries

### 6. Localization

* Use language-specific labels: `label_EN`, `label_PL`
* Define language arrays in config.php
* Use Twig templates for dynamic labels

### 7. Relationships

* Use `model_tree.json` to define clear parent-child relationships
* Use `elements` for ordered relationships
* Use `checkboxes` for unordered multi-select
* Consider performance when loading related models


## Examples

### Example 1: Simple Blog Post Model

**File:** `cms_config/pages/articles.json`

```json
{
    "table": "articles",
    "label": {
        "page": "Articles",
        "edit": "{% if not title %}New Article{% else %}{{title}}{% endif %}"
    },
    "order": "publish_date DESC, title",
    "layout": {
        "type": "grid",
        "html": "{{html.image|raw}}<h6>{{record.values.title}}</h6><small>{{record.values.publish_date}}</small> {{html.active|raw}}",
        "settings": {
            "cards": true
        }
    },
    "fields": [
        {
            "field": "title",
            "type": "string",
            "settings": {
                "length": 255
            },
            "cms": {
                "label": "Title",
                "required": true,
                "list": "show",
                "search": true
            }
        },
        {
            "field": "slug",
            "type": "string",
            "settings": {
                "length": 64
            },
            "cms": {
                "label": "URL Slug",
                "on_demand": true,
                "auto": {
                    "on_null": true,
                    "pattern": "{{title}}",
                    "unique": true,
                    "url": true
                }
            }
        },
        {
            "field": "content",
            "type": "html",
            "cms": {
                "label": "Content",
                "width": 12
            }
        },
        {
            "field": "image",
            "type": "image",
            "settings": {
                "folder": "/public/upload/articles",
                "filename": "%uid%",
                "webp": true
            },
            "images": [
                {
                    "folder": "original",
                    "label": "Original"
                },
                {
                    "folder": "thumbnail",
                    "label": "Thumbnail",
                    "width": 400,
                    "height": 300,
                    "crop": true
                }
            ],
            "cms": {
                "label": "Featured Image",
                "list": {
                    "height": 150
                }
            }
        },
        {
            "field": "publish_date",
            "type": "date",
            "cms": {
                "label": "Publish Date",
                "list": "show",
                "search": true
            }
        },
        {
            "field": "active",
            "type": "boolean",
            "cms": {
                "label": "Published",
                "list": "edit",
                "search": true
            }
        }
    ]
}
```

### Example 2: Model with Relationships

**File:** `cms_config/pages/products.json`

```json
{
    "table": "products",
    "label": {
        "page": "Products",
        "edit": "Product: {{name}}"
    },
    "order": "name",
    "fields": [
        {
            "field": "name",
            "cms": {
                "label": "Product Name",
                "required": true,
                "list": "show"
            }
        },
        {
            "field": "category_id",
            "type": "select",
            "source": {
                "model": "categories",
                "search": ["name"]
            },
            "cms": {
                "label": "Category",
                "list": "show",
                "search": true
            }
        },
        {
            "field": "tags",
            "type": "checkboxes",
            "source": {
                "model": "tags",
                "model_fields": ["label"]
            },
            "cms": {
                "label": "Tags",
                "small": true
            }
        },
        {
            "field": "related_products",
            "type": "elements",
            "source": {
                "model": "products",
                "model_fields": ["name", "price"],
                "filters": {
                    "active": 1
                }
            },
            "cms": {
                "label": "Related Products"
            }
        }
    ]
}
```

### Example 3: Custom Plugin

**File:** `cms_config/plugins/export_data/plugin.json`

```json
{
    "icon": "download",
    "en": {
        "label": "Export Data"
    }
}
```

**File:** `cms_config/plugins/export_data/plugin.php`

```php
<?php

class serdelia_plugin_export_data
{
    private $cms, $params, $parent;

    public function __construct($cms, $params, $parent)
    {
        $this->cms = $cms;
        $this->params = $params;
        $this->parent = $parent;
    }

    public function getData()
    {
        $model = $this->params['model'] ?? 'articles';
        
        // Get all active records
        $items = $this->cms->query(
            'SELECT * FROM ' . $model . ' WHERE active = 1 ORDER BY id'
        );
        
        // Process data
        $export = [];
        foreach ($items as $item) {
            $export[] = [
                'id' => $item['id'],
                'title' => $item['title'],
                'created' => $item['created_at']
            ];
        }
        
        return [
            'result' => true,
            "data" => $export,
            'count' => count($export)
        ];
    }
}
```


## Additional Resources

### CKEditor Configuration

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

Then, add CSS styles in `cms_config/ckeditor5/config.css`:

### Assets

Place CMS assets in `cms_config/assets/`:

* `logotype.png`: CMS logo
* `blank.png`: Placeholder image
* `blank169.png`: 16:9 placeholder

### Logs and Temp Folders

The CMS automatically creates:

* `{config_folder}-logs/`: Error logs
* `{config_folder}-temp/`: Temporary uploads

These are created automatically on first CMS access.


## Troubleshooting

### CMS Not Loading









1. Check `uho-cms.json` exists in project root
2. Verify `cms_config` folder exists
3. Check `cms_config/config.php` syntax
4. Review error logs in `cms_config-logs/`

### Models Not Appearing









1. Verify JSON syntax in page configuration files
2. Check database table exists
3. Ensure field types match database columns
4. Check authorization.json includes model

### Plugins Not Working









1. Verify class name matches folder name pattern
2. Check PHP syntax errors
3. Ensure `getData()` method returns array
4. Review plugin.json structure

### Image Upload Issues









1. Verify folder paths are absolute
2. Check folder permissions (write access)
3. Ensure `uid` field exists in model
4. Verify image size configurations


## Conclusion

This documentation covers the essential aspects of configuring uho-cms. The CMS is highly flexible and configuration-driven, allowing you to create custom content management solutions without modifying core code.

For additional support or advanced configurations, refer to the core CMS code in `/cms` or consult the framework documentation.