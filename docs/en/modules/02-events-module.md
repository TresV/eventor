# Module: Events

## Purpose

Covers event storage, metadata, taxonomies, status badges, timeslots, discovery fields, and feed/query formatting.

## Main pieces

- event CPT and taxonomies
- event editor sections: basics, schedule, location, attendance, delivery, status, ticket output
- timeslot and capacity services
- event status resolver and badge overrides
- AJAX/query formatting for the Events Browser

## Current implementation highlights

- event taxonomies are exposed to Gutenberg
- event status precedence is `cancelled > postponed > over`
- event DTOs include normalized status output for frontend consumers

