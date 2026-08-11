# Module: Elementor Widgets and Dynamic Tags

## Purpose

Describes the Elementor integration layer used to render events, accept ticket requests, and expose event data inside templates.

## Current scope

- Events Browser
- Ticket Box
- Ticket view / resend / cancel / PDF widgets
- Check-in widget
- event dynamic tags, including Event Status

## Current implementation notes

- Event Status now uses the shared status resolver
- dynamic-tag output can be filtered by selected statuses
- status badges and labels stay aligned with the shared event status system
- Elementor Pro Theme Builder now gets a post-style `Singular > Event` branch with `Events`, `In Category`, `In Child Categories`, `In Tag`, and `Events by author`
