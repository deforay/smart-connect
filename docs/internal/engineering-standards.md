# Engineering standards

The bar this codebase is held to.

Sections 1 and 2 apply to every change, however it was written and however it was checked.
They are the rules. Section 3 describes one optional tool for checking them, which some
people here use and others do not. Reading section 3 is not a condition of contributing, and
nothing in the repository requires it: there is no hook, no CI job, and no gate.

## 1. Standing invariants

These hold whatever else you do. They are listed before any tooling because they are the part
that matters.

See section 4 for the full list with the reasoning behind each one. In short:

- Schema changes live in `sys/migrations/` and must replay on a fresh install.
- A controller is unreachable until the ACL knows it.
- Do not wrap an indexed datetime column in `DATE()` inside a range predicate.
- `dash_form_vl` is 243 columns wide, so measure before adding a column to an aggregate.
- `lab_id` is null on most historical rows, so an INNER JOIN silently drops them.
- Check for an existing index before adding one.
- An `IN` list needs one placeholder per value.
- Facility names arrive from remote instances and are not trusted input.
- Timestamps come from one clock. `App\Timezone` applies it at every entry point.

## 2. What counts as a finding

A defect with a failure scenario. Name concrete inputs or state, and the wrong output or lost
data that results. "This could be cleaner" is not a finding. A trade-off already recorded in
these documents is a rebuttal, not a fix.

Address or explicitly rebut anything raised against these invariants before merging, whether
it came from a colleague or a tool. A rebuttal is a sentence saying why the code is right.
Silence is not a rebuttal.

## 3. Optional: the adversarial review pass

Skip this section if you do not use an automated reviewer. Nothing depends on it.

For those who do, `bin/dev/review` runs one against the brief below:

```bash
bin/dev/review                  # the working branch against master
bin/dev/review <commit-sha>     # one commit
bin/dev/review --uncommitted    # the working tree, before committing
```

Three more commands keep a ledger of what has already been reviewed:

```bash
bin/dev/review --pending        # commits not yet recorded as reviewed
bin/dev/review --since-last     # review everything since the last recorded commit
bin/dev/review --mark [sha]     # record a commit as reviewed
```

The ledger lives inside the `.git` directory. It is local to one clone, never committed, and
never deployed. Set `REVIEW_LEDGER` in `.env` to keep it somewhere that outlives a clone.

The reviewing CLI is named by `REVIEW_AGENT`, read from the untracked `.env` and falling back
to your shell profile. It is deliberately not written down here. The tool can be swapped
without editing anything, and no vendor name enters the repository. `REVIEW_AGENT` is a
command line rather than a bare binary, so a reviewer that takes a subcommand and one that
takes a flag are both a setting. Set `REVIEW_AGENT_STDIN=1` for a reviewer that reads its
prompt from standard input. See `.env.dist`.

The review is a local step, not a CI job. The reviewing CLI is authenticated on your machine.

**The review brief** — single source of truth, extracted verbatim by the script, so it cannot drift:
> "You are reviewing a change to Smart Connect, a Laminas PHP national dashboard that ingests viral load, EID and Covid-19 testing data from a fleet of remote InteLIS laboratory instances over an API. One Smart Connect install serves a country. Do not summarize the code. Find: (1) aggregate panels whose filters silently drop rows, especially an INNER JOIN to `facility_details` on `lab_id` or `facility_id` where the column is null on most historical rows, and any two figures on one page that count the same thing over different row sets; (2) a date predicate that wraps an indexed datetime column in `DATE()`, `YEAR()` or `MONTH()`, which puts every index on that column out of reach and turns a range scan into a full scan of a 243-column table, and any new dashboard query with no bound on `sample_collection_date` or `sample_tested_datetime`; (3) SQL built by concatenating request data instead of binding it, which this codebase does widely enough that a new occurrence must be judged on whether the value can reach it from `$_POST` or the route; (4) a query over sample, patient or facility data that ignores the caller's `mappedFacilities` scope from the `credo` session container, where sibling queries in the same service apply it; (5) schema changes made anywhere but `sys/migrations/`, migrations that are not re-runnable on both fresh and upgraded installs, a migration whose version does not match `composer.json`, and `data/setup.sql` edited to make a schema change rather than to seed a fresh install; (6) a new controller or action with no matching rows in `dash_resources`, `dash_privileges` and `dash_roles_privileges_map`, which leaves the route unreachable by every role including the administrator, and the menu entry invisible; (7) user-visible strings that bypass `translate`, and database values echoed into a `.phtml` without `escapeHtml`, remembering that facility and laboratory names arrive from remote instances and are not trusted input; (8) ingestion that trusts a laboratory or instance identifier taken from the request payload where the credential already establishes it; (9) an index added without checking whether an existing index already covers the same columns in the same order, since `dash_form_vl` accumulated ten such duplicates; (10) a multi-value predicate written as `IN (?)` with the values joined into one bound string, which MySQL casts to the first value so the filter silently matches one of them. Rank findings by severity. If you find nothing in a category, say 'clear'. Do not pad."

