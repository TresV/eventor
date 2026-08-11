
# Техническа документация за Event Tickets for Elementor

Тази документация е техническият reference пакет на плъгина. Предназначена е за разработчици, интегратори, хора по поддръжката и напреднали потребители, които трябва да разбират структурата на плъгина и връзките между отделните му части.

## Какво покрива техническата документация

- bootstrap и регистриране на услугите
- модела на данните за събития и билети
- Elementor widgets и dynamic tags
- email, PDF, ICS и QR delivery стека
- admin страници, настройки, експорти и repair инструменти
- вградената роля `evt_staff`, синхронизацията на правата и check-in пренасочването

## Карта на техническата документация

- `modules/01-core-architecture.md`
- `modules/02-events-module.md`
- `modules/03-tickets-checkin-cancellation.md`
- `modules/04-elementor-widgets-dynamic-tags.md`
- `modules/05-email-pdf-calendar-qr.md`
- `modules/06-admin-settings-ops.md`
- `modules/07-staff-role-access.md`
- `wporg-readme.txt`

## Бележки за текущата реализация

- Плъгинът вече има отделни technical documentation и user-guide екрани в wp-admin.
- Event editor-ът използва новия card-based admin shell със секции.
- Event status badge-овете се резолват централно и се използват повторно от Events Browser и Elementor dynamic tags.
- Вградената роля `evt_staff` се управлява от плъгина и включва конфигурируеми capability групи плюс check-in redirect логика.
