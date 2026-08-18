# How to upgrade an installation

`bin/upgrade.sh` brings an installed Smart Connect up to the latest released code. It backs up what cannot be replaced, deploys the new source over the tree, installs dependencies, applies migrations, resets permissions, and reloads the web server.

A server usually hosts one dashboard. Where it hosts more, the script upgrades all of them in one run and keeps going when one fails.

The script does no system-level work. PHP, MySQL, and Apache belong to the setup script and are left alone.

## Prerequisites

- Root access to the server
- `php`, `composer`, and `rsync` installed
- Working off-machine backups, ideally. See [Set up off-machine backups](backups.md)

## Upgrade the server

Run this as root:

```bash
sudo bash -c "$(curl -fsSL "https://raw.githubusercontent.com/deforay/smart-connect/master/bin/upgrade.sh?v=$(date +%s)")"
```

Pass the script to `bash -c` as an argument, exactly as shown above. Piping it in as `curl ... | sudo bash` breaks the prompts, and the script then answers its own questions with whatever the pipe holds.

The `?v=` on the URL defeats the raw CDN cache, so the run always fetches the current script.

The script finds every Smart Connect installation under `/var/www` and upgrades each one. Almost every server hosts a single installation, so no options are needed. A server that also runs `smart-connect-training` gets both in the same run. The script lists what it found and asks for confirmation before starting.

## Upgrade one installation

Name the installation with `-p`:

```bash
sudo bin/upgrade.sh -p /var/www/smart-connect-training
```

Use `-p` for an installation outside `/var/www`, which the scan does not reach.

## Options

| Option | Effect |
|---|---|
| `-p PATH` | Upgrades only this installation. Without it, every installation found in `/var/www` |
| `-b` | Skips the database dump. The `config/autoload` tarball is still taken |
| `-y` | Answers every prompt with its default and never blocks |

`-y` is for unattended runs. Every default is the cautious answer, so a failed database dump stops that installation rather than upgrading it without one.

## What the script preserves

Four files under `config/autoload` belong to the deployment. The deploy never overwrites them:

| File | Holds |
|---|---|
| `global.php` | The DSN, which names the database |
| `local.php` | The database username and password |
| `custom.global.php` | The enrollment key and the SMTP settings |
| `*.local.php` | Any other per-deployment override |

Everything else under `config/autoload` is upstream code and does get updated.

These directories are left alone as well: `vendor/`, `data/cache/`, `data/logs/`, `public/uploads/`, `public/temporary/`, `temporary/`, and `backup/`.

The deploy runs without `--delete`, so files the release does not ship stay where they are.

## What the script backs up

Two things, before it changes anything:

1. `config/autoload`, as a tarball in `/var/smart-connect-backup/config/`. Taken on every run, including under `-b`, because it is a few kilobytes.
2. The database, dumped with `db-tools` into `/var/smart-connect-backup/db/`. Skipped under `-b`.

The application code is not backed up. It comes back from git, and an upgrade restores a deployment from those two pieces plus a checkout.

Where two installations share one database, it is dumped once per run.

Restore a dump from an upgrade with:

```bash
cd /var/www/smart-connect && php vendor/bin/db-tools restore /var/smart-connect-backup/db/NAME.sql.zst
```

These backups sit on the same machine as the installation, so they survive a bad upgrade but not a dead disk. Off-machine copies are a separate job. See [Set up off-machine backups](backups.md).

## Check the upgrade worked

The run ends with a summary listing every installation it upgraded and every one it failed. The exit status is non-zero if any installation failed.

Confirm the schema reached the version the new code expects:

```bash
cd /var/www/smart-connect && sudo -u www-data php bin/check-version-sync
```

The command prints `Version in sync` and exits 0 when the code and the database agree. Any other output means the migrations did not fully apply. Run `php bin/migrate` again and read the output.

Then open the dashboard in a browser and log in. The footer names the version, and in brackets the ref the installation was deployed from. Quote both when reporting a problem. A warning next to them means the database is behind the code, which is the same fault `bin/check-version-sync` reports.

## Read the warnings

A run can succeed and still print warnings that need acting on.

| Warning | What to do |
|---|---|
| `local.php was missing. Copied it from local.php.dist` | Set the database username and password in it |
| `custom.global.php was missing` | Fill in the SMTP settings, and check the enrollment key |
| `global.php was missing. Copied the upstream one` | Check the DSN names this deployment's database |
| `has settings this deployment does not` | The release added settings. The application uses its built-in defaults until you add them |
| `Version check failed` | The database is not at the code's version. Run `php bin/migrate` again and read the error |
| `still has a .git` | Harmless. The deploy ignores git, so that `HEAD` no longer describes the files on disk |

## When an upgrade fails

Every run writes a log to `/tmp/smart-connect-upgrade-TIMESTAMP.log`. The path is printed at the start and at the end.

One failing installation does not stop the others. The summary names which ones failed, and the log holds the reason.

The script is safe to run again. It refreshes the source, notices that nothing changed, and repeats the rest of the steps.

If the failure was the database dump, the prompt asking whether to continue without one defaults to no. Answering no leaves that installation exactly as it was.
