# Architecture

How Smart Connect fits together. Read this before changing something you have not touched
before. It is short because the application is small.

For the rules a change is held to, see [engineering standards](engineering-standards.md).

## What it is

Smart Connect is a national dashboard. One installation serves a country. Laboratories run
their own InteLIS instances and push testing records to it over an API. Nobody enters test
data into Smart Connect directly. It reads what the laboratories send and aggregates it.

It is a Laminas MVC application on PHP 8.2 or later, with MySQL and Apache.

## Entry points

Four, and they do not share a bootstrap. This matters more than anything else in this
document, because work done in one is absent from the others.

| Entry point | Serves | Runs `Module::onBootstrap` |
|---|---|---|
| `public/index.php`, Laminas path | The dashboard UI and the v1 API | Yes |
| `public/index.php`, v2 fork | `/api/v2/*` | No |
| `bin/console` | Scheduled commands | No |
| `bin/migrate` | Schema migrations | No |

`public/index.php` checks the request path first. A path under `/api/v2` is handed to
`src/Api/bootstrap.php`, a Slim application, which then exits. Laminas never boots for those
requests. The Laminas service layer is still reachable from v2 through
`App\Services\LaminasBridge`, which builds its own service manager on demand.

`bin/console` and `bin/migrate` load modules without running the MVC application, so
`onBootstrap` listeners never fire for them either.

Anything that must be true for every request has to be applied in all four places.
`App\Timezone` is the worked example.

## Modules

`config/modules.config.php` lists them in load order. Later modules merge over earlier ones.

| Module | Holds |
|---|---|
| `Application` | The dashboard, users, roles, facilities, configuration, the ACL, shared services |
| `Api` | The v1 API |
| `Eid` | Early infant diagnosis pages |
| `Covid19` | Covid-19 pages |
| `DataManagement` | Duplicate data tools |

Load order has caught people out. Three modules set `display_exceptions` in their own
`view_manager` config, so a value set in `Application` alone does not survive the merge.

## How a request is authorised

`Module::preSetter` runs on dispatch. It requires a session for every module except `Api`,
and answers an unauthenticated `XmlHttpRequest` with 401 rather than a redirect.

The per-action ACL check runs on non-XHR requests to the `Application` module. `Acl` is a flat
allow-list built once per request from four tables: `dash_resources`, `dash_privileges`,
`dash_roles_privileges_map` and `dash_user_roles`. The resource is the controller class name.
The privilege is the action name in dashed form.

The check fails closed. A controller with no rows is unreachable by every role, including the
administrator, and its menu entry never renders.

Actions reached over `XmlHttpRequest` are authenticated but not yet checked against the ACL.
Closing that needs a privilege row for every AJAX action, and several controllers reached that
way have no rows at all.

## How data arrives

Two APIs, both live.

**v1**, in `module/Api/`, at `/api/vlsm`, `/api/vlsm-eid`, `/api/vlsm-covid19`,
`/api/vlsm-metadata`, `/api/weblims-vl`, `/api/import-viral-load`, `/api/facility`,
`/api/user` and `/api/source-data`. The ingestion endpoints have no authentication. This is
the reason v2 exists.

**v2**, in `src/`, at `/api/v2/{vl,eid,covid19,metadata,facilities,enroll,health,auth/login}`
and the `vl/` variants. Callers authenticate with a bearer token. An instance obtains one by
posting to `/api/v2/enroll` with the shared enrollment key from
`config/autoload/custom.global.php`, and the issued client is stored in `dash_api_clients`.

Records land in `dash_form_vl`, `dash_form_eid` and `dash_form_covid19` through
`CommonService::upsert`, which is `INSERT ... ON DUPLICATE KEY UPDATE`. The unique indexes on
those tables are therefore load-bearing. Dropping one changes an update into a duplicate row.

`dash_track_api_requests` records every API call. Request bodies are written as zipped files
under `public/uploads/track-api/`, and only form parameters are captured, not JSON bodies.

## Where the data lives

`dash_form_vl` is the table that matters. It holds 243 columns and, on the DRC installation,
2.7 million rows with more index than data. Every dashboard panel aggregates it. A query that
touches a column with no index scans the whole clustered index.

`facility_details` names the laboratories and health facilities. `dash_form_vl.lab_id` joins
to it, and that column is null on most rows collected before 2022.

## Scheduled work

One crontab entry runs `cron.sh` every minute. It runs crunz, which reads
`sys/cron/ScheduledTasks.php` and decides what is due. Tasks call `bin/console` commands:
`housekeeping`, `send-mail` and `rebuild-snapshots`.

`rebuild-snapshots` copies the last twelve months of each `dash_form_*` table into a
`_current` table. It is off by default, and reaching those tables needs a per-user session
toggle, so it does not affect what a dashboard page costs by default.

## Schema and versioning

`composer.json` holds the version. `sys/migrations/` holds plain SQL files named for the
version they belong to. `bin/migrate` applies them in order and stamps `db_version` and
`app_version` into `dash_global_config`. `bin/check-version-sync` compares the two.

Migrations replay. `bin/migrate` routes common DDL through helpers that check
`information_schema` first, and it re-runs the file matching the current version on every run,
so idempotency is required rather than encouraged.

Two things about the schema are not obvious and cost time when discovered late.

`sys/migrations/3.0.0-baseline.sql` is not a full schema. Its own header says it is a snapshot
of `data/alter.sql` at the point the migrator arrived. It creates twelve tables.

`data/setup.sql` is not an installer. It creates six tables by copying their structure from a
co-located `vlsm` database on the same server, and it begins by dropping the `dash_form_*`
tables. It is destructive on a populated database.

Together these mean a database cannot be built from this repository alone. Around a hundred
tables that the running application uses, including `facility_details` and the `r_*` reference
tables, have no definition here.

## Deployment

`bin/upgrade.sh` refreshes a shallow source mirror from master and rsyncs it over the tree,
excluding `.git`. It never consults an instance's own git state, and it never overwrites
`config/autoload/global.php`, `local.php`, `custom.global.php` or any `*.local.php`. It then
runs `composer install`, the migrations and the version check.

Run it from the repository:

```bash
sudo bash -c "$(curl -fsSL "https://raw.githubusercontent.com/deforay/smart-connect/master/bin/upgrade.sh?v=$(date +%s)")"
```

## Where the numbers come from

Row counts and column counts in this document were measured against the DRC installation.
Re-measure before relying on them elsewhere.
