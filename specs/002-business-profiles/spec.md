# Spec 002: Business Profiles, Claiming & Categories

**Status:** Draft · **Phase:** 1 (MVP) · **Depends on:** 001

## 1. Goal

Give every business a public profile page where people read and write reviews about it, whether or not the business has signed up. Let real owners prove they control a business and claim its profile. Organise businesses into categories. Each category defines what gets asked in a review (context-aware prompts) and lets the Platform launch one vertical at a time.

## 2. User Scenarios

1. **Consumer adds a missing business.** When a consumer searches for "skyhop-travel.com" and gets no result, they can add the business by entering its website domain (or name + country when it has no website). An **unclaimed** profile is created and they go straight into writing a review.
2. **Business claims its profile.** When a business user searches for their domain and clicks "Claim this profile", they prove control by receiving a code at an address on that domain (e.g., `jane@skyhop-travel.com`), or by adding a DNS TXT record or HTML meta tag. The profile then shows a **Claimed** label with the claim date, and the user becomes its Owner.
3. **Business edits its profile.** An Admin updates the description, logo, contact details, and categories. Visitors see the changes right away, except category changes, which go to staff review.
4. **Multi-location business.** An Owner adds 12 branch locations with addresses. Consumers can then review a specific branch, and the profile shows both a company-wide Review Score and per-location scores.
5. **Seeded airline.** On launch day a traveller searches "Ryan…" and finds a seeded, **unclaimed** airline profile created by staff before launch. They can review it straight away.
6. **Vertical launch.** Staff enable the "Travel" category tree (Airlines, Travel Agencies & OTAs, Airports) for public listing. Only categories marked **launched** appear in navigation and category rankings. Businesses in categories that haven't launched can still be found by search.

## 3. Functional Requirements

### Profiles
- **FR-002-01** A Business must have: unique ID, display name, unique slug, primary domain (optional for businesses without a website), additional domains, country, and status (`unclaimed`, `claimed`, `suspended`, `closed`).
- **FR-002-02** The public profile URL must be `/business/{slug}`. When the slug changes, the old slug must redirect permanently.
- **FR-002-03** Editable profile fields: logo (JPEG/PNG/WebP, ≤ 2 MB, at least 200×200), description (≤ 1,500 characters), website, email, phone, address, social links, categories (1 primary + up to 5 secondary), and opening hours per location.
- **FR-002-04** The profile page must show: name, logo, **Claimed/Unclaimed** label (claimed shows the claim date), Review Score + stars + word label and Trust Index (008), total review count, rating distribution, AI summary (011, when available), company details, reply-behaviour signals (007), case statistics (010), the review list (003), "Similar businesses" (009), and any Consumer Warning (006).
- **FR-002-05** Unclaimed profiles must clearly say "This business has not claimed its profile" and must still accept reviews.
- **FR-002-06** *(Removed 2026-09-25: no change log, constitution §5.1.)*
- **FR-002-07** Name, domain, or primary category changes on a **claimed** profile must go through a staff approval queue before publishing. All other fields publish immediately and are checked afterwards by automated content checks.

### Consumer-created businesses
- **FR-002-08** A signed-in consumer may create an unclaimed Business by domain, or by name + country + city when there is no website.
- **FR-002-09** Before creating a Business, the system must check for duplicates by normalised domain (lowercase, `www.` removed, IDN converted to punycode) and by fuzzy name + city match. If a match is found, it must suggest the existing profile.
- **FR-002-10** Newly created unclaimed Businesses must go through an automated check (domain resolves, not on the blocklist, not adult/illegal) before they appear in search. Reviews can be written, but the profile stays `pending` until the check passes.

