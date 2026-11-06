# Smart Connect

Smart Connect is a national dashboard for viral load and early infant diagnosis (EID) testing. It brings the results from every laboratory in a country into one place, so a national program can see testing volumes, suppression rates and turnaround times without collecting spreadsheets.

![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue)

## How it works

Each laboratory records its tests in its own laboratory information system (LIS), usually [InteLIS](https://github.com/deforay/intelis). The LIS sends its results to Smart Connect over the internet. Smart Connect combines them and shows them on dashboards for the whole country.

## What it shows

- **Viral load and EID dashboards.** Each has a national view, a laboratory view, a clinic view and a point-of-care laboratory view.
- **Turnaround time.** It shows how long samples take from collection to receipt, from receipt to analysis, and from analysis to authorisation. You can drill down by province, district and clinic.
- **Sync status.** It shows which laboratories have sent results recently and which have stopped.
- **Data quality.** It shows the duplicate records that laboratories have sent.

Access is controlled by user roles. A user can be limited to the facilities they are responsible for.

## Documentation

The documentation is at <https://deforay.github.io/smart-connect/>. It covers:

- [connecting a laboratory's LIS](docs/guides/connecting-a-lis.md);
- [diagnosing a laboratory that isn't syncing](docs/guides/troubleshooting.md);
- [upgrading an installation](docs/guides/upgrading.md);
- [setting up backups](docs/guides/backups.md) and [restoring one](docs/guides/restoring-a-backup.md);
- [the API](docs/api/index.md), for LIS developers.

Developers and maintainers should start with [Architecture](docs/developers/architecture.md) and the [maintainer reference](docs/developers/maintaining.md).

## Funding and partners

Smart Connect is developed with funding from the United States Government (USG). Over the years, the project has benefited from the support and collaboration of partners including the African Society for Laboratory Medicine (ASLM), the American Society for Microbiology (ASM), the African Field Epidemiology Network (AFENET), Emory University, and the Maryland Global Initiatives Corporation (MGIC), among others.

## License

Smart Connect is free and open-source software released under the **GNU Affero General Public License v3.0 (AGPL-3.0)**.

Read the full text in [LICENSE.md](LICENSE.md).

## Contact

You can reach us at hello (at) deforay (dot) com
