# 💰 Flexible Pricing System

This package includes a flexible pricing system that supports price changes without breaking existing subscriptions (grandfathering).

## Architecture

```
subscription_products (Products)
├── id, name, description
├── stripe_id (Stripe Product ID)
└── files_allowed (quota)

subscription_prices (Versioned Prices)
├── subscription_product_id (FK)
├── stripe_price_id (Stripe Price ID)
├── amount (cents)
├── currency (eur)
├── interval (month/year)
├── active (boolean)
└── archived_at (timestamp)

subscriptions (Laravel Cashier)
├── stripe_price (current Price ID)
└── Reference to subscription_prices
```

## Features

✅ **Price History** - Keep track of all price changes  
✅ **Grandfathering** - Existing subscriptions keep their original price  
✅ **Multi-frequency** - Support monthly and yearly plans per product  
✅ **Auto-sync** - Bidirectional sync with Stripe  
✅ **Price Archiving** - Automatically archive old prices

## Usage

### 1. Import from Stripe

```bash
# Import all products and prices from Stripe
php artisan ai-cad:sync-stripe

# Also archive inactive prices
php artisan ai-cad:sync-stripe --archive
```

### 2. Use in Code

```php
use Tolery\AiCad\Models\SubscriptionProduct;
use Tolery\AiCad\Models\SubscriptionPrice;

// Get a product with its prices
$product = SubscriptionProduct::with('prices')->find(1);

// Get active monthly price
$monthlyPrice = $product->activeMonthlyPrice;
echo $monthlyPrice->price; // 39.00 (automatically converted from cents)

// Get active yearly price
$yearlyPrice = $product->activeYearlyPrice;
echo $yearlyPrice->price; // 390.00

// Get all prices (including archived)
$allPrices = $product->prices;

// Get only active prices
$activePrices = $product->activePrices;
```

### 3. Query Prices

```php
// Get all active prices
$activePrices = SubscriptionPrice::active()->get();

// Get monthly prices
$monthly = SubscriptionPrice::monthly()->active()->get();

// Get yearly prices
$yearly = SubscriptionPrice::yearly()->active()->get();

// Get prices for a specific product
$productPrices = SubscriptionPrice::forProduct(1)->active()->get();
```

### 4. Archive a Price

```php
$price = SubscriptionPrice::find(5);
$price->archive();

// Now:
// - active = false
// - archived_at = now()
// - Still accessible for existing subscriptions
```

### 5. Create Stripe Resources from Local

```php
use Tolery\AiCad\Services\StripeSyncService;

$syncService = app(StripeSyncService::class);

// Create Stripe Product from local
$stripeProduct = $syncService->createStripeProductFromLocal($product);

// Create Stripe Price from local
$stripePrice = $syncService->createStripePriceFromLocal($price);
```

## Stripe Metadata

When creating products in Stripe Dashboard, add this metadata:

```
files_allowed: 10
```

This will be synced to the `files_allowed` column.

## Price Changes Workflow

### Scenario: Increase Basic plan from 39€ to 49€

1. **Create new price in Stripe**
   ```
   Product: Basic (prod_xxx)
   New Price: 49€/month (price_new_xxx)
   ```

2. **Sync to local database**
   ```bash
   php artisan ai-cad:sync-stripe --archive
   ```

3. **What happens:**
   - Old price (39€) archived: `active = false`, `archived_at = now()`
   - New price (49€) created: `active = true`
   - Existing subscriptions keep their `stripe_price = price_old_xxx`
   - New subscriptions use `stripe_price = price_new_xxx`

4. **Result:**
   - Old customers: Still pay 39€ ✅
   - New customers: Pay 49€ ✅
   - Complete price history maintained ✅

## Database Seeder

Use the seeder for initial setup:

```bash
php artisan db:seed --class="Tolery\\AiCad\\Database\\Seeders\\SubscriptionProductSeeder"
```

## Factory Usage (Testing)

```php
use Tolery\AiCad\Models\SubscriptionProduct;
use Tolery\AiCad\Models\SubscriptionPrice;

// Create a product with prices
$product = SubscriptionProduct::factory()->create();

$monthlyPrice = SubscriptionPrice::factory()
    ->monthly()
    ->create([
        'subscription_product_id' => $product->id,
        'amount' => 3900, // 39€
    ]);

$yearlyPrice = SubscriptionPrice::factory()
    ->yearly()
    ->create([
        'subscription_product_id' => $product->id,
        'amount' => 39000, // 390€
    ]);

// Create archived price
$oldPrice = SubscriptionPrice::factory()
    ->archived()
    ->create(['amount' => 2900]);
```

## API Reference

### SubscriptionProduct Model

**Relations:**
- `prices()` - All prices (active + archived)
- `activePrices()` - Only active prices

**Accessors:**
- `$product->activeMonthlyPrice` - Current monthly price
- `$product->activeYearlyPrice` - Current yearly price

### SubscriptionPrice Model

**Scopes:**
- `active()` - Only active, non-archived prices
- `monthly()` - Monthly interval
- `yearly()` - Yearly interval
- `forProduct($productId)` - Prices for specific product

**Methods:**
- `archive()` - Mark price as archived
- `isArchived()` - Check if price is archived

**Accessors:**
- `$price->price` - Amount in euros (float)

### StripeSyncService

**Methods:**
- `syncProductsFromStripe()` - Import all products/prices
- `archiveOldPrices()` - Archive inactive prices
- `createStripeProductFromLocal($product)` - Push product to Stripe
- `createStripePriceFromLocal($price)` - Push price to Stripe

## Testing

Run the test suite:

```bash
vendor/bin/pest tests/Feature/SubscriptionPriceTest.php
```

Tests cover:
- Price creation
- Relations
- Multiple prices per product
- Archiving
- Scopes
- Grandfathering scenarios
