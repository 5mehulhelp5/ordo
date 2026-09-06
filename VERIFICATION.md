# Verification checklist

Last run against a real Magento Open Source 2.4.7 instance (Docker: PHP 8.2-FPM, MySQL 8.0,
OpenSearch 2.12), 2026-08-25. `composer.json` now requires PHP >=8.4 <8.6 and targets Magento
2.4.8/2.4.9 (`magento/framework ^103.0.8`) — this checklist has not been re-run against that
combination yet; treat it as historical evidence for the 2.4.7/PHP 8.2 pass, not a current
verification. Re-running it against 2.4.8 or 2.4.9 is tracked in ROADMAP.md.

## 0. Prerequisites

- [x] PHP 8.2, Composer 2.x, MySQL 8.0, OpenSearch 2.12
- [x] Magento Open Source 2.4.7 installed

## 1. Install

```bash
composer config repositories.ordo-automation path /absolute/path/to/mma
composer require ordo/module-automation:@dev
bin/magento module:enable Ordo_Automation
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

- [x] `setup:upgrade` completes, all `ordo_*` tables created
- [x] `bin/magento module:status Ordo_Automation` shows enabled
- [x] Admin panel loads, no white screen / 500

Note: requires a Composer path repository with `"options": {"symlink": false}` — resync
(`rm -rf vendor/ordo/module-automation && composer update ordo/module-automation`) after
every local edit.

## 2. Static checks

```bash
composer require --dev phpstan/phpstan bitexpert/phpstan-magento phpunit/phpunit
vendor/bin/phpstan analyse -c vendor/ordo/module-automation/phpstan.neon
vendor/bin/phpunit vendor/ordo/module-automation/Test/Unit
```

- [x] PHPStan completes clean
- [x] Unit test suite passes

## 3. Admin UI

- [x] "Ordo Automation" menu item present
- [x] Campaigns grid loads
- [x] New Campaign form loads, saves, appears in grid
- [x] Conditions/actions dynamicRows render and are editable
- [x] Reorder Cycles grid loads
- [x] Stores → Configuration → Ordo Automation — all sections render

## 4. B2B triggers

- [x] Offer expiry reminder — matches and attempts delivery
- [x] Credit limit alert — computes utilization, attempts delivery
- [x] Order approval — hold/approve/reject chain, correct `order_id` on the approval row
- [x] Reorder reminders — cycle detection, reminder delivery
- [x] Abandoned cart — detection, reminder delivery, `cart_abandoned` campaign dispatch
- [x] Real order placed through full storefront checkout — held, approved via the real
  email link, released

## 5. Campaign engine

- [x] `order_placed` → `order_total_gte` → `add_tag`, admin-built campaign, real dispatch
- [x] `generate_coupon` → `send_email` chaining (context carried across actions)
- [x] `tag_added` trigger firing a second campaign
- [x] Full chain via a real checkout order (native event, not a direct dispatcher call)

## 6. Promotion Builder

- [x] `CheapestItemFree` — 3-item cart, only the cheapest item discounted, one unit only
- Native admin "Apply" dropdown has no friendly label for this `simple_action` (set via
  API/DB)

## 7. On-site tracking

- [x] `POST /ordo/track/event` — writes `ordo_visitor_event`, anonymous `customer_id` is
  `NULL`
- [x] Identity stitching on login — backfills `customer_id`, re-runs aggregation
- [x] Retention pruning — respects `retention_days = 0`
- [x] `tracker.js` in a real browser — cookie issued, automatic `page_view`, manual
  `window.ordoTrack()` calls recorded

## Result

Sections 1–7 pass against a real, live instance. Current state and open items: see
[ROADMAP.md](ROADMAP.md). Fix history: see [docs/CHANGELOG.md](docs/CHANGELOG.md).
