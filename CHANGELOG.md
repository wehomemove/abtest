# Changelog

## v1.7.1 — 2026-09-22

### Fix
- Concurrent first-visit requests for the same user could both pass the
  "already assigned?" check and both insert; the unique index on
  `ab_user_assignments (experiment_id, user_id)` then failed the loser with a
  `UniqueConstraintViolationException` (a 500 on the page). The insert is now
  `insertOrIgnore` and the loser adopts the winning row, so one user always
  has exactly one variant and no request errors. No retry, no recomputation —
  adaptive allocation could otherwise pick a different arm on the second
  pass. Present since the first release; affects every version up to 1.7.0.

## v1.7.0 — 2026-09-16

### Assignment policy (opt-in, `config('ab-testing.assignment')`)
- `enforce_traffic_allocation` — users hashed outside `traffic_allocation`%
  resolve to `control` with **no assignment row** (not counted as
  participants). The allocation hash is salted separately from the arm hash,
  and `bucket < allocation` keeps ramps monotonic. Default `false` (v1.6:
  the column was displayed but never enforced).
- `enforce_schedule` — new assignments only while `status = running` and
  now() is inside `start_date`/`end_date` (the `Experiment::isActive()`
  rule). Existing assignments always win, so pausing/completing never moves a
  returning user. Default `false` (v1.6: only `is_active` was checked).
- `adaptive_allocation` — set `false` for pure deterministic md5 bucketing.
  Default `true` (v1.6 behaviour: after 20 assignments, steer new users into
  the most under-represented arm).
- No behaviour change unless you opt in.

### Lifecycle
- `store` sets `status` (`running` by default, or `draft` when posted) and
  derives `is_active` from it. Previously new rows kept the DB defaults
  (`is_active=1`, `status='draft'`).
- `update` accepts an optional `status` (`draft|running|paused|completed`)
  which derives `is_active`; without it the `is_active` checkbox behaves as
  before.
- Toggle now sets `status` to `running`/`paused` alongside `is_active`.
- New `POST /ab-testing/dashboard/{experiment}/complete`
  (`ab-testing.dashboard.complete`): `status=completed`, `is_active=false`,
  stamps `end_date` if unset.
- Fix: the funnel-steps editor on create/edit emitted raw JSON inside a
  double-quoted `x-data` attribute, which broke the Alpine component as soon
  as an experiment had steps (uses `Js::from` now).
- Variant names are validated on store/update: non-empty, `[a-z0-9_]+`. A
  blank name used to save as arm `0`.
- `Experiment::lifecycle()` returns `completed|draft|paused|scheduled|ended|running`
  (legacy/unknown statuses read as paused) and `storableStatus()` maps that
  back to a value the edit form can post. Package views use both.

## v1.6.0 — 2026-09-09

### Portability
- Route middleware is configurable: `routes.dashboard_middleware` (dashboard
  pages + new POST actions), `routes.api_middleware` (open tracking
  endpoints), `routes.stats_middleware` (read-only results endpoints; null
  inherits the dashboard group). `routes.enabled => false` registers nothing.
- **Behaviour change:** the read-only stats endpoints (`/results`, `/stats`,
  `/recent-activity`, `/chart-data`) now run under the dashboard's middleware
  group instead of `['api']` — gating the dashboard gates them too. Same URLs
  and route names.
- **Behaviour change:** `DebugMiddleware` no longer auto-injects into the
  `web` group whenever `app.debug` is true — opt in with
  `AB_TESTING_DEBUG_MIDDLEWARE=true`. Its per-request info logging is gone.
- `cache.prefix`, `cache.ttl` and `session_key` config keys are now honoured.
- The `ab_user_id` cookie is set with `SameSite=Lax` and `secure` mirroring
  the request (both configurable under `cookie.*`).

### Conversion metric
- New per-experiment `primary_metric` column: the event the dashboard treats
  as the conversion (null = `conversion`). Saved from the experiment page.
- `?event=` on the show page and the stats/results/chart-data endpoints
  recomputes all statistics against any tracked event (view-only lens); the
  page's pollers carry the selection.
- The reach funnel pins the selected conversion event last.
- New migration: nullable `primary_metric` on `ab_experiments` (guarded,
  additive — zero-downtime).
