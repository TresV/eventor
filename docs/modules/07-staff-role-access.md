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
- WooCommerce capabilities
- Advanced Admin capabilities

The source of truth is stored in the main plugin settings option, but the editing UI lives on the Staff admin page rather than in the general Settings page.

## Check-in target resolution

The Staff system resolves the check-in destination in this order:

1. custom Staff check-in URL saved in Staff Access
2. starter page mapped under `evt_tickets_starter_pages['staff_checkin']`
3. fallback URL `/staff-checkin/`

This resolved URL is used for admin-bar redirects such as `Visit Site` for Staff users.

## Menu behavior

- administrators keep the full Event Tickets admin menu
- Staff users are routed to the Tickets list from the top-level Event Tickets menu
- Staff users do not receive the full admin-only Event Tickets submenu set

## Operational expectations

- Staff users can be limited to check-in only
- Staff users can also be granted ticket admin access
- capability changes on the Staff screen resync the role immediately after save

---

# Модул: Staff роля и достъп

## Цел

Този модул описва вградения слой за Staff достъп, използван за оперативна работа в деня на събитието, администриране на билети и frontend check-in пренасочване.

## Основни компоненти

- role manager: `includes/class-staff-access-manager.php`
- admin екран: `includes/admin/class-staff-page.php`
- admin menu routing: `includes/admin/class-admin-menu.php`
- check-in access enforcement: `includes/class-checkin-ajax.php`
- check-in widget gate: `includes/elementor-widgets/class-widget-checkin.php`

## Жизнен цикъл на ролята

- slug на ролята: `evt_staff`
- човекочетен етикет: `Staff`
- runtime български етикет: `Персонал`
- ролята се създава при активация и се възстановява при bootstrap, ако липсва
- базово право: `read`

## Управлявани права

Ролята Staff не е заключена до едно право. Плъгинът управлява каталог от capabilities, групирани в Staff admin екрана:

- права за Event Tickets
- права за Табло / Медия
- права за Публикации / Страници
- права за Потребители
- права за WooCommerce
- разширени администраторски права

Източникът на truth е записан в основния option на плъгина, но редакцията става от Staff екрана, а не от общата Settings страница.

## Определяне на check-in целта

Staff системата определя check-in дестинацията в този ред:

1. custom Staff check-in URL, запазен в Staff Access
2. starter page, записана в `evt_tickets_starter_pages['staff_checkin']`
3. fallback URL `/staff-checkin/`

Този resolved URL се използва и за admin-bar пренасочвания като `Visit Site` при Staff потребителите.

## Поведение на менюто

- администраторите запазват пълното Event Tickets меню
- Staff потребителите се насочват към Tickets list от top-level Event Tickets менюто
- Staff потребителите не получават пълния набор от admin-only submenu страници

## Оперативни очаквания

- Staff потребителите могат да бъдат ограничени само до check-in
- Staff потребителите могат да получат и достъп до ticket администрация
- промяната на правата в Staff екрана синхронизира ролята веднага след save
