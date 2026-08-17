# Smart Connect documentation

Smart Connect is the national dashboard that collects viral load, EID, and Covid-19 testing data from InteLIS installations across a country. Each laboratory runs its own InteLIS instance. Those instances push their records to Smart Connect over the API.

Use the sidebar to navigate.

## Connecting a LIS

- [Connect a LIS to Smart Connect](guides/connecting-a-lis.md) — the whole path, from enrollment key to first sync
- [Manage the enrollment key](guides/enrollment-key.md) — generate, read, and rotate the key

## Backups

- [Set up off-machine backups](guides/backups.md) — copy the database and configuration to another machine
- [Restore from a backup](guides/restoring-a-backup.md) — put one back, including onto a rebuilt server

## Troubleshooting

- [Diagnose a laboratory that is not syncing](guides/troubleshooting.md) — find the stage where records stop

## Reference

- [API](api/index.md) — every endpoint, field, and status code
