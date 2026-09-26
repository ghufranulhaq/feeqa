# Spec 002 Tasks: Business Profiles, Claiming & Categories

Each task is implemented, tested, and committed on its own before the next
one starts (constitution §2, §8). Task order follows dependency, not FR
number. Spec 001 left a minimal `businesses` stand-in (id, name) — this
spec owns and extends it.

- [x] **T1. Industries & categories schema.** `categories` table (id,
      parent_id, slug, name, `launched` bool for pre-existing leaf-category
      behaviour, `state` enum `draft/launched/paused` for the top-level
      industry lifecycle, `icon`, timestamps). Self-referencing tree, max 3
      levels enforced in code. Seed the full top-level taxonomy from
      plan.md §"Demo seeders" (Travel, Finance & Insurance, Retail &
      E-commerce, Technology & Software, Home & Local Services, Health &
      Wellbeing, Education, Automotive, Telecoms & Utilities, Food &
      Hospitality, Public & Non-profit, "Other / Uncategorised") plus the
      Travel sub-tree and the two `launched = false` sub-categories from
      `travel-content.md`. FR-002-18, FR-002-29.
- [x] **T2. Business schema extension.** Extend `businesses`: slug
      (unique), primary_domain, additional_domains (json), country, status
      enum (`unclaimed/pending/claimed/suspended/closed`), claimed_at,
      description, website, email, phone, address (json), social_links
      (json), employee_size_band, logo_path, primary_category_id (FK,
      nullable until T4 backfills it), data_source, import_batch. Update
      `Business` model (casts, fillable) and add a `BusinessFactory`.
      FR-002-01, FR-002-25.
- [x] **T3. Public business profile page (read-only).** Route
      `/business/{slug}`, controller, Inertia page rendering the FR-002-04
      fields that exist today (name, logo, Claimed/Unclaimed label,
      description, contact details, categories); everything owned by a
      later spec (scores, reviews, cases, similar businesses) shows an
      explicit placeholder, not a fake value. Old-slug redirect on slug
      change. FR-002-01, FR-002-02, FR-002-04, FR-002-05.
- [x] **T4. Consumer creates an unclaimed business.** Domain normalisation
      (protocol/path stripped, `www.` removed, IDN → punycode) or name +
      country + city when there's no website; duplicate detection by
      normalised domain and fuzzy name+city match, suggesting the existing
      profile instead of creating one; category picker (including `draft`
      industries and "Other / Uncategorised"); automated pre-listing check
      (domain resolves, not blocklisted) gates search visibility only —
      the business is reviewable immediately. FR-002-08, FR-002-09,
      FR-002-10, FR-002-35, edge cases (domain normalisation, subdomains,
      marketplace sellers).
- [x] **T5. Profile editing + sensitive-field approval queue.** Owner/Admin
      edit action for FR-002-03 fields; name/domain/primary-category
      changes on a claimed profile go to a staff approval queue instead of
      publishing immediately; validation edge cases (empty description
      allowed, URL/phone-in-name/ALL-CAPS rejected, logo type/size/
      animated-vs-transparent). FR-002-03, FR-002-07, edge cases table.
- [x] **T6. Claiming.** All four methods (email code on the domain with
      free-domain-provider block + 30 min expiry + 5-attempt cap, DNS TXT
      record, HTML meta tag/file, manual staff review with documents);
      automatic claim on success for (a)-(c); claiming never touches any
      review/score/label (a T2-era business has none yet, but the guard
      goes in now so later specs can't regress it); re-claim request flow
      with a 7-day Owner response window falling through to staff review.
      FR-002-11 through FR-002-15.
- [x] **T7. Locations.** `locations` table (business_id, name, address,
      lat/lng, phone, hours) with its own sub-page; multi-location
      businesses get a company-wide profile plus per-location pages (their
      own Review Score is spec 008 — placeholder for now, same pattern as
      T3). FR-002-16.
- [x] **T8. Category question sets, versioned.** `category_question_sets`
      (category_id, version, published_at) + `category_questions` (key,
      label, type, required, order); parent-to-leaf inheritance; seed the
      Travel/Airlines/Agencies/Airports sets from `travel-content.md`.
      Nothing consumes a question set yet (review submission is spec 003) —
      this task only builds and tests the versioned data model and the
      inheritance resolution. FR-002-19, FR-002-20.
- [x] **T9. Industries staff console: CRUD + lifecycle.** Staff pages to
      create/edit an industry or category, and to move it through
      `draft → launched → paused → launched`; state changes take effect on
      public pages (nav, category pages, rankings placeholder) within 5
      minutes with no deploy; permissions per FR-002-33 (Admin: create/
      launch/pause/merge/move; Senior Moderator: edit question sets/topic
      lists/names; others read-only); launches/pauses recorded on the
      Transparency Center changelog (006 — write the compliance_log entry
      now, the public changelog page lands with 006). FR-002-28,
      FR-002-30, FR-002-33.
- [x] **T10. Readiness checklist + preview.** Blocking items (slug +
      localised name for every active locale, icon, ≥ 1 sub-category) and
      warning-only items (question set, topic list, invitation delay,
      business count, benchmark feasibility) gate the `launched`
      transition; staff preview of an industry's public pages before
      launch; edge case: launching with a missing blocking item is
      rejected and lists what's missing. FR-002-31, FR-002-32, edge cases.
- [x] **T11. Seeding pipeline + size-band dispute.** A staff import action
      that creates `unclaimed` Businesses in bulk (name, domain, primary
      category, country, optional licensed logo), records source + batch
      (FR-002-24, reusing T4's duplicate/automated checks), and an
      employee-size-band dispute flow (business submits evidence, staff
      decide within 7 days, current band holds until then). FR-002-24,
      FR-002-25 dispute case, FR-002-34.
- [x] **T12. Category move/merge + closure.** Staff move-businesses and
      merge-categories actions (question-set answers keep their original
      version; old slug redirects; a category with businesses can't be
      deleted outright); business closure (`closed` status, "Closed"
      banner, new reviews blocked 12 months after closure); invariance
      test proving none of this changes a Review Score or Trust Index.
      FR-002-36, FR-002-37, edge cases (industries merged, seeded profile
      no longer exists, business closes).
- [ ] **T13. "Mentioned in reviews" placeholder.** The profile section and
      its data hook exist and are tested to show nothing and affect no
      score today — the actual tagging happens when a review is submitted
      (spec 003 FR-003-31), same "built now, filled later" pattern as
      spec 001 T5's review count. FR-002-27.
- [ ] **T14. Docs pass.** README, `docs/user-guides/`,
      `docs/system-overview/` updated for everything above (constitution
      §8 rule 9); `make docs-check` passes.
- [ ] **T15. Acceptance sweep.** Re-check every box in spec.md §6 against
      what's actually implemented; close any gap the sweep finds; `make ci`
      green.
