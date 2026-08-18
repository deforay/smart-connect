# Smart Connect API v2 reference

Describes Smart Connect 3.2.0.

Base path: `/api/v2`. All responses use `Content-Type: application/json`.

## Response envelope

Every response has this shape.

| Field | Type | Description | Default |
| --- | --- | --- | --- |
| `status` | string | `success` or `error`. | none |
| `message` | string \| null | Human-readable summary. | `null` |
| `data` | mixed \| null | Payload. `null` on errors. | `null` |

The HTTP status code carries the same verdict as `status`. A response with `"status": "error"` never has a 2xx code.

Some responses add fields alongside the envelope.

| Field | Type | Description | Default |
| --- | --- | --- | --- |
| `error_id` | string | 16 hex characters. Present on 500 responses. Matches an `[api-v2] error_id=…` line in the PHP error log. | absent |
| `partial` | boolean | `true` when an ingestion request stored some records and rejected others. | absent |

## Status codes

| Code | Meaning |
| --- | --- |
| 200 | Request succeeded. |
| 201 | Resource created. Returned by `/enroll` and `/vl/import`. |
| 401 | Bearer token missing, unknown, or revoked. Enrollment key wrong. |
| 403 | Token valid but of the wrong kind for the endpoint. Enrollment disabled. |
| 404 | Unknown endpoint. |
| 405 | Method not allowed. |
| 413 | Request body exceeds `post_max_size`. |
| 422 | Request understood, contents invalid. |
| 500 | Unhandled server error. Carries `error_id`. |

## Authentication

Two kinds of principal exist. Each endpoint accepts one kind.

| Kind | Credential | Source |
| --- | --- | --- |
| `client` | Token issued by `POST /api/v2/enroll`. | `dash_api_clients` |
| `user` | Token issued by `POST /api/v2/auth/login`. | `dash_users.api_token`, role 6 |

Send the token in the `Authorization` header.

```http
Authorization: Bearer 42fd3a27c68b688fc8e23e7150e37b823b67bd952eeb30cd48eba8c82af9c9d3
```

Presenting a `client` token to an endpoint that requires a `user` token returns 403. Presenting no token returns 401 with a `WWW-Authenticate: Bearer` header.

Smart Connect stores the sha256 of each client token. The plaintext token appears once, in the enrollment response.

## Endpoints

### GET /api/v2/health

Reports that the API is reachable. Requires no authentication. Touches neither the database nor the Laminas container.

A 404 from this endpoint means the deployment does not serve the API. Upgrade Smart Connect.

Request parameters: none.

```bash
curl https://dashboard.example.org/api/v2/health
```

```json
{
  "status": "success",
  "message": "Smart Connect API is reachable",
  "data": { "api_version": "v2", "timestamp": "2026-08-17T09:19:57+00:00" }
}
```

### POST /api/v2/enroll

Issues a `client` token. Requires no Bearer token. The enrollment key in the request body is the credential.

Accepts JSON or form-encoded bodies.

| Field | Type | Description | Default |
| --- | --- | --- | --- |
| `enrollment_key` | string | Deployment enrollment key. Matched against config key `api.enrollment_key`. | required |
| `instance_uuid` | string | Identifies the LIS installation. In vlsm this is `s_vlsm_instance.vlsm_instance_id`. | required |
| `lab_id` | integer | Testing lab identifier. In vlsm this is the `sc_testing_lab_id` global config value. | `null` |
| `facility_code` | string | Facility code of the enrolling instance. | `null` |
| `label` | string | Display name shown by `bin/console api-usage`. | `null` |

Response `data`:

| Field | Type | Description | Default |
| --- | --- | --- | --- |
| `token` | string | 64 hex characters. Not retrievable again. | none |
| `client_id` | integer | Primary key in `dash_api_clients`. | none |
| `instance_uuid` | string | Echoes the request. | none |
| `status` | string | Always `active`. | none |

One row exists per `instance_uuid`. Enrolling an `instance_uuid` that already has a row replaces its token and sets its status to `active`. The previous token stops working immediately.

Returns 401 when `enrollment_key` does not match. Returns 422 when `instance_uuid` is absent. Returns 403 when `api.enrollment_key` is `null`.

