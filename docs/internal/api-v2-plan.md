# API v2 Plan — Slim 4 strangler seam at `/api/v2/*`

!!! note "Historical record — not the shipped design"

    This is the plan as written before implementation. It is kept for the design
    reasoning, not as a description of the API. It is deliberately excluded from
    the published documentation site.

    Three parts of Phase 2 were dropped during implementation, because each one
    required a human action per installation and there are hundreds of
    installations per country:

    - **Token pinning** (409 on re-enroll, admin must revoke first) — a lab that
      reinstalled or restored a backup could not recover on its own.
    - **`api.require_enrollment_approval`** — an admin approving each enrollment.
    - **Client-generated tokens** — the server generates and returns the token.

    Also added after the plan was written: `bin/generate-enrollment-key.php`,
    which auto-generates `api.enrollment_key` on `composer post-update`, so a
    deployment does not have to invent one.

    For what actually shipped, see the [API reference](../api/index.md).
    The one-time cutover runbook for the pre-1.2.0 endpoints is
    [Retire the legacy API](retiring-the-legacy-api.md).

## Context

The current Api module (Laminas MVC, `module/Api/`) has structural problems:

- **No authentication on ingestion endpoints** (`/api/vlsm`, `/api/vlsm-eid`, `/api/vlsm-covid19`,
  `/api/weblims-vl`, `/api/import-viral-load`) — anyone can POST data into the dashboard DB.
  Only `/api/source-data` validates a token (`dash_users.api_token`, role 6).
- **Three dead endpoints**: `/api/receiver/{vl,eid,covid19}` call service methods that do not exist
  (`saveVLDataFromAPI`, `saveEidDataFromAPI`, `saveCovid19DataFromAPI`) → fatal on every request.
- **HTTP status codes lie**: errors returned as `'403'`/`'422'` inside HTTP 200 bodies.
- **Version smuggled in the request body** (`api-version: v1|v2` selects `saveFileFromVlsmAPIV1/V2`).
- Part of the broader Laminas exit strategy: `/api/v2` becomes the first Slim 4 seam, mirroring
  the vlsm architecture (`public/api/index.php`, invokable handlers, middleware, PHP-DI).

Constraints agreed:

- **No manual token issuance** — hundreds of LIS instances per country. Labs self-enroll
  (trust-on-first-use, pinned token), gated by one per-deployment enrollment key shipped in the
  vlsm installer config. Optional admin approval mode.
- **Legacy `/api/*` retained unchanged until a config-driven cutoff date**, then `410 Gone`.
  `Deprecation`/`Sunset` headers (RFC 8594) in the meantime.

## Route map

| v2 route | replaces | auth |
| --- | --- | --- |
| `GET  /api/v2/health` | `/api/health` | none |
| `POST /api/v2/enroll` | — (new) | enrollment key |
| `POST /api/v2/auth/login` | `/api/user` | credentials in body |
| `POST /api/v2/vl` | `/api/vlsm` | Bearer (lab client) |
| `POST /api/v2/eid` | `/api/vlsm-eid` | Bearer (lab client) |
| `POST /api/v2/covid19` | `/api/vlsm-covid19` | Bearer (lab client) |
| `POST /api/v2/vl/weblims` | `/api/weblims-vl` | Bearer (lab client) |
| `POST /api/v2/vl/import` | `/api/import-viral-load` | Bearer (lab client) |
| `POST /api/v2/vl/source-data` | `/api/source-data` | Bearer (api user) |
| `POST /api/v2/metadata` | `/api/vlsm-metadata` | Bearer (lab client) |
| `POST /api/v2/facilities` | `/api/facility` | Bearer (api user) |

`/api/receiver/*` gets **no v2 equivalent** (dead code — controllers deleted in Phase 4).
v2 ingestion paths always invoke the existing `saveFileFromVlsmAPIV2()` service methods — the
`api-version` body param dies with v1.

Response envelope everywhere: `{"status": "success|error", "message": ..., "data": ...}` with
**real HTTP status codes** (200/201, 400, 401, 403, 410, 422, 500).

