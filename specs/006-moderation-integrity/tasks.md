# Spec 006 Tasks: Moderation, Integrity & Transparency

Each task is implemented, tested, and committed on its own before the next
one starts (constitution §2, §8). Task order follows dependency, not FR
number. This spec depends on 001 (staff roles), 002 (business profiles,
profile-change queue), and 003 (reviews, the existing `ScreenReviewSubmission`
placeholder and `ReviewStatus::Held`, both explicitly written to be replaced
by this spec). Like every spec so far, there is no consumer-, business-, or
staff-facing page for anything built here yet (`resources/js/pages` has no
moderation console, flag button, or transparency center) — actions,
migrations, and their tests are the unit of "done"; a route/controller is
added only where an existing spec already established the convention of a
minimal public endpoint (e.g. `verification-check`). Replies (007), case
messages (010), media items (012), and insider reviews (013) don't exist
yet, so FR-006-03's "every ... reply, case message ... media item" is
implemented for the content types that exist today (reviews, lifecycle
updates) with the screening/flagging contract shaped so a later spec plugs
in the same way 005's invitations plugged into 003's `SubmitReview` — not
guessed at for content that isn't there yet.

- [x] **T1. Guidelines and reason codes.** `App\Domain\Moderation\ReasonCode`
      enum, the ten codes from FR-006-02. `guideline_versions` table
      (audience: reviewer/business, version, body text, published_at,
      is_current, timestamps; unique `[audience, version]`) + model.
      `PublishGuidelineVersion` staff action (Admin only): creates the next
      version number for the audience, marks the previous current version
      not-current, never deletes an old version (FR-006-01). Seeder
      publishing v1 of both audiences from this spec's own FR-006-01 list
      (genuine experience, 12-month window, one review per experience,
      18+, no incentives, no conflicts of interest, plus the ten
      FR-006-02 categories) — English only, per this spec's own decided
      note (§7). Public read action `ListGuidelineVersions` (current +
      history, both audiences) — the first piece of the Transparency
      Center (FR-006-21a), with no page rendering it yet. FR-006-01,
      FR-006-02.
