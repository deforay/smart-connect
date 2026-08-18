# How to retire the legacy API

The legacy `/api/*` endpoints have no authentication. Retiring them closes that hole. Retiring them too early stops laboratories from syncing, and the data loss surfaces weeks later.

This guide covers finding out who still calls the legacy endpoints, and setting the cutoff once nobody does.

## Prerequisites

- Smart Connect 3.1.0 or above
- Shell access to the Smart Connect server
- Write access to `config/autoload/custom.global.php`

## Find out who still uses it

```bash
php bin/console api-usage
```

The report has two tables. The second one is the decision.

```text
Legacy /api/* callers in the last 90 days (2)
---------------------------------------------

+-----+-------+---------------------+-----------------------+
| Lab | Calls | Last call           | Enrolled in v2?       |
+-----+-------+---------------------+-----------------------+
| 99  | 1     | 2026-08-17 14:55:15 | NO — would be cut off |
| 7   | 1     | 2026-08-17 14:55:15 | yes                   |
+-----+-------+---------------------+-----------------------+
```

To widen or narrow the window, pass `--days`.

```bash
php bin/console api-usage --days 180
```

A laboratory marked `NO — would be cut off` loses its sync on the cutoff date. Upgrade its InteLIS installation before continuing. See [Connect a LIS to Smart Connect](../guides/connecting-a-lis.md).

## Announce the date

Set `api.legacy_sunset` in `config/autoload/custom.global.php` to a future date.

```php
'legacy_sunset' => '2027-06-30',
```

Legacy responses then carry a `Sunset` header alongside the `Deprecation` header they already send. Behaviour does not change otherwise.

```bash
curl -sD- -o /dev/null https://dashboard.example.org/api/health | grep -iE 'deprecation|sunset|link'
```

```text
Deprecation: true
Link: </api/v2/health>; rel="successor-version"
Sunset: Wed, 30 Jun 2027 00:00:00 GMT
```

The date is parsed as UTC.

## Enforce the cutoff

On and after the date, every `/api/*` endpoint returns 410.

```json
{
  "status": "error",
  "message": "The /api/vlsm API was retired on 2027-06-30. Use the /api/v2/* endpoints instead.",
  "sunset": "2027-06-30",
  "data": null
}
```

No further action is needed. The date does the work.

To postpone, move the date forward. To cancel, set `legacy_sunset` back to `null`. A null value never returns 410.

## Verify

1. Confirm the announcement is live before the date.

    ```bash
    curl -sD- -o /dev/null https://dashboard.example.org/api/health | head -1
    ```

    Expect `HTTP/1.1 200 OK` with a `Sunset` header.

2. Confirm enforcement works. Set the date to one in the past on a staging deployment.

    ```bash
    curl -s -o /dev/null -w '%{http_code}\n' https://staging.example.org/api/health
    ```

    Expect `410`.

3. Confirm v2 is unaffected.

    ```bash
    curl -s -o /dev/null -w '%{http_code}\n' https://staging.example.org/api/v2/health
    ```

    Expect `200`.