### Claiming
- **FR-002-11** Claim methods: (a) verification code emailed to an address on the Business's domain, (b) DNS TXT record, (c) HTML meta tag or file on the domain root, (d) manual staff review with documents (for businesses without a domain).
- **FR-002-12** Free email domains (e.g., gmail.com) cannot be used for method (a).
- **FR-002-13** A claim with methods (a)–(c) must complete automatically once the check succeeds. The claiming user becomes **Owner**.
- **FR-002-14** If a profile is already claimed, a new claimant can request access. Existing Owners are notified and have 7 days to approve or reject. With no response, the request goes to staff review.
- **FR-002-15** Claiming a profile must **not** change any existing review, score, or label.

### Locations & products
- **FR-002-16** A claimed Business may define Locations (name, address, geo-coordinates, optional phone and hours). Each location has its own sub-page and its own Review Score.
- **FR-002-17** *(Deferred, not Phase 1.)* Product catalogues and product reviews are deferred until the Platform expands beyond travel. The data model must allow a Product entity to be added later without migrating existing reviews.

### Categories & context-aware prompts
- **FR-002-18** Categories form a tree up to 3 levels deep (e.g., Travel → Air Travel → Airlines). Each category has a slug, localised names, and a `launched` flag.
- **FR-002-19** Each category may define a **question set** for context-aware prompts: 0–8 attributes, each with a key, a localised label, a type (`rating_1_5`, `yes_no`, `single_choice`, `short_text ≤ 200 chars`), and a `required` flag. Leaf categories inherit questions from their parents.
  - *Example (Hotels):* cleanliness (rating), check-in speed (rating), noise level (rating), would stay again (yes/no).
  - *Example (Airlines):* on-time performance, baggage handling, seat comfort, crew, refund handling.
- **FR-002-20** Question-set changes must be versioned. Stored answers keep the version they were answered under, and answers to retired questions stay visible on existing reviews.
- **FR-002-21** Only staff may create or edit categories, question sets, and topic lists (see "Industries" below for who may do what).
- **FR-002-22** Categories marked `launched = false` must not appear in navigation or category rankings, but their businesses can still be found by search.
- **FR-002-23** Launch categories and their question sets and topic lists are defined in [`travel-content.md`](travel-content.md) (draft, needs client approval).

### Industries (staff-managed, no code change needed)
- **FR-002-28** An **industry** is a top-level category. Staff must be able to create, edit, launch, pause, and re-launch industries, and to add sub-categories, from the staff console **without a code change or deployment**. Changes take effect on public pages within **5 minutes**.
- **FR-002-29** The Platform ships with a **full top-level taxonomy** (e.g., Travel, Finance & Insurance, Retail & E-commerce, Technology & Software, Home & Local Services, Health & Wellbeing, Education, Automotive, Telecoms & Utilities, Food & Hospitality, Public & Non-profit) plus a system category **"Other / Uncategorised"**. At launch only **Travel** is `launched`. All others exist in `draft` so businesses can already be listed under them.
- **FR-002-30** Industry lifecycle: `draft → launched → paused → launched`.
  - `draft` and `paused`: hidden from navigation, category pages, and "Best in…" rankings (FR-002-22). Businesses in them can still be found by search, reviewed, claimed, and scored.
  - `launched`: shown in navigation, category pages, rankings, and benchmarks (015).
  - Changing state never changes any review, score, label, or business data.
