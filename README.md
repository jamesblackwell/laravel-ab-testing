# Laravel A/B Testing

A simple, robust A/B testing framework for Laravel using [Laravel Pennant](https://laravel.com/docs/10.x/pennant).

- **Code driven** - define experiments using Laravel Pennant. Track views and conversions in your code.
- **Zero flicker** - determine variants before rendering, no calls to external services.
- **Privacy friendly** - keep all data on server.
- **Admin dashboard** for real-time results and statistical significance
- **Supports primary and secondary goals**

## Motivation

Laravel Pennant is a great package to handle feature flags, however it still requires you to setup a lot of tracking to run actual A/B tests. This package works on top of Pennant and adds various easy to use helpers to track views and conversions, then displays the results in a dashboard.

---

## Installation

```bash
composer require quizgecko/laravel-ab-testing
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag=ab-testing-migrations
php artisan migrate
```

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=ab-testing-config
```

---

## Setup

1. **Define Experiments**

Define your experiments in a service provider (e.g., `AppServiceProvider`). Use the `Feature` facade from Laravel Pennant. Always use a descriptive, kebab-case name including month/year.

Important: currently only two variants are supported and the Feature should return `'test'` or `'control'`.
You can also return `'not-in-experiment'` to exclude a user from the experiment. Only variants with `'test'` or `'control'` are considered in the admin dashboard.

```php
use Illuminate\Support\Facades\Feature;
use Illuminate\Support\Arr;
use App\Models\User;

Feature::define('homepage-signup-copy-april-2025', function ($scope) {
    // Exclude paid users
    if ($scope instanceof User && $scope->is_paid) {
        return 'not-in-experiment';
    }
    // 50/50 split for guests and free users
    return Arr::random(['test', 'control']);
});
```

- **Scope**: Use `$scope` as either a `User` or a guest ID (like `abid()`).
- **Return values**: `'test'`, `'control'`, or `'not-in-experiment'`.

2. **Check Variant in Code**

Use the `feature_flag()` helper to get the assigned variant:

```php
$variant = feature_flag('homepage-signup-copy-april-2025', $userOrUniqueId);
if ($variant === 'test') {
    // Show test variation
} elseif ($variant === 'control') {
    // Show control
} else {
    // Not in experiment
}
```

3. **Track Views and Conversions**

Call these helpers at the appropriate places:

```php
experiment_view('homepage-signup-copy-april-2025', $userOrUniqueId); // When user sees the experiment
experiment_conversion('homepage-signup-copy-april-2025', $userOrUniqueId); // When user completes the primary goal
experiment_secondary_conversion('homepage-signup-copy-april-2025', $userOrUniqueId); // For secondary goals
```

- Always pass the same scope (`User` or `abid`) as used in the feature definition.
- Views are only tracked once per user/guest per experiment.
- Conversions are only tracked if a view was previously tracked.

---

## Admin Dashboard

Visit `/admin/ab` (requires `auth` and `can:viewAdmin`) to see:

- All running experiments
- Views, conversions, conversion rates for each variant
- Statistical significance (p-value, confidence)
- Primary and secondary goal tracking

---

## Database

The package creates an `experiments` table:

- `experiment_name` (string)
- `variant` (string)
- `total_views` (int)
- `conversions` (int)
- `secondary_conversions` (int)

---

## Helper Functions

- `feature_flag($experimentName, $scope = null)`
- `experiment_view($experimentName, $scope = null, $variant = null)`
- `experiment_conversion($experimentName, $scope = null)`
- `experiment_secondary_conversion($experimentName, $scope = null)`
- `abid()`

## abid

The `abid()` function returns a unique identifier for the current user or guest. It is used to track views and conversions for anonymous users.

```php
$abid = abid();
```

For example, if you wanted to test a signup flow, use the abid as the scope:

```php
$variant = feature_flag('homepage-signup-copy-april-2025', abid());
```

---

## Best Practices

- Use descriptive, kebab-case experiment names with month/year.
- Always pass the correct scope (User or abid) to helpers.
- Remove old experiments and helpers when finished.
- Place `experiment_view()` where the user meaningfully sees the experiment.
- Place `experiment_conversion()` where the primary goal is completed.

---

## License

MIT
