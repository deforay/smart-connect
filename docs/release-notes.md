# Release notes

Changes in each release that affect the people who run or use Smart Connect. Read the notes for every version between the one a server runs and the latest before you upgrade it. [Upgrade an installation](guides/upgrading.md) applies all of them in one run.

To see which version a server runs:

```bash
cd /var/www/smart-connect && sudo -u www-data php bin/check-version-sync
```

## 3.5.2

### Before you upgrade

- Tell the admins about the role change below. Some users end up in a role with a long new name, and admins may want to move them.

### Changes

- **The VL turnaround-time page opens.** Before, it refused every user, including admins. Every role that can open the Labs dashboard can now open it.
- **Every role has its own id.** On older servers, Clinician, Testing Lab Manager and every role added through the Roles page shared id 0. Users in any of them got the combined access of all of them. The upgrade turns those roles into one role, named after all of them, with the same access. No user gains or loses access. Admins can then create separate roles and move users into them from the Users page.
- **The CSV data export is removed.** It had no menu link and did not work.
- **The one-time-password login step is removed.** It never worked. Its page did not exist and no password was ever issued. Data Integration accounts now log in with their password, like every other role.
- **The upgrade installs cron when the server lacks it.** Minimal Ubuntu images ship without cron. On such a server the upgrade reported success, but no scheduled job ever ran. Upgrade any server set up from a minimal image, and check the crontab afterwards:

    ```bash
    sudo crontab -u www-data -l
    ```

    The output includes a line that ends in `cron.sh >> /var/www/smart-connect/data/logs/crunz.log 2>&1`.

## 3.5.1

### Before you upgrade

- **Upgrade PHP to 8.4.27 or later.** The upgrade checks the version first and stops without changing anything if PHP is older. PHP 8.4.27 fixes a security flaw in earlier 8.4 releases.
- **Upgrade outside the busiest sync hours on a large database.** The upgrade builds four date indexes on the VL and EID tables. On a large database this takes several minutes. Syncs keep working during the build, but they run slower.
- **Tell the country teams that the turnaround-time numbers change.** The reasons are listed below.

### Changes

- **Turnaround-time reports are corrected.** The numbers change after the upgrade:
    - The lab filter and facility mapping apply. Before, the reports ignored them.
    - The VL turnaround-time reports load. Before, they failed on every load.
    - The reports show three stages instead of four. The two registration steps merge into one Receive to Analysis stage.
    - A sample with one bad date still counts toward its other stages.
    - The last day of the date range is included.
    - The Total label shows the real total. Before, it showed the height of the stacked bar.
- **The Covid-19 dashboard is removed.** Nobody used it. Its data stays in the database. Laboratories that still send Covid-19 records keep syncing. Smart Connect turns those records away, and the laboratory keeps them to send again if the dashboard returns.
- **New servers install with one script.** See [Install on a new server](guides/installing.md).
- **Sample removal works on every server.** Servers that lacked the `removed_samples` table get it.
- **Hepatitis risk factors sync.** The misspelled table `r_hepatitis_rick_factors` is renamed to `r_hepatitis_risk_factors`, so the risk-factor list syncs.
