# Laravel A/B Testing Package

[![Latest Stable Version](https://poser.pugx.org/wehomemove/abtest/v/stable)](https://packagist.org/packages/wehomemove/abtest)
[![License](https://poser.pugx.org/wehomemove/abtest/license)](https://packagist.org/packages/wehomemove/abtest)

A comprehensive Laravel package for A/B testing with user-organized event tracking, interactive dashboard, and count-based analytics.

## ✨ Features

- 🎯 **Multi-Application Targeting** - Run experiments across multiple apps
- 📊 **Statistical Significance** - Built-in calculations with configurable confidence levels  
- 🎨 **Beautiful Dashboard** - Intuitive UI for experiment management
- 📱 **Simple Integration** - Easy `@variant()` Blade directives and facades
- 📈 **Event Tracking** - Track conversions, clicks, and custom events
- 🛡️ **Session Security** - Secure user identification and assignment
- 🎛️ **Traffic Control** - Precise traffic allocation and rollout controls

## 🚀 Quick Start

### 1. Install

```bash
composer require wehomemove/abtest
php artisan vendor:publish --provider="Homemove\AbTesting\Providers\AbTestingServiceProvider"
php artisan migrate
```

### 2. Create Experiment

Visit `/ab-testing/dashboard` or create programmatically:

```php
Experiment::create([
    'name' => 'checkout_button',
    'variants' => ['control' => 50, 'new_design' => 50],
    'is_active' => true
]);
```

### 3. Use in Blade Templates

```blade
@if(AbTest::variant('checkout_button') === 'new_design')
    <button class="btn-new">Complete Purchase</button>
@else
    <button class="btn-default">Buy Now</button>
@endif
```

### 4. Track Conversions

```php
// Perfect for Stripe payments!
AbTest::track('checkout_button', $userId, 'conversion', [
    'amount' => $paymentAmount,
    'currency' => 'usd'
]);
```

## 📊 Dashboard

Access your dashboard at: `/ab-testing/dashboard`

- **Funnel view** — per-variant drop-off, step by step, with the biggest drop
  highlighted per arm (see *Funnels* below)
- Real-time conversion rates, per-device filtering, event filtering
- **Per-variant statistical significance** — every arm tested against control
  (two-proportion z-test); the headline card names the best-performing arm
- Real day-over-day conversion-rate movement
- Conversion-over-time line chart (24h / 7d / 30d)
- Live activity feed and per-user event breakdowns
- Manage experiments (create, pause, delete)

## 🔽 Funnels

The dashboard renders a funnel of where each variant loses people. Two data
tiers, picked automatically:

**1. Event reach funnel (built in).** Distinct users reaching each named event,
per variant, from `ab_events`. Step order comes from the experiment's *Funnel
steps* list (editable on the create/edit forms; stored in `custom_events`), or
falls back to first-observation order. `ab_events` stores one upserted row per
user + event — there is no inter-event ordering — so this tier is honestly
labelled "first touch reach", and `conversion` always renders last.

**2. Step-level funnel (host-provided).** If your app keeps a real telemetry
stream (page views, form steps), bind the provider and the dashboard shows
step-level drop-off instead:

```php
use Homemove\AbTesting\Contracts\FunnelStepDataProvider;

// app/Providers/AppServiceProvider.php
$this->app->bind(FunnelStepDataProvider::class, MyFunnelProvider::class);
```

```php
class MyFunnelProvider implements FunnelStepDataProvider
{
    public function funnelFor(string $experimentName, ?string $deviceType = null): ?array
    {
        // Return null when you have no data for this experiment — the
        // dashboard falls back to the reach funnel.
        return [
            'source' => 'my_telemetry_table',
            'steps' => [
                ['key' => 'landing', 'label' => 'Landing', 'index' => 0, 'counts' => ['control' => 900, 'variant_b' => 880]],
                ['key' => 'question_1', 'label' => 'Question 1', 'index' => 1, 'counts' => ['control' => 400, 'variant_b' => 610]],
                // ...
            ],
        ];
    }
}
```

## 📡 Dashboard API

Endpoints polled by the dashboard (all under `/api/ab-testing/experiments/{id}`):

- `GET /stats` — variants (participants/conversions/rate/lift/color), totals,
  `statistical_significance` (headline, named arm), `significance_by_variant`,
  `rate_change_pp`, `event_counts_by_name`. Add `?include=funnel` for funnel
  data (the dashboard requests it on a slower 60s cadence).
- `GET /recent-activity` — merged feed of latest events + assignments.
- `GET /chart-data?period=24h|7d|30d` — per-variant time series:
  `{labels, variants: {name: {participants[], conversion_rate[], color}}}`.

> **Security note:** `routes.dashboard_middleware` defaults to `['web']` —
> **unauthenticated**, for backwards compatibility. The dashboard is a
> management surface (create/edit/delete experiments, set the primary
> metric): production apps MUST set real auth middleware here (e.g.
> `['web', 'auth', 'your-admin-middleware']`) or gate the
> `/ab-testing/dashboard*` paths in host middleware. Since v1.6 the
> read-only stats endpoints (`/results`, `/stats`, `/recent-activity`,
> `/chart-data`) run under the same group (overridable via
> `routes.stats_middleware`), so gating the dashboard gates them too. The
> tracking endpoints (`/track`, `/variant`, `/register-debug`) stay open —
> they are called from every visitor's browser.

## 🎯 Conversion metric (v1.6)

Stats default to the `conversion` event. On the experiment page a
**Conversion metric** dropdown recomputes every stat (cards, table,
significance, chart, funnel terminal step) as if any other tracked event were
the conversion — carried into the polled API as `?event=`. **Save as primary**
persists the choice per experiment (`primary_metric` column; `conversion`
stores as null). Live tracking is never affected.

## 🔧 Configuration

Publish the config (`php artisan vendor:publish --tag=config`). Key options:

```php
'routes' => [
    'enabled' => true,                    // false: register no package routes
    'dashboard_middleware' => ['web'],    // e.g. ['web','auth','super_admin']
    'api_middleware' => ['api'],          // open tracking endpoints
    'stats_middleware' => null,           // null = inherit dashboard_middleware
],
'debug_middleware' => env('AB_TESTING_DEBUG_MIDDLEWARE', false), // opt-in panel (also needs app.debug)
'cookie' => ['secure' => null, 'same_site' => 'Lax'],            // null secure = mirror the request
```

`cache.*` and `session_key` are honoured since v1.6. `database.*_table` and
`tracking.queue` are reserved and not yet implemented; `target_applications`
is host-specific legacy.

> **Upgrading from ≤1.5:** the debug panel no longer auto-injects on
> `app.debug` — set `AB_TESTING_DEBUG_MIDDLEWARE=true` to keep it. The stats
> API endpoints move out of the `api` middleware group into the dashboard's
> group (same URLs and route names).

## 📖 Advanced Usage

### Custom Events

```php
AbTest::track('button_test', $userId, 'button_click', [
    'button_type' => 'add_to_cart',
    'page' => 'product_detail'
]);
```

### JavaScript Integration

```html
<script>
window.abtrack = function(experiment, event, properties = {}) {
    return fetch('/api/ab-testing/track', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json', 
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content 
        },
        body: JSON.stringify({ experiment, event, properties })
    });
};

// Usage
abtrack('checkout_button', 'conversion');
</script>
```

### Multi-Application Targeting

```php
Experiment::create([
    'name' => 'mobile_nav',
    'target_applications' => ['motus', 'apollo'], // Only these apps
    'variants' => ['control' => 60, 'hamburger' => 40],
]);
```

## 🧪 Testing

```bash
composer test
```

## 📋 Requirements

- PHP 8.1+
- Laravel 10.0+
- PostgreSQL/MySQL/SQLite

## 📄 License

MIT License - see [LICENSE](LICENSE) file.

---

**Built with ❤️ by the Homemove Team**

For support: [GitHub Issues](https://github.com/wehomemove/abtest/issues)