# Event Tickets for Elementor Technical Documentation

This documentation is the technical reference for the plugin package. It is written for developers, implementers, maintainers, and advanced site builders who need to understand how the plugin is structured and how its moving parts interact.

## What the technical docs cover

- plugin bootstrap and service registration
- event and ticket data model
- Elementor widgets and dynamic tags
- email, PDF, ICS, and QR delivery stack
- admin pages, settings, exports, and repair tooling
- built-in Staff role, access sync, and check-in routing

## Technical documentation map

- `modules/01-core-architecture.md`
- `modules/02-events-module.md`
- `modules/03-tickets-checkin-cancellation.md`
- `modules/04-elementor-widgets-dynamic-tags.md`
- `modules/05-email-pdf-calendar-qr.md`
- `modules/06-admin-settings-ops.md`
- `modules/07-staff-role-access.md`
- `wporg-readme.txt`

## Current implementation notes

- The plugin now has separate technical documentation and user-guide workspaces in wp-admin.
- The event editor uses the newer card-based admin shell with section grouping.
- Event status badges are resolved centrally and reused by the Events Browser and Elementor dynamic tags.
- The built-in `evt_staff` role is plugin-managed and includes configurable capability groups plus check-in redirect support.

