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

## Admin UI/UX

- **A dedicated visual redesign pass over the admin UI — minimalist, but using this module's own
  brand identity instead of defaulting to Magento's stock grey.** Surfaced by direct user feedback
  while manually verifying the `send_push` Flow-editor fields (screenshots of the real admin, not
  a design review from mockups) — this is deliberately scoped as its own task, not folded into
  whatever feature work happens to touch the admin next, since it's a real, multi-surface design
  effort rather than a one-off fix. Two concrete bugs found in passing during that session were
  already fixed directly (not part of this item): the Flow canvas's connection arrowheads
  rendering detached/misaligned (`campaign-flow-editor.js`'s SVG `<marker>` had inconsistent
  `markerUnits`), and the sidebar's brand mark icon being off-center in the collapsed icon-only
  menu (`menu-icon.css` used inline-block + vertical-align instead of the block+margin:auto
  centering Magento's own native menu icons rely on).
  - **This module already has a real brand palette**, established in `.github/assets/hero.svg`
    (the README hero banner) and reused by the admin menu icon (`view/adminhtml/web/images/
    icon.svg`): a pink-to-blue gradient accent (`#E879F9` → `#0EA5E9`), a dark navy/slate base
    (`#0B1E2E`/`#0F172A`/`#161F32`), and semantic status colors already in use elsewhere
    (success `#22C55E`, warning `#F59E0B`, danger `#EF4444`). "Minimalist but on-brand" means
    using these deliberately (status colors, section accents, the gradient as a rare highlight)
    rather than either grey Magento-stock defaults everywhere or a decorative rainbow of ad-hoc
    colors — this palette is the actual constraint, not a blank slate.
  - **Reported problems to scope against** (real screenshots, not speculation):
    - Dashboard (`view/adminhtml/templates/dashboard/index.phtml` + its blocks) reads as a wall
      of monochrome text/tables — no color-coding for status (enabled/disabled campaigns,
      response-rate bands, loyalty tiers), poor scannability, "not user friendly."
    - The Flow editor's palette panel (Triggers/Conditions/Actions lists) is a flat, uncategorized
      list of plain boxes — no per-kind color coding, no collapsible sections, gets long and hard
      to scan once every trigger/condition/action type is listed.
    - The Flow canvas's own connection curves look poor when nodes are placed close together
      (Drawflow's default bezier control-point math doesn't adapt to short distances) - needs
      either curve-parameter tuning or a different connection-rendering approach.
  - Needs a real scoping pass (which admin screens are in/out, whether this touches only CSS/
    templates or also UI component layouts, a11y/contrast check against the new palette) before
    implementation starts - this entry exists to make sure that scoping happens deliberately
    rather than the redesign arriving piecemeal, one screenshot complaint at a time.

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
