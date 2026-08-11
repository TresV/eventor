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

---

# Модул: Основна архитектура

## Цел

Описва как плъгинът се зарежда, регистрира услугите си и свързва admin, frontend, Elementor и ticketing модулите.

## Основни bootstrap файлове

- `event-tickets-elementor.php`
- `includes/class-plugin.php`
- `includes/requirements.php`

## Структура на високо ниво

- регистрация на CPT и таксономии
- ticketing, calendar, email, PDF, QR и cancellation услуги
- admin страници и schema за настройки
- Elementor widgets и dynamic tags
- frontend AJAX и публични endpoints

## Текущи бележки

- документацията и user guide-ът са отделни admin екрани
- event editor-ът използва секционен admin shell
- staff access и check-in routing се управляват от плъгина
