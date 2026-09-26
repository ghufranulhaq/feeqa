# Spec 005 Tasks: Review Invitations

Each task is implemented, tested, and committed on its own before the next
one starts (constitution §2, §8). Task order follows dependency, not FR
number. This spec depends on 002 (categories, for default send delay) and
004 (transaction records + attestations, for `transaction_invitation`); it
is itself a dependency of 004's own `transaction_invitation` method, which
was modelled but unproducable until this spec exists (see 004 T10's
acceptance sweep). `integration` (booking-engine/GDS connectors) is Phase 2
and out of scope here (spec.md §7 decided 2026-09-24). There is no review
*submission* route/page yet for any source (organic, invited, or
redirected) — spec 003's own `SubmitReview` action has none either
(`docs/user-guides/consumer.md`) — so "opening a link pre-fills the review
form" (FR-005-10) is built as real, tested resolution logic with nothing to
render it yet, the same honest-placeholder relationship every other spec
in this codebase has with its own missing UI.

- [x] **T1. Invitation schema & domain model.** `review_invitations` table
      (business_id, method, recipient_email encrypted nullable,
      recipient_email_hash nullable+indexed, recipient_name nullable,
      locale, reference encrypted nullable, reference_hash
      nullable+indexed, template_id nullable, product_skus json nullable,
      location_id nullable, status, token unique, scheduled_at, sent_at/
      delivered_at/opened_at/clicked_at/reviewed_at/bounced_at/
      complained_at/cancelled_at nullable, cancellation_reason nullable,
      review_id nullable, created_by nullable, queued_reason nullable,
      expires_at, timestamps). `invitation_suppressions` table (business_id
      nullable — null means global, recipient_email_hash, reason,
      timestamps; unique business_id+recipient_email_hash).
      `invitation_templates` table (business_id, locale, subject, body,
      is_active, timestamps; unique business_id+locale). Domain enums in
      `app/Domain/Invitations/`: `InvitationMethod`
      (bcc/integration/api/csv/manual/link — `integration` modelled, never
      produced, same relationship `payment_link` has with 004),
      `InvitationStatus` (queued/sent/delivered/opened/clicked/reviewed/
      bounced/complained/unsubscribed/suppressed/cancelled/expired).
      Models + factories for `ReviewInvitation`, `InvitationSuppression`,
      `InvitationTemplate`. Reuses `App\Domain\Verification\
      TransactionRecordHash` for email/reference hashing rather than a
      second HMAC helper — one secret to manage for both specs. FR-005-05.
- [ ] **T2. Template neutrality guard.** Pure, unit-tested
      `GuardNeutralTemplate` (`app/Domain/Invitations/`): rejects a
      template missing `{review_link}`/`{unsubscribe_link}`, mentioning an
      incentive (discount/coupon/prize/gift/refund/points/"in return"/etc,
      a maintained lexicon covering `en-GB` plus `fr`/`de`/`es` as
      launch-market EU languages a business's own customers may be
      addressed in), asking only for positive reviews or suggesting a
      rating, pre-selecting a rating/sentiment question before the review
      link ("gating"), or linking anywhere other than the review link, the
      business's own registered domain, or unsubscribe. An unrecognised
      locale is held for staff review before first use rather than
      silently allowed or silently rejected (`Log::warning`, same
      honest-placeholder shape as every other unbuilt-006-tooling signal
      in this codebase). `UpsertInvitationTemplate` action + business
      dashboard endpoint (`SendInvitations` permission — Responder/Analyst
      get 403). Test corpus of ≥ 50 negative examples across the four
      rejection reasons and the three non-English lexicons. FR-005-13,
      FR-005-14, edge cases (incentive in a non-English template,
      unauthorized Responder).
- [ ] **T3. Core invitation engine: manual method.** `CreateInvitation`
      action every method funnels through: guards recipient-is-a-business-
      member (suppress + fraud-signal log, edge case), suppression-list
      check (business-level and global), the 30-day-per-recipient-per-
      Business rule and one-per-reference rule (FR-005-09), issues a
      signed single-use token expiring in 60 days (FR-005-10), and
      computes `scheduled_at` from the category's default send delay
      (`travel-content.md` §4: Airlines/Travel Agencies & OTAs 1 day after
      a `travel_date` if given else the category fallback; Airports has no
      transaction-linked default and falls back to the spec's plain 7-day
      default) or a per-Business override (FR-005-08). `RequestManualInvitation`
      action + business dashboard endpoint (`manual` method, FR-005-01) and
      `CancelInvitation` (FR-005-12, queued-only, counted). FR-005-01
      (manual), FR-005-05, FR-005-08 through FR-005-12.
- [ ] **T4. CSV upload method.** `ImportInvitationsFromCsv` action:
      UTF-8-only, ≤ 20 MB, ≤ 50,000 rows, required-column check, row-level
      validation (malformed email → reject that row; duplicate rows in one
      file collapse to one) through a downloadable error report, each valid
      row created through T3's `CreateInvitation`. Reviews from this method
      are `Invited` but never auto-verified by this spec — only 004's own
      reference-matching rules can verify them, because the data was typed
      by hand (FR-005-03). FR-005-01 (csv), FR-005-03, edge cases (empty/
      wrong encoding/missing columns/oversized file or row count,
      malformed emails, duplicate rows).
