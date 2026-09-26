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
- [x] **T4. Flagging (notice-and-action).** `flags` table (polymorphic
      `flaggable`, reporter_id nullable, reporter_email nullable — required
      when reporter_id is null, FR-006-07 — reason_code, details nullable
      ≤ 1000 chars, evidence file paths json, status: open/blurred/upheld/
      rejected, is_business_flag bool, decided_by nullable, decided_at,
      decision_reason, sla_due_at, timestamps) + model. Two additions
      beyond this sketch, both documented in the migration itself: a
      `business_id` nullable FK (only set when `is_business_flag`) so
      FR-006-10's per-business open-flag cap and reject-rate check don't
      need a fragile polymorphic join through whatever `flaggable` happens
      to be; and `assigned_to` (nullable FK to users), added now rather
      than by a later ALTER TABLE in T5, same call already made for
      `moderation_incidents` in T3. `CreateFlag`
      action: any visitor (guest needs email) or business (checked against
      `BusinessPermission::FlagReviews`); rejects a
      missing reason code, details over 1,000 characters, or more than 5
      evidence files / 10 MB each (edge cases table); a repeat flag by the
      same reporter (or same business) on the same item is a no-op
      returning the existing flag; `harmful_illegal`/`personal_info`
      reasons auto-blur when the reporter is trusted (an account in good
      standing, no upheld-against-them flags — `User::hasUpheldFlagAgainstThem()`,
      the honest-placeholder definition of "good standing" today) or ≥ 3
      distinct reporters have flagged the same item, with a 24h SLA —
      everything else gets a
      7-day SLA (FR-006-08); a business flag never sets status past `open`
      by itself (FR-006-09, tested explicitly per the acceptance
      checklist) and a `not_genuine` business flag re-runs T2's screening
      engine against the flagged review's stored text. Per-business open-
      flag cap of 50 (hard reject) plus a rolling 90-day reject-rate check (≥ 20 flags,
      > 80% rejected ⇒ `Log::warning` educational-notice signal, same
      staff-alert shape as 005 T10, feeding T6's ladder step 1) —
      FR-006-10. Rate limit: > 20 flags/hour from one account raises the
      same kind of fraud-signal log 005 T10 already established (a logged
      signal, not a hard reject — rejecting a genuine batch of harmful-
      content reports outright would fight FR-006-08's own SLA, a
      documented judgment call). `Review::isBlurred()` / `scopePubliclyVisible()`
      now exclude a review with any `blurred` flag on it. FR-006-07
      through FR-006-10, edge cases table (no reason code, oversized
      details/evidence, duplicate flag, mass flagging, business flags
      every negative review).
- [x] **T5. Moderation console: queues and staff actions.** Three read
      actions in a new `App\Actions\Staff\Queues` namespace —
      `ListHeldReviewsQueue` (reviews with `status = Held`, oldest first —
      no priority/category field exists yet for this queue, an honest gap
      unlike flags' own `sla_due_at`), `ListFlagsQueue` (defaults to
      unresolved — open/blurred — ordered by `sla_due_at`, filterable by
      an explicit status or `reason_code`), and `ListModerationIncidentsQueue`
      (defaults to open/investigating, oldest-detected first). Verification
      proofs stay at `staff/review-verifications` from 004 T5; profile-
      change requests stay wherever 002 already lists them; appeals have no
      queue yet (T7 doesn't exist). `AssignQueueItem`: one action for every
      queue item type carrying an `assigned_to` column (`Review` — new
      column added by this task; `Flag` and `ModerationIncident` already
      had theirs from T4/T3) — assignment isn't itself a moderation
      decision, so it skips the compliance log. `ModerateReview` action
      covering FR-006-12's verbs that apply to a review today (publish,
      remove, redact a span with `[removed]`, mark not genuine, request
      verification — delegates to 004's existing `RequestReviewVerification`
      rather than duplicating it, via a new `bool $requestedByStaff`
      parameter that bypasses its own `BusinessPermission` check; not
      `RequestDocumentVerification`, the reviewer's own proof-upload flow —
      a naming slip in an earlier draft, caught before T5 started). `remove`
      and `mark_not_genuine` both land on `ReviewStatus::Rejected` (the
      same status auto-reject already uses, since both take the review off
      every public/scored surface) but stay distinct verbs: `mark_not_genuine`
      requires the `not_genuine` reason code and always re-triggers
      `RecalculateBusinessScore`; `remove` accepts any reason code and only
      recalculates if the review had been published (FR-006-17). Every
      verb requires a `ReasonCode`, writes a `ComplianceLogEntry` (reusing
      001/002/004's existing model), and sends a `StatementOfReasonsNotification`
      (what was affected, the reason code, the current guideline version
      from T1, that a human — not automation — made the call, and an
      honest-placeholder appeal instruction since T7 doesn't exist) to the
      review's author — except `request_verification`, which already gets
      its own `BusinessRequestedVerificationNotification` from the
      delegate, so this skips a duplicate. `DecideFlag` (`App\Actions\Staff`):
      not named in this task's original description, but necessary to make
      spec.md's User Scenario 2 real — resolves a flag to `upheld`/`rejected`,
      which is also what "restores" a blurred review, since
      `Review::isBlurred()` (T4) only checks for a flag still carrying
      `blurred`; notifies the reporter (signed-in via `notify()`, guest via
      `Notification::route('mail', ...)`) of the outcome per FR-006-07.
      `BlockUserAccount` (new `users.blocked_at`/`blocked_reason` columns —
      no existing account-block field from 001) and `RestrictBusinessFeature`
      (new `businesses.restricted_features` json column + a new
      `RestrictableFeature` enum: flagging/invitations/profile_edits) cover
      the account/business verbs the T6 ladder needs, pulling their
      migrations forward now rather than in T6, same precedent as T3/T4's
      early `assigned_to` columns. Only the `Flagging` restriction is wired
      into an actual check (`CreateFlag` now refuses a restricted
      business's flag) — invitations (005) and profile edits (002) are
      documented gaps until those specs' own actions check
      `Business::hasFeatureRestricted()` too. A new `moderator_conflicts`
      table (staff_id, business_id, unique pair) backs
      `Business::hasConflictWithStaff()`, checked by both `ModerateReview`
      and `DecideFlag` before they touch a business's content. FR-006-11,
      FR-006-12, FR-006-13, edge cases (conflict of interest, unauthorized
      Responder queue access — reuses existing business-role authorization,
      nothing new to add there).
- [x] **T6. Enforcement ladder and Consumer Warning.** `EnforcementLadder`
      (business/reviewer) and `EnforcementStep` enums — one `EnforcementStep`
      type for both ladders since they share their first two steps'
      wording ("educational notice", "warning"); `EnforcementStep::
      sequenceFor()` returns the ordered 6-step business or 3-step reviewer
      sequence. `enforcement_actions` table (subject — Business or User,
      via a morph, plus `ladder` stored alongside `step` so a query never
      has to load the subject; reason_code, applied_by, applied_at,
      expires_at nullable, lifted_by/at nullable, lift_reason nullable,
      senior_approved_by nullable) + model, never deleted — lifting a step
      updates the same row rather than removing it, so the ladder's
      history survives for T9's Transparency Center. `ApplyEnforcementStep`:
      validates the step belongs to the subject's own ladder; blocks a
      conflicted moderator (reusing T5's `Business::hasConflictWithStaff()`);
      requires Senior Moderator approval (`?User $seniorApprover`) to skip
      ahead of the subject's current rung ("staff may skip steps for
      severe or proven fraud"); applies each step's side effects inline
      rather than delegating to T5's `BlockUserAccount`/`RestrictBusinessFeature`
      (those each run their own full compliance-log-and-notify cycle for
      one ad hoc decision, while a ladder step is one decision covering
      several columns at once) — `feature_restriction` restricts
      invitations and profile edits (not flagging: FR-006-16's later
      "reply/flag-only access" implies flagging survives this earlier
      step too); `consumer_warning` sets `Business.consumer_warning_at`/
      `consumer_warning_reason`, restricts every feature except flagging,
      and forces `plan = Free` (this repo's only Business-plan machinery
      today, since 017/Plans & Billing doesn't exist — an honest stand-in
      for "suspends paid features," not a real subscription cancellation);
      `account_block` sets `users.blocked_at`/`blocked_reason` (added in
      T5 alongside `BlockUserAccount`, reused here, so T6 needed no new
      migration for it). `Business::trustSignalsHidden(): bool` reads
      `consumer_warning_at` — a forward hook for 008/009, same shape as
      `RecalculateBusinessScore` — deliberately not `BusinessStatus::Suspended`,
      a separate, currently-unused status this spec leaves alone.
      `LiftEnforcementStep`: any Moderator+ can lift any step at any time,
      except `consumer_warning`, which needs a Senior Moderator and the
      applied-at-plus-6-months minimum (FR-006-16); reverses the side
      effects it can (un-restricts the two features, clears the warning,
      unblocks the account) but doesn't restore a suspended `plan` — no
      billing record of the prior tier exists to restore it to, another
      honest gap for 017. `ApplyEnforcementStep` requires a `ReasonCode`,
      writes exactly one `ComplianceLogEntry`, and sends one
      `StatementOfReasonsNotification` (to every Business owner, or
      directly to a reviewer) — the same "ModerateReview-style wrapper"
      tasks.md called for. `LiftEnforcementStep` takes free-text resolution
      notes instead (there's no punitive `ReasonCode` to attach to lifting
      something), so it logs to compliance but doesn't send a statement of
      reasons — FR-006-13 is about actions against a user, not ones
      restoring them. FR-006-14 through FR-006-17.
- [x] **T7. Appeals.** `appeals` table: `appealable` (a morph covering the
      three decision types that exist today — `EnforcementAction`, `Flag`,
      and `Review` — the "enforcement_action or moderation decision"
      this task's own sketch names), `appellant_id` (nullable/`nullOnDelete`,
      same choice `flags.reporter_id` already made), `statement`,
      `evidence_paths` json, `status` (pending/upheld/overturned),
      `decided_by`, `decided_at`, `decision_reason`. No separate SLA
      column: FR-006-19's 7-day window is a staff process target, not a
      stored deadline, the same choice made everywhere else in this spec
      that a *queue* deadline exists as a real column (flags'
      `sla_due_at`) but a *process* target doesn't. `AppealableDecision`
      (`App\Domain\Moderation`): a small lookup class both actions share,
      since "when was this decided and by whom" differs by appealable
      type — `EnforcementAction.applied_at`/`applied_by`, `Flag.
      decided_at`/`decided_by`, or (since `Review` has neither column)
      the latest matching `ComplianceLogEntry` `ModerateReview` already
      wrote. `SubmitAppeal`: validates the statement (≤ 2,000 chars),
      that the caller is the affected party (the reviewer for their
      review, the flag's own filer — reusing `FlagReviews` for a business
      flag — or the enforcement subject: the blocked reviewer, or any
      business member, since no appeals-specific permission exists yet),
      that no appeal from this same appellant against this same decision
      already exists, and the 30-day window from `AppealableDecision`
      (rejected as late past that unless a `bool $staffOverride` is set —
      edge cases table). `DecideAppeal`: rejects a second decision on an
      already-decided appeal; blocks the original decision-maker via
      `AppealableDecision::deciderId()` (FR-006-19, enforced, not just
      documented); `overturned` flips a flag's upheld/rejected verdict,
      republishes a removed/mark-not-genuine'd review and recalls
      `RecalculateBusinessScore` (redact can't be reversed this way — the
      original text is gone, `ModerateReview::redact()` overwrites it in
      place, an honest gap rather than a guess at reconstructing it), or
      marks an enforcement action lifted and reverses its side effects —
      deliberately bypassing `LiftEnforcementStep`'s own Senior-Moderator-
      plus-6-month gate for a Consumer Warning, since an appeal being
      upheld already means staff judged the step wrong, not that it ran
      its course. That reversal (`EnforcementAction::reverseSideEffects()`)
      moved onto the model so `LiftEnforcementStep` and `DecideAppeal`
      share it rather than duplicating the mutation; writing it surfaced
      a real T6 gap — lifting a `feature_restriction` step never actually
      restored the two features, despite T6's own tasks.md description
      already claiming it did — fixed here rather than narrowed to match
      the old code, with a regression test added to
      `LiftEnforcementStepTest`. `AppealDecidedNotification` sent to the
      appellant either way. FR-006-18, FR-006-19, edge case (late appeal,
      staff override).
- [x] **T8. Weekly audit sampling and auto-disable.** `audit_samples`
      table: `screening_id` (unique — a screening is never sampled twice),
      `correct` (nullable bool: null is "pending a staff decision" rather
      than a separate status column, since there's nothing else a decided
      row can be), `staff_id`, `decided_at`. `AuditScreeningSample`: pulls
      a random ≥ 2% sample of the trailing week's `screenings` rows with a
      `publish`/`reject` recommendation (a `hold` isn't an automated
      *decision* — it's already a referral to a human) via `firstOrCreate`
      per row. `ScreenReviewSubmission::autoRejectRuleIds()`: the
      previously-private auto-reject registry, made public so T8 can read
      it without duplicating the list. `ComputeRulePrecision`: for each of
      those rule IDs, precision over decided samples (`correct` not null)
      in the trailing 90 days; below 99% calls `ScreeningRuleState::
      disable()` with the measured numbers in the reason — FR-006-20's
      automatic disabling, not a staff step; a rule with no decided
      samples yet is left alone rather than disabled on zero evidence (not
      specified either way, an honest choice over guessing). Two
      "necessary but unnamed" additions, the same class of gap T5's
      `DecideFlag` was: `ListAuditSamplesQueue` (`App\Actions\Staff\
      Queues`, defaulting to pending samples — without it nothing ever
      slots the sample into T5's console as promised) and
      `DecideAuditSample` (a staff correct/incorrect verdict; no
      compliance log, same reasoning `AssignQueueItem` already gives —
      this is quality control on an automated decision, not a moderation
      or enforcement decision against a user). One weekly scheduled
      command, `moderation:weekly-audit`, runs both actions in sequence
      (routes/console.php). FR-006-20.
- [x] **T9. Transparency Center.** `GuidelineAudience` gains a third case,
      `EnforcementPolicy` — FR-006-21(b)'s enforcement policy reuses
      `GuidelineVersion`/`PublishGuidelineVersion`/`ListGuidelineVersions`
      as-is rather than a parallel table, since it's "versioned like
      guidelines," not a different kind of document (v1's body, the
      ladder text itself, added to `GuidelineVersionsSeeder`); this also
      means (a) and (b) share one action, T1's own `ListGuidelineVersions`,
      once the enum grew a case. `transparency_reports` table
      (period_start, period_end, figures json, generated_at; unique on the
      period). `ComputeTransparencyReport` (`App\Actions\Staff`, since
      generating one is a staff/scheduled act, not a public read): a
      reproducible aggregation straight from `compliance_log`, `flags`,
      `appeals`, `screenings`, and the enforcement ledger (FR-006-22 — no
      number stored that doesn't reconcile back to those tables; calling
      it twice for the same period `updateOrCreate`s the same row)
      covering every figure FR-006-21(e) lists: reviews submitted/
      published (the period's *submission* cohort) and removed by reason
      (staff *actions taken* in the period, from the compliance log —
      the only honest reading once the source is the log rather than the
      review), the automated-vs-flagged detection split, flags received/
      handled by reporter type plus the median hours to action, appeal
      decided/overturned counts and rate, Consumer Warnings issued,
      accounts blocked, and legal/government requests — a manually-
      entered `int $legalRequestsCount` parameter with no source table,
      an honest gap rather than building a request-tracking feature this
      spec never asked for. `ListMethodologyLinks` (`App\Actions\
      Moderation`) covers (c) and (d) together: `review_score`/
      `trust_index` are `null` (008/009 don't exist), `verification`
      points at 004's own public attestation check page — 004's own
      tasks.md already noted there's no separate methodology page, so
      this doesn't invent one. `ListTransparencyReports` (same namespace,
      same public/no-auth shape as `ListGuidelineVersions`) covers (e).
      (f) is exactly the scope tasks.md's own sketch named —
      `Business::scopeUnderConsumerWarning()` — with no action wrapper,
      since a one-line scope needs none. FR-006-21, FR-006-22.
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
