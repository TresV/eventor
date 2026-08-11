# Module: Core Architecture

## Purpose

Describes how the plugin boots, registers services, and keeps admin, frontend, Elementor, and ticketing modules connected.

## Main bootstrap files

- `event-tickets-elementor.php`
- `includes/class-plugin.php`
- `includes/requirements.php`

## High-level structure

- CPT and taxonomy registration
- ticketing, calendar, email, PDF, QR, and cancellation services
- admin pages and settings schema
- Elementor widgets and dynamic tags
- frontend AJAX and public endpoints

## Current notes

- documentation and user guide are separate admin workspaces
- event editor uses a sectioned admin shell
- staff access and check-in routing are plugin-managed

