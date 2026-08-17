# How to connect a LIS to Smart Connect

An InteLIS installation has to authenticate before it can push testing data to Smart Connect. This guide takes a laboratory from nothing configured to a verified first sync.

The LIS enrolls itself. Nobody issues it a token by hand. A country runs hundreds of installations, so per-installation setup work does not scale.

## Prerequisites

- Smart Connect 1.2.0 or above, reachable over HTTPS from the laboratory
- InteLIS with the Smart Connect sync scripts in `bin/smart-connect/`
- Shell access to the Smart Connect server
- Database access to the Smart Connect database, to run `bin/console`

## Confirm the deployment speaks v2

Call the health endpoint.

```bash
curl https://dashboard.example.org/api/v2/health
```

A 200 response confirms v2 is available.

```json
{
  "status": "success",
  "message": "Smart Connect API is reachable",
  "data": { "api_version": "v2", "timestamp": "2026-08-17T09:19:57+00:00" }
}
```

If this returns 404, the deployment predates v2. Upgrade Smart Connect first. The LIS keeps using the legacy `/api/*` endpoints until then.

## Set the enrollment key

1. Generate the key on the Smart Connect server. `composer post-update` does this automatically, so a current deployment already has one.

    ```bash
    php bin/generate-enrollment-key.php
    ```

2. Read the key back.

    ```bash
    php bin/generate-enrollment-key.php --show
    ```

3. Copy the key into the InteLIS installer config for this country.

One key covers every laboratory in the deployment. For rotation, see [Manage the enrollment key](enrollment-key.md).

## Enroll the LIS

The sync scripts enroll on their first run. To enroll by hand, or to test the credential before deploying, call the endpoint directly.

```bash
curl -X POST https://dashboard.example.org/api/v2/enroll \
  -H 'Content-Type: application/json' \
  -d '{
        "enrollment_key": "<key from --show>",
        "instance_uuid": "<s_vlsm_instance.vlsm_instance_id>",
        "lab_id": 7,
        "label": "Kinshasa NRL"
      }'
```

The response carries the token once. Store it in `s_vlsm_instance.sc_api_token`.

```json
{
  "status": "success",
  "data": {
    "token": "42fd3a27c68b688fc8e23e7150e37b823b67bd952eeb30cd48eba8c82af9c9d3",
    "client_id": 1,
    "instance_uuid": "hq9ry6qazp6fqv8dnzhhwngregvro2",
    "status": "active"
  }
}
```

To recover a lost token, call the same endpoint again. The previous token stops working at that moment.

## Push data

Send the token as a Bearer credential.

```bash
curl -X POST https://dashboard.example.org/api/v2/vl \
  -H "Authorization: Bearer $TOKEN" \
  -F 'source=LIS' \
  -F 'labId=7' \
  -F 'vlFile=@vl-payload.json'
```

For the other endpoints and their upload field names, see the [API v2 reference](../api/index.md).

## Handle a 401

A 401 means the token is unknown or revoked. Clear the stored token, enroll again, and retry the request once. Do not retry in a loop.

## Verify

1. Confirm the response reports the record count.

    ```json
    { "status": "success", "message": "3 uploaded successfully", "data": null }
    ```

2. Confirm Smart Connect recorded the laboratory.

    ```bash
    php bin/console api-usage
    ```

    The laboratory appears under `v2 clients` with a recent `Last seen`.

    ```text
    +-----+--------------+--------------+--------+---------------------+
    | Lab | Label        | Instance     | Status | Last seen           |
    +-----+--------------+--------------+--------+---------------------+
    | 7   | Kinshasa NRL | inst-abc-123 | active | 2026-08-17 16:22:24 |
    +-----+--------------+--------------+--------+---------------------+
    ```

3. Confirm the records landed.

    ```sql
    SELECT COUNT(*) FROM dash_form_vl;
    ```
