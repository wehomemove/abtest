# 🚀 Motus Integration Steps

Follow these steps to integrate the A/B testing package into your Motus project:

## Step 1: Copy Files

1. **Copy the controller** to your Motus project:
   ```bash
   cp motus-test-controller.php /path/to/motus/app/Http/Controllers/AbTestController.php
   ```yo ucan fine them 

2. **Copy the view** to your Motus resources:
   ```bash
   cp motus-test-view.blade.php /path/to/motus/resources/views/ab-test-page.blade.php
   ```

## Step 2: Add Routes

Add these routes to your `routes/web.php` file:

```php
use App\Http\Controllers\AbTestController;

// A/B Testing test routes
Route::get('/ab-test', [AbTestController::class, 'testPage'])->name('ab-test.page');
Route::post('/ab-test/api-test', [AbTestController::class, 'apiTest'])->name('ab-test.api');
```

## Step 3: Install Package

In your Motus project root:

```bash
# Install the package
composer require homemove/ab-testing

# Run migrations (if not auto-discovered)
php artisan migrate

# Clear caches
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

## Step 4: Configure Service Provider

If auto-discovery doesn't work, add to `config/app.php`:

```php
'providers' => [
    // ...
    Homemove\AbTesting\Providers\AbTestingServiceProvider::class,
],
```

## Step 5: Test Integration

1. **Visit the test page:**
   ```
   https://motus.localhost/ab-test
   ```

2. **Check package status** - Click "Check Package Status" button

3. **Load experiment** - Click "Load Experiment" to test variant assignment

4. **Test routes** - Click "Test Routes" to verify all endpoints work

## Step 6: Create Test Experiment

Run this in your Motus project to create a test experiment:

```bash
php artisan tinker
```

Then in Tinker:
```php
DB::table('ab_experiments')->insert([
    'name' => 'survey_red_buttons',
    'description' => 'Test red buttons on survey pages',
    'variants' => json_encode(['control' => 50, 'red_buttons' => 50]),
    'is_active' => true,
    'created_at' => now(),
    'updated_at' => now()
]);
```

## Troubleshooting

**Package not found?**
- Check `composer show | grep ab-testing`
- Run `composer dump-autoload`

**Routes not working?**
- Run `php artisan route:list | grep ab`
- Check service provider is loaded

**Database errors?**
- Run `php artisan migrate`
- Check database connection in `.env`

**Debug panel not showing?**
- Set `APP_DEBUG=true` in `.env`
- Check middleware is loaded

## Success Indicators

✅ Package status shows "success: true"  
✅ Variant assignment returns "control" or "red_buttons"  
✅ Routes test shows 200 status codes  
✅ Debug panel appears on pages (if debug enabled)  
✅ Event tracking works without errors

Once all tests pass, your A/B testing package is fully integrated! 🎉