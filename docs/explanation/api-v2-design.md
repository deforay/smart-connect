# About the API v2 design

Why does API v2 let laboratories issue themselves credentials, instead of an administrator issuing them?

The short answer is scale. A country deployment has hundreds of InteLIS installations, each in a different laboratory, each upgrading on its own schedule. Any design that needs a human to act once per installation does not finish. This one constraint shaped nearly every decision below.

## What was wrong with v1

The v1 API had no authentication on its ingestion endpoints. Anyone who knew the URL of a national dashboard could POST records into it. That alone justified a replacement.

Three other problems came along with it. Errors were reported as the strings `'403'` and `'422'` inside HTTP 200 responses, so a client could not tell success from failure without parsing the body. The API version was smuggled in a request body field, `api-version`, which selected between two different storage paths. And three endpoints under `/api/receiver/` called service methods that did not exist, so every request to them was a fatal error.

Fixing authentication in place would have meant changing the contract for every laboratory at once. That is the update-everything-simultaneously problem again, and it is why v2 is a parallel API rather than a repair of v1.

## Why a strangler seam rather than a rewrite

Smart Connect runs on Laminas MVC, which is in maintenance mode. The long-term direction is to converge on the InteLIS stack, which is Slim 4 with PHP-DI. A full port would touch every controller, every view, and 43 database table classes at once.

Instead, `/api/v2` is served by Slim and everything else stays on Laminas. `public/index.php` branches on the request path before Laminas boots. The two frameworks never meet in a request.

The new handlers still reuse the existing services. `App\Services\LaminasBridge` builds the Laminas service container lazily, without dispatching the MVC application, which is the same trick `bin/console` and `bin/migrate` already use. So `SampleService::saveFileFromVlsmAPIV2()` is called by both APIs, unchanged. That is what made it possible to ship a new authenticated API without a service-layer rewrite, and it is why v1 and v2 cannot drift apart in how they store data.

The cost is that the storage layer keeps its quirks. The ingestion services read `$_FILES` and `$_POST` directly rather than taking arguments. v2 works around this rather than fixing it, by validating uploads before the service sees them. That debt is real and deliberate.

## Why laboratories enroll themselves

The alternative designs were considered and rejected in the order below.

**An administrator issues each token.** This is the conventional answer and the one that fails hardest here. It needs an action per installation at setup, and another every time a laboratory reinstalls or restores a database backup.

**One shared key for every laboratory.** No table, no enrollment endpoint, one string comparison. It was tempting. It cannot revoke or observe a single laboratory, though, and rotating means editing config on every installation in the country. It also leaves no per-laboratory activity data, which is the data the legacy cutoff decision depends on.

**A shared key plus a recorded client row.** This keeps the activity data but still cannot revoke one laboratory.

What shipped is enrollment: one key in the deployment config, which a laboratory presents once to receive its own token. Per-laboratory revocation works. Per-laboratory activity is recorded. The setup cost is one key per country rather than one credential per installation.

## Why re-enrolling is allowed

An earlier draft of the design pinned tokens. Once a laboratory enrolled, a second enrollment for the same instance was rejected until an administrator revoked the first one.

That is the correct instinct in most systems and wrong here. A laboratory that reinstalls, or restores a database backup from before it enrolled, arrives with no token and cannot get one. Its sync stops. Somebody notices weeks later, when the data is missing. Multiply by hundreds of installations and the support burden is the whole point of failure.

So re-enrollment reissues, freely. The trade-off is explicit: anyone holding the deployment's enrollment key can rotate any laboratory's token in that deployment. That grants no new access, because the same key already allows enrolling. The trust boundary is unchanged. What changes is that a laboratory recovers on its own.

The same reasoning removed the approval mode from the design. An administrator approving each enrollment is a human step per installation.

Tokens are stored as a SHA-256 hash, so a dump of `dash_api_clients` cannot be replayed against the API. This is also why a lost token cannot be looked up and re-sent. Reissuing is the only recovery path, which is another reason it has to be unrestricted.

## Why the legacy API is still running

Smart Connect and InteLIS are separate repositories, deployed separately, by different people, at different times. Neither can assume the other has been updated.

Two mechanisms handle that. `GET /api/v2/health` is unauthenticated, so a client can ask whether a deployment speaks v2 at all. A 404 means fall back to `/api/*`. And the legacy endpoints keep working, unchanged, until a deployment sets `api.legacy_sunset`.

Retirement is therefore a per-deployment decision with a date attached, rather than a release event. Until that date, legacy responses advertise their own retirement through the `Deprecation` and `Sunset` headers described in RFC 8594. After it, they return 410 and name the replacement.

The `bin/console api-usage` report exists to make that date a decision rather than a guess. It lists which laboratories have enrolled in v2 and which still call the legacy endpoints, so the deployment can see exactly who a cutoff would strand.

The `/api/receiver/*` endpoints were the exception. They were already broken, so they were deleted outright rather than deprecated, and they have no v2 equivalent.

## Related reading

- [API v2 reference](../api/index.md) for the endpoints, fields, and status codes
- [Connect a LIS to Smart Connect](../guides/connecting-a-lis.md) for the integration path
- [Retire the legacy API](../guides/retiring-the-legacy-api.md) for the cutoff process
