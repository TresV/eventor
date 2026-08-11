
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