```bash
curl -X POST https://dashboard.example.org/api/v2/enroll \
  -H 'Content-Type: application/json' \
  -d '{"enrollment_key":"...","instance_uuid":"hq9ry6qazp6fqv8dnzhhwngregvro2","lab_id":7,"label":"Kinshasa NRL"}'
```

```json
{
  "status": "success",
  "message": "Enrolled. Store this token — it is not retrievable again.",
  "data": {
    "token": "42fd3a27c68b688fc8e23e7150e37b823b67bd952eeb30cd48eba8c82af9c9d3",
    "client_id": 1,
    "instance_uuid": "hq9ry6qazp6fqv8dnzhhwngregvro2",
    "status": "active"
  }
}
```

### POST /api/v2/auth/login

Issues a `user` token. Requires no Bearer token.

| Field | Type | Description | Default |
| --- | --- | --- | --- |
| `userName` | string | Email address of a `dash_users` row with role 6 and status `active`. | required |
| `password` | string | Account password. | required |

Response `data` contains `token`. Returns 401 for wrong credentials. Returns 422 when either field is absent or empty.

```bash
curl -X POST https://dashboard.example.org/api/v2/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"userName":"api@example.org","password":"..."}'
```

### POST /api/v2/vl

Stores viral load records in `dash_form_vl`. Requires a `client` token.

| Field | Type | Description | Default |
| --- | --- | --- | --- |
| `vlFile` | file | Multipart upload. Extension `.json` or `.json.gz`. Contents are `{"timestamp": <int>, "data": [ …rows… ]}`. | required |
| `source` | string | `LIS` or `STS`. Recorded in `dash_api_receiver_stats`. | `LIS` |
| `labId` | integer | Testing lab identifier. Recorded in `dash_api_receiver_stats`. | `null` |

Each request also updates `facility_details.facility_attributes`, inserts one row into `dash_api_receiver_stats`, inserts one row into `dash_track_api_requests`, and sets `dash_api_clients.last_seen`.

Returns 422 when `vlFile` is absent, empty, failed to upload, or carries another extension. Returns 413 when the body exceeds `post_max_size`.

```bash
curl -X POST https://dashboard.example.org/api/v2/vl \
  -H "Authorization: Bearer $TOKEN" \
  -F 'source=LIS' -F 'labId=7' -F 'vlFile=@vl-payload.json'
```

```json
{ "status": "success", "message": "3 uploaded successfully", "data": null }
```

### POST /api/v2/eid

Stores early infant diagnosis records. Requires a `client` token.

Identical to `POST /api/v2/vl`, with the upload field named `eidFile`.

```bash
curl -X POST https://dashboard.example.org/api/v2/eid \
  -H "Authorization: Bearer $TOKEN" \
  -F 'source=LIS' -F 'labId=7' -F 'eidFile=@eid-payload.json'
```

### POST /api/v2/covid19

Stores Covid-19 records. Requires a `client` token.

Identical to `POST /api/v2/vl`, with the upload field named `covid19File`.

```bash
curl -X POST https://dashboard.example.org/api/v2/covid19 \
  -H "Authorization: Bearer $TOKEN" \
  -F 'source=LIS' -F 'labId=7' -F 'covid19File=@covid19-payload.json'
```

### POST /api/v2/metadata

Merges reference tables such as facilities, geography, and instruments. Requires a `client` token.

| Field | Type | Description | Default |
| --- | --- | --- | --- |
| `referenceFile` | file | Multipart upload. Extension `.json` or `.json.gz`. Contents are `{"timestamp": <int>, "data": {"<table>": {"tableData": […], "lastModifiedTime": "…", "tableStructure": "…"}}}`. | required |
| `forceSync` | boolean | Sent inside the file. `true` drops and recreates each table from `tableStructure`. | `false` |

Without `forceSync`, a table syncs only when its remote `lastModifiedTime` is newer than the local one.

```bash
curl -X POST https://dashboard.example.org/api/v2/metadata \
  -H "Authorization: Bearer $TOKEN" \
  -F 'referenceFile=@reference-data.json'
```

### POST /api/v2/vl/weblims

Stores viral load records sent in the WebLIMS format. Requires a `client` token.

