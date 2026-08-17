# How to set up off-machine backups

A Smart Connect server holds data that no laboratory can send again. This guide sets up an automatic backup of the database and the deployment configuration to another machine.

Two things are copied on every run. The first is the database, dumped fresh with `db-tools`. The second is `config/autoload`, which carries the DSN, the database credentials, and the enrollment key every laboratory authenticates against. The application code is not copied, because it comes back from git.

To put a backup back, see [Restore from a backup](restoring-a-backup.md).

## Prerequisites

- Root access to the Smart Connect server
- PHP on the server, and `composer install` already run
- One of these destinations, reachable from the server:
    - another Linux machine, with an SSH login
    - a Windows machine with a shared folder, and a Windows user holding Change permission on it
    - a USB or external drive, plugged in and mounted

## Set up the backup

Run the script from the installation:

```bash
sudo bin/remote-backup.sh
```

On a server without a checkout, fetch it directly:

```bash
sudo bash -c "$(curl -fsSL "https://raw.githubusercontent.com/deforay/smart-connect/master/bin/remote-backup.sh?v=$(date +%s)")"
```

Answer the prompts:

| Prompt | Answer |
|---|---|
| Name for this dashboard | The country or site, such as `kenya-national` |
| Smart Connect folder path | Detected. Press Enter unless it is wrong |
| Where should the backup be sent | `1` for SSH, `2` for a Windows share, `3` for a drive |
| Destination details | The login, the share, or the folder on the drive |
| How many backups to keep | `14` keeps a fortnight of daily backups |
| How often should it run | `1` for every 6 hours, `2` for every 12, `3` for 02:30 daily |

The script saves the answers, installs the runner at `/usr/local/bin/smart-connect-backup.sh`, adds the crontab entry, and runs the first backup while you watch. Leave the window open until it finishes.

To change any answer later, run the script again. It offers every saved answer as the default.

## Run the commands day to day

| Command | Does |
|---|---|
| `sudo smart-connect-backup.sh --status` | Reports when the last backup ran and whether it worked |
| `sudo smart-connect-backup.sh --test` | Checks the database and the destination, and changes nothing |
| `sudo smart-connect-backup.sh` | Runs a backup now |
| `sudo smart-connect-backup.sh --list` | Lists the backups held at the destination |
| `sudo smart-connect-backup.sh --disable` | Stops the scheduled backups |
| `sudo smart-connect-backup.sh --enable` | Starts them again |

## Encrypt the dumps

Dumps are written unencrypted, so the destination folder holds the database credentials in readable form. Restrict who can read that folder.

To encrypt the dump instead, set a passphrase in `/etc/smart-connect/backup.conf`:

```bash
BACKUP_ENCRYPT_PASSWORD='choose-something-long'
```

Store that passphrase somewhere other than this server. A passphrase held only on the machine that fails is lost with it, and the backups are then unreadable.

## Verify it is working

```bash
sudo smart-connect-backup.sh --status
```

A working backup reports a recent success:

```text
Installation   : kenya-national (kenya-national-0c4cb82d)
Backing up to  : backup@192.168.1.20:/home/backup/smart-connect-backups/kenya-national-0c4cb82d
Last good backup: 2026-08-17T02:30:14Z (3 hours ago)
  folder        : 20260817-023012
  database dump : vldashboard-20260817-023012.sql.zst
Size at rest    : 4.2G
Last attempt    : succeeded in 96s
Schedule        : 0 */12 * * *
Keeping         : the last 14 backups
```

Each run leaves one dated folder at the destination:

```text
kenya-national-0c4cb82d/
└── 20260817-023012/
    ├── db/vldashboard-20260817-023012.sql.zst
    ├── config/autoload.tar.gz
    ├── config/crontab-root.txt
    ├── manifest.txt
    └── SHA256SUMS
```

The runner checks every file against `SHA256SUMS` at the destination before it prunes anything, and it stops on a mismatch. Older folders are removed only after that check passes.

If the status reports a failure, read the reason it prints, then run `sudo smart-connect-backup.sh --test`. The full history is in `/var/log/smart-connect-backup.log`.
