# Roadmap

What's still open — for shipped/stable features see [README.md](README.md), for implementation history/verification
detail see [CHANGELOG.md](docs/CHANGELOG.md) and [VERIFICATION.md](VERIFICATION.md), for the REST API reference see
[API.md](API.md), and the `Test/*/README.md` files.

Ownership split: B2B direction is scoped by the technical/architecture side (this repo's maintainer); B2C direction is
scoped from real hands-on marketing automation experience.

## Test coverage

- **`VERIFICATION.md`'s manual checklist was last run against Magento 2.4.7 / PHP 8.2.**
  `composer.json` now requires PHP >=8.4 <8.6 and targets Magento 2.4.8/2.4.9 — the checklist
  needs a fresh manual pass against that combination; CI's own MFTF/PHPUnit/PHPStan lanes
  already run on the current versions, so this is specifically about the manual walkthrough
  going stale, not a sign the module itself is untested on 2.4.8/2.4.9.
- **`send_sms` has no test against a real Twilio account.** Unit tests (`TwilioSmsSenderTest`) drive the real SDK
  request-building/error-parsing logic via a fake `Twilio\Http\Client`, and the integration test
  (`CampaignSendSmsActionTest`) uses real DI/database but swaps out `SmsSenderInterface` for a
  `RecordingTwilioSmsSender` — so the actual Twilio API call (auth, delivery, and the
  `Controller\Sms\StatusCallback` webhook receiving a genuine signed callback) has never been exercised end to end
  against a live/trial Twilio account. `StatusCallbackTest` is unit-level too: it uses a real
  `Twilio\Security\RequestValidator` to compute a correct signature, but the collection/resource-model calls are
  mocked, so a real DB round trip (write on send → status update on callback) is untested.
- **`AdminContentBlockRecommendationsOnSiteTest` is currently failing in real CI, reproducibly.** The `{{widget}}`
  directive fix (`etc/widget.xml`) is confirmed correct and in place, but `.recommended-products` still isn't
  found on the CMS page across multiple real CI runs — meaning `Model\Recommendation\ProductRecommender::
  getRecommendedSkus()`'s best-seller fallback is returning empty for the anonymous visitor in this specific
  flow, not a selector/widget-registration problem. Not yet root-caused: `ProductRecommender::
  rankedBestSellerSkus()`'s in-instance 60-second cache (shared across requests if the object is DI-shared and
  the test web server process persists across requests within a CI run) is the leading suspect — a request
  landing within that window right after the order is placed could plausibly get a best-seller list computed
  before this test's own order existed. Needs a real CI screenshot/DB check to confirm, not more guessing.
- **Ad-audience sync (`Cron\SyncAdAudiences`) has no test against a real Google Ads/Meta account.** Same shape
  as `send_sms` above: unit tests (`GoogleAdsSyncClientTest`/`MetaSyncClientTest`/`GoogleOAuthTokenProviderTest`)
  drive the real request-building/response-parsing logic via a fake `Curl`, and the integration test
  (`SyncAdAudiencesTest`) uses real DI/database (real segment/tag/customer rows, real `SegmentMemberResolver`
  query, real `PiiHasher`) but swaps `SyncClientInterface` for a `RecordingSyncClient` — so the actual HTTP
  calls to `googleads.googleapis.com`/`graph.facebook.com` (OAuth token exchange, offline user data job
  lifecycle, Custom Audience creation/replace) have never been exercised against live credentials.

### MFTF/scenario coverage

Full inventory with what's covered and why: `Test/Mftf/SCENARIOS.md`. Every row there is currently ✅ — no open
gaps. Kept as the standing scope check for anything newly added to the module (new trigger/condition/action/
controller/cron gets a row there before it's considered done).

## Gaps vs. a full-market MA platform

Not a code review — a capability comparison against the category. Each is a real, separate stream of work:

- **WhatsApp** (alongside the shipped SMS channel) — not "same API, `whatsapp:` prefix": outside a 24h
  customer-service session window, only pre-approved message templates can be sent (separate Meta fees,
  minutes-to-48h approval). Needs a template-authoring/approval-tracking admin UI, not just a new
  `SmsSenderInterface`-style action.
- **Push notifications** — not investigated yet.
- **SendGrid-backed email delivery tracking** — `send_email` currently fire-and-forget via `TransportBuilder`;
  SendGrid's Event Webhook could track delivery the same way `send_sms` now does. Separate architectural
  decision — `TransportBuilder` is called from more places than just `SendEmail`.

## Localization

- **Native-speaker review of the 10 machine-translated locales** (`de_DE`, `fr_FR`, `es_ES`, `it_IT`, `pt_BR`,
  `zh_Hans_CN`, `ja_JP`, `ru_RU`, `uk_UA`, `nl_NL`) — shipped as a machine-translated first pass (see
  docs/CHANGELOG.md),
  not yet signed off by a human reviewer per locale. Highest priority: launch-blocking strings (error messages,
  delete confirmations) over descriptive/help text.

## Documentation

- **GitHub Wiki covering every feature, bilingual PL/EN, with screenshots.** Not started. One walkthrough page per
  shipped capability (campaigns, segments, RFM, lead scoring, free gifts, order approval, tracking/popups, reorder
  cycles, dashboard), each with a real admin-UI screenshot and PL/EN text. Needs a structure decision first —
  GitHub Wiki has no built-in i18n, so bilingual-per-page vs. a language-split page tree is a real choice.
