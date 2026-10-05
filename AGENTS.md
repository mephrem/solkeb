# Working on this project

Solkeb Hotel Management System: a Laravel 13 app (PHP 8.3+, Blade, plain CSS/JS, MySQL). See README.md for
features, roles, setup and where things live.

## Conventions

- Controllers stay thin: validation in `app/Http/Requests`, hotel rules in `app/Services` (Booking, Payments).
- A broken hotel rule throws `App\Exceptions\BookingException` with a message for front-desk staff; it is shown
  as a red alert on the previous page (see `bootstrap/app.php`).
- Booking statuses and status groups are constants on `App\Models\Reservation` (CONFIRMED, CHECKED_IN, ACTIVE...).
  Roles are constants on `App\Models\User`.
- Page access: `role:` middleware in `routes/web.php`. Finer permissions: Gates in `AppServiceProvider`
  (`give-discount`, `record-refund`, `delete-finished-stay`), used with `@can` in Blade.
- Every change to a booking is logged with `Activity::log()`.
- Text on screen is plain English for hotel staff; money is ETB with 2 decimals; prices include VAT.
- After changing `public/css/solkeb.css` or `public/js/solkeb.js`, bump the `?v=` number where they are included.

## Before committing

```bash
vendor/bin/pint
php artisan test
```