**Where a second opinion earns its keep:** the dashboard aggregate queries, every migration,
the API ingestion path, and anything that changes what a panel counts. Routine CRUD does not.
Do not ritualize it into overhead.

A note on what this is worth, from the day the brief was written. Six passes over one day's
work found, among other things, a migration about to drop the only uniqueness constraint on
`(sample_code, lab_id)` on fresh installs, a stored XSS introduced while adding failed-login
auditing, and a service delegator that was registered and silently never ran. All three were
introduced while fixing something else, and none were caught by the person writing them. That
is the argument for a second look of some kind. It is not an argument for this particular
tool.

## 4. Standing invariants, in full

These are the rules section 1 lists, with the reasoning. The brief in section 3 is derived
from them, not the other way round. A change can be checked against them by reading.

- **Schema changes live in `sys/migrations/`.** `data/setup.sql` seeds a fresh install. Never
  edit it to change the schema. Migrations replay on fresh installs too, so they must be
  re-runnable. `bin/migrate` routes common DDL through helpers that check
  `information_schema` first. A new migration means a version bump in `composer.json` and
  `composer update --lock` so the lockfile hash stays current.

- **A controller is unreachable until the ACL knows it.** A new controller or action needs
  rows in `dash_resources`, `dash_privileges` and `dash_roles_privileges_map`. Without them
  the route exists and nobody can reach it, including the administrator, and the menu entry
  never appears. Grant to role 1 with a `NOT EXISTS` guard. The map has no unique key, so
  `INSERT IGNORE` does not prevent duplicates on replay.

- **Do not wrap an indexed datetime column in a function.** `DATE(sample_collection_date) >= ?`
  cannot use the index on `sample_collection_date`. Write the bounds as datetimes instead:
  `>= '2026-01-01 00:00:00'` and `<= '2026-06-30 23:59:59'`. On the DRC data this was the
  difference between examining 2,247,376 rows and 150,442 for the same answer. Comparisons
  against the `1970-01-01` and `0000-00-00` sentinels are exempt. Equality and inequality
  cannot drive an index whatever they are written against.

- **`dash_form_vl` is 243 columns wide.** Any aggregate touching a column with no index scans
  1.2 GB of clustered index. A panel that adds one such column to an otherwise index-only
  query pays for the whole table. Measure before adding one.

- **Historical rows carry no `lab_id`.** The sending instances did not populate it before
  2022, which leaves it null on roughly 59% of `dash_form_vl`. An INNER JOIN to
  `facility_details` drops every one of those rows. Use a LEFT JOIN and label the bucket, so
  totals reconcile across panels on the same page.

- **Never add an index without checking the table first.** `ALTER TABLE ... ADD INDEX` on a
  column that already has one succeeds. MySQL appends a `_2` suffix and maintains both on
  every insert. `dash_form_vl` and `dash_form_eid` accumulated ten duplicates this way,
  holding 306 MB and slowing the ingestion path that is the server's largest consumer of
  time.

- **One placeholder per value in an `IN` list.** `new WhereExpression("lab_id IN (?)",
  [implode(',', $ids)])` binds the list as a single string. It renders as
  `lab_id IN ('12, 34, 56')`, and MySQL casts that to `12` against an integer column, so the
  predicate matches the first id and drops the rest. Build the placeholders with
  `array_fill(0, count($values), '?')`. `SnapShotService::inList` is the worked example.

- **Timestamps come from one clock.** `App\Timezone` applies `defaults.time-zone` at every
  entry point, and a delegator puts each database connection on the same offset. Do not call
  `date_default_timezone_set()` anywhere else. A second one is what produced the original
  split, where PHP wrote UTC and SQL `NOW()` wrote the system zone into the same table. Note
  that a delegator has to be keyed on `AdapterInterface`, because `Adapter::class` is an alias
  and the container resolves an alias before it looks for delegators.

- **Facility names are not trusted input.** They arrive from remote instances over the API.
  Escape them with `escapeHtml` before rendering, and put user-visible strings through
  `translate`.

## 5. Where the numbers in this document come from

Every figure here was measured against the DRC production database. Re-measure before citing
them for another country. The row counts, the null `lab_id` share, and the index sizes are all
specific to that install.
