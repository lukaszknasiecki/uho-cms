# Structure Files

Structure files in `cms_config/structure/` define CMS navigation, dashboard view,
permissions, and relationships.

---

## menu.json

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

---

## dashboard.json

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

---

## /dashboards

You can define custom dashboards in `/dashboard` folder and redirect
to them via custom routing in schema's `cms.nav` property.

Here is sample custom dashboard, visible at `/cms/dashboard/projects`
or `/cms/dashboard/projects/123` pages. You can use dashboards per schema
or per model.

```json
{
    "type": "widgets",
    "widgets": [
        {
            "widget": "project"
        },
        {
            "widget": "people",
            "params": {
                "icon": "people",
                "label": "People",
                "page": "people"
            }
        },
        {
            "widget": "categories",
            "params": {
                "icon": "category",
                "label": "Categories",
                "page": "categories"
            }
        }
    ]
}
```


---

## authorization.json

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

---

## model_tree.json

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