Takes a raw JSON body rather than a file upload. Records live under the `data` pointer. Rows with a `TestId` other than `VIRAL_LOAD_2` are skipped.

Request parameters: none. The body is the payload.

Returns 422 when the body is empty, `[]`, or not valid JSON.

```bash
curl -X POST https://dashboard.example.org/api/v2/vl/weblims \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  --data-binary @weblims.json
```

### POST /api/v2/vl/import

Stores an uploaded file in `public/uploads/not-import-vl` for offline processing. Requires a `client` token.

| Field | Type | Description | Default |
| --- | --- | --- | --- |
| `vlFile` | file | Multipart upload. Extension `.json` or `.json.gz`. | required |

Smart Connect generates the stored filename. The name sent by the client is not used.

Returns 201 on success. Response `data` contains `stored_as`.

```bash
curl -X POST https://dashboard.example.org/api/v2/vl/import \
  -H "Authorization: Bearer $TOKEN" \
  -F 'vlFile=@backlog.json'
```

```json
{ "status": "success", "message": "File received", "data": { "stored_as": "20260817162244-9f2c1a7b4e01.json" } }
```

### POST /api/v2/vl/source-data

Returns viral load results. Requires a `user` token.

The token comes from the `Authorization` header. A `token` field in the request body is ignored.

| Field | Type | Description | Default |
| --- | --- | --- | --- |
| `patient_id` | string | Filters on `patient_art_no`. | none |
| `facility_id` | integer | Filters on `facility_id`. | none |
| `return_results` | integer | `1` returns only the most recent sample. | none |

Response `data` is an array of objects with `sample_code`, `sample_collection_date`, `sample_tested_datetime`, `result`, `patient_art_no`, `rejection_reason_name`, and `status_name`. The array is empty when no sample matches.

```bash
curl -X POST https://dashboard.example.org/api/v2/vl/source-data \
  -H "Authorization: Bearer $USER_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"patient_id":"ART-1234","return_results":1}'
```

### POST /api/v2/facilities

Returns the facility list. Requires a `user` token.

Request parameters: none.

```bash
curl -X POST https://dashboard.example.org/api/v2/facilities \
  -H "Authorization: Bearer $USER_TOKEN"
```

## Configuration

Set these keys in `config/autoload/custom.global.php`.

| Key | Type | Description | Default |
| --- | --- | --- | --- |
| `api.enrollment_key` | string \| null | Enrollment key accepted by `POST /api/v2/enroll`. `null` returns 403 from that endpoint. Generated by `composer generate-enrollment-key`. | `null` |
| `api.legacy_sunset` | string \| null | Date after which the legacy `/api/*` endpoints return 410. Parsed as UTC. `null` never returns 410. | `null` |
| `api.debug` | boolean | `true` puts exception messages in 500 response bodies. | `false` |

### Enrollment key

`composer post-update` runs `bin/generate-enrollment-key.php`, which writes a 64-character hex key into `config/autoload/custom.global.php` when none is set. The script edits only the `enrollment_key` line and leaves surrounding comments and settings in place. It validates the result with `php -l` before replacing the file.

| Option | Type | Description | Default |
| --- | --- | --- | --- |
| `--show` | flag | Print the configured key. Exits 1 when no key is set. | off |
| `--print` | flag | Print a fresh key without writing to the file. | off |
| `--force` | flag | Replace an existing key. | off |
| `--help` | flag | Print usage. | off |

Without `--force`, the script makes no change when a key of 32 characters or more is already set.

Read the key back with `--show` when configuring the vlsm side.

```bash
php bin/generate-enrollment-key.php --show
```

Rotating the key does not affect clients that already hold a token. Clients that have not enrolled cannot enroll until their vlsm config carries the new key.

## Client identity and revocation

`bin/console api-usage` lists the laboratories that have enrolled and when each one last called.

| Option | Type | Description | Default |
| --- | --- | --- | --- |
| `--days` | integer | Window for counting legacy calls. | `90` |

Revoke a client with SQL.

```sql
UPDATE dash_api_clients SET status = 'revoked' WHERE client_id = 7;
```

A revoked token returns 401. The client recovers by enrolling again.
