# Spec 002: Business Profiles, Claiming & Categories

**Status:** Draft · **Phase:** 1 (MVP) · **Depends on:** 001

## 1. Goal

Give every business a public profile page where people read and write reviews about it, whether or not the business has signed up. Let real owners prove they control a business and claim its profile. Organise businesses into categories. Each category defines what gets asked in a review (context-aware prompts) and lets the Platform launch one vertical at a time.

## 2. User Scenarios

1. **Consumer adds a missing business.** When a consumer searches for "skyhop-travel.com" and gets no result, they can add the business by entering its website domain (or name + country when it has no website). An **unclaimed** profile is created and they go straight into writing a review.
2. **Business claims its profile.** When a business user searches for their domain and clicks "Claim this profile", they prove control by receiving a code at an address on that domain (e.g., `jane@skyhop-travel.com`), or by adding a DNS TXT record or HTML meta tag. The profile then shows a **Claimed** label with the claim date, and the user becomes its Owner.
3. **Business edits its profile.** An Admin updates the description, logo, contact details, and categories. Visitors see the changes right away, except category changes, which go to staff review.
4. **Multi-location business.** An Owner adds 12 branch locations with addresses. Consumers can then review a specific branch, and the profile shows both a company-wide Review Score and per-location scores.
5. **Product catalogue.** A business on a paid plan uploads a product feed (SKU, name, image, URL, GTIN). Consumers invited after a purchase can review those products (spec 003).
6. **Vertical launch.** Staff enable the "Air Travel" category tree for public listing. Only categories marked **launched** appear in navigation and category rankings. Businesses in categories that haven't launched can still be found by search.

## 3. Functional Requirements

### Profiles
- **FR-002-01** A Business must have: unique ID, display name, unique slug, primary domain (optional for businesses without a website), additional domains, country, and status (`unclaimed`, `claimed`, `suspended`, `closed`).
- **FR-002-02** The public profile URL must be `/business/{slug}`. When the slug changes, the old slug must redirect permanently.
- **FR-002-03** Editable profile fields: logo (JPEG/PNG/WebP, ≤ 2 MB, at least 200×200), description (≤ 1,500 characters), website, email, phone, address, social links, categories (1 primary + up to 5 secondary), and opening hours per location.
- **FR-002-04** The profile page must show: name, logo, **Claimed/Unclaimed** label (claimed shows the claim date), Review Score + stars + word label and Trust Index (008), total review count, rating distribution, AI summary (011, when available), company details, reply-behaviour signals (007), case statistics (010), the review list (003), "Similar businesses" (009), and any Consumer Warning (006).
- **FR-002-05** Unclaimed profiles must clearly say "This business has not claimed its profile" and must still accept reviews.
- **FR-002-06** Profile changes made by businesses must be audit-logged (field, old value, new value, user).
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
- **FR-002-17** Businesses whose plan includes product reviews (017) may manage Products by manual entry, CSV upload, or feed URL (fetched every 24 hours). Fields: SKU (unique per business), name, URL, image URL, GTIN/MPN (optional), brand, and active flag.

### Categories & context-aware prompts
- **FR-002-18** Categories form a tree up to 3 levels deep (e.g., Travel → Air Travel → Airlines). Each category has a slug, localised names, and a `launched` flag.
- **FR-002-19** Each category may define a **question set** for context-aware prompts: 0–8 attributes, each with a key, a localised label, a type (`rating_1_5`, `yes_no`, `single_choice`, `short_text ≤ 200 chars`), and a `required` flag. Leaf categories inherit questions from their parents.
  - *Example (Hotels):* cleanliness (rating), check-in speed (rating), noise level (rating), would stay again (yes/no).
  - *Example (Airlines):* on-time performance, baggage handling, seat comfort, crew, refund handling.
- **FR-002-20** Question-set changes must be versioned. Stored answers keep the version they were answered under, and answers to retired questions stay visible on existing reviews.
- **FR-002-21** Only staff may create or edit categories and question sets.
- **FR-002-22** Categories marked `launched = false` must not appear in navigation or category rankings, but their businesses can still be found by search.

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Domain entered with protocol/path (`https://www.x.com/about`) | Normalise to `x.com`. |
| Subdomain businesses (`shop.brand.com` vs `brand.com`) | Treated as different businesses unless the Owner links them as additional domains, which staff approve. |
| Marketplace sellers (e.g., stores on a marketplace domain) | Must be created by name + marketplace store URL. They cannot claim the marketplace domain itself. |
| Empty description | Allowed. The profile shows "No description provided". |
| Description with a URL to a different domain, phone numbers in the name, or ALL-CAPS name | Rejected by validation. |
| Logo is animated or has transparency | Animated: rejected. Transparent: accepted. |
| Claim code emailed to a role address at a domain the user doesn't control | The code expires after 30 minutes. Max 5 attempts per claim. The claim is audit-logged. |
| DNS TXT record removed after the claim | No automatic unclaim, but re-verification is required before ownership transfer. |
| Two unclaimed duplicates discovered later | Staff merge them. Reviews move to the surviving profile, the other slug redirects, and scores are recalculated. |
| Business closes permanently | The Owner or staff set status `closed`. The profile stays readable with a "Closed" banner. New reviews are blocked 12 months after the closure date. |
| Product feed > 100,000 SKUs or malformed rows | Reject rows that fail validation, keep processing the rest, and send the Owner a report of rejected rows. Hard cap: 500,000 active SKUs per Business. |
| Unauthorized: an Analyst edits the profile | 403. |
| Business tries to remove the Claimed label or hide its review count | Not possible. There is no such setting. |

## 5. Out of Scope

- Business-paid control over what appears on the profile beyond the fields listed (e.g., hiding the rating distribution).
- Automatically importing business listings from third-party directories or scraping. Seed data, if any, needs its own spec.
- Franchise/parent-company hierarchies beyond multi-domain and locations.
- Map-based search UI (search is in 009).
- Business verification of legal registration (company number checks) beyond claiming. Could be added later as a "Registered company" badge.

## 6. Acceptance Criteria

- [ ] A consumer can create an unclaimed business and review it. Duplicate detection prevents obvious duplicates (test fixtures cover domain variants and name+city matches).
- [ ] All four claim methods work. Free email domains are rejected for email claims.
- [ ] Claiming changes no review, score, or label (checked with a before/after snapshot test).
- [ ] Profile page shows every element in FR-002-04 that has data.
- [ ] Sensitive field changes go to staff approval. Other changes publish immediately and are audit-logged.
- [ ] Locations have separate scores, and product feeds import with a per-row error report.
- [ ] Category question sets are versioned and inherited. Non-launched categories are hidden from navigation.

## 7. Dependencies & Open Questions

- **Q1:** Which **launch vertical** do we start with? The client's examples (airlines, agencies, booking references, refunds) point to **travel**. This needs confirmation.
- **Q2:** Who writes the initial question sets for the launch vertical, the client or us?
- **Q3:** Do we seed the directory with businesses before launch? If yes, that needs a separate spec covering data sources and licensing.
