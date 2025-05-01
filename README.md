# Laravel A/B Testing (Pennant)

A simple, robust A/B testing framework for Laravel using [Laravel Pennant](https://laravel.com/docs/10.x/pennant).

- **Track experiments** for both guests and authenticated users
- **Automatic view/conversion tracking** with caching to prevent double counting
- **Admin dashboard** for real-time results and statistical significance
- **Supports primary and secondary goals**
- **Flexible scope**: use user model or guest ID (e.g. at Quizgecko we have a qgid() helper)

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

Optionally, publish the views:

```bash
php artisan vendor:publish --tag=ab-testing-views
```

---

## Setup

1. **Define Experiments**

Define your experiments in a service provider (e.g., `AppServiceProvider`). Use the `Feature` facade from Laravel Pennant. Always use a descriptive, kebab-case name including month/year.

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

- **Scope**: Use `$scope` as either a `User` or a guest ID (like `qgid()`).
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

- Always pass the same scope (`User` or `qgid`) as used in the feature definition.
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

---

## Best Practices

- Use descriptive, kebab-case experiment names with month/year.
- Always pass the correct scope (User or qgid) to helpers.
- Remove old experiments and helpers when finished.
- Place `experiment_view()` where the user meaningfully sees the experiment.
- Place `experiment_conversion()` where the primary goal is completed.

---

## License

MIT
