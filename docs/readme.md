# UHO-CMS Documentation

Complete guide to creating and configuring uho-cms instances.

## Overview

UHO-CMS is a content management system built on the UHO-MVC framework, utilizing Bootstrap and Twig templating. The CMS follows a configuration-driven architecture where all CMS behavior is defined through JSON and PHP configuration files located in a `cms_config` folder.

### Key Concepts

* **CMS Core**: Located in `/cms` folder, contains the framework and core functionality
* **Configuration Folder**: Located in `/cms_config` folder, contains all project-specific configurations
* **Multi-Instance Support**: Can manage multiple CMS instances from a single core installation
* **Model-Driven**: Each content type is defined as a "model" with fields, layouts, and behaviors

---

## Table of Contents

1. [Installation & Setup](installation.md)
   - CMS installation, adding instances
   - Full environment variables reference (database, SMTP, S3, media, video APIs)
   - Troubleshooting

2. [Configuration](configuration.md)
   - `cms_config` folder structure
   - `config.php` options
   - CKEditor setup, assets, logs

3. [Page / Model Configuration](models.md)
   - Model JSON structure and top-level properties
   - List view layout (grid, table, badges)
   - Search shortcuts
   - Page and edit buttons (lifecycle: `on_load`, `on_update`)
   - Field configuration and `cms` properties
   - Best practices

4. [Field Types](field-types.md)
   - Basic types: `string`, `boolean`, `text`, `json`, `html`, `integer`, `float`, `date`, `datetime`, `timestamp`, `uid`
   - Selection types: `select`, `checkboxes`, `elements`
   - Media types: `image`, `file`, `video`, `media`
   - Other types: `hidden`, `order`, `plugin`, `table`, `url`, `virtual`
   - Auto fields

5. [Plugins](plugins.md)
   - Custom plugin structure and class naming
   - Plugin parameters and available CMS methods
   - Built-in plugins: `api_single`, `export`, `ffprobe`, `import`, `import_cover`, `media_resize`, `preview`, `refresh`, `test`
   - User management plugins: `client_users_anonimize`, `client_users_password`, `cms_users_auth`, `cms_users_password`

6. [Structure Files](structure.md)
   - `menu.json` — navigation
   - `dashboard.json` — widgets
   - `authorization.json` — permission presets
   - `model_tree.json` — parent-child relationships

7. [System Models](system-models.md)
   - User management: `cms_users`, `cms_users_logs`, `cms_users_logs_logins`
   - CMS configuration: `cms_settings`, `cms_languages`, `cms_translate`
   - Data management: `cms_backup`, `uho_worker`

8. [Examples](examples.md)
   - Blog post model
   - Model with relationships
   - Custom plugin
