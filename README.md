# Event Tickets for Elementor

Event ticketing for [Elementor](https://elementor.com/) with QR/PDF/ICS delivery, attendee flows, and WooCommerce-powered paid checkout.

## Features

- **Event management** – dedicated Events CPT with dates, location (incl. map + coordinates), capacity, categories/tags, venues, organizers, virtual/livestream events, recurring series, and status badges.
- **Ticket issuance & delivery** – attendee tickets with unique codes, QR codes, and rich HTML emails (with PDF + ICS + Google Calendar attachments/links).
- **Self-service attendee flows** – ticket lookup, PDF download, cancel ticket (with email-confirmed cancellation), and staff check-in via QR scanning.
- **Elementor integration** – a full set of widgets (Events Browser with list/grid/month/map views and filters, Events List, Summary, Calendar, Map, Ticket Box, Ticket View, Cancel, Resend, Check-in) plus dynamic tags and theme display conditions.
- **WooCommerce checkout** – sell tickets through WooCommerce orders.
- **Staff role** – built-in plugin-managed `evt_staff` role with granular capabilities and check-in routing.

## Requirements

- WordPress 5.8+ (6.x recommended)
- PHP 7.4+ (8.x recommended)
- [Elementor](https://elementor.com/) (free; some features require Elementor Pro)
- Optional: [WooCommerce](https://woocommerce.com/) for paid checkout

## Installation

1. Upload the plugin folder to `/wp-content/plugins/` (or install the ZIP via **Plugins → Add New → Upload Plugin**).
2. Activate **Event Tickets for Elementor**.
3. Follow the setup wizard to configure email, PDF, and page settings.

## Documentation

- `docs/README.md` – technical architecture & developer documentation (`docs/modules/*`)
- `docs/wporg-readme.txt` – WordPress.org readme
- In-admin **User Guide** and **Documentation** pages

## Development

```bash
# Syntax-check all PHP (excluding vendored libraries)
find . -name '*.php' -not -path '*/vendor/*' -exec php -l {} \;
```

Coding standards are enforced with `phpcs.xml.dist` (WordPress coding standards, `evt` prefix).

## Credits

- [dompdf](https://github.com/dompdf/dompdf) (PDF), [phpqrcode](https://github.com/t0k4rt/phpqrcode) (QR), [html5-qrcode](https://github.com/mebjas/html5-qrcode), [Leaflet](https://leafletjs.com/), [flatpickr](https://flatpickr.js.org/) – vendored under `includes/vendor/` and `assets/vendor/`.

## License

Proprietary. Distributed under the terms of the plugin author.
