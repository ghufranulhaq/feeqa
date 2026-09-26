# Spec 003 Tasks: Review Submission & Lifecycle

Each task is implemented, tested, and committed on its own before the next
one starts (constitution §2, §8). Task order follows dependency, not FR
number. Several FRs name capabilities owned by later specs (004
verification, 005 invitations, 006 moderation, 007 replies, 008 scores) —
those are built as honest placeholders ("coming soon" / hook exists, not
wired), same pattern as spec 002.

- [x] **T1. Reviews schema & domain model.** `reviews` table (business_id,
      location_id nullable, reviewer_id, status, source_label, star_rating,
      title, text, date_of_experience, reference_number nullable, language,
      question_set_version nullable, answers json nullable,
      tagged_business_id nullable, confirmed_genuine bool, idempotency_key,
      published_at, edited_at, soft-deletes). `review_lifecycle_updates`
      table (review_id, milestone, star_rating, text, answers json
      nullable, status, published_at). `review_useful_votes` (review_id,
      user_id, unique pair). Domain enums in `app/Domain/Reviews/`:
      `ReviewStatus` (published/held/rejected), `SourceLabel`
      (invited/redirected/organic), `LifecycleMilestone`
      (day_30/month_6/year_1), `DurabilitySignal`
      (improved/unchanged/declined). Models + factories. Product reviews
      (FR-003-01, FR-003-03) stay deferred — the schema doesn't need a
      product column until that spec exists. FR-003-01.
