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

## Admin UX: become the best-in-market admin experience, not a patched one

Explicit product bar, stated directly by the project owner after an initial "just add a
description to every screen" proposal was correctly rejected as a patch, not a real fix: **this
isn't about making 13 screens individually less confusing — it's about the whole admin experience
competing with Klaviyo/HubSpot/ActiveCampaign-caliber tools, not reading as "a developer's
internal debug views with a Magento skin."** Concretely, what separates those tools from an
average one, and the bar every phase below is measured against:

1. Reports show **business outcomes** (revenue, conversion, response rate) — never raw internal
   engine state as the main event.
2. Internal-engine diagnostics are **not first-class navigation items** competing for attention
   with real merchant workflows — a merchant should never need to know `Cron\CalculateReorderCycle`
   exists to trust that reorder reminders work.
3. Navigation is organized **around merchant goals** ("grow repeat purchases", "recover carts"),
   not around this module's own internal data model (a flat list of every entity type it happens
   to persist).
4. A first-time user is **guided to their first real action**, not shown 13 equally-weighted empty
   grids with no indication of where to start.
5. Color and visual hierarchy are **functional** (status, priority, this module's own brand
   palette — see below) — never decorative, never absent (flat monochrome grey) either.

A full inventory of all 13 screens was already done against this bar (controller/grid classes,
real columns, CRUD-vs-read-only, existing in-UI explanation) — findings below are grounded in that,
not speculation. Actual state is better than first assumed: RFM Report already joins customer
name/email (not just an id), and Campaign Calendar already has real intro prose — proof this
module can already hit the bar, just inconsistently.

### Phase 1 — Information architecture (highest leverage, do first)

- Regroup the flat 13-card dashboard into merchant-goal-oriented sections instead of one
  undifferentiated list, e.g.: **Campaigns & Automation** (Campaigns, Campaign Calendar, Free Gift
  Offers), **Audience & Targeting** (Segments, Score Rules, RFM Report), **Channels & Content**
  (Content Blocks, WhatsApp Templates, Ad Audiences), **Compliance** (GDPR/Consent), *Configuration*
  kept separate as settings always are. Needs a naming/grouping decision pass, not just a CSS
  reflow.
- Explicitly demote **Reorder Cycles** and **Message Log** out of that primary grouping into a
  clearly-labeled **Diagnostics / Advanced** area — both are genuinely "verify the engine is
  working" tools (confirmed by their own controller docblocks), not merchant workflows, and
  pretending otherwise is exactly the "internal debug view with a Magento skin" problem stated
  above. Not deleted, not hidden entirely — honestly labeled for what they are.

### Phase 2 — Turn the two real diagnostic dumps into outcome reports

- **Reorder Cycles**: join `customer_id` → real customer name, raw `SKU` → product name (both
  already resolvable via existing repository patterns elsewhere in this module), and add the
  outcome column that's currently entirely missing — did the customer actually reorder after the
  predicted date, and what was that order's revenue. This is what turns "here is some internal
  math" into "here is proof this feature makes you money," which is the actual bar, not a coat of
  paint on the same columns.
- **Message Log**: customer name instead of raw id; consider linking from each Campaign's own edit
  page to its filtered message log, rather than one global flat log being the only way to answer
  "did MY campaign's sends work."
- **RFM Report** already has the right bones (name/email, quintiles) - the remaining gap is
  action, not data: surface which segment(s) a customer's current RFM standing would qualify them
  for, so the report leads directly into an action instead of ending at a number.

### Phase 3 — Explain every screen in context, and guide first use

- Every remaining screen gets a real intro (what this is, why it exists, what to do here) -
  mirroring the quality of prose this module's own docblocks already have, not boilerplate.
  Column tooltips wherever a column name alone doesn't explain itself.
- Empty states matter as much as populated ones: a merchant with zero campaigns/segments/offers
  should land on a clear "here's your first action" state, not a bare empty grid identical to a
  configured one.

### Phase 4 — Visual/interaction polish (last — wasted if the content above isn't fixed first)

- Dashboard: functional color coding for status (enabled/disabled, response-rate bands, loyalty
  tiers) instead of monochrome text/tables.
- Flow editor's palette panel (Triggers/Conditions/Actions): color-coded by kind, collapsible
  sections - currently a flat, ever-growing list of plain boxes.
- Flow canvas connection curves look poor when nodes are close together (Drawflow's default
  bezier control-point math doesn't adapt to short distances) - needs curve-parameter tuning or a
  different connection-rendering approach.
- Native campaign edit form's Triggers/Conditions/Actions dynamicRows tables (what "New Campaign"
  lands on before anyone opens the Flow canvas) are bare grey grid rows with a plain "Type"
  dropdown and a raw JSON params textarea for anything without a dedicated column.
- **Brand palette to design against** (established in `.github/assets/hero.svg`, the README hero
  banner, already reused by the admin menu icon): pink-to-blue gradient accent (`#E879F9` →
  `#0EA5E9`), dark navy/slate base (`#0B1E2E`/`#0F172A`/`#161F32`), semantic status colors already
  in use elsewhere (success `#22C55E`, warning `#F59E0B`, danger `#EF4444`) - used deliberately
  (status, section accents, the gradient as a rare highlight), never grey Magento-stock defaults
  everywhere nor a decorative rainbow of ad-hoc colors.

Two small, unrelated visual bugs found in passing during the same feedback session were already
fixed directly (not part of this item, no further action needed): the Flow canvas's connection
arrowheads rendering detached/misaligned (`campaign-flow-editor.js`'s SVG `<marker>` had
inconsistent `markerUnits`), and the sidebar's brand-mark icon being off-center in the collapsed
icon-only menu (`menu-icon.css` needed block+`margin:auto` centering, not inline-block+
vertical-align).

**Sequencing is deliberate**: Phase 1-2 (architecture, real outcome data) is where "best in
market" is actually won or lost; Phase 4 (visual polish) on top of unfixed information
architecture would just be a prettier version of the same underlying problem.

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
