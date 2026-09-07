# Roadmap

What's still open — for shipped/stable features see [README.md](README.md), for implementation history/verification
detail see [CHANGELOG.md](docs/CHANGELOG.md) and [VERIFICATION.md](VERIFICATION.md), for the REST API reference see
[API.md](API.md), and the `Test/*/README.md` files.

Ownership split: B2B direction is scoped by the technical/architecture side (this repo's maintainer); B2C direction is
scoped from real hands-on marketing automation experience.

## Test coverage

- **Ad-audience sync (`Cron\SyncAdAudiences`) has no test against a real Google Ads/Meta account.** Note:
  a since-fixed bug (docs/CHANGELOG.md "Fixed") meant `getGoogleAdsClientSecret()`/`getGoogleAdsRefreshToken()`/
  `getGoogleAdsDeveloperToken()`/`getMetaAccessToken()` returned ciphertext at runtime, not the decrypted
  secret — every real API call would have failed auth regardless of this gap. Same shape
  as `send_sms` above: unit tests (`GoogleAdsSyncClientTest`/`MetaSyncClientTest`/`GoogleOAuthTokenProviderTest`)
  drive the real request-building/response-parsing logic via a fake `Curl`, and the integration test
  (`SyncAdAudiencesTest`) uses real DI/database (real segment/tag/customer rows, real `SegmentMemberResolver`
  query, real `PiiHasher`) but swaps `SyncClientInterface` for a `RecordingSyncClient` — so the actual HTTP
  calls to `googleads.googleapis.com`/`graph.facebook.com` (OAuth token exchange, offline user data job
  lifecycle, Custom Audience creation/replace) have never been exercised against live credentials.
- **`send_whatsapp` / WhatsApp templates have no test against a real Meta WhatsApp Business Account.** Note:
  the same since-fixed bug meant `getWhatsAppAccessToken()`/`getWhatsAppAppSecret()` returned ciphertext at
  runtime — every real Graph API call and every webhook signature check would have failed regardless of this
  gap. Same shape again: unit tests (`WhatsAppSenderTest`/`WhatsAppTemplateClientTest`) drive the real Graph API
  request-building/response-parsing logic via a fake `Curl`, and `WhatsAppSignatureValidatorTest`/`WebhookTest`
  use a real HMAC-SHA256 signature — but template submission (`SubmitForReview`), approval polling
  (`RefreshStatus`), and an actual template message send have never been exercised against a live WABA/phone
  number, and the webhook receiver has never received a genuine callback from Meta.
- **`send_push` / Web Push has no test against a real browser or push service (FCM, Mozilla autopush, etc.).**
  The RFC 8291/8188 encryption itself is covered thoroughly (`WebPushCryptoTest` round-trips a full encrypt against
  an independent, from-scratch decrypt reimplementation; `DerTest`/`VapidTokenBuilderTest` verify the ECDH/ECDSA
  primitives against real OpenSSL), but no CI run has ever registered a real subscription in an actual browser,
  sent a push through it, and confirmed a notification appeared — the one thing unit tests structurally can't
  exercise here.

### MFTF/scenario coverage

Full inventory with what's covered and why: `Test/Mftf/SCENARIOS.md`. Every row there is currently ✅ — no open
gaps. Kept as the standing scope check for anything newly added to the module (new trigger/condition/action/
controller/cron gets a row there before it's considered done).

## Admin information architecture & business-value pass

- **A whole-module audit of every admin screen against one question: what business outcome does
  this give a non-technical merchant, and does the screen actually communicate that, or does it
  just expose raw internal data?** Surfaced by direct user feedback while manually verifying
  `send_push` (real admin screenshots, not a design review from mockups) — starting from "what is
  the Reorder Cycles grid even for" and generalizing to: the module has genuine functionality
  (13 dashboard cards' worth) with no consistent answer, screen to screen, about what it's *for*.
  This supersedes treating it as a visual/CSS redesign — the root problem is information
  architecture and missing explanation, not (only) missing color.
  - **Every screen needs classifying, honestly, before any redesign work starts.** First pass,
    from the 13 cards `Block/Adminhtml/Dashboard/DashboardViewModel.php` currently lists as equals:
    - **Direct merchant value** (a store owner acts on this or it visibly makes them money):
      Campaigns, Free Gift Offers, GDPR/Consent (compliance, not optional), Ad Audience Sync.
    - **Configuration/setup** (necessary, but not itself a "report" - fine as a settings screen if
      clearly labeled as one): Segments, Score Rules, Content Blocks, WhatsApp Templates,
      Configuration.
    - **Technical/diagnostic tools** (built to verify the *engine* is working, not to be browsed
      by a merchant looking for insight) — currently presented as first-class, equal-weight
      features: **Reorder Cycles** (raw `customer_id`/SKU/interval-days numbers - see below),
      **Message Log** (delivery status - a support/debugging tool), and likely **RFM Report** and
      **Score Rules**' own scoring output once actually read with this lens applied.
    - Campaign Calendar sits in between - genuinely useful for a merchant ("what's about to fire
      this week") but currently presented with the same raw-data style as the diagnostic group.
  - **For the "technical/diagnostic" group, the real question isn't "add tooltips" - it's "should
    a merchant see this screen in primary navigation at all."** Two honest options, not both:
    demote them into an explicitly-labeled "Advanced / Diagnostics" area (a merchant never needs
    to open Reorder Cycles for `SendReorderReminders` to work - the value is the automated email,
    not the grid), or rebuild them as an actual business report (customer *name* not id, product
    *name* not SKU, and outcome data like "reminder sent → did they reorder, revenue recovered" -
    the same shape the dashboard's own "Trigger performance" block already uses with its
    Sent/Responded/Response Rate/Recovered Revenue columns). Concretely reported example: Reorder
    Cycles today shows `customer_id`, raw SKU, avg-interval-days, and "orders considered" with zero
    in-page explanation - a viewer has no way to tell it's a read-only "here's what the detection
    engine currently believes" view without reading `Cron\CalculateReorderCycle`'s own source.
  - **For screens kept as merchant-facing, each needs an actual in-UI explanation of its purpose**
    - a short page-level description block (this module's docstrings already write this kind of
    "why does this exist" prose; it just never made it into the admin UI itself) plus column
    tooltips where a column name alone doesn't explain itself.
  - Secondary, UI-level findings from the same feedback pass, real but subordinate to the
    classification work above - fix once the "what should even be visible" question is answered,
    not before:
    - Dashboard reads as a wall of monochrome text/tables once its actual content is decided.
    - The Flow editor's palette panel (Triggers/Conditions/Actions) is a flat, uncategorized list -
      no per-kind color coding, not collapsible, gets long and hard to scan.
    - The Flow canvas's connection curves look poor when nodes are close together (Drawflow's
      default bezier control-point math doesn't adapt to short distances).
    - The native campaign edit form's Triggers/Conditions/Actions dynamicRows tables (what a "New
      Campaign" load lands on before anyone opens the Flow canvas) are bare grey grid rows with a
      plain "Type" dropdown and a raw JSON params textarea for anything without a dedicated
      column - reported directly as "not simple at all" against a real New Campaign screenshot.
  - **This module already has a real brand palette** to design against once the content itself is
    sorted, established in `.github/assets/hero.svg` (the README hero banner) and reused by the
    admin menu icon (`view/adminhtml/web/images/icon.svg`): a pink-to-blue gradient accent
    (`#E879F9` → `#0EA5E9`), a dark navy/slate base (`#0B1E2E`/`#0F172A`/`#161F32`), and semantic
    status colors already in use elsewhere (success `#22C55E`, warning `#F59E0B`, danger
    `#EF4444`) - use these deliberately (status colors, section accents, the gradient as a rare
    highlight), not grey Magento-stock defaults everywhere nor a decorative rainbow of ad-hoc
    colors.
  - Two small, unrelated visual bugs found in passing during the same session were already fixed
    directly (not part of this item, no further action needed): the Flow canvas's connection
    arrowheads rendering detached/misaligned (`campaign-flow-editor.js`'s SVG `<marker>` had
    inconsistent `markerUnits`), and the sidebar's brand-mark icon being off-center in the
    collapsed icon-only menu (`menu-icon.css` needed block+`margin:auto` centering, not
    inline-block+vertical-align).
  - **Needs a real scoping session before any implementation** - go through all 13 (or however
    many after this audit) screens one by one, decide keep/demote/rebuild/merge for each, *then*
    design. This entry exists so that scoping happens deliberately, as a real product decision,
    rather than the module accumulating more equally-weighted screens indefinitely.

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