- [x] **T2. Content screening rules + safe rendering.** A pure
      `ScreenReviewSubmission` action (rules only, no external provider —
      not a driver): blocklist words → `rejected` with a reason; text
      near-identical to the same author's text on a *different* business
      within 30 days → `held` (fraud signal, edge case table); otherwise
      `published`. All markup/scripts stripped at input, plain text stored.
      A `linkifyOwnDomain()` helper (escape everything, wrap only exact
      occurrences of the reviewed business's own domain in a real anchor)
      used at read time. FR-003-11, FR-003-12, FR-003-13, edge cases (empty/
      whitespace/length, HTML/script stripping, same text on several
      businesses).
- [x] **T3. Submit review (core).** `SubmitReview` action: field validation
      (star rating 1–5, title 5–100 characters, text 30–5,000 characters
      with emoji counted toward length but >= 30 non-whitespace required,
      reference number <= 64 characters — FR-003-02), membership block
      (FR-003-07, reuses `Business::hasMembership()`), date-of-experience
      window (FR-003-04), confirmation checkbox required (FR-003-06),
      language stored (fixed `en` at this single-locale launch, correctable
      field per FR-003-09), idempotency key prevents double-post, one
      review per business per 30 days (FR-003-08's base rule — the
      different-invitation/verified-transaction exception is a hook noted
      for specs 004/005, not built yet), calls T2's screening, source label
      always `Organic` for now (T7 covers labelling properly). FR-003-02,
      FR-003-04, FR-003-06 through FR-003-09, edge cases (empty/whitespace/
      emoji title and text, rating missing/out of range, idempotency,
      duplicate submission, date rules).
- [x] **T4. Question set answers on submission.** Loads the Business's
      primary category's `effectiveQuestions()` (002 T8); required
      questions must be answered, optional ones may be skipped; answers
      stored with the question set's version; an answer keyed to a
      question not in the set is dropped and logged rather than stored.
      Locations have no category of their own in the current schema, so
      "the Location's category, if set" stays a pending hook — the
      Business's primary category is always used. Also closes a gap left
      open by T3: `SubmitReview` now accepts an optional `Location`
      (validated as belonging to the Business) so location reviews
      (FR-003-01) actually store `location_id`. FR-003-05, FR-003-01,
      edge case row (question-set answer for a question not in the set).
- [ ] **T5. Draft autosave.** `review_drafts` table (user_id, business_id,
      location_id nullable, payload json) + save/restore actions and
      endpoints for signed-in users; device-local autosave is a frontend
      concern (localStorage) with no server test needed. Unauthenticated
      submission keeps the draft and requires sign-in before submit.
      FR-003-10, edge case row.
- [ ] **T6. Publication & profile display.** Public review list replaces
      the business profile's "coming soon" reviews placeholder; each review
      gets a permanent URL; review card fields per FR-003-26 that exist
      today (author name/avatar/country/published review count, rating,
      title, text, dates, question answers) — reply/case/Verified
      Experience stay "coming soon" (007/010/004). Wires spec 001 T5's
      hardcoded `reviews_count`/`reviews` on the reviewer profile to real
      data. Reviews by an author with a pending account deletion are
      excluded from public view. FR-003-26, FR-003-29, ties to FR-001-07.
- [ ] **T7. Source labels.** `SourceLabel` is set by the system only, never
      user-editable — every write path is tested to prove it. Every review
      is `Organic` today because Invited/Redirected both require the
      invitation/redirect-link system (005), not built yet — documented as
      pending. FR-003-14, FR-003-15, FR-003-16.
- [ ] **T8. Useful votes.** `ToggleUsefulVote` action: any signed-in user
      except the author, tapping again removes the vote. Excluding
      fraud-flagged accounts' votes (edge case) is noted as pending spec
      006 (no fraud detection exists yet to flag them). FR-003-27.
- [ ] **T9. Filtering, sorting, pagination.** A review-listing query action
      for a Business/Location: sort by *Most recent* (default) or *Most
      useful*; filter by star rating (multi-select), source label,
      has-update, language, date range, location. Filters that name a
      not-yet-built concept (Verified Experience, has-reply, has-case)
      are accepted but have nothing to filter on yet — documented as
      pending 004/007/010. Page size capped at 50. FR-003-28, FR-003-29.
- [ ] **T10. Edit & delete.** `UpdateReview`/`DeleteReview` — author only
      (403 for anyone else, including a business member — tested via API);
      an edit is re-screened through T2 and shows "Edited" with a date; a
      delete soft-deletes the review, removing it from public view
      immediately. FR-003-23 through FR-003-25, edge case (business user
      403).
- [ ] **T11. Lifecycle updates.** Milestone window math from `published_at`
      (opens on the milestone day, closes when the next opens; the 1-year
      window stays open 90 days); `SubmitLifecycleUpdate` (author only,
      inside its window, 1–5 stars, 20–2,000 characters, optional answers,
      re-screened); current rating = latest published update's rating, or
      the original; derived `DurabilitySignal`; one reminder
      (email + in-app, opt-out respected) dispatched when a window opens.
      Time-travel tests at days 29/30/179/180/364/365/455. FR-003-17
      through FR-003-22, edge cases (update outside window, update on a
      deleted review).
- [ ] **T12. Tagging a second business.** Tag validation (must exist, can't
      be the reviewed business, at most one — reject otherwise); wires
      spec 002's `Business::mentions()` placeholder to a real query;
      notification to the tagged business's members (007's preference
      rules are noted as pending — everyone is notified for now); the tag
      can be added/changed/removed on edit. The one-reply-from-the-tagged-
      business part of FR-003-32 stays "coming soon" until 007 exists.
      FR-003-31, FR-003-33, edge cases (self-tag/multiple tags,
      Consumer-Warning business taggable).
- [ ] **T13. Score-recalculation hook + invariance.** A `RecalculateBusinessScore`
      no-op action (008's real target), called from every trigger point
      built above (T3 publish, T10 edit/delete, T11 update) — same
      "built now, filled later" pattern as spec 002's mentions hook.
      Invariance test proving a tag never triggers a recalculation for the
      *tagged* business. FR-003-30, FR-003-32's zero-score-effect clause.
- [ ] **T14. Docs pass.** README, `docs/user-guides/`,
      `docs/system-overview/` updated for everything above (constitution
      §8 rule 9); `make docs-check` passes.
- [ ] **T15. Acceptance sweep.** Re-check every box in spec.md §6 against
      what's actually built; mark fully-satisfied criteria `[x]` and
      partially-satisfied ones `[~]` with an inline note naming the
      dependent spec, same convention as specs 001/002's own sweeps. Fix
      any real gap the sweep turns up rather than just noting it.
