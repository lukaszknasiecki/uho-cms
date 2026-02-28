# Installation & Setup

## 1. CMS Installation

The CMS core is located in the `/cms` folder. To set up a new CMS instance:

1. Ensure the `/cms` folder contains clone of https://github.com/lukaszknasiecki/uho-cms
2. Then run `composer install` in the `/cms` directory to install dependencies

You can also execute both operations with one command:

`composer create-project lukaszknasiecki/uho-cms cms`

## 2. Root Configuration File

Create a configuration file in your project root (not in `/cms`):

* `/uho-cms.json`

**Example** `/uho-cms.json`:

```json
{
  "CMS_CONFIG_DEBUG": true,
  "CMS_CONFIG_PREFIX": "cms",
  "CMS_CONFIG_FOLDERS": "cms_config",
  "CMS_CONFIG_LANG": "en",
  "CMS_CONFIG_THEME": "light"
}
```

**Configuration Options:**

* `CMS_CONFIG_DEBUG`: Enable/disable debug mode (boolean)
* `CMS_CONFIG_PREFIX`: URL prefix for CMS access (default: "cms")
* `CMS_CONFIG_FOLDERS`: Comma-separated list of configuration folder names (default: "cms_config")
* `CMS_CONFIG_LANG`: Default language code (default: "en")
* `CMS_CONFIG_THEME`: Theme mode - "light" or "dark" (default: "light")

**Multiple CMS instances:**

You can have multiple instances of the CMS on one server, simply add a list of config
folders separated by commas:

```json
{
  "CMS_CONFIG_FOLDERS": "cms_config_1,cms_config_2"
}
```

**Environment Variable Support:**

You can use environment variables by prefixing values with `ENV.`:

```json
{
  "CMS_CONFIG_PREFIX": "ENV.CMS_PREFIX",
  "CMS_CONFIG_DEBUG": "ENV.CMS_DEBUG"
}
```

## 3. Environment Variables

Now you need to set up required environment variables:

```bash
# MySQL/MariaDB Database Configuration
SQL_HOST=mysql_host
SQL_USER=mysql_user
SQL_PASS=mysql_password
SQL_BASE=mysql_dbname
SQL_DEBUG=0|1

# Security Keys
CLIENT_PASSWORD_SALT=xxx
CLIENT_KEY1=xxxxxxxxxxxxxxxx
CLIENT_KEY2=xxxxxxxxxxxxxxxx
```

See [Environment Variables Reference](#environment-variables-reference) below for the full list.

## 4. Define Schemas

Now, you can build tables in your database, you can also skip that point, then tables
will be built on first CMS run.

Example (using `cms_config/.env` as your ENV file path):

```
vendor/lukaszknasiecki/uho-mvc/bin/schema-build cms_config/.env cms/application/models/_schemas.json cms/application/models/json
```

## 5. Access the CMS

After installation, access the CMS at:

```
https://yoursite.com/cms
```

On first access, you'll be prompted to:

1. Select a project (if multiple configuration folders are defined)
2. Set up the admin password

---

## Environment Variables Reference

All sensitive configuration (credentials, keys, paths) is passed to the CMS via environment variables. Set them in your server environment, or a `cms_config/.env` if you are on a shared instance.

### Required

These variables must be set for the CMS to start.

| Variable | Description |
|---|---|
| `DOMAIN` | Domain name of this CMS instance — must match the HTTP host (e.g. `example.com`) |
| `SQL_HOST` | Database host (e.g. `localhost`) |
| `SQL_USER` | Database username |
| `SQL_PASS` | Database password |
| `SQL_BASE` | Database name |
| `CLIENT_PASSWORD_SALT` | Salt used when hashing frontend user passwords |
| `CLIENT_KEY1` | Encryption key — **must be exactly 16 characters** |
| `CLIENT_KEY2` | Encryption key — **must be exactly 16 characters** |

### CMS Behaviour

| Variable | Default | Description |
|---|---|---|
| `CMS_CONFIG_DEBUG` | `false` | Enable debug mode — shows errors, disables some caching. Set to `false` in production. |
| `CMS_CONFIG_STRICT` | `false` | Strict schema validation — rejects unknown fields in model JSON files |
| `CMS_CONFIG_PREFIX` | `cms` | URL path prefix used to access the CMS (e.g. `cms` → `/cms`) |
| `SQL_DEBUG` | `false` | Log all SQL queries — useful during development, disable in production |
| `PHP` | system `php` | Absolute path to the PHP binary used by background plugins (e.g. `/usr/bin/php`) |
| `INT_API_TOKEN` | — | Token for internal API calls between CMS components or plugins |

### Media Processing

Required only when using the `ffprobe` or `import_cover` plugins.

| Variable | Description |
|---|---|
| `FFMPEG_PATH` | Absolute path to the `ffmpeg` binary (e.g. `/usr/bin/ffmpeg`) |
| `FFPROBE_PATH` | Absolute path to the `ffprobe` binary (e.g. `/usr/bin/ffprobe`) |

### Email / SMTP

Required only when the CMS or any plugin sends email.

| Variable | Description |
|---|---|
| `SMTP_SERVER` | SMTP server hostname (e.g. `smtp.mailgun.org`) |
| `SMTP_PORT` | SMTP port — typically `587` (TLS) or `465` (SSL) |
| `SMTP_SECURE` | Encryption type: `tls` or `ssl` |
| `SMTP_LOGIN` | SMTP username; also used as the sender email address |
| `SMTP_PASS` | SMTP password |
| `SMTP_NAME` | Sender display name (e.g. `"My Website"`) |

### S3 / Cloud Storage

Optional. When `S3_HOST` is set, file uploads are stored in S3 instead of the local filesystem.

| Variable | Description |
|---|---|
| `S3_HOST` | S3 endpoint hostname (e.g. `s3.amazonaws.com` or a custom endpoint) |
| `S3_KEY` | S3 access key ID |
| `S3_SECRET` | S3 secret access key |
| `S3_BUCKET` | Bucket name |
| `S3_REGION` | Bucket region (e.g. `us-east-1`) |
| `S3_FOLDER` | Path prefix within the bucket for uploaded files (e.g. `/uploads`) |
| `S3_ACL` | Object ACL — `public-read` for publicly accessible files, `private` otherwise |

### Video APIs

Required only when using the `import_cover` plugin with YouTube or Vimeo sources.

| Variable | Description |
|---|---|
| `YOUTUBE_TOKEN` | YouTube Data API v3 key |
| `VIMEO_CLIENT` | Vimeo app client ID |
| `VIMEO_SECRET` | Vimeo app secret |
| `VIMEO_TOKEN` | Vimeo personal access token |

---

## Troubleshooting

### CMS Not Loading

1. Check `uho-cms.json` exists in project root
2. Verify `cms_config` folder exists
3. Check `cms_config/config.php` syntax
4. Review error logs in `cms_config-logs/`

### Models Not Appearing

1. Verify JSON syntax in page configuration files
2. Check database table exists
3. Ensure field types match database columns
4. Check authorization.json includes model

### Image Upload Issues

1. Verify folder paths are absolute
2. Check folder permissions (write access)
3. Ensure `uid` field exists in model
4. Verify image size configurations
