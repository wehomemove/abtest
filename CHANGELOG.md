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
- New migration: nullable `primary_metric` on `ab_experiments` (guarded,
  additive — zero-downtime).
