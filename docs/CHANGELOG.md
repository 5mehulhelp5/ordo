# Changelog

All notable changes to this module are documented here. Format loosely
follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Campaign calendar view (`admin/ordo/campaign/calendar`) — every campaign's trigger(s) and action-chain
  timing (cumulative offset, not raw per-step `delay_minutes`) in one place.
- Dedicated admin fields for the 6 RFM-based campaign conditions (`days`/`count`/`percentile`), replacing the
  raw "Params (JSON)" fallback.
- MFTF coverage for the reminder/alert crons (`lifecycle` group): `TagInactiveCustomers`/`SendWinBackEmails`,
  `SendCreditLimitAlerts`, `SendSalesRepDigest`.
- `Test/Integration/CampaignDispatchLoadTest.php` — load/soak test for campaign dispatch (200 campaigns/trigger,
  600-row scheduled-action backlog).
- Message Log admin grid (`Controller/Adminhtml/MessageLog/Index.php`) over `ordo_message_log`.
- E.164 validation for `ordo_sms_phone` before a `send_sms` action spends a Twilio API call.
- On-site product recommendation content block (`recommendations` type) — reuses the same
  `ProductRecommender`/`ProductRecommendationRenderer` pair `add_product_recommendations` already uses for
  email, embeddable anywhere via a new `Block\Frontend\ContentBlock\Render` (resolves a content block by its
  `identifier`), registered as a real Magento widget (`etc/widget.xml`, id `ordo_content_block`) so it's usable
  from any CMS block/page via the `{{widget}}` directive (including the CMS WYSIWYG's own "Insert Widget"
  dialog, currently unverified end-to-end - see `Render.php`'s own docblock) or, the real proven mechanism,
  a plain layout XML file targeting a CMS page's own `cms_page_view_id_{identifier}` handle (`Magento\Cms\
  Helper\Page`'s own convention). `ProducerInterface::render()` gained an optional `$context` parameter so a
  producer can personalize by `customer_id`. MFTF coverage (`AdminContentBlockRecommendationsOnSiteTest`)
  verified by hand against a real local Magento install first (real admin UI, real guest order, real
  anonymous storefront visit) before being written, after `{{widget}}` and the CMS page's own "Layout Update
  XML" field (the latter turned out to be a dead end - `Magento\Cms\Model\Page::beforeSave()` unconditionally
  wipes that field to null unless a custom layout file is already registered and selected) both failed.
- Product feed export to shopping channels — a real, standalone Google Merchant Center-compatible
  XML feed (`Model/ProductFeed/GoogleMerchantFeedGenerator.php`), distinct from the existing
  `product_feed` content block (a small curated HTML grid inside campaigns/on-site, not an
  exportable file). `Cron/RefreshProductFeed.php` regenerates a cached copy every 6 hours
  (`ordo_product_feed_cache`, same "generate on a schedule, serve the cache" split as the RSS
  content-block cache); `Controller/ProductFeed/Index.php` (public, unauthenticated) serves it at
  `/ordo/productfeed/index`; an admin "Refresh Now" link on the dashboard
  (`Controller/Adminhtml/ProductFeed/RefreshNow.php`) regenerates it synchronously. Config under
  "Shopping Feed" (enabled/title/description).
- Ad-audience sync (Google Ads / Meta) — new `ordo_ad_audience` admin CRUD entity (segment +
  platform + external audience id), `Cron\SyncAdAudiences` resolves a segment's real current
  members (`Model\Segment\SegmentMemberResolver`, the same resolver `in_segment`/segment bulk
  actions already use), hashes their emails to the shared SHA-256 spec both platforms require
  (`Model\AdAudience\PiiHasher`), and hands the list to the configured platform's
  `SyncClientInterface`: `GoogleAdsSyncClient` (OAuth refresh-token exchange +
  create/addOperations/run against the OfflineUserDataJobService REST endpoints) or
  `MetaSyncClient` (Custom Audience create + `usersreplace`). Plain HTTP via
  `Magento\Framework\HTTP\Client\Curl`, no new SDK dependency — same choice `RssFetcher` already
  made for its own external call.