- **FR-002-31** Before an industry can move to `launched`, the console must show a **readiness checklist**. **Blocking** items: slug and localised name for every active locale, icon, and at least one sub-category. **Warning-only** items: question set (FR-002-19; without one, reviewers get the generic form), topic list (011), default invitation delay (005), number of businesses listed, and whether an industry benchmark is possible (015 FR-015-07). Staff must acknowledge the warnings to launch.
- **FR-002-32** Staff must be able to **preview** an industry's public pages (navigation entry, category page, ranking, review form with its question set) before launching.
- **FR-002-33** Permissions: staff `Admin` may create, launch, pause, merge, and move industries and categories. `Senior Moderator` may edit question sets, topic lists, and category names/descriptions. Other staff roles have read-only access. Launches and pauses are listed on the Transparency Center changelog (006).
- **FR-002-34** Staff may **seed** businesses for any industry using the rules and safeguards of FR-002-24 (public or licensed sources only, source and batch recorded, automated checks).
- **FR-002-35** Every Business must have a **primary category**. When a consumer creates a Business (FR-002-08), they pick from **all** categories, `draft` ones included, or choose "Other / Uncategorised". Uncategorised businesses go to a staff categorisation queue, with a target of 5 business days. They are fully reviewable in the meantime.
- **FR-002-36** A category that still contains businesses cannot be deleted. Staff must first **move** or **merge** it into another category. Moving businesses between categories re-runs rankings and benchmarks but never changes Review Scores or Trust Indexes (which don't depend on category: the same formula and prior apply to every business, 008 FR-008-03).
- **FR-002-37** Score formulas, Trust Index weights, and moderation rules are the **same in every industry** (constitution P2). Opening an industry never requires, and never allows, per-industry score changes through the console.

### Seeding, size flag, mentions
- **FR-002-24** **Pre-launch seeding:** staff import about 300–500 **airlines and large travel agencies/OTAs operating in the UK and EU** as `unclaimed` profiles (name, domain, primary category, country, and optionally logo if licensed). Only public or licensed sources may be used, **never data scraped from other review platforms** (constitution L1). Every seeded profile records its data source and import batch. Seeded profiles go through the same automated checks as FR-002-10.
- **FR-002-25** Each Business has an **employee size band** (`<50`, `50–249`, `250–999`, `1000+`, `unknown`). Staff set it from public data or accept the business's declaration (checked by staff). It is used by 013 (insider reviews allowed only for ≥ 50).
- **FR-002-26** *(Removed 2026-09-25: operator label, constitution P11 removed.)*
- **FR-002-27** Profiles have a **"Mentioned in reviews"** section listing published reviews of other businesses that tag this Business (003 FR-003-31). Mentions are shown separately from the Business's own reviews and never count toward its scores.

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Domain entered with protocol/path (`https://www.x.com/about`) | Normalise to `x.com`. |
| Subdomain businesses (`shop.brand.com` vs `brand.com`) | Treated as different businesses unless the Owner links them as additional domains, which staff approve. |
| Marketplace sellers (e.g., stores on a marketplace domain) | Must be created by name + marketplace store URL. They cannot claim the marketplace domain itself. |
| Empty description | Allowed. The profile shows "No description provided". |
| Description with a URL to a different domain, phone numbers in the name, or ALL-CAPS name | Rejected by validation. |
| Logo is animated or has transparency | Animated: rejected. Transparent: accepted. |
| Claim code emailed to a role address at a domain the user doesn't control | The code expires after 30 minutes. Max 5 attempts per claim. |
| DNS TXT record removed after the claim | No automatic unclaim, but re-verification is required before ownership transfer. |
| Staff try to launch an industry with a blocking checklist item missing | Reject. Show the missing items. |
| Staff pause an industry that has active sponsored slots (017) | Slots stop serving right away. Advertisers get a pro-rated credit. |
| Staff pause an industry that businesses show on widgets or in badges | Widgets and scores are unaffected. Only navigation and rankings are hidden. |
| Consumer creates a business and skips the category step | Not possible. "Other / Uncategorised" is the explicit fallback. |
| A category is renamed | Its slug stays the same, or the old slug permanently redirects if staff change it. |
| Two industries merged | Businesses, sub-categories, and question sets move to the surviving one. Question-set answers keep their original version (FR-002-20). The old slug redirects. |
| Two unclaimed duplicates discovered later | Staff merge them. Reviews move to the surviving profile, the other slug redirects, and scores are recalculated. |
| Business closes permanently | The Owner or staff set status `closed`. The profile stays readable with a "Closed" banner. New reviews are blocked 12 months after the closure date. |
| Business disputes its employee size band | It submits evidence. Staff decide within 7 days. Until then, the current band stays. |
| Seeded profile for a business that no longer exists | Staff mark it `closed` (see closure rule). It is not deleted, so existing reviews stay. |
| Unauthorized: an Analyst edits the profile | 403. |
| Business tries to remove the Claimed label or hide its review count | Not possible. There is no such setting. |

## 5. Out of Scope

- Business-paid control over what appears on the profile beyond the fields listed (e.g., hiding the rating distribution).
- Automatically importing business listings from third-party directories or scraping. Seed data, if any, needs its own spec.
- Franchise/parent-company hierarchies beyond multi-domain and locations.
- Map-based search UI (search is in 009).
- Business verification of legal registration (company number checks) beyond claiming. Could be added later as a "Registered company" badge.

## 6. Acceptance Criteria

- [~] A consumer can create an unclaimed business and review it. Duplicate detection prevents obvious duplicates (test fixtures cover domain variants and name+city matches). *Creation and duplicate detection (domain and fuzzy name+city variants) are fully built and tested — "review it" is spec 003, not built yet. Revisit this box when spec 003 ships.*
- [x] All four claim methods work. Free email domains are rejected for email claims.
- [~] Claiming changes no review, score, or label (checked with a before/after snapshot test). *A before/after snapshot test covers every profile field that exists today (`ClaimingInvarianceTest`) — Review/score models don't exist yet (specs 003/008) to literally snapshot. Revisit when they ship.*
- [x] Profile page shows every element in FR-002-04 that has data. Everything a later spec owns is an explicit "coming soon" placeholder, never a faked value.
- [x] Sensitive field changes go to staff approval. Other changes publish immediately.
- [~] Locations have separate scores. *Locations exist with their own public sub-page (FR-002-16) — a location's own Review Score is spec 008, not built yet. Revisit when it ships.*
- [x] The seed import creates unclaimed profiles with source and batch recorded, and seeded profiles pass automated checks.
- [x] The "Mentioned in reviews" section shows tagged reviews without affecting scores (spec 003 T12 wired the tag query; no Business field is written by tagging, so there is nothing for it to affect).
- [x] Category question sets are versioned and inherited. Non-launched categories are hidden from navigation.
- [~] Staff can create, preview, launch, pause, and re-launch an industry from the console, and public pages update within 5 minutes with no deployment (E2E test). *Every action is built and tested at the action/HTTP level — there is no staff console UI yet (consistent with the rest of this spec), and no public page reads category state yet (that's search, spec 009) to prove the 5-minute budget against end to end. What's tested instead: a state change is a plain, uncached column write, reflected immediately by anything that queries it (`Category::visibleInNavigation()`). Revisit this box once a console UI and spec 009 both exist.*
- [x] The readiness checklist blocks launch on missing blocking items and requires warnings to be acknowledged.
- [~] Industry state changes and category moves leave every Review Score and Trust Index unchanged (invariance test). Only rankings and benchmarks change. *Invariance tests prove a business's own fields are untouched by a category move or an industry launch/pause — Review Score and Trust Index don't exist yet (spec 008) to literally include in the snapshot. Revisit when it ships.*
- [x] Every Business has a primary category. Uncategorised businesses appear in the staff queue. Both real creation paths (a consumer adding one, a staff import) always assign a category, defaulting to "Other / Uncategorised"; that fallback is a real, tested staff queue (`GET /staff/businesses/uncategorised`), not just an internal label.
- [x] Industry permissions follow FR-002-33.

## 7. Dependencies & Open Questions

- **Decided (2026-09-24):** launch vertical is **travel** (airlines, agencies/OTAs, airports) in **UK + EU**. Question sets and topics are drafted by us in `travel-content.md` and approved by the client. The directory is seeded with airlines and major agencies (FR-002-24). Product reviews are deferred.
- **Q1:** Which public or licensed data source will be used for the seed list (e.g., a licensed airline registry vs. a manually compiled list)? To be chosen in plan.md. It must satisfy FR-002-24.
