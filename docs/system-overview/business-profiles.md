# Business Profiles, Claiming & Categories

What the platform does today for spec [002](../../specs/002-business-profiles/spec.md).
This is a plain-English description of behaviour, not an implementation
guide — see the spec and `specs/plan.md` for the "why" and "how". Only a
first slice is built so far; check the spec's `tasks.md` for what's done.

## Categories and industries

Businesses sit in a tree of categories up to 3 levels deep. A top-level
category is an "industry" (e.g. Travel) and has its own lifecycle —
`draft`, `launched`, or `paused` — separate from the plain launched/not
flag every other category has. Only a `launched` category shows up in
navigation or category rankings; a business inside an unlaunched one can
still be found, reviewed, and claimed, just not browsed to.

The platform ships with the full top-level taxonomy from day one (Travel,
Finance & Insurance, Retail & E-commerce, and so on, plus a system
"Other / Uncategorised" fallback), but at launch **only Travel is
launched**. Everything else exists so businesses can already be listed
under it, ready for staff to launch later without a code change. Travel's
own sub-tree — Airlines, Travel Agencies & OTAs (with Online Travel
Agencies and High-street/Tour Agencies underneath), and Airports — is
launched; Hotels, Car Hire, and Tour Operators exist under Travel but
aren't launched yet, per `travel-content.md`.

### Managing categories (staff)

Staff can create a new industry or sub-category, rename one, and move an
industry through its `draft → launched → paused → launched` lifecycle
(FR-002-28, FR-002-30) — a staff **Admin** creates, launches, and pauses;
a **Senior Moderator** can rename a category and edit its description;
every other staff role is read-only (FR-002-33). A state change is a
plain column update, so it's reflected anywhere that reads it (nothing
caches categories yet) immediately — well inside the FR-002-28 5-minute
budget. Pausing or launching an industry never touches any Business's
data, and every launch/pause is written to the compliance log (the public
Transparency Center changelog itself is spec 006). There's no staff
console UI for any of this yet — every endpoint under
`/staff/categories/...` is real and usable today, just not linked from
anywhere.

Launching is gated by a readiness checklist (FR-002-31): missing an icon,
a localised name for every supported locale, or at least one
sub-category **blocks** the launch outright, listing what's missing.
Beyond that, staff always see (and must acknowledge, whatever they say)
five warning-only lines — whether a question set exists yet, that the
topic list and default invitation delay aren't built yet (specs 011 and
005), how many businesses are listed, and whether there are enough of
them for a reliable benchmark (spec 015). Staff can also **preview** an
industry before launching (FR-002-32) — its navigation entry, category
page, and review-form questions; the ranking preview is honestly `null`
until spec 009's search exists.

### Question sets

Travel, Airlines, Travel Agencies & OTAs, and Airports each have their
own set of context-aware review questions (FR-002-19), seeded from
`travel-content.md` — e.g. Travel's "Would you use them again?" applies
everywhere underneath it, and Airlines adds its own on-time/baggage/crew
questions on top. A leaf category inherits every ancestor's questions,
root first; if it redefines the same question key itself, its own
version wins without creating a duplicate. Nothing consumes these yet
(the review form is spec 003) — only the versioned data model and the
inheritance itself exist so far. A question set is never edited in
place: publishing a change (staff Admin or Senior Moderator only) always
creates a new version, and an existing review keeps citing whichever
version it originally answered (FR-002-20).

## Business profiles

Every business has a unique slug and public profile page at
`/business/{slug}` — reachable by anyone, signed in or not. The page
today shows the business's name, logo, description, contact details,
country, its Claimed/Unclaimed label (with the claim date once claimed),
and its primary and secondary categories. An unclaimed profile says so in
plain words: "This business has not claimed its profile."

Everything owned by a later spec — Review Score and Trust Index, the
review list, the AI summary, reply-behaviour signals, case statistics,
similar businesses, and Consumer Warnings — is listed as "coming soon"
rather than shown with fake numbers, so the page is always honest about
what's actually live.

If a business's slug ever changes, the old one keeps working as a
permanent redirect to the new profile page — there's no dead link left
behind.

