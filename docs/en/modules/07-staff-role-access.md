# Module: Staff Role and Access

## Purpose

This module documents the built-in Staff access layer used for event-day operations, ticket administration access, and frontend check-in routing.

## Main components

- role manager: `includes/class-staff-access-manager.php`
- admin screen: `includes/admin/class-staff-page.php`
- admin menu routing: `includes/admin/class-admin-menu.php`
- check-in access enforcement: `includes/class-checkin-ajax.php`
- check-in widget gate: `includes/elementor-widgets/class-widget-checkin.php`

## Role lifecycle

- Role slug: `evt_staff`
- Human label: `Staff`
- Bulgarian runtime label: `Персонал`
- The role is created on activation and repaired on runtime bootstrap if it is missing.
- Baseline capability: `read`

## Managed capabilities

The Staff role is not hard-coded to one permission. The plugin manages a catalog of capabilities grouped for the Staff admin screen:

- Event Tickets capabilities
- Dashboard / Media capabilities
- Posts / Pages capabilities
- Users capabilities
- Advanced Admin capabilities

The source of truth is stored in the main plugin settings option, but the editing UI lives on the Staff admin page rather than in the general Settings page.

## Check-in target resolution

The Staff system resolves the check-in destination in this order:

1. custom Staff check-in URL saved in Staff Access
2. starter page mapped under `evt_tickets_starter_pages['staff_checkin']`
3. fallback URL `/staff-checkin/`

This resolved URL is used for admin-bar redirects such as `Visit Site` for Staff users.

## Menu behavior

- administrators and editors keep the full Event Tickets admin menu
- Staff users are routed to the Tickets list from the top-level Event Tickets menu
- Staff users do not receive the full admin-only Event Tickets submenu set

## Operational expectations

- Staff users can be limited to check-in only
- Staff users can also be granted ticket admin access
- capability changes on the Staff screen resync the role immediately after save
