# DoraBooking

Custom WordPress booking plugin for [dorabudapest.com](https://dorabudapest.com) — Budapest city tours and transfers.

## Features

- Multi-step booking form (service → date → details → payment → confirmation)
- Custom service management (no third-party dependency)
- Availability engine with conflict detection
- OTA sync compatibility (`ota-calendar-sync` plugin)
- WooCommerce / Stripe payment bridge
- Bilingual email notifications (HU/EN)
- Admin dashboard: bookings, services, pricing, settings

## Requirements

- WordPress 6.0+
- PHP 7.4+
- WooCommerce (for online payment)

## Installation

1. Upload the `dora-booking/` folder to `wp-content/plugins/`
2. Activate in **Plugins → Installed Plugins**
3. Add services under **DoraBooking → Services**
4. Place the shortcode on any page:

```
[dora_booking]
```

## Database Tables

| Table | Purpose |
|---|---|
| `wp_dora_services` | Service definitions (name, duration, available times/days) |
| `wp_dora_bookings` | All bookings (pending, confirmed, cancelled) |

## Admin Pages

- **Bookings** — list, filter, export CSV
- **Services** — CRUD for tour/transfer services
- **Pricing** — per-person pricing per service
- **Settings** — email sender, currency, WooCommerce product mapping

## OTA Sync Compatibility

If the [ota-calendar-sync](https://github.com/neurophunk/ota-calendar-sync) plugin is installed and its `wp_ota_sync_feeds` table has a `dora_service_id` column, the availability engine automatically checks OTA blocks to prevent double bookings from GetYourGuide, Viator, and GoWithGuide.

## Shortcode

```
[dora_booking]
```

Optional attributes:

| Attribute | Default | Description |
|---|---|---|
| `lang` | `hu` | Interface language (`hu` or `en`) |

## Development

```bash
composer install
./vendor/bin/phpunit
```
