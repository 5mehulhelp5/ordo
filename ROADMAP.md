# Roadmap

What's still open — for shipped/stable features see [README.md](README.md), for implementation history/verification
detail see [CHANGELOG.md](docs/CHANGELOG.md) and [VERIFICATION.md](VERIFICATION.md), for the REST API reference see
[API.md](API.md), and the `Test/*/README.md` files.

Ownership split: B2B direction is scoped by the technical/architecture side (this repo's maintainer); B2C direction is
scoped from real hands-on marketing automation experience.

## Test coverage

- ~~Load/soak test for Phase 7's dispatch performance work~~ — done.
  `Test/Integration/CampaignDispatchLoadTest.php` puts real numbers on both fixes: 200 campaigns
  matched to one trigger dispatch in ~1.05s (~191 campaigns/sec, batched condition/action loading,
  not one query per campaign), and a 600-row `ordo_campaign_scheduled_action` backlog (deliberately
  over `RunScheduledCampaignActions::BATCH_SIZE`, 500) is fully claimed and resumed in ~1.36s
  (~440 rows/sec) across two batches within one cron tick. Asserted bounds are deliberately generous
  (15s / 60s) — the point is catching a regression back to O(n) query behavior, not
  micro-benchmarking a specific number that would make CI flaky on a slower runner. Running this
  against a real install caught a real, separate bug along the way: the shared
  `AbstractCustomerAttributePatch` base class (extracted for the Setup-patch dedup, see
  docs/CHANGELOG.md) lived in `Setup/Patch/Data/` alongside its concrete subclasses — harmless for
  unit tests, but Magento's `PatchReader` globs every `*.php` directly under that folder and treats
  each one as a real patch class with no abstract-class check, so `setup:install`/`setup:upgrade`
  crashed calling the abstract class's unimplemented `getDependencies()`. Fixed by moving the base
  class to `Setup/Patch/AbstractCustomerAttributePatch.php`, one directory above where Magento's
  patch discovery looks.
- **`send_sms` has no test against a real Twilio account.** Unit tests (`TwilioSmsSenderTest`) drive the real SDK
  request-building/error-parsing logic via a fake `Twilio\Http\Client`, and the integration test
  (`CampaignSendSmsActionTest`) uses real DI/database but swaps out `SmsSenderInterface` for a
  `RecordingTwilioSmsSender` — so the actual Twilio API call (auth, delivery, and the
  `Controller\Sms\StatusCallback` webhook receiving a genuine signed callback) has never been exercised end to end
  against a live/trial Twilio account. `StatusCallbackTest` is unit-level too: it uses a real
  `Twilio\Security\RequestValidator` to compute a correct signature, but the collection/resource-model calls are
  mocked, so a real DB round trip (write on send → status update on callback) is untested.

### MFTF/scenario coverage gaps

Full inventory with what's already covered and why: `Test/Mftf/SCENARIOS.md`. The real gaps (⬜ rows there), grouped:

- ~~`cart_abandoned` trigger / `Cron\SendAbandonedCartReminders`~~ — done.
  `AdminSendAbandonedCartReminderAndTriggerTest`: a real cart abandoned for real (added to, then
  left — no synthetic dispatch call), `ordo_automation/abandoned_cart/delay_minutes` set to 0 so
  it already qualifies (real default is 120 minutes), `CronScheduleHelper` forces the cron to run
  now instead of waiting out its real every-30-minutes schedule. Asserts both real outputs: the
  cron's own fixed reminder email AND the campaign the `cart_abandoned` trigger dispatched —
  `MailHogHelper::seeTextInAnyRecentEmail()` (new, alongside the existing `seeTextInLatestEmail()`)
  since this one cron tick genuinely sends both emails to the same address in sequence.
- **Campaign engine** (§1): all 6 RFM-based conditions
  (`recency_days_at_most`, `order_frequency_at_least`, `monetary_total_at_least`, and their 3 percentile
  variants) are untested end to end; `add_tag` action has never been the thing directly under test (only a side
  effect elsewhere); multiple campaigns matching the same trigger where only some satisfy their conditions is
  only incidentally exercised, never asserted; chained delays (an action pauses, resumes, pauses again) aren't
  covered.
- **RFM** (§3): `Cron\RecomputeRfmScores` populating `ordo_customer_rfm_score` and the RFM report reflecting it,
  and the percentile-based campaign conditions actually reading that precomputed table (rather than a live scan),
  are both untested.
- **Free gift offers / storefront offers** (§5): offer self-extension (`Controller/Offer/Extend.php`,
  `Offer::canSelfExtend()`), the "My Offers" storefront account page (`Controller/Offer/Index.php`),
  `Cron\SendOfferExpiryReminders`'s reminder email, and `Cron\ExpireOverdueOffers` marking a lapsed offer expired
  are all uncovered.
- ~~Order approval (§6): `Cron\EscalateStalePendingApprovals`~~ — done. `AdminEscalateStalePendingApprovalTest`
  reuses `AdminApproveOrderViaEmailTest`'s exact fixture (`OrdoApprovalCustomer`, spend limit 10.00), but never
  follows the approve/reject link, leaving the approval genuinely pending; `ordo_automation/order_approval/
  escalation_days` set to 0 (real default 2) so it's already stale, `CronScheduleHelper` forces the cron to run
  now. "No spend limit / no approval email configured → never held" is still unit-tested only.
- **Tracking & popups** (§7): view-threshold crossing tagging the visitor (chaining into `visitor_tag_added`,
  §1a) isn't covered; neither is `Cron\PrunePendingPopups` or `Cron\PruneVisitorEvents`.
- ~~Reorder cycles (§8): `Cron\CalculateReorderCycle`, `Cron\SendReorderReminders`~~ — done.
  `AdminReorderCycleAndReminderTest`: three real storefront checkouts of the same SKU (
  `CalculateReorderCycle` requires >= 3 real orders and explicitly skips same-day repeats), backdated
  to a real, even 10-day spacing (30/20/10 days ago) via new `Test/Mftf/Helper/OrderBackdateHelper.php`
  — no MFTF-reachable UI/API sets `sales_order.created_at` directly. `CronScheduleHelper` forces both
  crons to run now instead of waiting out their real nightly schedules.
- ~~Reminder/alert crons (§10): `SendCreditLimitAlerts`, `SendSalesRepDigest`, `SendWinBackEmails`,
  `TagInactiveCustomers`~~ — done. New `lifecycle` MFTF group (`.github/workflows/mftf.yml` matrix):
  `AdminTagInactiveCustomersAndWinBackEmailTest` (covers the tightly-coupled
  `TagInactiveCustomers`/`SendWinBackEmails` pair in one test), `AdminSendCreditLimitAlertTest`,
  `AdminSendSalesRepDigestTest`. All four crons fire once a day/week at a fixed time no CI run can
  wait out — `Test/Mftf/Helper/CronScheduleHelper.php` forces the next `cron:run` to execute a
  specific job by inserting its `cron_schedule` row directly, verified for real against a live
  Magento install (confirmed the forced job actually reaches `status=success`, not just that the
  insert didn't error). `SendAbandonedCartReminders`/`cart_abandoned` (same family) still ⬜, see
  SCENARIOS.md §10.

## Code quality

- **The "Ordo_Automation: ..." per-item-failure + run-summary log shape is still duplicated across ~10 cron
  jobs** outside the reminder/alert family (`RecomputeRfmScores`, `RefreshRssContentBlocks`,
  `CalculateReorderCycle`, `RunScheduledCampaignActions`, `SendAbandonedCartReminders`,
  `EscalateStalePendingApprovals`, `TagInactiveCustomers`, `PruneVisitorEvents`, `PrunePendingPopups`,
  `ExpireOverdueOffers`) — `Model\Cron\CronRunLogger` (extracted for the 5 reminder/alert crons) covers exactly
  this shape and could be adopted there too. Low priority: each occurrence is only 2–4 lines and Sonar hasn't
  flagged it, so this is a minor readability cleanup, not a correctness or architectural issue.

## Gaps vs. a full-market MA platform

Not a code review — a capability comparison against the category. Each is a real, separate stream of work:

- **WhatsApp** (multichannel recovery, alongside the shipped SMS channel) — confirmed via Twilio's own docs to be
  materially more work than "same API, `whatsapp:` prefix": outside a 24-hour customer-service session window
  (started only when the *customer* messages first), only pre-approved message templates can be sent
  (Marketing/Utility/Authentication categories, each with separate Meta fees, ~minutes-to-48h approval
  turnaround). A cold-start marketing cart-recovery message is necessarily template-based, so this needs a
  template-authoring/approval-tracking admin UI, not just a new `SmsSenderInterface`-style action — scope this
  properly before starting, don't underestimate it as a copy of the SMS slice.
- **Push notifications** — still open, not investigated yet.
- **SendGrid-backed email delivery tracking** — a natural follow-up now that `ordo_message_log`/the SMS delivery
  webhook pattern exist: Twilio's SendGrid (Mail Send API + Event Webhook for opens/clicks/bounces) could replace
  `send_email`'s current fire-and-forget `TransportBuilder` call the same way `send_sms` now tracks delivery. A
  real, separate architectural decision (email sending is threaded through `TransportBuilder` in more places than
  just `SendEmail`), not a small addition.
- **On-site product recommendation blocks** — a new content-block type (or campaign action) rendering personalized
  product suggestions using data the module already has (customer/visitor tags, RFM scores, segment membership),
  not a new AI/recommendation engine. Extends the existing content-block and campaign-action surface rather than
  bolting on a separate subsystem.
- **Campaign calendar view** — an admin grid/calendar overlay showing every campaign's trigger window and any
  delayed actions (`delay_minutes`) in one place. Pure UI on top of data already modeled — no new entities.
- **Loyalty tiers on top of lead scoring** — map `ordo_customer_score` ranges to named tiers (e.g. Bronze/Silver/Gold),
  surfaced as a new segment condition type and a dashboard stat. Small and additive to the scoring system already
  built for §4 (lead scoring), not a separate loyalty subsystem.
- **Persistent in-site notification action** — a sibling to the existing `popup` campaign action, but non-modal and
  persisting until read or expired, instead of one-shot. Extends the existing `ordo_pending_popup`-style delivery
  mechanism rather than introducing a new one.
- **Single-question satisfaction/NPS survey action** — a 0–10 post-purchase (or post-support) prompt, stored as a
  new `ordo_customer_survey_response`-style entity, feeding into existing segment conditions the same way tags and
  scores already do. Deliberately narrower than a full open-ended survey builder, which is a genuinely separate
  subsystem and not scoped here.

## Localization

- **Native-speaker review of the 10 machine-translated locales** (`de_DE`, `fr_FR`, `es_ES`, `it_IT`, `pt_BR`,
  `zh_Hans_CN`, `ja_JP`, `ru_RU`, `uk_UA`, `nl_NL`) — shipped as a machine-translated first pass (see
  docs/CHANGELOG.md),
  not yet signed off by a human reviewer per locale. Highest priority: launch-blocking strings (error messages,
  delete confirmations) over descriptive/help text.

## Documentation

- **GitHub Wiki (WIKI.md) covering every feature, bilingual PL/EN, with screenshots.** Not started. Scope: a walkthrough
  of each shipped capability (campaigns, segments, RFM, lead scoring, free gifts, order approval, tracking/popups,
  reorder cycles, dashboard) with a real admin-UI screenshot per feature and description text in both Polish and
  English, published to the repo's GitHub Wiki (not just this ROADMAP/README). Needs a decision on structure first:
  one bilingual page per feature vs. a language-split page tree (`Feature-Name` + `Feature-Name-PL`) — GitHub Wiki
  has no built-in i18n, so this is a real information-architecture choice, not just a writing task. Screenshots
  should come from a real, running instance (the MFTF pipeline's own screenshot-on-failure mechanism proved useful
  for debugging — the same live-instance approach, deliberately captured on success this time, is the right source
  here too, not mockups).
