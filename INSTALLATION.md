# A/B Testing Package Installation Guide

## Step 1: Install the Package

### In Motus (where package is located):

```bash
# Add to composer.json repositories section
"repositories": [
    {
        "type": "path",
        "url": "./packages/homemove/ab-testing"
    }
]

# Install the package
composer require homemove/ab-testing
```

### In Apollo:

```bash
# Add to composer.json repositories section  
"repositories": [
    {
        "type": "path",
        "url": "../motus/packages/homemove/ab-testing"
    }
]

# Install the package
composer require homemove/ab-testing
```

## Step 2: Configuration

```bash
# Publish config and run migrations
php artisan vendor:publish --provider="Homemove\AbTesting\Providers\AbTestingServiceProvider"
php artisan migrate
```

## Step 3: Set up Redis (for caching)

Make sure Redis is configured in your .env:

```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_DRIVER=redis
```

## Step 4: Access Dashboard

Visit: `http://your-app.test/ab-testing/dashboard`

## Step 5: Create Your First Experiment

1. Go to the dashboard
2. Click "New Experiment"
3. Fill in experiment details:
   - Name: `checkout_flow`
   - Description: `Testing new checkout design`
   - Variants: control (50%), variant_b (50%)
   - Traffic: 100%

## Step 6: Implement in Your Code

### Blade Templates:
```blade
@variant('checkout_flow', 'variant_b')
    <div class="new-checkout">New design</div>
@else  
    <div class="old-checkout">Original design</div>
@endvariant

{{-- Track conversions --}}
<button onclick="trackConversion()">Complete Purchase</button>
@abtrack('checkout_flow', null, 'conversion')
```

### Controllers:
```php
use Homemove\AbTesting\Facades\AbTest;

public function checkout()
{
    $variant = AbTest::variant('checkout_flow');
    
    if ($variant === 'variant_b') {
        // Show new checkout flow
        return view('checkout.new');
    }
    
    return view('checkout.original');
}

public function purchase()
{
    // Track conversion
    AbTest::track('checkout_flow', null, 'conversion');
    
    // Continue with purchase logic...
}
```

### Middleware (optional):
```php
// In routes/web.php
Route::get('/checkout', 'CheckoutController@show')
    ->middleware('ab-test:checkout_flow');

// This automatically assigns variants and tracks page views
```

## Step 7: Monitor Results

1. Visit dashboard: `/ab-testing/dashboard`
2. Click on your experiment
3. Monitor conversion rates and statistical significance
4. Make decisions based on data

## Advanced Usage

### Target Specific Users:
```php
// Use authenticated user ID
$variant = AbTest::variant('checkout_flow', auth()->id());

// Use custom user identifier  
$variant = AbTest::variant('checkout_flow', $customUserId);
```

### Track Custom Events:
```php
AbTest::track('checkout_flow', null, 'button_click', [
    'button' => 'add_to_cart',
    'page' => 'product_detail'
]);
```

### Clear Cache:
```php
// Clear specific experiment cache
AbTest::clearCache('checkout_flow');

// Clear all A/B test cache
AbTest::clearCache();
```

## Database Tables Created

- `ab_experiments`: Experiment configurations
- `ab_user_assignments`: User variant assignments  
- `ab_events`: Event tracking data

## Security Notes

- Dashboard has no authentication by default
- Add authentication middleware to routes in production
- Consider IP-based rate limiting for tracking endpoints

## Troubleshooting

### Package not found:
- Check composer.json repositories configuration
- Run `composer update` after adding repository

### Views not loading:
- Run `php artisan vendor:publish --provider="Homemove\AbTesting\Providers\AbTestingServiceProvider"`
- Clear view cache: `php artisan view:clear`

### Cache issues:
- Restart Redis
- Check Redis connection in .env
- Run `php artisan cache:clear`