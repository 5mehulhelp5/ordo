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
- **`AdminContentBlockRecommendationsOnSiteTest` has no working MFTF coverage yet and is deliberately excluded
  from every CI-dispatched group** (it only carries `<group value="ordo_automation"/>`, no group in the
  workflow's own matrix) — the on-site content-block-rendering path (`Block/Frontend/ContentBlock/Render.php`)
  itself is unit-tested (`RenderTest`), just not proven end-to-end in a real browser. Two embedding mechanisms
  have been tried and both caused real CI damage, documented in full in the test's own description:
  1. The `{{widget}}` CMS directive (`etc/widget.xml` registers `Render` as `ordo_content_block`) resolves to
     nothing — no PHP error, `Render` never even instantiated — root cause still unconfirmed after ruling out
     single- vs. double-escaped backslashes in the class name.
  2. A real CMS page's own Layout Update XML field needed Magento_Cms's `CreateCMSPage` MFTF operation extended
     with the `layout_update_xml` field it doesn't declare (confirmed: MFTF silently drops undeclared fields
     from the real API request). Doing that via a second `Meta.xml` file extending the operation by name broke
     `Simple_US_Customer`/`SimpleProduct2` creation for the **entire** `campaign2` group in real CI — confirmed
     via a clean before/after comparison of two otherwise-identical CI runs. Reverted. Root cause of *that*
     breakage is still open, suspected to involve `Util\ModuleResolver`'s admin-token-gated file enumeration
     (module/config file resolution for any `Meta.xml` requires a live admin-token round-trip), but unconfirmed.
  Next attempt should happen against a disposable local Magento install (not by re-running real CI and
  observing collateral damage on unrelated tests) — see AGENTS.md's local MFTF sandbox section.
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
