# How to restore from a backup

This guide puts a stored backup back into a Smart Connect server. Use it after data loss, a bad import, or a server rebuild.

The restore replaces two things: the database, and the deployment files in `config/autoload`. Before it changes either, it copies the current database and configuration into `/var/smart-connect-backup/before-restore/`.

Setting up backups is covered in [Set up off-machine backups](backups.md).

## Prerequisites

- Root access to the Smart Connect server
- The backup destination reachable from the server
- MySQL running, with the user named in the backup able to log in

## See what is available

```bash
sudo smart-connect-backup.sh --list
```

```text
Backups at /home/backup/smart-connect-backups/kenya-national-0c4cb82d, oldest first:

  20260810-023011   4.1G
  20260817-023012   4.2G
```

## Restore

To take the newest backup:

```bash
sudo smart-connect-backup.sh --restore latest
```

To take an older one, pass the folder name from `--list`:

```bash
sudo smart-connect-backup.sh --restore 20260810-023011
```

Type `restore` at the prompt to go ahead. Anything else stops the command with nothing changed.

The command fetches the backup, checks every file against its checksums, copies the current state aside, puts `config/autoload` back, restores the database, and counts the tables it ends up with. It stops at the first step that fails, and names the folder holding the previous state when it does.

To run it without the prompt, from a script, add `--yes`.

## Restore onto a rebuilt server

A rebuilt server has no backup identity, so the setup script offers the folders already at the destination.

1. Install the server and check out the code, as in [Setting up](https://github.com/deforay/smart-connect#setting-up). Stop before creating the database.

2. Run `composer install` in the installation folder.

3. Run the setup script:

    ```bash
    sudo bin/remote-backup.sh
    ```

4. Point it at the same destination as before. It lists the folders it finds:

    ```text
      1) kenya-national-0c4cb82d
         kenya-national on dashboard-01, newest backup 20260817-023012
    ```

5. Enter the number of the folder that belongs to this installation. The script takes over that folder, so the history stays in one place.

6. Answer `y` when it offers to restore the newest backup.

If the restore stops because the database user cannot log in, create it with the credentials the backup carries. Read them from the restored `config/autoload/local.php`, then:

```sql
CREATE DATABASE IF NOT EXISTS vldashboard;
CREATE USER 'scuser'@'localhost' IDENTIFIED BY 'the-password-from-local.php';
GRANT ALL ON vldashboard.* TO 'scuser'@'localhost';
```

Then run `sudo smart-connect-backup.sh --restore latest` again.

## Verify the restore

The command reports the table count it found:

```text
✅ Success: The database vldashboard holds 214 tables
✅ Success: Restored the backup taken at 20260817-023012
```

Confirm the dashboard itself:

1. Open the dashboard in a browser and log in.
2. Check that the enrollment key is the expected one:

    ```bash
    php bin/generate-enrollment-key.php --show
    ```

3. Check that backups still run:

    ```bash
    sudo smart-connect-backup.sh --test
    ```

Laboratories that hold a token keep syncing. Only laboratories that have not yet enrolled need the enrollment key, which the restore puts back with the rest of `config/autoload`.
