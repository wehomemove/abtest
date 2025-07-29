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

- View real-time conversion rates
- Manage experiments (create, pause, delete)
- Statistical significance calculations
- Export experiment data

## 🔧 Configuration

Add to your `.env`:

```env
AB_TESTING_ENABLED=true
AB_TESTING_DEBUG=true
```

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