# Solkeb Hotel Management System

Front-desk and back-office system for **Solkeb Hotel, Jijiga**: bookings, check-in / check-out, guest bills,
payments into the hotel's bank accounts and wallets, organisation credit billing, reports and printouts.

Built with **Laravel 13** (PHP 8.3+), server-rendered Blade pages and plain CSS/JS. There is no build step.

---

## Features

| Area | What it does |
|---|---|
| **Dashboard** | Occupancy, arrivals, departures (due out today / overdue in red), rooms to clean, money collected today |
| **Room board** | Every room by floor: available, reserved, occupied, leaving today / overdue, out of order, needs cleaning; group bookings share a colour |
| **Bookings** | Choose dates → only free rooms are offered; one or many rooms; guest; "Bill to" an organisation, optionally on credit |
| **Check-in** | The guest in each room with ID type, ID number and ID photos (stored privately). A room whose guest has not come can be ticked *Not arrived yet*: it moves to its own booking |
| **Bill** | Room nights are added at check-in. Extra charges are description × price × quantity; managers can give discounts |
| **Payments** | Cash or one of the hotel's bank accounts / wallets, with transaction number and who paid. No advance payment by default (Hotel settings) |
| **Check-out** | Locked until what is owed for the nights actually stayed is paid. Room nights are re-counted for early departure / overstay. Organisations on credit get a credit invoice instead |
| **Organisations** | Credit allowance and payment terms, what each owes, credit invoices, payment request letters, one transfer shared over several invoices |
| **Bank accounts** | The hotel's accounts and wallets (several per bank), a statement per account, the one account printed on invoices |
| **Reports** | Revenue & occupancy (CSV export), night audit with cash-up per staff member and per account, staff report, *My activity* for each person |
| **Printouts** | Guest bill, credit invoice, payment request letter, breakfast list, night audit |
| **History** | Every action on a booking is logged with the staff member who did it. Deleted bookings stay in a log and can be restored by the admin |

## Roles

| Role | Can do |
|---|---|
| **admin** | Everything, including staff accounts, hotel settings, bank accounts, restoring deleted bookings and deleting finished stays |
| **manager** | Front desk, setup (rooms, prices, organisations), discounts, refunds, deleting bookings made by mistake, reports |
| **receptionist** | Bookings, check-in / check-out, bills, payments, guests, breakfast list, own *My activity* |
| **accountant** | Bookings and guests (view), payments and refunds, organisations, reports |

Page access is set per route with the `role:` middleware in `routes/web.php`. Finer permissions
(`give-discount`, `record-refund`, `delete-finished-stay`) are Gates in `app/Providers/AppServiceProvider.php`.

---

## Getting started (development)

Requirements: PHP 8.3+ with the usual Laravel extensions plus `pdo_mysql` (and `gd` to run the tests); Composer; MySQL 8 / MariaDB 10.6+.
On Windows, [Laragon](https://laragon.org) provides all of these.

```bash
composer install
cp .env.example .env          # then set DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan key:generate
php artisan migrate --seed    # tables + admin account, rooms and services
php artisan serve             # or use the Laragon site, e.g. http://solkeb.test
```

The seeder creates the admin account **admin@solkebhotel.com** with the password **password**.
Change it after the first login: click your name at the bottom of the sidebar.

Optional training data (bookings, guests, payments and four receptionists, all with password `password`):

```bash
php artisan db:seed --class=SampleBookingsSeeder   # removes existing bookings, guests and payments first
```

### Tests

```bash
php artisan test        # feature tests use an in-memory SQLite database, not your MySQL data
vendor/bin/pint         # format code to the Laravel style before committing
```

---

## Where things are

```
app/
  Exceptions/BookingException.php   A hotel rule stopped the action → shown to staff as a red alert
  Http/Controllers/                 Thin: read the request, call a service, return a page or redirect
  Http/Requests/                    Form validation (one class per form) and form-specific permissions
  Http/Middleware/EnsureRole.php    role:admin,manager route protection
  Models/                           Eloquent models; Reservation holds booking statuses and bill totals
  Services/Booking/                 ReservationService, CheckInService, CheckOutService
  Services/Payments/                PaymentRecorder (cash / account / reference rules), OrganizationPaymentService
  Services/RoomImport.php           Reading room spreadsheets for Setup → Rooms → Import
  Support/Countries.php             Nationality list (Ethiopia and neighbours first)
resources/views/                    Blade pages; partials/ holds shared pieces (forms, letterhead...)
public/css/solkeb.css, public/js/   Styles and small scripts (no build step; bump ?v= in the layout after changes)
database/migrations, seeders        Schema history and starting data
tests/Feature/                      One file per feature area
```

### Key rules (and where they are enforced)

- **No double booking**: `Room::scopeAvailableBetween()`; a guest still in the room after their date keeps it.
- **Price fixed at booking**: stored per room in `reservation_room.nightly_rate`.
- **Bill = room nights + charges − payments**: `Reservation::balance()`; "if leaving today" in `dueIfLeavingToday()`.
- **Check-out needs the bill paid** unless the organisation is on credit: `CheckOutService`.
- **Non-cash payments name the account and transaction number**: `PaymentRecorder`.
- **Every action is logged**: `Activity::log()`.

---

## Deploying

- Set `APP_ENV=production`, `APP_DEBUG=false` and a real `APP_URL` in `.env`.
- Run `php artisan migrate --force`, then `php artisan optimize`.
- **Back up the database and `storage/app/private`** (guest ID photos) regularly. Neither is in git.
- SweetAlert2 is optional: see `public/vendor/sweetalert2/README.txt`.

## Git notes

Not committed (see `.gitignore` files): `.env`, `vendor/`, `node_modules/`, `storage/app/private/*`
(guest ID photos), logs, caches and local SQLite files. Line endings are normalised to LF (`.gitattributes`).
