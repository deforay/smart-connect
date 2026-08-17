# Smart Connect documentation

Smart Connect is the national dashboard that collects viral load, EID, and Covid-19 testing data from InteLIS (vlsm) installations across a country. Each laboratory runs its own InteLIS instance. Those instances push their records to Smart Connect over the API.

Use the sidebar to navigate.

## Connecting a LIS

- [Connect a LIS to Smart Connect](guides/connecting-a-lis.md) — the whole path, from enrollment key to first sync
- [Manage the enrollment key](guides/enrollment-key.md) — generate, read, and rotate the key

## Operations

- [Retire the legacy API](guides/retiring-the-legacy-api.md) — decide the cutoff date and set it

## Reference

- [API v2](api/index.md) — every endpoint, field, and status code

## Explanation

- [Why API v2 looks like this](explanation/api-v2-design.md) — the design constraints and what they ruled out

## Two APIs

Smart Connect serves two APIs at once.

| API | Base path | Status |
| --- | --- | --- |
| v2 | `/api/v2` | Current. Requires authentication. |
| v1 | `/api` | Deprecated. Kept working until the deployment sets a cutoff date. |

New integrations use v2. Existing InteLIS installations move to v2 on their own schedule, because each one upgrades independently. [Retire the legacy API](guides/retiring-the-legacy-api.md) covers how to tell when every laboratory has moved.
