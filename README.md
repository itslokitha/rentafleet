# RentAFleet

A WordPress plugin for motorcycle and bike rental businesses. Provides a complete booking, payment, and fleet management solution with a customer-facing 5-step booking flow and a full-featured admin dashboard.

## Features

- **Booking Engine** — 5-step booking flow with search, vehicle selection, date/location picker, customer details, and confirmation
- **Fleet Management** — Track vehicles by category (Standard, Sport, Cruiser, Adventure, Touring, Scooter, Dirt, Naked) with specs, features, and images
- **Dynamic Pricing** — Daily, hourly, weekly, and monthly rates with seasonal overrides, weekend pricing, and coupon/discount support
- **Availability Checker** — Real-time availability with conflict detection and calendar view
- **Multi-Location Support** — Manage multiple pickup/dropoff locations with per-location fees and vehicle allocation
- **Booking Lifecycle** — Full status tracking: pending → confirmed → active → completed, with cancellation, no-show, and refund states
- **Payment Tracking** — Security deposit collection, partial/full payment status, and refund management
- **Customer Management** — Customer profiles with rental history, incident tracking, and driver age verification
- **Admin Dashboard** — Statistics, bulk actions, filtering, and calendar view across all entities
- **Email Notifications** — Booking confirmations, reminders, and status updates
- **PDF Generation** — Rental contracts and invoices
- **Damage Reports** — Log and track vehicle damage incidents
- **REST API** — Full REST API under `/wp-json/rentafleet/v1/` for integrations
- **Widgets & Shortcodes** — Embed search and vehicle listing anywhere on your site
- **Multi-Currency** — 20+ currencies supported

## Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL/MariaDB

## Installation

1. Copy the `rentafleet` folder into your WordPress `/wp-content/plugins/` directory.
2. In the WordPress admin, go to **Plugins** and activate **RentAFleet**.
3. On activation, the plugin automatically:
   - Creates 20+ custom database tables (`wp_raf_*`)
   - Creates the required pages (Booking, Confirmation, My Bookings)
   - Sets default configuration options

## Initial Setup

After activation, complete the setup in the **RentAFleet** admin menu:

1. **Locations** — Add at least one pickup/dropoff location.
2. **Vehicle Categories** — Create categories for your fleet.
3. **Vehicles** — Add vehicles with details, images, and assign them to locations.
4. **Pricing** — Set base rates (daily, weekly, monthly) per vehicle or category.
5. **Settings** — Configure currency, timezone, minimum advance booking hours, booking number prefix, and security deposit amount.

## Shortcodes

Place these shortcodes on any WordPress page:

| Shortcode | Description |
|-----------|-------------|
| `[raf_search]` | Vehicle search / booking form |
| `[raf_vehicles]` | Vehicle listing |
| `[raf_booking]` | Full booking flow |
| `[raf_confirmation]` | Booking confirmation page |
| `[raf_my_bookings]` | Customer booking history |

Aliases (`[rentafleet_search]`, `[rentafleet_vehicles]`, etc.) are also supported.

## REST API

All endpoints are available at `/wp-json/rentafleet/v1/`.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/vehicles` | List vehicles (with filters) |
| `GET` | `/vehicles/{id}` | Vehicle details |
| `GET` | `/locations` | List locations |
| `GET` | `/availability` | Check vehicle availability |
| `GET` | `/search` | Search available vehicles |
| `POST` | `/price` | Calculate price for a booking |
| `POST` | `/bookings` | Create a booking |
| `GET` | `/bookings/{id}` | Get booking details |

## Directory Structure

```
rentafleet/
├── rentafleet.php              # Plugin entry point
├── includes/
│   ├── Models/                 # Data models (Booking, Vehicle, Customer, etc.)
│   ├── Admin/                  # Admin pages (Dashboard, Vehicles, Bookings, Pricing, …)
│   ├── Booking/                # Booking engine and availability checker
│   ├── Pricing/                # Dynamic pricing engine
│   ├── Payment/                # Payment manager
│   ├── Email/                  # Email notifications
│   ├── PDF/                    # PDF contract/invoice generation
│   ├── Calendar/               # Availability calendar
│   ├── Coupons/                # Coupon/discount management
│   ├── Damage/                 # Damage report management
│   ├── Statistics/             # Analytics and reporting
│   ├── Widgets/                # WordPress widgets
│   ├── API/                    # REST API endpoints
│   ├── class-raf-shortcodes.php
│   ├── class-raf-helpers.php
│   ├── class-raf-activator.php
│   └── class-raf-deactivator.php
└── assets/
    ├── admin/                  # Admin CSS and JS
    └── public/                 # Frontend CSS and JS
```

## License

GPL v2 or later. See [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