## Adding a missing business

A signed-in consumer who can't find a business (`/businesses/new`) adds it
either by its website domain, or — if it has no website — by name,
country, and city. Before creating anything, the platform checks for an
existing match: the same normalised domain (protocol, path, and a leading
`www.` are stripped; a different subdomain like `shop.brand.com` still
counts as a different business from `brand.com`), or a typo-tolerant match
on name + city + country. If it finds one, it points the consumer at that
profile instead of creating a duplicate.

A newly-created business always needs a category — every category is
selectable, including ones not yet launched, or the "Other / Uncategorised"
fallback when nothing fits (that fallback queues it for staff to sort out
later). It's reviewable immediately, but sits in a brief `pending` state
until an automated check confirms the domain resolves and isn't on a
blocklist, at which point it becomes an ordinary unclaimed profile.

## Editing a profile

An Owner or Admin (spec 001's business roles) can update their profile:
description, website, email, phone, address, social links, logo, and up
to 5 secondary categories all publish immediately. A **name, domain, or
primary-category change on a claimed profile is different**: it queues
instead of publishing, and only takes effect once a staff member approves
it — rejecting one never touches the business and emails the requester
why. Nothing here changes on an unclaimed profile, which has no members
yet to make the request in the first place. There's no business dashboard
UI to do any of this from yet (that's a later part of spec 002) — the
`PATCH /business/{business}/profile`, `POST /business/{business}/logo`,
and staff `POST /staff/profile-change-requests/{id}/approve|reject`
endpoints are real and usable today, just not linked from anywhere.

A description can't link to a different website than the business's own
(anti-spam), and a logo follows the same rules as a personal photo
(JPEG/PNG/WebP, not animated) except its own size limit (2 MB) and a
200×200 minimum — unlike a photo, transparency is kept rather than
flattened.

## Claiming a profile

A signed-in consumer can claim an unclaimed business four ways
(FR-002-11): a verification code emailed to an address on the business's
own domain (never a free email provider like Gmail — FR-002-12), a DNS
TXT record, an HTML meta tag/file on the domain root, or — when the
business has no domain at all — manual staff review with uploaded
documents. The first three complete automatically the moment the check
passes and make the claimant an Owner right away; nothing about any
existing review, score, or label changes when that happens.

If someone else has already claimed the profile, proving domain control
doesn't hand it over immediately — the current Owners get 7 days to
approve or reject the newcomer joining as an Owner too, and it goes to
staff review automatically if they never respond. Rejecting a claim,
however it happens, emails the person who asked for it why. As with
profile editing above, there's no claim-flow UI yet — every step here is
a real, working endpoint under `/businesses/{business}/claim` and
`/business-claims/{claim}/...`.

## Locations

A **claimed** business can have branch locations, each with its own name,
address, optional phone/coordinates/opening hours, and its own public
sub-page (`/business/{slug}/locations/{location-slug}`, linked from the
main profile once there's at least one). Adding, editing, or removing a
location needs the same Owner/Admin permission as editing the profile
(FR-002-16). A location's own Review Score is spec 008 — "coming soon"
for now, same as the main profile.

## Seeding businesses (staff)

A staff Admin can import a batch of unclaimed businesses in bulk — name,
domain, country, category, and an optional licensed logo (FR-002-24,
FR-002-34) — from a public or licensed source only, never scraped from
another review platform. Every row records its data source and a shared
import batch id (auto-generated if none is given), goes through the same
FR-002-10 automated check as a business a consumer adds one at a time,
and a row whose domain is already listed is skipped rather than creating
a duplicate.

## Disputing an employee size band

An Owner or Admin can dispute their business's employee size band
(FR-002-25) by submitting evidence and, optionally, the band they think
is correct. The current band **stays exactly as it is** until a staff
member decides — upholding it or changing it — which emails the person
who raised the dispute either way and logs the decision.

## What's not built yet

No question sets are attached to a review form yet, and there's no
console UI anywhere for staff — for industries/categories, for the
profile-change/claim/import/dispute queues above, or for anything else
in this document. All of that is still on spec 002's `tasks.md`.
