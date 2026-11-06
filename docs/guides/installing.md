# How to install Smart Connect on a new server

`bin/setup.sh` installs Smart Connect on a new Ubuntu server: the web server, MySQL and PHP, the application, an empty database, the first admin user and the scheduler. It ends by offering to set up off-machine backups.

To bring an existing installation up to date, see [Upgrade an installation](upgrading.md) instead. Setup refuses to touch an existing installation.

## Prerequisites

- Ubuntu 22.04 or later, with root access
- An internet connection from the server
- The email, username and password you want for the first admin user

## Install

Download the script, then run it as root:

```bash
cd ~ && wget -O setup.sh "https://raw.githubusercontent.com/deforay/smart-connect/master/bin/setup.sh?v=$(date +%s)" && sudo bash setup.sh
```

Run the downloaded file. Do not pipe the script into `bash`. Setup stops if you do, because its prompts would read the script's own text.

Setup asks for:

1. The MySQL root password. If MySQL is not installed yet, the password you type becomes the root password. If `~/.my.cnf` already holds it, setup does not ask.
2. The admin email, username and password.

It then runs without further questions until the backup prompt at the end. The installation takes 10 to 20 minutes, most of it installing packages.

When it finishes, it prints the web address. Open it and log in with the admin email and password.

## Choose the address and folder

Setup serves Smart Connect at `http://smart-connect` from `/var/www/smart-connect` by default, using a database named `vldashboard`. To change any of these, pass an option:

```bash
sudo bash setup.sh --server-name=dashboard.example.org
```

| Option | Description | Default |
|---|---|---|
| `--server-name=<name>` | Hostname Apache serves Smart Connect under. Point the name's DNS record at the server. | `smart-connect` |
| `--path=<dir>` | Installation folder. | `/var/www/smart-connect` |
| `--db-name=<name>` | Database name. | `vldashboard` |
| `--database=<file>` | Dump to import instead of the empty schema, as `.sql`, `.sql.gz` or `.sql.zst`. Setup migrates it to the current version. | none |
| `--php=<branch>` | PHP branch to install. | `8.4`, or `8.5` on Ubuntu 26.04 and later |

## Move an existing dashboard to a new server

If the old server has [off-machine backups](backups.md), install with no `--database` option. Answer yes when setup offers to set up backups, and follow [Restore onto a rebuilt server](restoring-a-backup.md#restore-onto-a-rebuilt-server).

Without backups, take a dump on the old server and copy it across:

```bash
mysqldump --single-transaction vldashboard | gzip > vldashboard.sql.gz
```

Then install with it:

```bash
sudo bash setup.sh --database=/root/vldashboard.sql.gz
```

Setup creates a new database user. The admin user you type at the prompt is added to the users the dump already holds.

## Connect the first laboratory

Each LIS needs the enrollment key. Print it with:

```bash
sudo -u www-data php /var/www/smart-connect/bin/generate-enrollment-key.php --show
```

Then follow [Connect a LIS to Smart Connect](connecting-a-lis.md).

## If setup stops

Setup prints the failing step and the path of its log, `/tmp/smart-connect-setup-<time>.log`.

| Message | Cause | Fix |
|---|---|---|
| `already holds an installation` | The folder has a Smart Connect installation. | Use [Upgrade an installation](upgrading.md), or pass another `--path`. |
| `The database vldashboard already has N tables` | The database exists and is not empty. | Pass another `--db-name`, or drop the database if it holds nothing you need. |
| `The MySQL user sc_vldashboard already exists` | Setup creates one MySQL user per database, named after it. Another installation may use this one. | Pass another `--db-name`, or drop the user if nothing uses it. |
| `Cannot log in to MySQL as root` | The root password is wrong. | Run setup again with the right password. If `~/.my.cnf` holds a wrong one, fix or delete it first. |
| `bin/upgrade.sh failed` | Installing dependencies or running migrations failed. The message above it names the step. | Fix the cause, then run `sudo bash /var/www/smart-connect/bin/upgrade.sh -p /var/www/smart-connect`. |

If setup stopped after it downloaded the code, delete the installation folder and the database before running it again.
