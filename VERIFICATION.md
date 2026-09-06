# Verification checklist

Manual walkthrough of this module on a real Magento instance. CI (`.github/workflows/mftf.yml`) runs the
equivalent as MFTF against PHP 8.4 / Magento 2.4.9 on every push — this checklist is for a human doing the
same thing by hand.

## 0. Prerequisites

- [ ] PHP >=8.4 <8.6, Composer 2.x, MySQL 8.0, OpenSearch 2.12
- [ ] Magento Open Source 2.4.8 or 2.4.9 installed

## 1. Install

```bash
composer config repositories.ordo-automation path /absolute/path/to/mma
composer require ordo/module-automation:@dev
bin/magento module:enable Ordo_Automation
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

- [ ] `setup:upgrade` completes, all `ordo_*` tables created
- [ ] `bin/magento module:status Ordo_Automation` shows enabled
- [ ] Admin panel loads, no white screen / 500

Note: requires a Composer path repository with `"options": {"symlink": false}` — resync
(`rm -rf vendor/ordo/module-automation && composer update ordo/module-automation`) after
every local edit.

## 2. Static checks

```bash
composer require --dev phpstan/phpstan bitexpert/phpstan-magento phpunit/phpunit
vendor/bin/phpstan analyse -c vendor/ordo/module-automation/phpstan.neon
vendor/bin/phpunit vendor/ordo/module-automation/Test/Unit
```

- [ ] PHPStan completes clean
- [ ] Unit test suite passes

## 3. Admin UI

- [ ] "Ordo Automation" menu item present
- [ ] Campaigns grid loads
- [ ] New Campaign form loads, saves, appears in grid
- [ ] Conditions/actions dynamicRows render and are editable
- [ ] Reorder Cycles grid loads
- [ ] Stores → Configuration → Ordo Automation — all sections render

## 4. B2B triggers

- [ ] Offer expiry reminder — matches and attempts delivery
- [ ] Credit limit alert — computes utilization, attempts delivery
- [ ] Order approval — hold/approve/reject chain, correct `order_id` on the approval row
- [ ] Reorder reminders — cycle detection, reminder delivery
- [ ] Abandoned cart — detection, reminder delivery, `cart_abandoned` campaign dispatch
- [ ] Real order placed through full storefront checkout — held, approved via the real
  email link, released

## 5. Campaign engine

- [ ] `order_placed` → `order_total_gte` → `add_tag`, admin-built campaign, real dispatch
- [ ] `generate_coupon` → `send_email` chaining (context carried across actions)
- [ ] `tag_added` trigger firing a second campaign
- [ ] Full chain via a real checkout order (native event, not a direct dispatcher call)

## 6. Promotion Builder

- [ ] `CheapestItemFree` — 3-item cart, only the cheapest item discounted, one unit only
- Native admin "Apply" dropdown has no friendly label for this `simple_action` (set via
  API/DB)

## 7. On-site tracking

- [ ] `POST /ordo/track/event` — writes `ordo_visitor_event`, anonymous `customer_id` is
  `NULL`
- [ ] Identity stitching on login — backfills `customer_id`, re-runs aggregation
- [ ] Retention pruning — respects `retention_days = 0`
- [ ] `tracker.js` in a real browser — cookie issued, automatic `page_view`, manual
  `window.ordoTrack()` calls recorded

## Result

Current state and open items: see [ROADMAP.md](ROADMAP.md). Fix history: see
[docs/CHANGELOG.md](docs/CHANGELOG.md).
