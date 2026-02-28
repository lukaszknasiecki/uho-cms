# Plugins

Plugins extend CMS functionality. The CMS includes several built-in plugins and supports custom plugins placed in `cms_config/plugins/`.

---

## Custom Plugins

Each plugin is a folder in `cms_config/plugins/` containing:

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
        },
        {
            "type": "plugin",
            "plugin": "auto_update",
            "hidden": true,             // hide from the button view
            "on_update": true          // run on record update
        }
    ]
}
```

### Plugin Parameters

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
$item = $this->cms->get('model_name', ['id' => $id], true);

// Put JSON model (update JSON cache)
$this->cms->put('model_name', ['active' => 1], ['id' => $id]);
```

---

## Built-in Plugins

The CMS ships with a set of ready-to-use plugins. Reference them by name in any `buttons_page` or `buttons_edit` configuration.

### `api_single`

Executes a single HTTP request (GET or POST) against an internal or external URL. The URL supports Twig templating using the current record's values.

```json
{
    "buttons_edit": [
        {
            "type": "plugin",
            "plugin": "api_single",
            "label": "Sync",
            "params": {
                "url": "/api/sync/{{id}}",
                "method": "GET"
            }
        }
    ]
}
```

**Params:**

| Key | Description |
|-----|-------------|
| `url` | URL to call. Twig templates are resolved against current record. Relative URLs are made absolute automatically. |
| `method` | HTTP method: `GET` (default) or `POST` |

---

### `export`

Exports the current model's records (respecting active filters) as a CSV file. The user selects which fields to include before downloading.

```json
{
    "buttons_page": [
        {
            "plugin": "export",
            "label": "Export"
        }
    ]
}
```

Supported field types: `string`, `boolean`, `date`, `integer`, `datetime`, `text`, `media`.

---

### `ffprobe`

Reads technical metadata from a local or remote audio/video file using FFprobe and writes selected values (width, height, duration) back to record fields. Requires `FFPROBE_PATH` set in the environment.

```json
{
    "buttons_edit": [
        {
            "type": "plugin",
            "plugin": "ffprobe",
            "label": "Read metadata",
            "params": {
                "video": "field_video",
                "duration": "field_duration",
                "width": "field_width",
                "height": "field_height"
            }
        }
    ]
}
```

**Params:**

| Key | Description |
|-----|-------------|
| `video` | Field name containing the video file |
| `audio` | Field name containing the audio file (alternative to `video`) |
| `duration` | Field name to write the duration into |
| `width` | Field name to write video width |
| `height` | Field name to write video height |

**Environment:**

```bash
FFPROBE_PATH=/opt/homebrew/bin/ffprobe
```

---

### `import`

Imports records into a model from a CSV file upload or a pasted spreadsheet. Lets the user select which fields to map before running the import.

```json
{
    "buttons_page": [
        {
            "plugin": "import",
            "label": "Import"
        }
    ]
}
```

Supported field types: `string`, `boolean`, `text`, `select`, `date`.

---

### `import_cover`

Imports cover images and metadata from MP4 files, Vimeo, or YouTube. Can populate title, poster image, duration, subtitles, and HLS/MP4 source URLs from Vimeo.

```json
{
    "buttons_edit": [
        {
            "type": "plugin",
            "plugin": "import_cover",
            "label": "Import cover",
            "params": {
                "field_vimeo": "vimeo_id",
                "field_poster": "image",
                "field_title": "title",
                "field_duration": "duration"
            }
        }
    ]
}
```

**Params:**

| Key | Description |
|-----|-------------|
| `field_mp4` | Field storing MP4 video source |
| `field_video` | Field storing the video file |
| `field_youtube` | Field storing the YouTube ID |
| `field_vimeo` | Field storing the Vimeo ID |
| `field_poster` | Image field to write the cover into |
| `field_poster_timestamp` | Timestamp (seconds) for frame extraction |
| `field_duration` | Integer field to write video duration |
| `field_title` | String field to write the video title |
| `field_vtt` | File field for subtitle/VTT track |

---

### `media_resize`

Opens an interactive crop/resize tool for a specific item in a `media`-type field. Can also extract a video frame as a cover image.

```json
{
    "buttons_edit": [
        {
            "type": "plugin",
            "plugin": "media_resize",
            "label": "Crop",
            "params": {
                "field": "media",
                "nr": 0
            }
        }
    ]
}
```

**Params:**

| Key | Description |
|-----|-------------|
| `field` | Name of the `media` field (default: `media`) |
| `nr` | Zero-based index of the media item to edit (default: `0`) |

---

### `preview`

Opens a URL in an iframe panel inside the CMS. Useful for previewing the frontend page that corresponds to the record being edited. Supports opening in a new window.

```json
{
    "buttons_edit": [
        {
            "type": "plugin",
            "plugin": "preview",
            "label": "Preview",
            "params": {
                "url": "/article/{{slug}}"
            }
        }
    ]
}
```

---

### `refresh`

Batch-processes all records in a model: re-evaluates `cms.auto` fields and re-generates image sizes. Supports record range filtering and respects active page filters.

```json
{
    "buttons_page": [
        {
            "plugin": "refresh",
            "label": "Refresh"
        }
    ]
}
```

---

### `test`

A minimal no-op plugin that returns `{ "result": true, "message": "All good!" }`. Useful as a starting template for new custom plugins.

```json
{
    "buttons_edit": [
        {
            "type": "plugin",
            "plugin": "test",
            "label": "Test"
        }
    ]
}
```

---

## `uho_client` Model-Based Plugins

Those plugins help to manage list of users created and maintained with `_uho_client` class.

### `client_users_anonimize`

Irreversibly clears personal data from a client user record (email, name, institution, uid) and sets their status to `anonimized`. Requires confirmation.

```json
{
    "buttons_edit": [
        {
            "type": "plugin",
            "plugin": "client_users_anonimize",
            "label": "Anonymize",
            "params": {
                "record": "{{id}}",
                "page": "users"
            }
        }
    ]
}
```

---

### `client_users_password`

Same as `cms_users_password`, but for client-facing (front-end) user accounts. Requires additional `plugin_keys` configuration in `config.php`.

```json
{
    "buttons_edit": [
        {
            "type": "plugin",
            "plugin": "client_users_password",
            "label": "Change password",
            "params": {
                "record": "{{id}}"
            }
        }
    ]
}
```

---

### `cms_users_auth`

Provides a UI to configure per-section authorization levels (off / read / write / admin) for a CMS user. Supports authorization presets defined in `authorization_presets.json`.

```json
{
    "buttons_edit": [
        {
            "type": "plugin",
            "plugin": "cms_users_auth",
            "label": "Authorization",
            "params": {
                "record": "{{id}}"
            }
        }
    ]
}
```

---

### `cms_users_password`

Provides a form to set or auto-generate a password for a CMS user. Validates against configured minimum requirements.

```json
{
    "buttons_edit": [
        {
            "type": "plugin",
            "plugin": "cms_users_password",
            "label": "Change password",
            "params": {
                "record": "{{id}}"
            }
        }
    ]
}
```

---

## Troubleshooting

### Plugins Not Working

1. Verify class name matches folder name pattern
2. Check PHP syntax errors
3. Ensure `getData()` method returns array
4. Review plugin.json structure
