# Examples

## Example 1: Simple Blog Post Model

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

---

## Example 2: Model with Relationships

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

---

## Example 3: Custom Plugin

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
