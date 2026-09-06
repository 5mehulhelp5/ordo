# Architecture

Directory/class map for anyone working on the code. Not shipped documentation for end users — see
[README.md](../README.md) for that.

## Configuration

- `etc/` — `module.xml`, `di.xml`, `crontab.xml`, `db_schema.xml`, `events.xml`, `email_templates.xml`, `acl.xml`,
  `webapi.xml`
- `etc/adminhtml/system.xml` — store configuration
- `etc/frontend/routes.xml` — `/ordo/approval/*` (token-based, no login)
- `Api/`, `Api/Data/` — service contracts: `Offer*`, `Campaign*`, `Campaign/ConditionInterface`,
  `Campaign/ActionInterface`

## Cron jobs

- `CalculateReorderCycle.php`, `SendReorderReminders.php`
- `SendAbandonedCartReminders.php`
- `SendOfferExpiryReminders.php`, `ExpireOverdueOffers.php`
- `SendCreditLimitAlerts.php`
- `TagInactiveCustomers.php`, `SendWinBackEmails.php`
- `EscalateStalePendingApprovals.php`
- `SendSalesRepDigest.php`

## Observers

- `SendWelcomeEmail.php` — `customer_register_success`
- `HoldOrderForApproval.php` — `sales_order_place_after`
- `DispatchOrderPlacedCampaigns.php` — `sales_order_place_after`
- `DispatchCustomerRegisteredCampaigns.php` — `customer_register_success`
- `DispatchTagAddedCampaigns.php` — `ordo_customer_tag_added` (custom event)
- `TrimExcessFreeGifts.php` — drops gifts that no longer qualify when subtotal falls
- `StitchVisitorIdentity.php` — attributes pre-login visitor events to the customer on login

## Campaign engine

- `Model/Campaign/` — `ConditionPool`, `ActionPool`, `Condition/*`, `Action/*` (the plug-in registry)
- `Model/CampaignDispatcher.php` — trigger event + context in, matching campaigns run out
- `Block/Adminhtml/Campaign/Edit/Flow.php` — builds the Drawflow trigger/condition/action graph for the campaign
  edit page
- `Block/Adminhtml/Campaign/Calendar/` — read-only trigger/action-timing overview across all campaigns
- `view/adminhtml/web/lib/drawflow/` — vendored Drawflow (MIT) — https://github.com/jerosoler/Drawflow
- `Controller/Adminhtml/Campaign/`, `ReorderCycle/`, `FreeGiftOffer/` — admin grid/form controllers
- `Block/Adminhtml/Campaign/Edit/`, `FreeGiftOffer/Edit/` — toolbar button blocks (Back/Delete/Save & Continue)
- `Ui/Component/Listing/Column/` — `CampaignActions`, `FreeGiftOfferActions` (Edit/Delete row links)
- `view/adminhtml/ui_component/` — `ordo_campaign_listing/form`, `ordo_reorder_cycle_listing`,
  `ordo_free_gift_offer_listing/form`

## Promotion Builder

- `Model/Rule/Action/Discount/` — `CheapestItemFree` (custom SalesRule calculator), `QualifyingSetTracker`
- `view/adminhtml/ui_component/sales_rule_form.xml` — extends the native Cart Price Rule form with a live
  "Buy X Get Y" preview field
- `view/adminhtml/web/js/buy-x-get-y-calculator.js` — the preview's read-only calculator (mirrors the native
  discount logic, adds none)

## Credit limit & free gifts

- `Model/CreditLimitCalculator.php` — used-credit derived from open `sales_order.total_due`
- `Model/CreditLimitManagement.php` — REST-facing wrapper (mine / by customer id) over the calculator above
- `Plugin/Quote/BlockOverLimitCheckout.php` — blocks order placement at/over credit limit
- `Model/FreeGiftOffer(Tier/Product).php`, `Model/FreeGiftManagement.php` — cascading-tier gift offers + selection
- `Model/QuoteGiftItem.php` — marker linking a quote_item to the offer it was earned from

## Shared building blocks

- `Model/CustomerTagManager.php` — add/remove/check/list-by-tag; fires `ordo_customer_tag_added`
- `Model/CouponGenerator.php` — mints a single-use SalesRule coupon code
- `Model/SalesRepEmailContext.php` — shared email signature block
- `Setup/Patch/Data/` — customer attributes (credit/spend limit, approval admin email, sales rep), Pending Approval
  order status
- `Helper/Config.php` — typed access to `system.xml` values
- `view/frontend/email/` — email templates

## On-site tracking

- `Controller/Track/Event.php` — public, CSRF-exempt tracking endpoint
- `Model/VisitorEventLogger.php` — writes `ordo_visitor_event`, triggers aggregation when identity is known
- `Model/VisitorAggregator.php` — raw events → `ordo_customer_tag` threshold-crossing tags
- `view/frontend/web/js/tracker.js` — dependency-free visitor cookie + event snippet

## SMS & delivery tracking

- `Model/Sms/` — `SmsSenderInterface`, `TwilioSmsSender`, `CallbackUrlBuilder`, `MessageLogWriter`
- `Controller/Sms/StatusCallback.php` — signature-verified Twilio delivery-status webhook (public, CSRF-exempt)
- `Model/MessageLog.php` — `ordo_message_log`, channel-generic delivery tracking (SMS today, email later)
- `Controller/Adminhtml/MessageLog/Index.php` — read-only admin grid over `ordo_message_log`

## Tests & i18n

- `Test/Unit/` — PHPUnit tests
- `i18n/` — translation CSVs (`en_US`, `pl_PL`, + 10 machine-translated locales)
