# How to diagnose a laboratory that is not syncing

Records from a laboratory have stopped arriving, or never arrived. This guide finds the stage where they stop.

Work through the four checks in order. Each one narrows the problem to a single stage, and the sections below cover what to do at each stage.

## Prerequisites

- Shell access to the Smart Connect server
- Database access to the Smart Connect database
- A way to reach whoever runs the laboratory, for the checks that only they can do

## Find the stage where it stops

Records cross four stages. Check them in order and stop at the first that fails.

1. Confirm the laboratory has enrolled.

    ```bash
    php bin/console api-usage
    ```

    The laboratory should appear under `v2 clients`. If it does not, go to [The laboratory is missing from the report](#the-laboratory-is-missing-from-the-report).

2. Confirm it called recently. Read the `Last seen` column in the same report. If it is old or says `never called`, go to [Last seen is old](#last-seen-is-old).

3. Confirm its records arrived.

    ```sql
    SELECT received_on, test_type, lab_id, source,
           number_of_records_received, number_of_records_processed, status
    FROM dash_api_receiver_stats
    ORDER BY received_on DESC
    LIMIT 10;
    ```

    If `number_of_records_processed` is below `number_of_records_received`, go to [Fewer records stored than sent](#fewer-records-stored-than-sent).

4. Confirm the dashboard displays them.

    ```sql
    SELECT COUNT(*) FROM dash_form_vl WHERE lab_id = 7;
    ```

    If the count is right but the screens disagree, go to [Records are stored but do not appear](#records-are-stored-but-do-not-appear).

## The laboratory is missing from the report

The laboratory has never enrolled. Four causes, cheapest to check first.

Confirm the deployment serves the API.

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://dashboard.example.org/api/v2/health
```

A 404 means Smart Connect needs upgrading. A timeout means the laboratory's network cannot reach this server, which is the laboratory's firewall or DNS to investigate.

Confirm enrollment is switched on.

```bash
php bin/generate-enrollment-key.php --show
```

Exit code 1 means no key is set, so every enrollment attempt returns 403. Generate one, then distribute it. See [Manage the enrollment key](enrollment-key.md).

Confirm the laboratory holds the same key. Compare the value above against the key in its InteLIS config. A mismatch returns 401 with `Invalid enrollment key`.

Confirm the laboratory has upgraded InteLIS. An installation running an older release never calls the enrollment endpoint at all.

## Last seen is old

The laboratory enrolled once and then stopped. Check whether the token was revoked.

```sql
SELECT client_id, label, lab_id, status, enrolled_on, last_seen
FROM dash_api_clients
WHERE lab_id = 7;
```

A `status` of `revoked` means every call returns 401. The laboratory recovers by enrolling again, which happens on its next sync without anyone doing anything.

If `status` is `active`, the problem is at the laboratory. Ask them to confirm the scheduled sync is running and to send one manually. A laboratory that has been offline for a while catches up on its own once it reconnects, because it syncs everything modified since its last successful run.

Two laboratories sharing one `instance_uuid` produce this symptom continuously. Each enrollment reissues the token and invalidates the other, so both alternate between working and 401. Check for it:

```sql
SELECT instance_uuid, COUNT(*) AS labs, GROUP_CONCAT(lab_id) AS lab_ids
FROM dash_api_clients
GROUP BY instance_uuid
HAVING labs > 1;
```

Any row here is a cloned InteLIS installation. Give one of them a fresh `vlsm_instance_id`.

## Fewer records stored than sent

`dash_api_receiver_stats` records both counts per sync. A `status` of `partial` means some rows failed to store while the rest succeeded.

A row is counted as failed only when storing it raises a database error. Those errors go to the PHP error log rather than back to the laboratory, so read them there. Use `received_on` from the query above to find the right point in the log.

```bash
grep -i 'SQLSTATE\|dash_form' /var/log/apache2/error.log | tail -20
```

Expect a data problem in the row itself, such as a date the database rejects or a value too long for its column. There are no foreign keys on `dash_form_vl`, so a sample naming a facility the dashboard has not heard of still stores. That sample appears with no facility against it until the next metadata sync, which is a reporting gap rather than a failure, and it does not show up in these counts.

A `status` of `failed` with zero processed means nothing stored at all. Read the same log window. A whole failed sync usually means the uploaded file was unreadable rather than that every row was bad.

## Records are stored but do not appear

The data is in `dash_form_*`, so this is display, not sync.

Dashboards are cached. `cache-expiry` in `config/autoload/custom.global.php` sets the window in minutes, and it defaults to 1440, which is a full day. Use the clear cache link at the bottom of any page to see fresh numbers immediately.

If `use-current-sample-table` is on, the screens read snapshot tables rather than the raw tables. Rebuild them.

```bash
php bin/console rebuild-snapshots
```

## The laboratory reports an API error

Match the message the laboratory received.

| Message | HTTP | What to do |
| --- | --- | --- |
| `Missing Bearer token` | 401 | The laboratory is not sending its token. Its InteLIS needs upgrading or reconfiguring. |
| `Invalid or revoked token` | 401 | Check `status` in `dash_api_clients`. The laboratory re-enrolls on its next sync and recovers. |
| `Invalid enrollment key` | 401 | The key on the laboratory does not match the one on the server. Compare with `--show`. |
| `Enrollment is not enabled on this deployment` | 403 | `api.enrollment_key` is null. Generate a key. |
| `This endpoint requires a user token` | 403 | The caller used a laboratory token on a read endpoint. It needs an account token from `POST /api/v2/auth/login`. |
| `instance_uuid is required` | 422 | The laboratory sent no instance identifier. Its `s_vlsm_instance.vlsm_instance_id` is empty. |
| `Missing file upload "vlFile"` | 422 | The sync sent no file. Check the laboratory's error log for a failure while building it. |
| `Unsupported file type ".xml"` | 422 | The sync sent something other than `.json` or `.json.gz`. |
| `Request body exceeds the server limit` | 413 | Raise `post_max_size` and `upload_max_filesize` in the server's PHP config. A laboratory with a long backlog hits this on its first sync. |
| `Internal server error` | 500 | The response carries an `error_id`. See below. |

## Trace a 500 with its error_id

Every 500 response carries an `error_id`, and the same value is written to the PHP error log with the failure behind it. Ask the laboratory for the value, then search the log.

```bash
grep 'error_id=9f2c1a7b4e01d3a5' /var/log/apache2/error.log
```

The matching line names the file and line that failed, and the stack trace follows it.

Setting `api.debug` to true puts the exception message in the response body. Turn it off again afterwards, because it exposes internal detail to every caller.

## Verify

Confirm the laboratory is syncing again.

1. Ask them to run one sync.

2. Confirm `Last seen` has moved.

    ```bash
    php bin/console api-usage
    ```

3. Confirm the records arrived.

    ```sql
    SELECT received_on, number_of_records_received, number_of_records_processed, status
    FROM dash_api_receiver_stats
    ORDER BY received_on DESC
    LIMIT 1;
    ```

    `status` reads `success` and the two counts match.
