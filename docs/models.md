# Page / Model Configuration

Each content type in the CMS is defined as a "model" using a JSON configuration file in the `pages/` folder. The filename (without `.json`) becomes the model name.

## Basic Structure

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

## Top-Level Properties

* `table`: Database table name (required)
* `label`: Object with `page` (list view title) and `edit` (edit view title, supports Twig)
* `order`: Default order of field(s), comma-separated for multiple fields
* `model`: Object defining how records of this schema are displayed (supports Twig templates) in other models
* `layout`: List view layout configuration
* `fields`: Array of field definitions
* `buttons_page`: Array of buttons shown in list view
* `buttons_edit`: Array of buttons shown in edit view

## Page Title (label) Configuration

You can define separate title for list and edit views. Moreover you can use `helper_models` object to get properties from other models, especially useful for nested structures. In the following case CMS is using `params.1` value to get the parent model from the URL.

```json
{
    "label": {
        "page": "<code>/{{helper_models.page.path}}</code> Modules",
        "edit": "<code>/{{helper_models.page.path}}</code>: {% if not type %}New module{% else %}{{type.label}}{% endif %}"
    },
    "helper_models": {
        "page": {
            "model": "pages",
            "parent": "{{params.nested.1}}"     // url params, in this case - parent's id
        }
    }
}
```

## List View Layout Configuration

The standard layout is in the form of a list. An additional layout type (grid) is available. You can use built-on HTML for grid cells (based on `list` properties) or define custom HTML using values record values stored in `record.values` object. You can enhance the `grid` layout with `cards` settings, showing a more structured view.

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

For any field to be visible or accessible in the List View you need to set `cms.list` property,
with one of available options:

- `show` - shows field's value in the list view
- `read` - reads field's value, so it can be used by other fields to render output

You can also specify field's list view to render as a bootstrap badge, i.e.:

```json
{
    "cms":
    {
        "list":
        {
            "type": "badge",
            "values":{                  // badge label based on value
                "0":"No Media",
                "1":"Media"
            },
            "colors":{                  // badge color based on value
                "0":"danger",
                "1":"success"
            }
        }
    }
}
```

```json
{
    "cms":
    {
        "list":
        {
            "type": "badge",
            "color": "primary",
            "label": "Interviews",
            "break": true
        }
    }
}
```

## List View Search Shortcuts

You can add shortcuts buttons for quick filters. Usually you would use fields with search
option enabled and create filters based on combination of those fields values. Those search
fields use `s_` prefix in the URL and that's how you should use them in the `shortcuts` object:

```json
{
    "shortcuts":
        [
            {"label":"Draft","color":"secondary","link":{"query":{"s_status":"draft"}}},
            {"label":"Completed","color":"success","link":{"query":{"s_status":"completed"}}}
        ]
}
```

## Buttons Configuration

Each page can have custom buttons which can execute custom (or CMS-based) plugins or simply move users to other CMS pages. Please note that you can use button icons from https://mervick.github.io/material-design-icons/.

### Page Buttons (List View)

```json
{
    "buttons_page": [
        {
            "label": "Plugin Label",
            "icon": "icon-name",
            "type": "plugin",         // "plugin" or "page"
            "plugin": "plugin_name",
            "class": "danger",        // Bootstrap button style: "default", "primary", "success", "warning", "danger"
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

### Edit Buttons

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

In addition, `buttons_edit` accept lifecycle properties that control when and how they run.
You can use `on_load` or `on_update` together with `hidden` for silent background execution.

| Property | Type | Description |
|----------|------|-------------|
| `on_load` | `true` | Execute the plugin automatically when the page or edit form loads, before rendering. |
| `on_update` | `true` | Execute the plugin automatically after a record is saved. |

**Example — silent auto-run on load and after save:**

```json
{
    "buttons_edit": [
        {
            "label": "Sync index",
            "plugin": "index_sync",
            "on_load": true,
            "on_update": true,
            "hidden": true
        }
    ]
}
```

## Field Configuration

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
        "code": false,
        "rows": 5,
        "required": true,
        "list": "show|read|edit|order|object",
        "search": true,              // Include in search filters
        "tab": "Tab Name",           // Group fields in tabs
        "hr": true,                  // Show divider above field
        "header": "Section Header",  // Section header text
        "on_demand": true,           // Only show when requested
        "edit": {
            "remove": true           // Hide from edit form
        },
        "toggle_fields": {          // Shows/Hides other fields based on this field's value
            "Video": {
                "show":["video","cover"],
                "hide":["text"]
            },
            "Audio": {
                "show":["audio"],
                "hide":["video","cover","text"]
            },
            "Text": {
                "show":["text"],
                "hide":["audio","video","cover"]
            }
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

**Field `cms` Properties:**

* `label`: Display label for the field
* `required`: Field is required
* `list`: Visibility in list view:
  * `show`, shows field's value in the list
  * `read`, reads field's value, to be used by other fields
  * `edit`, for `boolean` type only, allows to edit field value directly in the list view
  * `order`, for `order` type only, enables drag&drop feature
  * `{type:"",value:"",width:""}`
* `search`: Include in search/filter panel
* `tab`: Group field in a named tab
* `hr`: Show horizontal divider above field
* `header`: Show section header above field
* `on_demand`: Only display when explicitly requested
* `edit.remove`: Hide field from edit form
* `help`: Show help text with field
* `auto`: Auto-generate field value (see [Field Types — Auto Fields](field-types.md#auto-fields))

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

## Best Practices

### Naming Conventions

* **Model files**: Use lowercase with underscores: `my_model.json`
* **Database tables**: Match model names exactly
* **Field names**: Use snake_case: `first_name`, `publish_date`
* **Plugin folders**: Use lowercase with hyphens: `my-plugin`

### Field Organization

* Use `tab` to group related fields
* Use `hr: true` to visually separate sections
* Use `header` for section titles
* Place most important fields first

### Performance

* Use `list: "read"` for fields needed in templates but not displayed
* Use `on_demand: true` for rarely-edited fields
* Define appropriate `order` for list views
* Use `search: true` only for fields that need filtering

### Relationships

* Use `model_tree.json` to define clear parent-child relationships
* Use `elements` for ordered relationships
* Use `checkboxes` for unordered multi-select
* Consider performance when loading related models

### Localization

* Use language-specific labels: `label_EN`, `label_PL`
* Define language arrays in config.php
* Use Twig templates for dynamic labels
