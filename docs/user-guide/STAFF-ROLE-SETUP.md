# Staff Role Setup

Use the built-in Staff role when you want event-day operators to work with tickets and check-in without giving them full administrator access.

## Setup steps

1. Open `Event Tickets -> Staff`.
2. In `Staff Access`, choose which capability groups the Staff role should receive.
3. Set the Staff check-in page URL if your real page is not the default starter-page path.
4. Save the access rules.
5. In `Staff Users`, assign existing WordPress users to the Staff role.
6. Log in as a Staff test user and confirm:
   - `Event Tickets` opens the Tickets list
   - `Visit Site` opens the intended check-in page
   - the user can only do the work you intended

## Practical recommendation

Start with the Event Tickets capability group only, then grant extra WordPress or WooCommerce capabilities only if you have a real operational reason.