## Architecture decisions

- **Mount**: branch at the top of `public/index.php` — if the request path starts with `/api/v2`,
  require `src/Api/bootstrap.php` (Slim) and exit; otherwise continue to Laminas. No `.htaccess`
  changes (some deployments override it in vhost config).
- **Namespace**: `App\` → `src/` (new PSR-4 entry in composer.json), converging on vlsm's `App\`
  namespace so utilities can eventually be shared. Laminas `Application\` module is untouched.
- **DI**: `php-di/php-di`, autowiring. Bridge definitions expose existing Laminas services
  (`SampleService`, `EidSampleService`, `Covid19FormService`, `CommonService`, `FacilityService`,
  `UserService`, `Laminas\Db\Adapter\Adapter`) by lazily building the Laminas ServiceManager the
  same way `bin/console` / `bin/migrate` do (module bootstrap without MVC dispatch). Zero
  service-layer rewrites to ship v2.
- **Handlers**: single-action invokable classes, `__invoke(ServerRequestInterface): ResponseInterface`,
  constructor-injected services — vlsm's modern pattern
  (`vlsm/app/classes/HttpHandlers/InterfaceApi/*`).
- **Tokens stored hashed** (`sha256`) in a new `dash_api_clients` table; plaintext never persisted.

## New files (structure)

```text
src/
  Api/bootstrap.php               Slim AppFactory + container + routes + middleware stack
  Http/ApiResponse.php            static json()/error() helpers (mirror vlsm InterfaceApiResponse)
  Middlewares/
    ApiErrorMiddleware.php        outermost try/catch -> JSON error + error_id, hides details in prod
    BearerAuthMiddleware.php      validates lab-client token (dash_api_clients) OR api-user token
                                  (dash_users.api_token, role 6); sets request attribute; 401 JSON
    RequestTrackingMiddleware.php logs to dash_track_api_requests via existing addApiTracking()
  Services/
    LaminasBridge.php             lazy ServiceManager bootstrap (pattern from bin/console)
    EnrollmentService.php         enroll/validate/revoke lab clients
  HttpHandlers/V2/
    HealthHandler.php  EnrollHandler.php  AuthLoginHandler.php
    ReceiveVlHandler.php  ReceiveEidHandler.php  ReceiveCovid19Handler.php
    WeblimsVlHandler.php  ImportVlHandler.php  SourceDataHandler.php
    MetadataHandler.php  FacilitiesHandler.php
sys/migrations/1.2.0-api-v2.sql   dash_api_clients table (idempotent DDL)
docs/api-v2.md                    endpoint reference for LIS integrators
```

## Phases

### Phase 1 — seam + health (proves the mount)

1. `composer require slim/slim:^4 slim/psr7 php-di/php-di`
2. `public/index.php`: `/api/v2` branch → `src/Api/bootstrap.php`
3. composer PSR-4: `"App\\": "src/"`
4. `ApiResponse`, `ApiErrorMiddleware`, Slim body-parsing middleware, `GET /api/v2/health`
5. Verify: `curl /api/v2/health` → 200 JSON; any Laminas page still works

### Phase 2 — enrollment + auth

1. Migration `1.2.0-api-v2.sql`: `dash_api_clients`
   (`client_id` PK, `lab_id`/`facility_id`, `instance_uuid`, `token_hash` unique, `label`,
   `status` enum pending/active/revoked, `enrolled_on`, `last_seen`, `enrolled_ip`).
   Bump composer.json version to 1.2.0.
2. Config keys in `config/autoload/custom.global.php` (+ `.dist`):
   `api.enrollment_key` (null disables enrollment), `api.require_enrollment_approval` (bool,
   default false), `api.legacy_sunset` (date|null).
3. `POST /api/v2/enroll`: validates enrollment key, upserts client as `active` (or `pending` when
   approval required); **pinning** — an existing active client for the same lab/instance cannot be
   overwritten (409), only re-enrolled after admin revokes. Client generates and sends its own
   token; server stores hash only.
   Contract (fields confirmed against what vlsm already has available):
   `{enrollment_key, instance_uuid, facility_code, label, token}` →
   `vlsm_instance_id` / `sc_testing_lab_id` / `instance_facility_name` / locally generated 64-hex.
   Response includes `status: active|pending` so the client knows whether to start using v2
   immediately or keep falling back until approved.
4. `BearerAuthMiddleware` + `RequestTrackingMiddleware` (updates `last_seen` — heartbeat precedent:
   `DashApiReceiverStatsTable::updateFacilityAttributes`).
5. Verify: enroll → authorized call → 200; missing/bad/revoked token → 401; duplicate enroll → 409.

### Phase 3 — v2 handlers

1. The 9 remaining handlers, thin wrappers over existing services (same call the old controllers
   made, e.g. `SampleService::saveFileFromVlsmAPIV2()`), but with: real status codes, the standard
   envelope, upload validation (extension whitelist `.json/.json.gz`, no `0777` mkdir), and
   defined behavior on bad input (422, never undefined `$response`).
2. `AuthLoginHandler` keeps the existing `UsersTable::userLoginDetailsApi` flow (returns the
   api-user token) — status codes fixed (401 not '403-in-200').
3. Verify: end-to-end POST with a sample vlsm JSON payload file into a dev DB.

### Phase 4 — legacy sunset + dead code removal

1. Delete `/api/receiver/*` routes + 3 controllers (dead).
2. Laminas Api module listener (dispatch, Api\* controllers only): before `api.legacy_sunset` add
   `Deprecation: true` + `Sunset: <date>` headers; after it, return
   `410 {"status":"error","message":"...use /api/v2/...","sunset":"<date>"}`.
   Null sunset = headers only, never 410.
3. Verify: legacy endpoint pre-cutoff unchanged (+headers); set past date in local config → 410.

### Phase 5 — observability (small)

1. `bin/console api-usage`: per-lab last-seen report — legacy hits (from
   `dash_track_api_requests`) vs v2 clients (`dash_api_clients.last_seen`) — the data that decides
   when the cutoff date is safe.

## Companion vlsm work (separate repo, confirmed feasible — small)

The four sync scripts (`vlsm/bin/smart-connect/{vl,eid,covid19,metadata}.php`) share one preamble;
`ApiService` already supports Bearer (`setBearerToken()`); instance identity already exists
(`s_vlsm_instance.vlsm_instance_id`, `instance_facility_code`, `sts_token` precedent). Needed:

1. `s_vlsm_instance.sc_api_token` column + per-country `enrollment_key` config entry.
2. One shared helper: auto-enroll when no stored token (generate token locally, POST enroll,
   persist); `setBearerToken()`; on 401 clear + re-enroll once + retry.
3. Capability probe: `GET /api/v2/health` — on 404 fall back to legacy `/api/*` paths, making
   vlsm/smart-connect update order independent per deployment.

## Out of scope (tracked, not built here)

- vlsm-side sync client changes (enrollment call, Bearer header, v2 paths) — separate vlsm work.
- Admin UI for approving/revoking clients (list/approve/revoke can start as SQL/console; dashboard
  page later).
- Migrating `fetchSourceData`'s string-interpolated SQL (pre-existing; worth parameterizing when
  the handler is written — noted in Phase 3).

## Verification (end-to-end, after all phases)

1. `php bin/migrate --dry-run` then `php bin/migrate` on dev DB.
2. Boot check: `Laminas\Mvc\Application::init(...)` + `curl` a web page → unaffected.
3. Full v2 flow: health → enroll → authed `POST /api/v2/vl` with sample payload → row lands in
   `dash_form_vl`, stats row in `dash_api_receiver_stats`, tracking row in
   `dash_track_api_requests`, `last_seen` updated.
4. Auth failures: no header, bad token, revoked client → 401 envelope.
5. Legacy: `POST /api/vlsm` unchanged with Deprecation/Sunset headers; past-dated sunset → 410.
6. Dead receivers: `POST /api/receiver/vl` → 404 (routes removed).
