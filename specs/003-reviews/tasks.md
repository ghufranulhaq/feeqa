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
- [x] **T5. Draft autosave.** `review_drafts` table (user_id, business_id,
      location_id nullable, payload json) + `SaveReviewDraft`/
      `RestoreReviewDraft` actions and a `ReviewDraftController` (POST/GET
      `businesses/{business}/review-draft`, `auth` middleware) for
      signed-in users; one draft per user per Business (or per
      Business+Location). No review submission form reaches these
      endpoints yet — same "real endpoint, not linked from anywhere"
      situation as spec 002's claim/location endpoints. Device-local
      autosave (localStorage) is a frontend concern with no server test.
      The unauthenticated-submission edge case ("Draft kept. Sign-in
      required before submit.") is satisfied by the `auth` middleware
      itself (proven by an HTTP test) plus frontend localStorage — no
      further server behaviour was needed. Clearing a draft after a real
      submission is deferred to whichever task wires the submission HTTP
      endpoint (not built yet). FR-003-10, edge case row.
- [x] **T6. Publication & profile display.** Public review list replaces
      the business profile's "coming soon" reviews placeholder; each review
      gets a permanent URL (`ReviewController@show`, `businesses.reviews.show`);
      review card fields per FR-003-26 that exist today (author name/
      avatar/country/published review count, rating, title, text, dates,
      source label, question answers), built as a shared `ReviewCard`
      support class + React component — reply/case/Verified Experience/
      Useful count stay "coming soon" (007/010/004/T8). Wires spec 001 T5's
      hardcoded `reviews_count`/`reviews` on the reviewer profile to real
      data (capped at 50, not fully paginated — that page isn't one of
      T9's "Business/Location" lists). Reviews by an author with a pending
      account deletion are excluded from public view (`Review::
      scopePubliclyVisible()`/`isPubliclyVisible()`). Business profile list
      paginated at 20/page (under FR-003-29's 50 cap); sorting/filtering
      beyond "most recent" is T9. FR-003-26, FR-003-29, ties to FR-001-07.
- [x] **T7. Source labels.** `SourceLabel` is set by the system only, never
      user-editable — every write path is tested to prove it (the only
      write path is `SubmitReview`, which hardcodes `Organic` and ignores
      any `source_label` smuggled into its input). Every review is
      `Organic` today because Invited/Redirected both require the
      invitation/redirect-link system (005), not built yet — documented as
      pending. Source label shown on every review card with a tooltip
      explaining what it means (FR-003-16's Verified Experience badge stays
      "coming soon", 004). FR-003-14, FR-003-15, FR-003-16.
- [x] **T8. Useful votes.** `ToggleUsefulVote` action: any signed-in user
      except the author, tapping again removes the vote (unique
      `review_useful_votes` pair from T1 enforces one vote per reader).
      Excluding fraud-flagged accounts' votes (edge case) is noted as
      pending spec 006 (no fraud detection exists yet to flag them). A
      real endpoint (`reviews.useful-vote.store`) exists; the review card
      now shows the real count, but no button reaches the endpoint yet —
      same "endpoint before UI" situation as T5's drafts. FR-003-27.
- [x] **T9. Filtering, sorting, pagination.** `ListReviewsForProfile`
      action, shared by the Business profile and (newly wired) Location
      profile: sort by *Most recent* (default) or *Most useful*; filter by
      star rating (multi-select), source label, has-update, language, date
      range, and location (by slug). `verified_experience`, `has_reply`,
      and `has_case` are accepted in the query string and silently ignored
      — documented as pending 004/007/010, since there's nothing to filter
      on yet. Page size stays at 20/page (T6's choice, under FR-003-29's 50
      cap). A shared `ReviewFilters` React component gives both profile
      pages real filter/sort controls. Fixed a latent `LocationFactory` bug
      along the way: it stored `slug` as a `Stringable` instead of a plain
      string, which `route()`'s query-string builder silently drops.
      FR-003-28, FR-003-29.
- [x] **T10. Edit & delete.** `UpdateReview`/`DeleteReview` — author only
      (403 for anyone else, including a business member — tested via API,
      both actions and real `PATCH`/`DELETE reviews/{review}` HTTP
      endpoints); an edit is re-screened through T2 (via the same
      `ScreenReviewSubmission`) and shows "Edited" with a date on the
      review card; a delete soft-deletes the review (T1's `SoftDeletes`),
      removing it from public view immediately (the default scope already
      excludes trashed rows everywhere). Field guards (rating/title/text/
      date/reference-number) extracted from `SubmitReview` into a shared
      `ReviewFieldGuards` class so submission and editing enforce identical
      limits. FR-003-23 through FR-003-25, edge case (business user 403).
- [x] **T11. Lifecycle updates.** `LifecycleMilestone::windowOpensAt/
      windowClosesAt` do the window math from `published_at` (opens on
      the milestone day, closes when the next opens; the 1-year window
      stays open 90 days). `SubmitLifecycleUpdate` (author only, inside
      its window, 1–5 stars, 20–2,000 characters, optional answers,
      re-screened through T2) rejects a second update for an already-used
      milestone, a submission outside every window ("Your next update
      opens on <date>"), and one on a deleted/unpublished review. Current
      rating (`Review::currentRating()`) = latest published update's
      rating, or the original; `DurabilitySignal` is derived and stored
      after every published update. A new `SendReviewLifecycleReminders`
      daily command emails + in-app-notifies (new `notifications` table)
      the author when a window opens for the first time, tracked by a new
      `review_lifecycle_reminders` table so a re-run never double-sends;
      a new `users.lifecycle_reminders_opted_out_at` column lets an
      author opt out. The review card now shows the original + updates as
      a dated timeline. Time-travel tests at days 29/30/179/180/364/365/
      455. FR-003-17 through FR-003-22, edge cases (update outside
      window, update on a deleted review).
- [x] **T12. Tagging a second business.** `ReviewFieldGuards::taggedBusiness()`
      rejects a nonexistent business, the reviewed business itself
      (self-tag), and more than one tag in the same submission — shared by
      `SubmitReview` and `UpdateReview` so the rule is identical on
      creation and edit. `Business::mentions()` (spec 002's placeholder)
      now runs a real query: published reviews tagging this Business,
      never added to its own `reviews()` (so nothing about it can feed a
      score — FR-003-32's zero-effect clause needs no extra guard, since
      nothing is written to the tagged Business at all). A new
      `Business::members()` (every role holder, not just Owners) and
      `ReviewTaggedNotification` (mail) notify the tagged business the
      moment a tagged review is actually published — never for a held one
      — with 007's preference rules noted as pending (everyone is
      notified today). On edit, the tag can be added, changed, or removed
      like every other field (an edit fully replaces it, so omitting it
      means "no tag"); a notification only fires when the tag is newly
      set or points at a different business, never on an edit that leaves
      it alone. Consumer-Warning-status businesses are taggable with no
      extra code, since no such flag exists on `Business` yet (006) — the
      guard only checks existence and self-tag, so nothing blocks it;
      noted as pending until 006 adds the flag itself to test against.
      The one-reply-from-the-tagged-business part of FR-003-32 stays
      "coming soon" until 007 exists. Also updated spec 002's own
      acceptance line for this section from "always empty" to done, since
      it's now wired. FR-003-31, FR-003-33, edge cases (self-tag/multiple
      tags, Consumer-Warning business taggable).
- [x] **T13. Score-recalculation hook + invariance.** `App\Actions\Businesses\
      RecalculateBusinessScore` (008's real target) is a deliberate no-op,
      resolved through the container at each call site rather than
      constructor-injected — so every existing caller's constructor stayed
      untouched, and a test can swap it out to observe which Business each
      trigger point actually recalculates for. Called from every trigger
      point built above: T3's `SubmitReview` (every submission, whatever
      the screening outcome), T10's `UpdateReview` and `DeleteReview`
      (every edit and delete), and T11's `SubmitLifecycleUpdate` (every
      lifecycle update) — same "built now, filled later" pattern as spec
      002 T13's mentions hook. Each call site passes the review's own
      business, never a tagged one; an invariance test per trigger point
      proves a tagged review recalculates only the business being
      reviewed, confirming FR-003-32's zero-score-effect clause holds even
      where a tag is present. FR-003-30, FR-003-32's zero-score-effect
      clause.
- [ ] **T14. Docs pass.** README, `docs/user-guides/`,
      `docs/system-overview/` updated for everything above (constitution
      §8 rule 9); `make docs-check` passes.
- [ ] **T15. Acceptance sweep.** Re-check every box in spec.md §6 against
      what's actually built; mark fully-satisfied criteria `[x]` and
      partially-satisfied ones `[~]` with an inline note naming the
      dependent spec, same convention as specs 001/002's own sweeps. Fix
      any real gap the sweep turns up rather than just noting it.