- [x] **T2. Screening engine v2: signals, risk score, auto-reject
      precision gate.** Replaces the placeholder single-blocklist-check
      inside `ScreenReviewSubmission` (003) with the full signal set
      FR-006-04 asks for, computed from data already on hand (no new
      external provider — same "rules only, nothing to swap in `.env`"
      reasoning the class's own doc-comment already gives): account age
      and history (reviewer's account age, prior review count), velocity
      (reviews by this reviewer in 24h, reviews on this business in the
      last hour), text similarity (existing cross-business near-duplicate
      check, plus a new same-business/last-hour cluster check across ≥ 3
      distinct reviewers — User Scenario 1's coordinated-fraud shape),
      incentive language (a maintained lexicon, reusing 005 T2's
      `GuardNeutralTemplate` incentive list rather than a second one),
      personal-info detection (phone numbers, email addresses, postal
      codes in free text), a link check, and network reputation (an
      optional `?string $ipAddress`, checked only against a configured
      list of known-bad ranges — `config('platform.moderation.network.
      known_bad_ranges')` — since there is no request/IP available yet at
      any of `ScreenReviewSubmission`'s three call sites; wiring a real IP
      in is future work once a submission route exists, same gap 005 T7
      documented for its own token resolution). `ScreeningOutcome` gains
      `riskScore` (0–1) and `triggeredRules` (list of rule IDs). A new
      `screenings` table (polymorphic `screenable`, recommendation,
      risk_score, triggered_rules json, signals json, reason nullable,
      timestamps) + `Screening` model, written by the three existing call
      sites (`SubmitReview`, `UpdateReview`, `SubmitLifecycleUpdate`) right
      after they persist their content, so a screening record always
      names the content it judged. `screening_rule_states` table
      (rule_id primary key, enabled default true, disabled_at,
      disabled_reason, timestamps) + `ScreeningRuleState::isEnabled()`:
      an auto-reject rule normally recommends `reject`, but recommends
      `hold` instead the moment it's been disabled here — the hook T8's
      weekly audit will call automatically (FR-006-05, FR-006-20). Only
      the exact-duplicate-cluster and blocklist-word rules are registered
      as auto-reject (documented, narrow, matches the spec's own
      examples); every other signal only ever pushes the recommendation to
      `hold`, never `reject`, over a configurable risk-score threshold.
      FR-006-03, FR-006-04, FR-006-05.
- [x] **T3. Business anomaly detection and incidents.** `moderation_
      incidents` table (business_id, type, detected_at, metrics json,
      status: open/investigating/resolved/dismissed, frozen_until
      nullable, resolved_by nullable, resolved_at, resolution_notes,
      timestamps) + model + `IncidentType` enum (review_spike/
      rating_shift/new_account_cluster). `DetectBusinessAnomalies` action:
      per business, flags a review-spike (> 5× the 30-day daily median
      with ≥ 20 reviews that day — FR-006-06's own numbers), a rating
      shift (documented threshold: today's average differs from the prior
      30-day average by ≥ 1.5 stars, ≥ 10 reviews on each side), or a
      new-account cluster (≥ 5 reviews in 24h from accounts created in the
      last 7 days) — dedups against an already-open incident of the same
      type. `review-invitations:...`-style scheduled command running
      hourly (FR-006-06's own cadence). `FreezeBusinessReviews` (Moderator+,
      up to 72h, compliance log entry, incident → investigating) and
      `ResolveModerationIncident` (resolved/dismissed, notes, compliance
      log). `Business::hasActiveModerationFreeze()`: `ScreenReviewSubmission`
      checks this first and short-circuits straight to `hold` with the
      spec's own neutral wording ("We're checking unusual activity on this
      profile") when true, skipping the rest of T2's signals — a frozen
      business holds everything, full stop. FR-006-06.
- [ ] **T4. Flagging (notice-and-action).** `flags` table (polymorphic
      `flaggable`, reporter_id nullable, reporter_email nullable — required
      when reporter_id is null, FR-006-07 — reason_code, details nullable
      ≤ 1000 chars, evidence file paths json, status: open/blurred/upheld/
      rejected, is_business_flag bool, decided_by nullable, decided_at,
      decision_reason, sla_due_at, timestamps) + model. `CreateFlag`
      action: any visitor (guest needs email) or business; rejects a
      missing reason code, details over 1,000 characters, or more than 5
      evidence files / 10 MB each (edge cases table); a repeat flag by the
      same reporter (or same business) on the same item is a no-op
      returning the existing flag; `harmful_illegal`/`personal_info`
      reasons auto-blur when the reporter is trusted (an account in good
      standing, no upheld-against-them flags) or ≥ 3 distinct reporters
      have flagged the same item, with a 24h SLA — everything else gets a
      7-day SLA (FR-006-08); a business flag never sets status past `open`
      by itself (FR-006-09, tested explicitly per the acceptance
      checklist) and a `not_genuine` business flag re-runs T2's screening
      engine against the flagged review's stored text. Per-business open-
      flag cap of 50 plus a rolling 90-day reject-rate check (≥ 20 flags,
      > 80% rejected ⇒ `Log::warning` educational-notice signal, same
      staff-alert shape as 005 T10, feeding T6's ladder step 1) —
      FR-006-10. Rate limit: > 20 flags/hour from one account raises the
      same kind of fraud-signal log 005 T10 already established. FR-006-07
      through FR-006-10, edge cases table (no reason code, oversized
      details/evidence, duplicate flag, mass flagging, business flags
      every negative review).
- [ ] **T5. Moderation console: queues and staff actions.** Read actions
      for each FR-006-11 queue (held content — reviews with
      `status = Held`; open flags; T4's own queue; verification proofs,
      already listed at `staff/review-verifications` from 004 T5;
      business incidents from T3; appeals from T7; profile-change requests,
      already listed from 002) filterable by priority/SLA/category and
      assignable to a staff member (`assigned_to` nullable column added to
      `reviews` and `flags` — `moderation_incidents` already has one from
      T3). `ModerateReview` action covering FR-006-12's verbs that
      apply to a review today (publish, remove, redact a span with
      `[removed]`, mark not genuine, request verification — delegates to
      004's existing `RequestDocumentVerification` rather than
      duplicating it): every call requires a `ReasonCode`, writes a
      `ComplianceLogEntry` (reusing the existing model from 001/002/004
      rather than a new log), and sends a `StatementOfReasonsNotification`
      (what was affected, the reason code, the guideline version in force
      from T1, whether automation was involved, and an appeal link) to the
      content's owner — FR-006-13. `remove`/`not_genuine` re-triggers
      `RecalculateBusinessScore` (the existing 008 placeholder hook) per
      FR-006-17. `BlockUserAccount` and `RestrictBusinessFeature` cover the
      account/business verbs the ladder (T6) needs. A moderator with a
      recorded conflict of interest on a business (a `moderator_conflicts`
      table: staff_id, business_id) is blocked from acting on it — edge
      cases table. FR-006-11, FR-006-12, FR-006-13, edge cases (conflict of
      interest, unauthorized Responder queue access — reuses existing
      business-role authorization, nothing new to add there).
- [ ] **T6. Enforcement ladder and Consumer Warning.** `EnforcementStep`
      value object/enum for both ladders (FR-006-14, FR-006-15).
      `enforcement_actions` table (subject_type/id — business or user,
      step, reason_code, applied_by, applied_at, expires_at nullable,
      lifted_by/at nullable, lift_reason nullable, senior_approved_by
      nullable — required to skip a step, timestamps). `ApplyEnforcementStep`
      action: records the step, and for step 5 (Consumer Warning) sets a
      new `Business.consumer_warning_at` + `consumer_warning_reason`,
      forces `status = Suspended`-equivalent score hiding (Review Score and
      Trust Index hidden — a `Business::trustSignalsHidden(): bool`
      accessor 008/009 will read once they exist, same forward-hook shape
      as `RecalculateBusinessScore`), suspends paid features (reuses 002's
      existing plan/status machinery), and can only be lifted by a Senior
      Moderator after the 6-month minimum and a `resolution_notes` entry
      (FR-006-16). `LiftEnforcementStep`. Reviewer ladder block reuses
      001's existing account-block field if one exists, otherwise adds
      `users.blocked_at`. Every step call is a `ModerateReview`-style
      wrapper: reason code required, compliance log, statement of reasons.
      FR-006-14 through FR-006-17.
- [ ] **T7. Appeals.** `appeals` table (appealable — the
      enforcement_action or moderation decision being appealed —
      appellant_id, statement ≤ 2000 chars, evidence paths json, status:
      pending/upheld/overturned, decided_by, decided_at, decision_reason,
      timestamps). `SubmitAppeal`: one per decision, within 30 days
      (rejected as late past that, per the edge cases table, unless a
      staff override flag is set), guards against the same appellant
      appealing twice. `DecideAppeal`: the decider must not be the
      original `applied_by`/`decided_by` staff member (FR-006-19,
      enforced, not just documented); a 7-day SLA field for T5's queue;
      `overturned` fully reverses the original action (unblurs/republishes
      the content, lifts the enforcement step, recalculates scores) and
      notifies the appellant either way. FR-006-18, FR-006-19, edge case
      (late appeal, staff override).
- [ ] **T8. Weekly audit sampling and auto-disable.** `AuditScreeningSample`
      action: pulls a random ≥ 2% sample of the week's `screenings` rows
      with a `publish`/`reject` recommendation, and creates an
      `audit_samples` table row per one (screening_id, staff decision
      pending, correct bool nullable, staff_id, decided_at) for a staff
      member to mark correct/incorrect against what actually happened
      (upheld on appeal, later flagged and upheld, etc.) — the queue slots
      into T5. `ComputeRulePrecision`: per rule_id, precision over the
      trailing audit window; any **reject**-type rule (T2's registry)
      under 99% precision calls `ScreeningRuleState::disable()` and logs
      why — FR-006-20's automatic disabling, not a staff step. Weekly
      scheduled command. FR-006-20.
- [ ] **T9. Transparency Center.** `ComputeTransparencyReport` action:
      a quarterly, reproducible aggregation straight from `compliance_log`,
      `flags`, `appeals`, and `screenings` (FR-006-22 — no numbers stored
      that don't reconcile back to those tables) covering every figure
      FR-006-21(e) lists (submitted/published/removed by reason,
      automated-vs-flagged detection split, flags received/handled by
      reporter type, median time-to-action, appeal/overturn rates,
      Consumer Warnings issued, accounts blocked, legal requests — the
      last one a manually-entered count with no source table yet, the
      same "recorded, not derived" honest gap 005 left for FR-005-16).
      `transparency_reports` table (period_start, period_end, figures
      json, generated_at). Public read actions for (a) guidelines — T1 —
      (b) the enforcement policy (the ladder text itself, versioned like
      guidelines), (c) links out to 008/009's methodology pages (not
      built yet — a documented placeholder, not a guess), (d) 004's
      verification methodology (already public at 004's own routes), (e)
      the generated reports, and (f) currently-warned businesses (a
      `Business::underConsumerWarning()` scope). FR-006-21, FR-006-22.
- [ ] **T10. Docs pass + acceptance sweep.** `docs/system-overview/
      moderation.md` written (screening, flagging, the ladder, Consumer
      Warning, appeals, Transparency Center — and what isn't reachable
      yet: no console UI, no flag button, no submission route feeding
      real IPs into network reputation). `docs/user-guides/consumer.md`
      gets flagging + appeals; a new `docs/user-guides/staff-moderator.md`
      (referenced by `specs/plan.md` but never written) covering queues,
      the ladder, and audit sampling; `docs/user-guides/business.md`
      (already exists from 002/005) gets the business-flag flow and
      Consumer Warning consequences. README `.env` reference covers every
      setting this spec added (`platform.moderation.*`). All checklist
      items in spec.md §6 re-checked, gaps recorded honestly in §7 rather
      than guessed at (Q2 EU DSA designation, Q3 Consumer Warning
      searchability, real IP plumbing, real network-reputation data
      source). `make ci` green, `make docs-check` passes.
