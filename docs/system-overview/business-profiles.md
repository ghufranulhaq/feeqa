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

## What's not built yet

Consumers can't yet add a missing business themselves, businesses can't
yet claim or edit a profile, there are no locations, no question sets are
attached to a review form yet, and there's no staff console for
industries/categories. All of that is still on spec 002's `tasks.md`.
