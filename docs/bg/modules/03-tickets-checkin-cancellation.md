
# Модул: Билети, check-in и отказване

## Цел

Документира издаването на билети, обработката на ticket status-ите, валидирането в деня на събитието и cancellation потоците за посетителите.

## Основни обекти

- Ticket CPT: `evt_ticket`
- issuance услуга: `includes/class-ticket-service.php`
- cancellation услуга: `includes/tickets/class-ticket-cancellation-service.php`
- check-in endpoint: `includes/class-checkin-ajax.php`

## Пътища за издаване

- Ticket Box AJAX
- Elementor Pro Forms integration
- WooCommerce поток за платени билети

## Текущи бележки за check-in

- вградената Staff роля може да получи право за check-in
- Staff потребителите могат да получат и ticket admin достъп
- `Visit Site` за Staff може да сочи към конфигурируем check-in URL

## Текущи бележки за отказване

- поддържат се signed cancel линкове
- cancellation и refund-request tracking могат да бъдат разделени
- cancelled билетите не могат да бъдат чекирани
