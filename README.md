# UHO-CMS Quick Reference

Quick reference guide for common uho-cms configuration tasks.

## Setup Checklist

- [ ] Create `uho-cms.json` in project root
- [ ] Create `cms_config/` folder
- [ ] Create `cms_config/config.php`
- [ ] Set environment variables (database, keys)
- [ ] Create `cms_config/structure/` folder with structure files
- [ ] Create `cms_config/pages/` folder with model configurations
- [ ] Run `composer install` in `/cms` directory
- [ ] Access CMS at `/cms` URL

## File Structure

```
project_root/
├── uho-cms.json              # Root configuration
├── cms/                      # CMS core (framework)
└── cms_config/               # Your configuration
    ├── config.php
    ├── pages/
    │   └── *.json           # Model configurations
    ├── plugins/
    │   └── plugin_name/
    │       ├── plugin.json
    │       ├── plugin.php
    │       └── plugin.html
    └── structure/
        ├── menu.json
        ├── dashboard.json
        ├── authorization.json
        └── model_tree.json
```

## Configuration Files

### uho-cms.json
```json
{
  "CMS_CONFIG_DEBUG": true,
  "CMS_CONFIG_PREFIX": "cms",
  "CMS_CONFIG_FOLDERS": "cms_config",
  "CMS_CONFIG_LANG": "en"
}
```

### cms_config/config.php
```php
<?php
$cfg = [
    'cms' => [
        'title' => 'CMS Title',
        'app_languages' => ['en']
    ]
];
```

## Field Types Cheat Sheet

| Type | Description | Example |
|------|-------------|---------|
| `boolean` | Checkbox | Active, published |
| `checkboxes` | Multiple choice | Tags |
| `date` | Date (YYYY-mm-dd) | Publish date |
| `datetime` | Date + time | Created at |
| `elements` | Ordered multiple | Related items |
| `file` | Fila upload | PDF file |
| `float` | Decimal number | Price |
| `html` | Rich text editor | Content |
| `integer` | Whole number | Count, ID |
| `image` | Image upload | Featured image |
| `media` | Media attachment | Gallery |
| `order` | Sort order | Drag & drop |
| `string` | Text (256 chars) | Title, name |
| `select` | Single choice | Category, status |
| `text` | Multi-line text | Description |
| `table` | Table data | Schedule |
| `uid` | Auto UID | Unique identifier |
| `video` | Video upload | Video file |

## Common Field Patterns

### Required Text Field
```json
{
    "field": "title",
    "cms": {
        "label": "Title",
        "required": true,
        "list": "show"
    }
}
```

### Auto-Generated Slug
```json
{
    "field": "slug",
    "cms": {
        "label": "URL",
        "on_demand": true,
        "auto": {
            "on_null": true,
            "pattern": "{{title}}",
            "unique": true,
            "url": true
        }
    }
}
```

### Image with Sizes
```json
{
    "field": "image",
    "type": "image",
    "settings": {
        "folder": "/public/upload/images",
        "filename": "%uid%"
    },
    "images": [
        {"folder": "original", "label": "Original"},
        {"folder": "thumb", "width": 400, "height": 300, "crop": true}
    ]
}
```

### Select from Model
```json
{
    "field": "category_id",
    "type": "select",
    "source": {
        "model": "categories",
        "search": ["name"]
    }
}
```

### Multiple Selection
```json
{
    "field": "tags",
    "type": "checkboxes",
    "source": {
        "model": "tags",
        "model_fields": ["label"]
    }
}
```

### Publish Toggle
```json
{
    "field": "active",
    "type": "boolean",
    "cms": {
        "label": "Publish",
        "list": "edit"
    }
}
```

## List View Options

```json
{
    "cms": {
        "list": "show"        // Show in list
        "list": "read"        // Read but don't show
        "list": "edit"        // Editable toggle (boolean)
        "list": "order"       // Drag & drop (order type)
        "list": {             // Custom display
            "type": "show",
            "value": "{{title}} - {{status}}",
            "width": 30
        }
    }
}
```

## Button Types

### Page Button (Navigate)
```json
{
    "label": "Related Items",
    "type": "page",
    "icon": "reorder",
    "page": "related_model,%id%"
}
```

### Plugin Button
```json
{
    "type": "plugin",
    "plugin": "plugin_name",
    "params": {
        "key": "value"
    }
}
```

## Structure Files

### menu.json
```json
[
    {
        "label": "Content",
        "submenu": [
            {"label": "Pages", "page": "pages"}
        ]
    }
]
```

### dashboard.json
```json
{
    "type": "widgets",
    "widgets": [
        "hello",
        {
            "widget": "page",
            "params": {"page": "pages", "icon": "article"}
        }
    ]
}
```

### authorization.json
```json
[
    {
        "id": "admin",
        "label": "Administrator",
        "models": ["pages", "media", "settings"]
    }
]
```

### model_tree.json
```json
{
    "models": {
        "pages": ["pages_modules"],
        "interviews": ["sessions"]
    }
}
```

## Field Organization

### Tabs
```json
{
    "cms": {
        "tab": "Content"
    }
}
```

### Sections
```json
{
    "cms": {
        "header": "Section Title",
        "hr": true
    }
}
```

## Image List Display
```json
{
    "cms": {
        "list": {
            "height": 110,
            "src_blank": "blank169.png"
        }
    }
}
```

## Search/Filter
```json
{
    "cms": {
        "search": true
    }
}
```

## Help Text
```json
{
    "cms": {
        "help": {
            "text": "Helpful information",
            "size": "full"
        }
    }
}
```

## Environment Variables

```bash
# Database
SQL_HOST=localhost
SQL_USER=user
SQL_PASS=password
SQL_BASE=database

# Security
CLIENT_PASSWORD_SALT=salt
CLIENT_KEY1=16charskey1
CLIENT_KEY2=16charskey2

# CMS
CMS_CONFIG_DEBUG=true
CMS_CONFIG_STRICT=false
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| CMS not loading | Check `uho-cms.json` exists |
| Model not appearing | Verify JSON syntax, check table exists |
| Plugin error | Check class name matches folder |
| Image upload fails | Verify folder permissions, check path |
| Field not showing | Check `cms.list` property |

