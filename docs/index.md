# Smart Connect documentation

Smart Connect is the national dashboard that collects viral load and EID testing data from InteLIS installations across a country. Each laboratory runs its own InteLIS instance. Those instances push their records to Smart Connect over the API.

Use the sidebar to navigate.

## Connecting a LIS

- [Connect a LIS to Smart Connect](guides/connecting-a-lis.md) — the whole path, from enrollment key to first sync
- [Manage the enrollment key](guides/enrollment-key.md) — generate, read, and rotate the key

## Backups

- [Set up off-machine backups](guides/backups.md) — copy the database and configuration to another machine
- [Restore from a backup](guides/restoring-a-backup.md) — put one back, including onto a rebuilt server

## Installing and upgrading

- [Install on a new server](guides/installing.md) — the web server, PHP, the database and the application, in one script
- [Upgrade an installation](guides/upgrading.md) — bring a server up to the latest release, one instance or all of them
- [Release notes](release-notes.md) — what changed in each release, and what to do before upgrading

## Troubleshooting

- [Diagnose a laboratory that is not syncing](guides/troubleshooting.md) — find the stage where records stop

## Reference

- [API](api/index.md) — every endpoint, field, and status code
