# Field Types

Each field consists of at least two parameters - `field` and `type`. Every field can have additional objects of `settings` (advanced parameters connected with field's type) and `cms` - object describing CMS-only parameters of this field, usually connected with its appearance in the CMS.

```json
{
    "field": "title",
    "type": "string",
    "settings": {
    },
    "cms": {
        "label": "Field Label",     // field label visible next to input
        "default": "draft",         // default value for empty fields
        "placeholder": "Enter something",   // placeholder for input, use boolean to use label value as placeholder
        "required": true,           // field is required
        "on_demand": true,          // field is initially disabled for input
        "edit": false               // field is disabled for input
    }
}
```

---

## Basic Types

### `string`

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
        "style": "json",    // Use monospace, smaller font for multi-row edit
        "max": 100,         // maximum length of input (chars)
        "wide": true,       // Use wide text input for multi-row edit
        "code": true        // Use monospace font for standard edit
    }
}
```

### `boolean`

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

### `text`

Multi-line text field (no HTML).

```json
{
    "field": "description",
    "type": "text",
    "cms":
    {
        "style": "json",    // Use monospace, smaller font for multi-row edit
        "wide": true,       // Use wide text input for multi-row edit
        "rows": 5,          // multi-row input field, you can use also "medium" value
        "max": 100          // maximum length of input (chars)
    }
}
```

### `json`

Multi-line text field in JSON format.

```json
{
    "field": "description",
    "type": "text",
    "cms":
    {
        "pretty": false,    // Prettifies input
        "rows": "default"   // default|large|small
    }
}
```

### `html`

Rich text editor field (CKEditor). It's based on CKEditor5, which you can enhance
with your own toolbars, classes and styles in `/cms_config/ckeditor5` folder.

```json
{
    "field": "content",
    "type": "html",
    "cms": {
        "width": 8,           // Bootstrap column width (1-12)
        "tall": true,         // Tall Edit Area
        "style": "standard"   // One of available toolbar styles
    },
    "settings": {
        "wysiwyg": "ckeditor5"  // ckeditor5 is a default editor
    }
}
```

You can integrate `html` field with media to add/manage inline images:

```json
{
    "field": "news_content",
    "type": "html",
    "cms": {
        "width": 8,           // Bootstrap column width (1-12)
        "tall": true,         // Tall Edit Area
        "style": "standard"   // One of available toolbar styles
    },
    "settings": {
        "media": "media",      // Connected Media field
        "media_field": "news"   // Overwrite current model name with this field in media model
    }
}
```



### `integer`

Integer number (INT(11) in database).

```json
{
    "field": "count",
    "type": "integer"
}
```

### `float`

Floating-point number.

```json
{
    "field": "price",
    "type": "float",
    "cms": {
        "step": 0.1,           // increase/decrease step
        "blank_on_zero": true  // show empty input on "0" value
    }
}
```

### `date`

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

### `datetime`

Date and time field (YYYY-mm-dd H:i:s format).

```json
{
    "field": "event_date_time",
    "type": "datetime",
    "settings":
    {
        "format":"ISO8601"      // ISO8601|UTC - reformats YYYY-MM-DD to ISO8601
    }
}
```

### `timestamp`

Date and time field (YYYY-mm-dd H:i:s format, converted to UTC timezone).

```json
{
    "field": "created_at",
    "type": "timestamp"
},
{
    "field": "updated_at",
    "type": "timestamp",
    "cms":
    {
        "auto":"timestamp"      // updated field on every record change in the CMS
    }
}
```

### `uid`

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

---

## Selection Types

### `select`

Single selection from options or another model.

**Static Options:**

```json
{
    "field": "status",
    "type": "select",
    "options": [                // for enum SQL fields, you can skip options as they will be derived from field definition
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

Alternative version on `options` property:

```json
      "options":
      {
          "draft":"Draft",
          "published": "Published"
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

### `checkboxes`

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

### `elements`

Multiple selection from another model (with fixed order).

```json
{
    "field": "related_items",
    "type": "elements",
    "cms":
    {
        "layout": "simple",    // both settings mean the same
        "small": true,
        "input": "standard"    // use standard drop-down select, not "search"
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

---

## Media Types

### `blocks`

Rich text editor built with blocks (https://github.com/editor-js).
Blocks are defined in JSON file aligned with EditorJS schema.

```json
{
    "field": "content",
    "type": "blocks",
    "cms": {
        "wide": true   // One of available toolbar styles
    },
    "settings": {
        "media": "media_field"  // connected media model - field of type `media`
    }
}
```

### `image`

Image upload with multiple sizes. Creates virtual schema (no SQL field).
CMS is using phpThumb library and by default `uid` field to create unique filenames.
By default all images are converted to JPG and WEBP (optional).

```json
{
    "field": "image",
    "type": "image",
    "settings": {
        "folder": "/public/upload/images",  // root folder for uploads
        "filename": "%uid%",                // filename template
        "filename_field": "filename",       // field to store original filename
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
            "crop": true,                   // use to force fixed ratio
            "retina": true                  // create retina (x2) images, in desktop_x2 folder
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
            "height": 110,                      // image height in CMS preview
            "change_uid_on_upload": "uid",      // changes uid field on each upload (prevents overwriting)
            "src_blank": "blank169.png"         // blank file to be shown if no image is present, uses /cms_config/assets folder as a root
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
        "folder": "/public/upload/images",
        "filename": "%uid%",
        "filename_field": "filename",
        "extensions": ["png"],              // allowed extensions
        "extension_field": "extension",     // field to store original extension
        "webp": true
    },
    "images": [
        {
            "folder": "original",
            "label": "Original image"
        },
        {
            "folder": "desktop",
            "label": "Desktop",
            "width": 1200,
            "height": 800,
            "crop": true,
            "retina": true
        },
        {
            "folder": "mobile",
            "label": "Mobile",
            "preview": true,                // use this as primary image in the CMS
            "width": 640,
            "height": 480,
            "crop": true
        }
    ],
    "cms": {
        "color_background": "red",          // background color for transparent images
        "list": {
            "height": 110,
            "src_blank": "blank169.png"
        }
    }
}
```

### `file`

File upload - by default filename is created from uid field with added extension.

```json
{
    "field": "file",
    "type": "file",
    "settings": {
        "folder": "/public/upload/files",       // folder to upload files
        "filename": "{{filename}}",
        "filename_original": "filename",

        "extension": "pdf",                     // for static extension you just define it here

        "extensions": ["docx", "pdf"],          // list of supported extensions for multi-extension fields
        "extension_field": "ext",               // for multiple extensions you need field to store those values

        "size": "size",                         // field to store file size
        "hashable": false                       // if you want to enable option to hash/dehash files
    },
    "cms":
    {
        "change_uid_on_upload": "uid",   // changes uid field on each upload (prevents overwriting)
        "metadata":
        [
            "date_modified",             // shows when file was last time modified
            "duration"                   // media duration, if env.FFPROBE_PATH path is specified
        ],
        "auto":
        [
            {"type":"duration","field":"file_duration"} // fill other field with file's values, only duration (GetID3.playtime_seconds) is supported now
        ]
    }
},
{
    "field":"ext",
    "type":"string"
},
{
    "field":"file_duration",
    "type":"integer"
}
```

You can also store files with their original filenames, you will need additional field to store that filename. Additionally you can specify just one extension, then you won't need separate extension field.

```json
{
    "field": "file",
    "type": "file",
    "settings": {
        "folder": "/public/upload/files",
        "filename_original": "filename_org",    // field to store original filename
        "filename":"{{filename_org}}",           // pattern to create filename
        "extension": "docx"                     // extension for single-extension fields
    }
},
{
    "field":"filename_org",
    "type":"string"
}
```

### `video`

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

### `media`

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

```
VIMEO_CLIENT
VIMEO_SECRET
VIMEO_TOKEN
YOUTUBE_TOKEN
```

Fields used to store the data:
`youtube`, `vimeo`, `vimeo_sources`

---

## Other Types

### `hidden`

Field is not shown in the CMS, but its value is available for other fields
as a possible source of pattern fills.

```json
{
    "field": "unused",
    "type": "hidden"
}
```

### `order`

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

### `plugin`

Creates plugin button as a mirror of any plugin defined in `buttons_edit` array.
Useful if you want to visually attach plugin to some field and hide top plugin button.

```json
{
    "type": "plugin",
    "plugin": "convert",        // plugin name, must match .plugin from buttons_edit
    "plugin_nr": 1,             // if there is more than 1 plugin of this type, choose which one to use
    "page": "items"             // plugin name, if it's of page type, must match .page from buttons_edit
}
```

### `table`

Table view with multiple columns and rows.

```json
{
    "field": "schedule",
    "type": "table",
    "settings": {
        "cols": 3,                          // columns count
        "style": "distinct",
        "header": [                         // header shows table header and sets columns count
            {
                "label": "Time",            // column header
                "width": 30,                // column width in % of table width
                "placeholder": "HH:MM"      // row placeholder for this column
            },
            {
                "label": "Text",
                "type": "html",             // row type, string is default, allowed: "string|html"
                "width": 70
            }
        ]
    },
    "cms": {
        "counter": true,                    // if set to true, row numbers are visible
        "height": 300,                      // row height in pixels
        "wide": false,                      // if true - table takes 100% of screen width
        "style": "json",                    // available: json|distinct
        "placeholders": ["A", "B"],         // placeholders for each column
        "widths": [30,50,70]                // width of each column (%)
    }
}
```

### `url`

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

### `virtual`

Field used for copy to clipboard.

```json
{
    "type": "virtual",
    "cms": {
        "label": "Copy link"
    },
    "settings":
    {
        "clipboard": true
    }
}
```

---

## Auto Fields

Field values can be automatically generated using the `auto` property:

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
