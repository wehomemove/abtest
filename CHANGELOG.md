# Changelog

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

### Accept Variant
- `POST dashboard/{experiment}/accept` (type-to-confirm): winner to 100% of
  traffic — existing assignments included via a `variant()` short-circuit that
  also bypasses the per-user cache; weights 0/100; `status = completed`;
  tracking stays live; adaptive allocation disabled while accepted.
  Assignment history is never rewritten.
- `POST dashboard/{experiment}/reopen` restores the snapshotted
  pre-acceptance state.
- Cleanup report: queued scan of the host codebase for leftover experiment
  code (name + variant literals, suggested actions), stored in the new
  `ab_acceptance_reports` table, rendered on the dashboard, dispatched as the
  `VariantAccepted` event and POSTed to `accept.webhook_url` for a PR bot.
- New migration: `primary_metric`, `accepted_variant`, `accepted_at`,
  `pre_acceptance` on `ab_experiments`; new `ab_acceptance_reports` table.
  All additive and nullable — zero-downtime.
