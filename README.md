# Smart Connect

Open source national dashboard for priority diseases (Viral Load, EID, COVID-19).

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
```

Common composer shortcuts:

```sh
composer migrate         # run pending database migrations
composer housekeeping    # run housekeeping
composer cron-list       # show the scheduled task list
composer refresh         # pull latest code, install deps, migrate + housekeeping
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

## Database migrations

Schema changes live in `sys/migrations/` as plain SQL files named
`X.Y.Z-description.sql` and are applied with `php bin/migrate`. The current
schema version is tracked in the `dash_global_config` table (`db_version`).

```sh
php bin/migrate              # run pending migrations
php bin/migrate --status     # show current version and pending files
php bin/migrate --dry-run    # preview statements without executing
php bin/migrate --verbose    # also print benign skips
```

Migrations are safe to re-run: common DDL is routed through idempotent
handlers that check `information_schema` first, and benign errors (duplicate
column/key, already-dropped objects) are downgraded to warnings.

`data/alter.sql` is frozen — new schema changes go into `sys/migrations/`.

## Who do I talk to?

You can reach us at hello (at) deforay (dot) com
