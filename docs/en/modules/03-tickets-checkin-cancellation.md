# Module: Tickets, Check-in, and Cancellation

## Purpose

Documents ticket issuance, ticket status handling, event-day validation, and attendee cancellation flows.

## Main objects

- Ticket CPT: `evt_ticket`
- issuance service: `includes/class-ticket-service.php`
- cancellation service: `includes/tickets/class-ticket-cancellation-service.php`
- check-in endpoint: `includes/class-checkin-ajax.php`

## Issuance paths

- Ticket Box AJAX
- Elementor Pro Forms integration
- Direct payment processor flow (Stripe / ePay.bg)

## Current check-in notes

- the built-in Staff role can be granted check-in capability
- Staff users can also be granted ticket admin access
- `Visit Site` for Staff can resolve to a configurable check-in page URL

## Current cancellation notes

- signed cancel links are supported
- cancellation and refund-request tracking can be separated
- cancelled tickets are blocked from check-in

