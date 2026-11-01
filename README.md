# Orders back office

Customer orders and refunds: a checkout API for the storefront and an admin area for staff.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

The seed loads an anonymised copy of the staging data. Demo users (password `password`): `admin@example.com`, `finance@example.com`, `support@example.com`, `customer@example.com`.

## Tests

```bash
php artisan test
```
