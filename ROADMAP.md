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

### Mutation testing

`mutation-testing` runs in CI on every PR but is non-blocking (Quality Gate/merge never wait on
it) — right now nobody actually reads its output before merging, which raises the question of
whether the line-coverage push above is proving real test *quality* or just exercising lines.
Needs: someone to actually open the mutation-testing report on a few recent PRs and see what
survives (untested edge cases the coverage number hides), decide a realistic minimum mutation
score, and only then flip the job to blocking — flipping it blind, before knowing the current
baseline, would just make every PR red on day one.

### MFTF/scenario coverage

Full inventory with what's covered and why: `Test/Mftf/SCENARIOS.md`. Every row there is currently ✅ — no open
gaps. Kept as the standing scope check for anything newly added to the module (new trigger/condition/action/
controller/cron gets a row there before it's considered done).

## Scheduled (date-based) campaigns and a real calendar view

Raised directly after renaming "Campaign Calendar" to "Campaign Action Timeline" (it showed
relative delay offsets, not dates — every trigger today fires on a customer event, not a fixed
schedule, so a literal calendar would have been empty): **should a campaign be able to fire at a
specific date/time instead of only on a customer event?**

- A new trigger type, e.g. `scheduled_at` (fixed date/time) or `recurring_schedule` (cron-like:
  every Monday, first of the month, etc.) — `CampaignTriggerInterface` and `TriggerEvent`'s option
  source are the two places a new trigger type is wired in.
- A cron that scans for campaigns whose scheduled time has arrived and fires them the same way
  `CampaignDispatcher` fires event-based triggers today, so the rest of the pipeline (conditions,
  actions, delay_minutes chaining) needs no change.
- Only once that exists does an actual date-grid calendar view become meaningful — plotting when
  each scheduled campaign will (or did) fire. Worth revisiting whether "Campaign Action Timeline"
  should grow a calendar-view toggle at that point, or stay a separate screen.

Needs a scoping decision before implementation: is a one-off scheduled send (e.g. "Black Friday
email, Nov 28 9am") or a recurring schedule (e.g. "every Monday") the more valuable first case.

## Visual rule builder for Segment/Campaign conditions (AND/OR groups)

Raised after the Segment condition form got dedicated per-type fields (no more raw JSON for the
common condition types): the remaining gap is structural, not cosmetic. Both `Segment` and
`Campaign` conditions are a **flat list always joined by AND** — `SegmentSaveProcessor`/
`CampaignSaveProcessor` delete-and-reinsert a plain row-per-condition, and the matching logic
(`SegmentMemberResolver`, campaign condition evaluation) has no concept of a nested group or an
OR join. A real "(A AND B) OR (C AND D)" builder needs, in order:

- A schema change: either a `group_id`/`parent_group_id` + `join_type` (AND/OR) column set on the
  condition tables, or a switch to storing the whole tree as one JSON Logic-style blob per
  segment/campaign (trades relational queryability for structural flexibility — worth an explicit
  decision, not a default).
- Matching logic in `SegmentMemberResolver` (and wherever campaign conditions are evaluated) to
  walk the group tree instead of AND-ing a flat list — the actual segment-membership SQL/PHP
  changes shape, not just the form.
- Only then does the admin UI part make sense: a nested drag-and-drop group builder with an
  ALL/ANY toggle per group and an "Add a condition group" action. Off-the-shelf JS toward this:
  `react-querybuilder` (the closest to a de-facto standard; exports directly to JSON Logic) or,
  scoped down to fit Magento's own `Magento_Ui/js` component style rather than pulling in React,
  a bespoke tree UI following the same field-per-type pattern the current switcherConfig already
  uses, just nested.
- A live "estimated audience size" counter next to the segment builder — needs a fast
  count-only path through the same matching logic above; naive re-running the full member
  resolver on every keystroke would be too slow to feel live.
- Separately (independent of the above): the "Bulk actions on current members" block sharing the
  same form/page as the condition builder was flagged as a mis-grouping risk (an action button
  living directly below unrelated condition rows). Worth its own tab/section or a confirmation
  step before this gets built out further, regardless of when/whether the AND/OR rework happens.

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