- [ ] **T5. API and BCC methods.** `RequestApiInvitation` (idempotent by
      reference — a repeat call with the same reference returns the
      existing invitation rather than creating a second one; a past send
      time is clamped to the next allowed send window) covering the
      "Invitation API" data shape FR-005-01 (api) asks for, same
      documented relationship to 016's real token-authenticated contract
      as 004's own Business API stand-in. `ImportBccEmail` action: a
      demo-only `.eml` upload endpoint standing in for real inbound mail
      (plan D14 — no real SMTP/SPF/DKIM infrastructure exists yet), a
      pass/fail SPF/DKIM-alignment check against the Business's verified
      domains or registered sender addresses (real header check, `fake`-
      driver-free because there's no external provider to swap — same
      "rules only" shape as review screening), first-`To:`-recipient-only
      parsing (CC ignored), reference extraction via a business-configurable
      regex pattern, and immediate, irreversible discard of the email body
      after parsing (only the extracted fields are persisted). A body that
      doesn't match the reference pattern (e.g. a newsletter) creates no
      invitation and is counted in a diagnostics tally instead of erroring.
      Both methods are transaction-linked (FR-005-02): a reference present
      here is trusted directly, not run back through 004's reference-match
      flow. FR-005-01 (api, bcc), FR-005-02, FR-005-06, FR-005-07, edge
      cases (BCC multi-recipient, non-transactional email, API replay with
      same reference, API past send time).
- [ ] **T6. Link method, unsubscribe, and suppression.** A stable, unique
      per-Business review link and QR-encodable URL (`link` method,
      `Redirected` label, never auto-verified — FR-005-04) needing no
      `review_invitations` row per use, only a Business-level token.
      `Unsubscribe` action: one click, no sign-in, per-Business or
      platform-wide (FR-005-15), permanent until the recipient reverses
      it, enforced by every method at send/creation time through T3's
      suppression check. Hard bounces and spam complaints add the address
      to suppression automatically (`RecordBounce`/`RecordComplaint`,
      FR-005-17). FR-005-04, FR-005-15, FR-005-17.
- [ ] **T7. Sending, engagement tracking, and link resolution.** A
      scheduled command sends every due `queued` invitation through
      Mailpit/the configured mailer (`queued`→`sent`), a 1-pixel open
      tracker and a redirecting click-through link move status forward
      (`sent`→`delivered`→`opened`→`clicked`), an unreminded, unopened
      invitation gets exactly one reminder 3–7 days later (FR-005-08), and
      an invitation past `expires_at` with no terminal status becomes
      `expired`. `ResolveInvitationToken`: pre-fills business, location,
      products, and reference for a valid token; returns the specific
      "this invitation expired, write an Organic review instead" state for
      an expired one (edge case) — no page renders this yet, per this
      spec's own opening note. FR-005-08, FR-005-10, FR-005-11, edge case
      (expired link).
- [ ] **T8. Review submission integration.** `SubmitReview` (003) accepts
      an optional invitation token: sets `source_label` from the
      invitation's method (`Invited` for bcc/integration/api/csv/manual,
      `Redirected` for link) instead of always `Organic`, marks the
      invitation `reviewed` and links `review_id`, and — only for a
      transaction-linked method carrying a reference (FR-005-02) — issues
      a `transaction_invitation` attestation directly through 004's
      `IssueAttestation`, fingerprinted and anti-reuse-checked the same as
      every other verification method, with no separate consumer
      confirmation step (the business's own validated channel is the
      proof). FR-005-02, closes 004's own `transaction_invitation` gap
      from its T10 acceptance sweep.
- [ ] **T9. Plan limits (017 placeholder).** Monthly invitation limits by
      plan, read from a config-driven placeholder (017 doesn't exist yet,
      same honest-placeholder relationship 004 T9 had with unbuilt specs):
      a Business at its limit keeps new invitations `queued` with
      `queued_reason = 'plan_limit'`, never dropped, released by a
      scheduled sweep once the next period starts or an upgrade is
      recorded. FR-005-20.
- [ ] **T10. Neutrality report, anti-gaming alerts, and analytics.** A
      daily per-Business neutrality report (invitations per trigger,
      cancellation rate, percentage of known transactions invited, invited
      vs. organic rating comparison) with the two staff alerts (>20%
      cancellation over 30 days with ≥50 invitations; invited average >1.5
      stars above organic with ≥50 reviews each and <50% of transactions
      invited) — `Log::warning`, same real-signal-now/full-006-console-
      later shape as every other staff-facing signal in this spec and 004.
      `GET` funnel/conversion/by-method/by-template analytics endpoint for
      the Analyst role and above. FR-005-18, FR-005-19.
- [ ] **T11. Docs pass + acceptance sweep.** `docs/system-overview/
      invitations.md`; consumer-guide and business-guide notes for what's
      reachable vs. not; README env vars for every setting this spec adds;
      re-check every box in spec.md §6, fixing any real gap found rather
      than only noting it. Full acceptance sweep.