- GDPR/consent manager — new `ordo_customer_consent` table (per-customer, per-channel: email/
  sms/push), `Model/ConsentManager.php` is the single source of truth `send_email`/`send_sms`
  both check before sending anything (opt-out register, not opt-in — a customer with no row is
  treated as consented, so this never retrofits existing customers into a breaking prior-opt-in
  requirement). New admin page (`admin/ordo/gdpr/index`, linked from the dashboard) to search a
  customer by email, toggle their per-channel consent, download a full data-subject export
  (`Controller/Adminhtml/Gdpr/Export.php`), and erase every row this module holds about them
  (`Controller/Adminhtml/Gdpr/Erase.php`).
- Single-question satisfaction/NPS survey action (`nps_survey`) — same queue-and-poll delivery
  shape as `popup`/`notify`: new `ordo_survey_prompt` table holds both the queued 0-10 question
  and its eventual response in one row, `Controller/Track/Survey.php` claims and hands it out
  (claim-before-use, same as `popup`), a real click posts to `Controller/Track/
  SubmitSurveyResponse.php` which records the answer once and never overwrites it. New
  `nps_score_at_least` segment/campaign condition (`Model/Campaign/Condition/
  NpsScoreAtLeast.php`) reads a customer's most recent answered score, reusing the same
  dedicated "threshold" field `score_at_least` already has — no new admin field needed for the
  condition side. `Cron/PruneSurveyPrompts.php` cleans up responded/expired/stale-delivered rows.
