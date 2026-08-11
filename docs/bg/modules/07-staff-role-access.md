
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

- администраторите и editor потребителите запазват пълното Event Tickets меню
- Staff потребителите се насочват към Tickets list от top-level Event Tickets менюто
- Staff потребителите не получават пълния набор от admin-only submenu страници

## Оперативни очаквания

- Staff потребителите могат да бъдат ограничени само до check-in
- Staff потребителите могат да получат и достъп до ticket администрация
- промяната на правата в Staff екрана синхронизира ролята веднага след save
