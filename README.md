# UHO CMS

Easy to use CMS utilizing UHO-MVC framework, Bootstrap and Twig.

## Available field types

`boolean`

Boolean type

`checkboxes`

Multi-elements choice of records from other model
with no fixed order

`date`

Date type in `YYYY-mm-dd` format

`datetime`

Datetime type in `YYYY-mm-dd H:i:s` format 

`elements`

Multi-elements choice of records from other model with fixed order

`float`

Float number type

`html`

HTML edit field

`integer`

Integer type INT(11)

`image`

Creates virtual image schema, no SQL field is used.

Image sizes are defined in `.images` array with object:

    {
        "folder":"folder-name",
        "width":integer,
        "height":integer,
        "retina":true|false,
        "crop":true|false
    }

Additional settings:

    {
        "folder": absolute-path-to-images-folder
        "settings": {"webp":true|false}
    }

`media`

Attaches external model with media to specified field. You need to specify this media model as source.model, and add types of media to be available to upload (`image`, `video`, `audio`, `file`), example:

    "source": {
                    "model": "media",
                    "types": [
                        "image"
                    ]
                }

You can also add additional text fields from this model to be edited in captions array:

    "captions": [
                    {
                        "label": "Caption",
                        "field": "label"
                    },
                    {
                        "label": "Copy",
                        "field": "text",
                        "field_type": "text"
                    }
                ]            

`order`

Defines records order, `INT(11)`

`string`

String (256 chars) type. If no type is defined this type
is being used as a default one. The only exception is field
name `id` which by default is integer.

Additional settings:

`(int) settings.length` - changes default size (of 256)
`(bool) settings.code` - changes input field's font to Courier

`text`

Multi-line text type, no HTML.

Additional settings:

    settings: {"rows":integer}

`uid`

Automatacally generated UID with PHP's uniq()
varchar(13)

## Additional field parameters:

`auto`

Updates field value on record save.

Properties:

`on_null (bool)` performs action only if field value is empty

`pattern (string)` uses TWIG template to create value, using other fields from the same model

`unique (bool)` checks if other record in this model have the same value, if yes - adds unique suffix

`url (string)` converts value to URL-type string

Example building unique URL string from object's title:

    "auto": {
                    "on_null": true,
                    "pattern": "{{title}}",
                    "unique": true,
                    "url": true                
                }

`cms`

Properties to be performed if object has beed loaded in the CMS only

`.edit.remove (bool)` removes field from edit mode

`hr`

Boolean property (true|false) shows divider line above the field

`list`

Object setting visibility of the field value in page (list) view.
Can be used with a shortcut as a string - defining list.type

    {
        "type": show|read|edit|order
        "value": string
        "width": integer
    }

Available values for `type`:

`show` shows value in the table view

`read` reads value only withot showing it, use if want to use this value for Twig pattern in other fields

`edit` shows value in editable form, applicable only for boolean-type fields

`order` for fields of type "order" - enabled drag&dtop sorting option

You can override record's view value with .value string which is using Twig template
and record's data. You can also set field's width a percent of whole table width.
If empty, default widths will be calculated based on field types.


`search`

Boolean property, shows record in filters tab in Page (list) mode

`settings`

Object with common settings for various types of fields.