- Persistent in-site notification action (`notify`) — non-modal, sibling to the existing `popup` action, but
  never claimed-and-gone: new `ordo_notification` table, `Controller/Track/Notification.php` returns every
  unread row on every poll (unlike `Popup.php`'s one-shot claim), `Controller/Track/DismissNotification.php` is
  the only thing that marks one read, and `Cron/PruneNotifications.php` cleans up read/expired rows. Reuses
  `popup`'s own admin fields (headline/body/cta_label/cta_url) and `tracker.js`'s existing poll-loop pattern.
- Loyalty tiers on top of lead scoring — `Model/LoyaltyTierCalculator.php` maps the existing `ordo_customer_score`
  running total (the same score `score_at_least` already reads) into Bronze/Silver/Gold, configurable via two new
  `lead_scoring` config thresholds. New `loyalty_tier_at_least` campaign/segment condition (`{tier}`, no dedicated
  admin field yet — via the Params JSON fallback) and a new dashboard stat showing the customer count per tier.
- SendGrid-backed `send_email` delivery tracking — closes the gap this file's own ROADMAP.md previously
  flagged. Writes to the same channel-generic `ordo_message_log` `send_sms` already writes to: a per-send
  `Message-ID` header (`Model/Email/MessageIdGenerator.php`) is set via `Plugin/Email/
  EmailMessageMessageIdPlugin.php` on `Magento\Framework\Mail\EmailMessageInterfaceFactory::create()` — the
  only reachable interception point, since `TransportBuilder` itself exposes no public seam to reach the
  message it builds (no `getMessage()`, `prepareMessage()` is protected) — then `Controller/Email/
  StatusCallback.php` (public, unauthenticated, ECDSA-signature-verified via `Model/Email/
  SendGridSignatureValidator.php`, same trust model as `send_sms`'s own Twilio callback) correlates a later
  SendGrid Event Webhook delivery/bounce/dropped event back to that row. Scoped to the `send_email` campaign
  action only, not every `TransportBuilder` call site in this Magento install (see that class's own docblock).
  New "Email Delivery Tracking (SendGrid)" config section holds the webhook's verification key.

### Fixed

- Stored XSS in the campaign flow editor — `Block/Adminhtml/Campaign/Edit/Flow.php`'s
  `getFieldsConfigJson()`/`getFlowDataJson()` are embedded raw (`@noEscape`) directly inside a
  `<script>` block, not an HTML attribute `escapeHtmlAttr()` would cover. A literal `</script>`
  in any admin-authored text reaching that JSON (action/condition params, content block names)
  would close the script tag early. Fixed with `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|
  JSON_HEX_QUOT` on both `json_encode()` calls. Found via a dedicated security-audit subagent pass.
- `Cron\SendCreditLimitAlerts` re-loaded every customer via EAV a second time inside its own loop
  (`CreditLimitCalculator::getCreditLimit()`/`getUsedCredit()`), on top of the batch load
  `CustomerMapBuilder` already did — real impact at a few thousand credit-limit customers. New
  `CreditLimitCalculator::getCreditLimitFromCustomer()` (works off an already-loaded customer)
  and `getUsedCreditForCustomers()` (one `GROUP BY` query for every customer instead of one query
  per customer) close this. `Cron\SyncAdAudiences` had the same shape for resolving segment
  members' emails — switched to the existing `CustomerMapBuilder`. Found via a dedicated
  performance-audit subagent pass.
- `SendSalesRepDigest`'s email always rendered an empty customer list — Magento's `{{for}}` directive silently
  skips non-array loop items; `customer_names` was a plain `string[]`. Fixed by using `array{name: string}[]`.
- `setup:install`/`setup:upgrade` crashed on this module's data patches — `AbstractCustomerAttributePatch`
  lived alongside its concrete subclasses in `Setup/Patch/Data/`, and Magento's `PatchReader` globs every file
  there as a patch class with no abstract check. Moved to `Setup/Patch/AbstractCustomerAttributePatch.php`.
- Campaigns grid still showed the deprecated single `trigger_event` column (empty since the multi-trigger
  migration) — now joins `ordo_campaign_trigger` and shows every trigger, comma-separated.

### Changed

- Deduplicated the `*PercentileAtLeast` campaign conditions and the customer-attribute Setup patches into shared
  base classes (`AbstractPercentileAtLeast`, `AbstractCustomerAttributePatch`) — no behavior change.
- Extracted `Model/Cron/CronRunLogger.php` for the shared per-item-failure/run-summary log shape, adopted across
  all 15 crons that had it duplicated inline.
- Extracted `Model/Campaign/Action/ContextTargetResolver.php` (+ `ContextTarget` value object) for the
  byte-for-byte-identical customer_id-or-visitor_id resolution block duplicated across `ShowPopup`, `Notify`,
  and `NpsSurvey` — no behavior change. Found via a design-review subagent pass.

## [1.0.0]

First full pass verified end to end against a real Magento Open Source 2.4.7 instance, including a real order
placed through storefront checkout, held for approval, approved via the token link, and released.

### Added

- Multi-trigger campaigns — trigger event moved from a single `ordo_campaign.trigger_event` column to its own
  child entity, `ordo_campaign_trigger` (`CampaignTriggerInterface`); REST: `/V1/ordo/campaign-triggers`.
- Editable Drawflow scenario canvas on the campaign edit page — visual trigger(s) → conditions → actions graph,
  drag-and-drop palette, dedicated fields per condition/action type instead of a raw JSON textarea.
- Product recommendations (`add_product_recommendations` campaign action) — co-purchase affinity via SQL against
  order history, falling back to store-wide best-sellers.
- Lead scoring — demographic-attribute scoring rules (`ordo_score_rule`), applied on `customer_save_after`, with
  a `score_threshold_crossed` campaign trigger.
- Popup targeting — frequency capping and an `element_clicked` tracked event type.
- Dynamic content blocks (`ordo_content_block`: snippet/RSS/product-feed), resolved by a new
  `add_dynamic_content` campaign action.
- Segments — bulk actions on current members (add tag/points), a standalone RFM report, percentile-based RFM
  conditions, and `Cron\RecomputeRfmScores` precomputing quintiles nightly.
- Full i18n coverage — `en_US`/`pl_PL` rebuilt to the module's full string surface; 10 machine-translated locales
  added (`de_DE`, `fr_FR`, `es_ES`, `it_IT`, `pt_BR`, `zh_Hans_CN`, `ja_JP`, `ru_RU`, `uk_UA`, `nl_NL`).
- SMS campaign action (Twilio) — `send_sms`, delivery tracked via `ordo_message_log` and a signature-verified
  status webhook; opted-out recipients recorded distinctly from a generic failure.

### Fixed

- `HoldOrderForApproval` recorded `order_id = 0` on every `ordo_order_approval` row — read
  `$order->getEntityId()` before the order's own save assigned it. Fixed by reordering the two saves.

## [0.9.4]

### Fixed

- Audited and fixed all 12 instances of a `?: $default` config-getter bug in `Helper/Config.php` that treated an
  explicit `0` as unset. Extracted a single `intConfig()` helper.

## [0.9.3]

### Fixed

- `VisitorEventLogger::attributeVisitorToCustomer()` never re-ran aggregation after backfilling a visitor's
  events on login — a threshold crossed pre-login stayed untagged until the next scheduled run.
- `Config::getTrackingRetentionDays()` treated `0` ("prune everything") as unset, falling back to the 7-day
  default.

## [0.9.2]

### Fixed

- `etc/di.xml` wired `CheapestItemFree` against the wrong extension point (`Validator::calculators` doesn't
  exist in Magento 2.4.x; the real one is `CalculatorFactory::discountRules`).
- `QualifyingSetTracker` gave every item in the cart 100% off, not just the cheapest — `Quote\Address\Item::
  getItemId()` is null during real discount collection, and the null cast to `0` made every item match. Switched
  identity from item id to SKU.

## [0.9.1]

### Fixed

- Every custom customer attribute this module defines (`ordo_credit_limit`, `ordo_order_spend_limit`,
  `ordo_approval_admin_email`, the 3 `ordo_sales_rep_*` fields) silently failed to persist — the setup patches
  set `user_defined => true` without `group`, so `EavSetup::addAttribute()` never attached them to an attribute
  set, and `AbstractEntity::_collectSaveData()` silently drops values for attributes outside the entity's set.
  Fixed by adding `'group' => 'General'` to all 6 attribute definitions.

## [0.9.0]

### Added

- Dedicated fields per condition/action type (`tag`, `amount`, `rule_id`, `prefix`, `template`, `message`) in
  `ordo_campaign_form.xml`, shown/hidden via `<switcherConfig>`. `params_json` remains as fallback.

### Fixed

- `<switcherConfig>` was on the target fields instead of the controlling `type` select.
- `ordo_campaign_form.xml`'s `<dataSource>` was missing `<submitUrl path="ordo/campaign/save"/>`.
- `Save.php` read `$data['conditions']`/`$data['actions']` directly, but the dynamicRows posted structure is
  double-nested (`conditions[conditions][0][...]`) — conditions/actions never actually persisted before this fix.

## [0.8.5]

### Added

- Custom admin dashboard (`ordo/dashboard/index`) — server-rendered, not a UI Component.

### Changed

- Admin menu is now a single flat entry pointing at the dashboard, with Campaigns/Reorder Cycles/Configuration
  linked as cards from it.

## [0.8.4]

### Fixed

- Several admin controllers didn't implement `HttpGetActionInterface`/`HttpPostActionInterface` — Magento's
  `BackendValidator` silently rejected the requests before `execute()` ran.
- `ordo_campaign_form.xml`'s knockout template resolved to `templates/form/default.xhtml`, which binds to an
  `areas` scope nothing in this form ever creates — permanent silent hang. Fixed via
  `templates/form/collapsible`.

## [0.8.3]

First run against a live Magento Open Source 2.4.7 instance. 12 bugs found and fixed.

### Fixed

- `Api/CampaignRepositoryInterface.php`, `Api/OfferRepositoryInterface.php` — incomplete `@return` docblocks
  broke the WebAPI reflection generator.
- `Model/Campaign.php`, `Model/Offer.php` — `setEntityId()` was parameter-incompatible with `AbstractModel`.
- `Model/CampaignRepository.php`, `Model/OfferRepository.php` — `getList()` missing its declared return type.
- Three toolbar button blocks implemented a nonexistent Magento interface.
- `etc/acl.xml` — missing `Magento_Backend::stores_settings` ancestor created a conflicting ACL resource.
- Grid collections needed `mainTable`/`resourceModel` via `di.xml`, not `_init()`.
- `ordo_campaign_form.xml`'s `save` button referenced a nonexistent core class.
- `Model/Campaign/DataProvider.php` — undeclared dynamic property.
- `QualifyingSetTracker.php` called `$rule->getRuleId()`, which doesn't exist (only `getId()`).
- `Model/SalesRepEmailContext.php` called `->getFrontendName()` on a `StoreInterface`-typed value (only on the
  concrete `Store` model) — switched to `getName()`.
- `phpstan.neon` was missing `includes:`/using the wrong parameter key — PHPStan never actually ran.

## [0.8.2]

### Added

- `VERIFICATION.md` — install/test checklist for a fresh Magento Open Source instance.

## [0.8.1]

### Added

- `HasTagTest`, `AddTagTest` unit tests.
- First MFTF test, `AdminCreateCampaignTest.xml`.

## [0.8.0]

### Added

- On-site behavior tracking core — `tracker.js` (visitor cookie, `page_view`/`product_view`/`category_view`),
  `POST /ordo/track/event`, `customer_login` identity stitching, `VisitorAggregator`.
- `ordo_visitor_event` table with `PruneVisitorEvents` retention cron (default 7 days).

## [0.7.0]

### Added

- Campaign builder admin UI — grid and edit form with dynamicRows conditions/actions.
- Read-only "Reorder Cycles" admin grid.

## [0.6.0]

### Added

- `cart_abandoned` campaign event, dispatched from `SendAbandonedCartReminders`.
- `CheapestItemFree` custom SalesRule discount calculator (+ `QualifyingSetTracker`).

## [0.5.0]

### Added

- Campaign engine (`ordo_campaign`/`_condition`/`_action`, `CampaignDispatcher`, `ConditionPool`/`ActionPool`
  plug-in registry). Ships with `tag`/`order_total_gte` conditions and `add_tag`/`send_email`/`generate_coupon`
  actions. Triggers: `order_placed`, `customer_registered`, `tag_added`.
- `CouponGenerator` — mints single-use SalesRule coupon codes.
- REST service contract for campaigns (`/V1/ordo/campaigns`).

## [0.4.0]

### Added

- Sales-rep signature on automated emails, falling back to the store name when unassigned.
- Weekly sales-rep digest email grouping inactive customers by rep.
- Quality standards adopted: PHPStan `level: max`, unit tests per non-trivial class, planned MFTF/API coverage.
- Localization scaffold — `i18n/en_US.csv`, `i18n/pl_PL.csv`.

### Fixed

- Two email templates used an invalid `{{depend}}{{else}}` construct — replaced with independent `{{depend}}`
  blocks.

## [0.3.0]

### Added

- Order approval workflow — per-customer spend limit, `Pending Approval` order status, token-based
  approve/reject email.
- Escalation cron for stale pending approvals (capped at 3 resends).

## [0.2.0]

### Added

- B2C lifecycle automation — welcome email, nightly inactivity tagging, self-clearing win-back email.
- `CustomerTagManager` — shared add/remove/check/list-by-tag service.

## [0.1.1]

### Added

- Proactive credit limit alerts — cron warning at a configurable threshold (default 80%).

## [0.1.0]

### Added

- First-party B2B offer/quote entity (`ordo_offer`) with a proactive expiry reminder.

## [0.0.1] — initial release

### Added

- Reorder reminders — recurring purchase pattern detection per customer/SKU.
- Abandoned cart recovery — inactive carts above a configurable subtotal, capped per cart.
- Module skeleton.
