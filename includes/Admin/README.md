# ATA Admin UI

React SPA loaded inside WordPress admin pages.

## Files
- `js/admin-app.js` — React app (WP core deps: wp-element, wp-components, wp-i18n)
- `css/admin-style.css` — layout styles
- `MenuProvider.php` — registers 14 submenu pages + enqueues assets
- `RestApi.php` — 24 REST endpoints (ata/v1)

## Run
No build step. JS loads directly via `wp_enqueue_script`.