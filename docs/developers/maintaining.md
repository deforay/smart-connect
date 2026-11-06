# Maintainer reference

Commands, scheduled tasks and schema versioning for whoever maintains Smart Connect. See [Architecture](architecture.md) for how the pieces fit together.

## Requirements

| Component | Version |
|---|---|
| PHP | 8.4.27 or later, with `pdo_mysql`, `intl`, `mbstring`, `gd` and `zip` |
| Database | MySQL 8. `bin/setup.sh` installs it. MariaDB also works for a manual install. |
| Web server | Apache with `mod_rewrite`, or equivalent |
| Composer | Any current release |

## Install from source

To install on a server, use `bin/setup.sh`. See [Install Smart Connect on a new server](../guides/installing.md). On a development machine, do the same steps by hand:

1. Clone the source code into the web server's root folder.
2. Run `composer install`.
3. Create the database.
4. Import `sys/schema/init.sql` into it.
5. Set the DSN in `config/autoload/global.php`.
6. Set the credentials in `config/autoload/local.php`.
7. Run `php bin/migrate`.
8. Run `php bin/console seed-admin` to create the first admin user.
9. Point a virtual host at the `public` folder:

```apache
<VirtualHost *:80>
   DocumentRoot "/var/www/smart-connect/public"
   ServerName smart-connect

   <Directory "/var/www/smart-connect/public">
       Options FollowSymLinks
       AllowOverride All
       Require all granted
   </Directory>
</VirtualHost>
```

If dashboards look stale after a data import, use the "clear cache" link at the bottom of the page.

!!! warning
    `data/setup.sql` is not an installer. It copies table structures from a co-located `vlsm` database and drops the `dash_form_*` tables first. Never run it on a populated database.

## The bundled schema

`sys/schema/init.sql` is the empty database that setup imports. `php bin/dev/export-schema` regenerates it from a development database at the current schema version:

- It reads the structure of every table except the retired Covid-19 ones.
- It takes the structure of tables the code uses but the development database lacks from the `vlsm` database on the same server. smart-connect mirrors those from vlsm.
- It seeds the roles, privileges, resources, locales and global configuration. Country-specific values, such as the logos, start empty.
- It writes `CREATE TABLE IF NOT EXISTS`, keeps each index once, and uses `utf8mb4_general_ci` throughout, so the file also imports on MariaDB.

Regenerate it after a release whose migrations change the schema, so a new install starts at that version. Pass `--vlsm-db=<name>` if the vlsm database has another name.

## Console commands

| Command | Description |
|---|---|
| `php bin/console list` | List every command. |
| `php bin/console housekeeping [--dry-run]` | Prune temporary files, API payloads and stale database rows. |
| `php bin/console rebuild-snapshots [-f]` | Rebuild the `dash_form_*_current` snapshot tables. |
| `php bin/console seed-admin` | Create the first admin user. |
| `php bin/console send-mail` | Send the queued mail in `temp_mail`. |
| `php bin/console api-usage [--days=90]` | Show which laboratories use API v2 and which use the legacy `/api/*` endpoints. |
| `php bin/generate-enrollment-key.php --show` | Print the configured enrollment key. |

## Composer scripts

| Script | Description |
|---|---|
| `composer migrate` | Run pending database migrations. |
| `composer housekeeping` | Run housekeeping. |
| `composer cron-list` | Show the scheduled task list. |
| `composer refresh` | Pull the latest code, install dependencies, migrate and run housekeeping. |
| `composer generate-enrollment-key` | Create the API v2 enrollment key if none is set. |

## Scheduled tasks

`sys/cron/ScheduledTasks.php` defines every recurring job. [crunz](https://github.com/crunzphp/crunz) runs them. A server needs one crontab entry:

```text
* * * * * /path/to/smart-connect/cron.sh
```

crunz checks the schedule every minute and runs whatever is due.

| Task | Schedule | Default |
|---|---|---|
| Housekeeping | Daily at 02:00 | On |
| Queued mail | Every 5 minutes | On |
| Snapshot rebuild | Daily at 01:00 | Off. Set `daily_snapshot_rebuild` to `yes` in the admin global configuration to turn it on. |

## Database migrations

`sys/migrations/` holds the schema changes as plain SQL files named `X.Y.Z-description.sql`. `php bin/migrate` applies them. The `db_version` row in `dash_global_config` records the current schema version.

| Command | Description |
|---|---|
| `php bin/migrate` | Run pending migrations. |
| `php bin/migrate --status` | Show the current version and the pending files. |
| `php bin/migrate --dry-run` | Print the statements without running them. |
| `php bin/migrate --verbose` | Also print benign skips. |
| `php bin/check-version-sync` | Exit non-zero if the schema version differs from the code version. |

The version in `composer.json` is the only source of truth. `bin/migrate` stamps it into `dash_global_config` as `app_version`. The footer shows it through the `APP_VERSION` constant.

Every version bump needs a migration file carrying that version, so `db_version` keeps pace. A release with no schema change still gets one. An empty file is enough.

`bin/upgrade.sh` runs `bin/check-version-sync` after every migration. The footer shows the same mismatch as a warning next to the version.

Migrations are safe to re-run. `bin/migrate` routes common DDL through handlers that check `information_schema` first. It downgrades benign errors, such as a duplicate column or key or an already-dropped object, to warnings.

`data/alter.sql` is frozen. New schema changes go into `sys/migrations/`.
