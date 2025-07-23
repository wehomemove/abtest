# Laravel A/B Testing Package

[![Latest Stable Version](https://poser.pugx.org/wehomemove/abtest/v/stable)](https://packagist.org/packages/wehomemove/abtest)
[![License](https://poser.pugx.org/wehomemove/abtest/license)](https://packagist.org/packages/wehomemove/abtest)

A comprehensive Laravel package for A/B testing with user-organized event tracking, interactive dashboard, and count-based analytics.

![A/B Testing Dashboard](https://via.placeholder.com/800x400/4F46E5/FFFFFF?text=A%2FB+Testing+Dashboard)

## ✨ Features

- 🎯 **Multi-Application Targeting** - Run experiments across Motus, Apollo, and Olympus
- 📊 **Statistical Significance** - Built-in z-test calculations with configurable confidence levels  
- 🚀 **High Performance** - Redis caching with <15ms response times
- 🎨 **Beautiful Dashboard** - Intuitive UI for experiment management and analytics
- 📱 **Blade Directives** - Simple `@variant()` syntax for templates
- 🔄 **Sticky Assignments** - Users see consistent variants across sessions
- 📈 **Custom Metrics** - Track conversions, clicks, and custom events
- 🛡️ **Session Security** - Secure user identification and assignment
- 🎛️ **Traffic Control** - Precise traffic allocation and rollout controls
- 📋 **Comprehensive Testing** - Full PHPUnit test coverage

## 🚀 Quick Start

### 1. Installation

```bash
composer require wehomemove/abtest
```

### 2. Setup

```bash
# Publish configuration and migrations
php artisan vendor:publish --provider="Homemove\AbTesting\Providers\AbTestingServiceProvider"

# Run migrations
php artisan migrate
```

### 3. Include JavaScript Helper

Add to your layout file:
```html
<!-- Include A/B testing helper -->
<script src="{{ asset('vendor/abtest/abtest.js') }}"></script>

<!-- Or copy the helper function -->
<script>
window.abtrack = function(experiment, event, properties = {}) {
    return fetch('/api/ab-testing/track', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ experiment, event, properties })
    }).catch(console.error);
};
</script>
```

### 4. Track Events (Super Simple!)

```javascript
// Basic tracking
abtrack('button_color_test', 'button_click');

// With properties
abtrack('button_color_test', 'button_click', {
    button_type: 'add_property',
    page: 'mortgages_service'
});

// Track conversions
abtrack('checkout_flow', 'conversion', { amount: 99.99 });
```

### 5. Access Dashboard

Visit: **`http://your-app.test/ab-testing/dashboard`**

## 📖 Usage Guide

### Creating Your First Experiment

1. **Dashboard Setup:**
   - Go to `/ab-testing/dashboard`
   - Click "New Experiment"
   - Configure experiment settings:

```yaml
Name: checkout_flow
Description: Testing new checkout design vs original
Applications: [motus, apollo]
Traffic: 50% (gradual rollout)
Variants:
  - control: 50%
  - new_design: 50%
Success Metrics: [conversion, checkout_completion]
Minimum Sample Size: 100
Confidence Level: 95%
```

### 2. **Implementation in Code:**

**Blade Templates:**
```blade
@variant('checkout_flow', 'new_design')
    <div class="checkout-v2">
        <h2>New Streamlined Checkout</h2>
        <button class="btn-primary-v2" onclick="trackCheckout()">
            Complete Purchase
        </button>
    </div>
@else  
    <div class="checkout-v1">
        <h2>Standard Checkout</h2>
        <button class="btn-primary" onclick="trackCheckout()">
            Buy Now
        </button>
    </div>
@endvariant

{{-- Track conversions --}}
<script>
function trackCheckout() {
    abtrack('checkout_flow', 'checkout_click');
    // Continue with checkout logic
}
</script>
```

**Controllers:**
```php
use Homemove\AbTesting\Facades\AbTest;

class CheckoutController extends Controller 
{
    public function show()
    {
        $variant = AbTest::variant('checkout_flow');
        
        return view('checkout.index', [
            'variant' => $variant,
            'showNewFeatures' => $variant === 'new_design'
        ]);
    }
    
    public function complete(Request $request) 
    {
        // Process payment...
        
        // Track successful conversion
        AbTest::track('checkout_flow', null, 'conversion', [
            'amount' => $request->amount,
            'payment_method' => $request->payment_method
        ]);
        
        return redirect()->route('success');
    }
}
```

**Middleware (Auto-Assignment):**
```php
// routes/web.php
Route::group(['middleware' => ['ab-test:checkout_flow']], function () {
    Route::get('/checkout', [CheckoutController::class, 'show']);
    Route::post('/checkout', [CheckoutController::class, 'complete']);
});
```

## 📊 Analytics & Results

### Dashboard Features

- **Real-time Metrics**: Live conversion rates and user assignments
- **Statistical Analysis**: Confidence intervals, p-values, z-scores  
- **Variant Comparison**: Side-by-side performance analysis
- **Traffic Monitoring**: Application-specific traffic breakdown
- **Event Timeline**: Recent user actions and conversions

### Sample Results View

```
Experiment: checkout_flow
Status: Running | Confidence: 95% | Sample Size: 1,247 users

┌─────────────┬───────────┬─────────────┬─────────────┬──────────────┬────────────┐
│   Variant   │ Traffic % │ Participants │ Conversions │ Conv. Rate   │    Lift    │
├─────────────┼───────────┼─────────────┼─────────────┼──────────────┼────────────┤
│   control   │    50%    │     623     │     87      │    13.97%    │     -      │
│ new_design  │    50%    │     624     │    118      │    18.91%    │  +35.4% ✓  │
└─────────────┴───────────┴─────────────┴─────────────┴──────────────┴────────────┘

📈 Statistical Significance: YES (p-value: 0.0023, z-score: 3.04)
```

## 🔧 Advanced Configuration

### Application Targeting
```php
// Only run in specific apps
Experiment::create([
    'name' => 'mobile_nav_test',
    'target_applications' => ['motus'], // Only in Motus app
    'variants' => ['control' => 60, 'hamburger_menu' => 40],
]);
```

### Custom Metrics & Events
```php
// Track custom business metrics
AbTest::track('checkout_flow', auth()->id(), 'add_to_cart', [
    'product_id' => $product->id,
    'category' => $product->category,
    'value' => $product->price
]);

// Track time-based metrics  
AbTest::track('page_layout', null, 'time_on_page', [
    'seconds' => 45,
    'scroll_depth' => '75%'
]);
```

### Targeting Rules (Advanced)
```php
// Target specific user segments
$experiment->targeting_rules = [
    'user_type' => ['premium', 'enterprise'],
    'location' => ['UK', 'US'],  
    'device' => 'mobile'
];
```

## 🧪 Testing & Quality

### Running Tests
```bash
# Run package tests
composer test

# Run with coverage
composer test -- --coverage-html coverage
```

### Test Example
```php
use Homemove\AbTesting\Facades\AbTest;

/** @test */
public function it_assigns_consistent_variants()
{
    $this->createExperiment('test_exp', [
        'control' => 50,
        'variant_b' => 50  
    ]);
    
    $userId = 'user_123';
    
    // Should get same variant on repeated calls
    $variant1 = AbTest::variant('test_exp', $userId);  
    $variant2 = AbTest::variant('test_exp', $userId);
    
    $this->assertEquals($variant1, $variant2);
}
```

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    A/B Testing Architecture                     │
└─────────────────────────────────────────────────────────────────┘

┌─────────┐  ┌─────────┐  ┌─────────┐     ┌─────────────────┐
│  Motus  │  │ Apollo  │  │Olympus  │────▶│   Dashboard     │
│   App   │  │   App   │  │  App    │     │ (Management UI) │  
└─────────┘  └─────────┘  └─────────┘     └─────────────────┘
     │            │            │                    │
     └────────────┼────────────┼────────────────────┘
                  │            │           
                  ▼            ▼
         ┌─────────────────────────────────┐
         │     A/B Testing Package         │
         │   • Variant Assignment          │ 
         │   • Event Tracking             │
         │   • Statistical Analysis        │
         │   • Application Detection       │
         └─────────────────────────────────┘
                       │
                       ▼
              ┌─────────────────┐      ┌─────────────────┐
              │ Redis Cache     │      │ PostgreSQL DB   │ 
              │ (Fast Lookup)   │      │ (Experiments,   │
              │                 │      │  Events, Users) │
              └─────────────────┘      └─────────────────┘
```

## 🔍 Troubleshooting

**Common Issues:**

1. **"No publishable resources" error:**
   ```bash
   php artisan vendor:publish --provider="Homemove\AbTesting\Providers\AbTestingServiceProvider" --tag=config
   ```

2. **Variants not showing:**
   - Check experiment is `active` and `status='running'`
   - Verify application targeting includes current app
   - Clear cache: `php artisan cache:clear`

3. **Dashboard not loading:**  
   - Ensure routes are loaded: `php artisan route:list | grep ab-testing`
   - Check database migrations: `php artisan migrate:status`

## 🤝 Contributing

```bash
# Clone and setup
git clone git@github.com:wehomemove/abtest.git
cd abtest

# Install dependencies  
composer install

# Run tests
composer test
```

## 📋 Requirements

- PHP 8.2+
- Laravel 11.0+
- Redis (recommended)
- PostgreSQL/MySQL

## 📄 License

MIT License - see [LICENSE](LICENSE) file.

---

**Built with ❤️ by the Homemove Team**

For support: [GitHub Issues](https://github.com/wehomemove/abtest/issues)