# System Models

The CMS ships with a set of built-in models accessible from the CMS interface. Their database tables are created automatically on first run. Those models are used by the `_uho_client` class and they can be added to your `structure/menu.json` like any custom models:

```json
{
    "label": "Admin",
    "submenu": [
        {
            "label": "CMS Users",
            "page": "cms_users"
        },
        {
            "label": "CMS Action Log",
            "page": "cms_users_logs"
        },
        {
            "label": "CMS Logins Log",
            "page": "cms_users_logs_logins"
        }
    ]
}
```

---

## User Management

### `cms_users`

The main list of CMS administrator accounts.

| Field | Description |
|-------|-------------|
| `name` | Display name |
| `login` | Login username |
| `password` | Hashed password |
| `auth` / `auth_label` | Serialized per-section authorization levels |
| `status` | Account status |
| `admin` | Admin flag |
| `superadmin` | Superadmin flag — full access, ignores `auth` |
| `email` | Email address |
| `hide_menu` | Hide CMS sidebar (kiosk mode) |

Use the `cms_users_password` and `cms_users_auth` plugins on edit buttons to manage passwords and permissions for each user.

### `cms_users_logs`

Audit trail of CMS actions (page visits, record saves, deletions).

| Field | Description |
|-------|-------------|
| `datetime` | Timestamp of the action |
| `session` | Session identifier |
| `user` | Username |
| `action` | Action performed |

### `cms_users_logs_logins`

Login attempt history, useful for security auditing.

| Field | Description |
|-------|-------------|
| `datetime` | Timestamp |
| `login` | Login used |
| `ip` | IP address |
| `success` | Whether login succeeded |

---

## CMS Configuration

### `cms_settings`

Key-value store for arbitrary CMS configuration values. Useful for storing project-wide settings that need to be editable from the CMS without code changes.

| Field | Description |
|-------|-------------|
| `slug` | Setting key |
| `value` | Setting value |

### `cms_languages`

Defines the languages available in the CMS interface and content.

| Field | Description |
|-------|-------------|
| `label` | Human-readable language name |
| `slug` | Language code (e.g. `en`, `pl`) |
| `active` | Whether the language is enabled |

### `cms_translate`

Translation strings for CMS labels and UI text across languages.

| Field | Description |
|-------|-------------|
| `slug` | Translation key |
| `label:lang` | Translated value (per language) |

---

## Data Management

### `cms_backup`

Stores a record-level change log that can be used to restore previous field values.

| Field | Description |
|-------|-------------|
| `date` | Timestamp of the change |
| `session` | Session identifier |
| `page` | Model/page name |
| `record` | Record ID |
| `data` | Serialized previous record data |

### `uho_worker`

Background job queue. Plugins that trigger long-running tasks can push jobs here; a separate PHP worker process picks them up asynchronously.

| Field | Description |
|-------|-------------|
| `date_created` | When the job was queued |
| `date_completed` | When the job finished |
| `action` | Job type / action name |
| `status` | `pending`, `processing`, `done`, `error` |
| `message` | Result or error message |

To process the queue, run the worker via CLI (requires `PHP` env variable set):

```bash
php cms/worker.php
```

---

## Adding System Models to Your Menu

Any system model can be referenced in `structure/menu.json`:

```json
{
    "label": "System",
    "submenu": [
        { "label": "Users",        "page": "cms_users" },
        { "label": "Settings",     "page": "cms_settings" },
        { "label": "Translations", "page": "cms_translate" },
        { "label": "Backup log",   "page": "cms_backup" },
        { "label": "Job queue",    "page": "uho_worker" }
    ]
}
```
