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

---

# Модул: Събития

## Цел

Покрива съхранението на събитията, мета данните, таксономиите, статус значките, часовите слотове, discovery полетата и feed/query форматирането.

## Основни части

- CPT и таксономии за събития
- секции в event editor-а: basics, schedule, location, attendance, delivery, status, ticket output
- услуги за timeslot-и и капацитет
- event status resolver и badge overrides
- AJAX/query форматиране за Events Browser

## Акценти от текущата реализация

- event таксономиите са изложени към Gutenberg
- приоритетът на status-ите е `cancelled > postponed > over`
- event DTO-тата включват нормализиран статус за frontend потребителите
