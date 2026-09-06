# Roadmap

What's still open — for shipped/stable features see [README.md](README.md), for implementation history/verification
detail see [CHANGELOG.md](docs/CHANGELOG.md) and [VERIFICATION.md](VERIFICATION.md), for the REST API reference see
[API.md](API.md), and the `Test/*/README.md` files.

Ownership split: B2B direction is scoped by the technical/architecture side (this repo's maintainer); B2C direction is
scoped from real hands-on marketing automation experience.

## Test coverage

- **`send_sms` has no test against a real Twilio account.** Unit tests (`TwilioSmsSenderTest`) drive the real SDK
  request-building/error-parsing logic via a fake `Twilio\Http\Client`, and the integration test
  (`CampaignSendSmsActionTest`) uses real DI/database but swaps out `SmsSenderInterface` for a
  `RecordingTwilioSmsSender` — so the actual Twilio API call (auth, delivery, and the
  `Controller\Sms\StatusCallback` webhook receiving a genuine signed callback) has never been exercised end to end
  against a live/trial Twilio account. `StatusCallbackTest` is unit-level too: it uses a real
  `Twilio\Security\RequestValidator` to compute a correct signature, but the collection/resource-model calls are
  mocked, so a real DB round trip (write on send → status update on callback) is untested.

### MFTF/scenario coverage

Full inventory with what's covered and why: `Test/Mftf/SCENARIOS.md`. Every row there is currently ✅ — no open
gaps. Kept as the standing scope check for anything newly added to the module (new trigger/condition/action/
controller/cron gets a row there before it's considered done).

## Gaps vs. a full-market MA platform

Not a code review — a capability comparison against the category. Each is a real, separate stream of work:

- **WhatsApp** (multichannel recovery, alongside the shipped SMS channel) — confirmed via Twilio's own docs to be
  materially more work than "same API, `whatsapp:` prefix": outside a 24-hour customer-service session window (started
  only when the *customer* messages first), only pre-approved message templates can be sent
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
