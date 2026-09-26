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
under it, ready for staff to launch later without a code change (the
console to do that isn't built yet). Travel's own sub-tree — Airlines,
Travel Agencies & OTAs (with Online Travel Agencies and High-street/Tour
Agencies underneath), and Airports — is launched; Hotels, Car Hire, and
Tour Operators exist under Travel but aren't launched yet, per
`travel-content.md`.

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

## What's not built yet

Consumers can't yet claim a profile themselves, there are no locations, no
question sets are attached to a review form yet, and there's no staff
console for industries/categories or for the profile-change queue above.
All of that is still on spec 002's `tasks.md`.
