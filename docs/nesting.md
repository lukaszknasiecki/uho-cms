# Nesting

## Parent-Children Pattern

Schemas can be nested as parent-children, to make editing of multi-dimensional structures easier.
First, define nesting in proper json file:

**File:** `cms_config/structure/model_tree.json`

```json
{
    "models": {
        "pages": [
            "pages_modules"
        ]        
    }
}
```

This means the `page_modules` schema is a child of `pages` schema.

Now, in definition of `pages` schema add a CMS button to show its children:

```json
{
    "buttons_edit": [
        {
            "label": "Modules",
            "type": "page",
            "icon": "reorder",
            "page": "pages_modules,{{id}}"
        }
    ]
}
```

As you can see - we need to add current "parent" module `id` to destination page URL, so nested modules can track their parent. Thanks to day walking between nested modules in the CMS (via "back" button) will be possible.

Now, you need to add filter to `pages_modules` schema so it shows only proper children.

```json
    "filters": {
        "parent": "{{params.nested.1}}"
    }
```

The `params` array is taken from the URL and consists of array taken from comma separated values.
The last thing you need to do is to add `default` value to the `parent` field so it keeps track of its parent
when new records are added. A good practice is to hide this field so it's invisible to the user and prevents
users from accidentally changing models "parents":

```json
{
            "field":"parent",
            "type":"select",
            "source": {
                "model": "pages"
            },
            "cms": {
                "hidden": true,
                "default":"{{params.nested.1}}"
            }
        }
```
