# Smart Connect

Open source national dashboard for priority diseases (Viral Load, EID, COVID-19).

![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue)

## Documentation

Full documentation is at <https://deforay.github.io/smart-connect/>, built from
`docs/` with [MkDocs](https://www.mkdocs.org/). Start there for:

- [Connecting a LIS](docs/guides/connecting-a-lis.md) — enrollment through first sync
- [Manage the enrollment key](docs/guides/enrollment-key.md) — generate, read, and rotate the key
- [Set up off-machine backups](docs/guides/backups.md) — database and configuration, copied to another machine
- [Restore from a backup](docs/guides/restoring-a-backup.md) — put one back, including onto a rebuilt server
- [Upgrade an installation](docs/guides/upgrading.md) — bring a server up to the latest release
- [API reference](docs/api/index.md) — endpoints, fields, status codes

`docs/internal/` holds material for whoever maintains Smart Connect itself. It
stays out of the published site.

To preview the site locally:

```sh
pip install -r docs/requirements.txt
mkdocs serve
```

## Requirements

- PHP 8.2+ with the usual extensions (pdo_mysql, intl, mbstring, gd, zip)
- MySQL / MariaDB
- Apache with `mod_rewrite` (or equivalent)
- [Composer](https://getcomposer.org/)

## Setting up

1. Download or clone the source code into your server's root folder.
2. Run `composer install`.
3. Create a database and import the initial SQL (`data/setup.sql`).
4. Update the database parameters in `config/autoload/global.php` (DSN) and
   `config/autoload/local.php` (credentials).
5. Run the database migrations: `php bin/migrate`
6. Create the first admin user: `php bin/console seed-admin`
7. Create a virtual host pointing to the `public` folder:

```apache
<VirtualHost *:80>
   DocumentRoot "/var/www/smart-connect/public"
   ServerName smart-connect

   <Directory "/var/www/smart-connect/public">
       Options Indexes MultiViews FollowSymLinks
       AllowOverride All
       Require all granted
   </Directory>
</VirtualHost>
```

Once set up, visit the site URL and log in with the admin user you created.
After importing fresh data, use the "clear cache" link at the bottom of the
page if dashboards look stale.

## Console commands

Application commands run through `bin/console`:

```sh
php bin/console list                       # list all commands
php bin/console housekeeping [--dry-run]   # prune temp files, API payloads, stale DB rows
php bin/console rebuild-snapshots [-f]     # rebuild dash_form_*_current snapshot tables
php bin/console seed-admin                 # create the first admin user
php bin/console send-mail                  # send queued mails from temp_mail
php bin/console api-usage [--days=90]      # who uses API v2 vs the legacy /api/* endpoints
```

Common composer shortcuts:

```sh
composer migrate         # run pending database migrations
composer housekeeping    # run housekeeping
composer cron-list       # show the scheduled task list
composer refresh         # pull latest code, install deps, migrate + housekeeping

composer generate-enrollment-key            # create the API v2 enrollment key if unset
php bin/generate-enrollment-key.php --show  # print the configured key
```

## Scheduled tasks

All recurring jobs are defined in `sys/cron/ScheduledTasks.php` and run through
[crunz](https://github.com/crunzphp/crunz). The only crontab entry a server
needs is:

```text
* * * * * /path/to/smart-connect/cron.sh
```

crunz evaluates the schedule every minute and runs whatever is due
(housekeeping nightly, queued mail every 5 minutes, snapshot rebuild nightly).

The nightly snapshot rebuild is OFF by default; enable it by setting
`daily_snapshot_rebuild` to `yes` in the admin global configuration settings.

## Upgrading

`bin/upgrade.sh` brings an installation up to the latest released code. It backs
up `config/autoload` and the database to `/var/smart-connect-backup/`, deploys
the new source, installs dependencies, applies migrations, resets permissions,
and reloads the web server. It does no system-level work.

```sh
# on a server with no checkout
sudo bash -c "$(curl -fsSL "https://raw.githubusercontent.com/deforay/smart-connect/master/bin/upgrade.sh?v=$(date +%s)")"

sudo bin/upgrade.sh                                      # every installation found
sudo bin/upgrade.sh -p /var/www/smart-connect-training   # just this one
sudo bin/upgrade.sh -y                                   # unattended, safe defaults
```

Pass the script to `bash -c` as an argument, as shown. Piping it in with
`curl ... | sudo bash` breaks the prompts.

This deployment's `config/autoload/global.php`, `local.php`, `custom.global.php`,
and any `*.local.php` are never overwritten, and neither are `vendor/`,
`data/`, `public/uploads/`, `public/temporary/`, `temporary/`, or `backup/`.
One failing installation does not stop the others. Full instructions are in
[Upgrade an installation](docs/guides/upgrading.md).

## Backups

`bin/remote-backup.sh` sets up automatic off-machine backups of the database and
`config/autoload`, to another Linux machine over SSH, a Windows shared folder, or
an external drive.

```sh
sudo bin/remote-backup.sh                          # set up or change the backup
sudo smart-connect-backup.sh --status              # did the last one work?
sudo smart-connect-backup.sh --list                # what is stored
sudo smart-connect-backup.sh --restore latest      # put the newest one back
```

Each run leaves a dated folder holding a fresh dump and a copy of the
configuration, verified by checksum at the destination. Full instructions are in
[Set up off-machine backups](docs/guides/backups.md) and
[Restore from a backup](docs/guides/restoring-a-backup.md).

## Database migrations

Schema changes live in `sys/migrations/` as plain SQL files named
`X.Y.Z-description.sql` and are applied with `php bin/migrate`. The current
schema version is tracked in the `dash_global_config` table (`db_version`).

The version in `composer.json` is the single source of truth. `bin/migrate`
stamps it into `dash_global_config` as `app_version`, and the footer renders it
through the `APP_VERSION` constant. Every version bump needs a migration file
carrying that version, so `db_version` keeps pace. A release with no schema
change still gets one, and an empty file is enough.

```sh
php bin/migrate              # run pending migrations
php bin/migrate --status     # show current version and pending files
php bin/migrate --dry-run    # preview statements without executing
php bin/migrate --verbose    # also print benign skips
php bin/check-version-sync   # is the schema at the version the code expects?
```

`bin/check-version-sync` exits non-zero when the two versions differ.
`bin/upgrade.sh` runs it after every migration, and the footer shows the same
mismatch as a warning next to the version.

Migrations are safe to re-run: common DDL is routed through idempotent
handlers that check `information_schema` first, and benign errors (duplicate
column/key, already-dropped objects) are downgraded to warnings.

`data/alter.sql` is frozen — new schema changes go into `sys/migrations/`.

## Funding and partners

Smart Connect is developed with funding from the United States Government (USG). Over the years, the project has benefited from the support and collaboration of partners including the African Society for Laboratory Medicine (ASLM), the American Society for Microbiology (ASM), the African Field Epidemiology Network (AFENET), Emory University, and the Maryland Global Initiatives Corporation (MGIC), among others.

## License

Smart Connect is free and open-source software released under the **GNU Affero General Public License v3.0 (AGPL-3.0)**.

Read the full text in [LICENSE.md](LICENSE.md).

## Who do I talk to?

You can reach us at hello (at) deforay (dot) com
