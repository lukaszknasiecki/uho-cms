# Custom Access

## Configuration Structure

By default CMS is using access set up with authorization schema.

Sometimes you may need to control access to every model in detail,
to do so you can use `access` models.

## Define access model name

Let's say we want to control access to our projects, where each project
has `editors` field of type `elements` connected to CMS users, and we want 
CMS users to have access to "their" own projects.

First, define access model in model's schema, let's call it `projects`.

```json
"cms": {
        "access":"projects"
    }
```

## Create access class

Now, you need to create PHP class to control access
to `project` models.

Create `access/access_projects.php` file:

```php
<?php

class serdelia_access_projects extends serdelia_access
{

    /*
        Create additional filters for list view page
    */
    public function getFilters()
    {
        return ['editors' => $this->parent->getUser()['id']];
    }

    /*
        Check if user can access list view page
    */
    public function checkAccessList()
    {
        return true;
    }

    /*
        Check access for given record
    */
    public function isAccessRecord($record_id)
    {
        $f = $this->getFilters();
        $f['id'] = $record_id;
        $access = $this->orm->get(['schema' => 'projects', 'filters' => $f, 'first' => true, 'fields' => ['id']]);
        if ($access) return true;
        else return false;
    }

    /*
        Return additional data always written to this model
        In this case - user who is creating/editing this record can always access it
    */
    public function getAccessWrite()
    {
        return ['editors' => $this->parent->getUser()['id']];
    }
}
```
