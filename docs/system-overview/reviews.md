# Reviews & Lifecycle Updates

What the platform does today for spec [003](../../specs/003-reviews/spec.md).
This is a plain-English description of behaviour, not an implementation
guide — see the spec and `specs/plan.md` for the "why" and "how". Check
the spec's `tasks.md` for exactly what's done.

## Writing a review

A signed-in consumer rates a business 1–5 stars with a title
(5–100 characters), text (30–5,000 characters — emoji count toward the
length, but at least 30 non-whitespace characters are required), a date
of experience (today or up to 12 months ago), and an optional reference
number. Confirming "this is my own genuine experience" is required.
Locations can be reviewed the same way, scoped to that specific branch.
A member of the business being reviewed (an Owner, Admin, Responder, or
Analyst) cannot review it. Only one review per business every 30 days is
allowed per person; re-submitting with the same idempotency key returns
the original review instead of creating a duplicate. A business closed
(spec 002) more than 12 months ago can no longer be reviewed; a more
recent closure still accepts them.

If the business's category has review questions (spec 002), a review
also collects an answer to each required one and stores which version of
the question set it answered — so a later change to the questions never
rewrites what an old review's answers meant.

A review can optionally **tag one other business** involved in the same
experience — e.g. the travel agency that sold a ticket for an airline
review. The tag must point to an existing business and can't be the
business being reviewed; more than one tag is rejected outright. The
tagged business's members get an email the moment the review actually
publishes; the tag never affects the tagged business's own Review Score
or Trust Index in any way (both are specs 008 not built yet — there's
simply nothing written to the tagged business at all).

**There is no page or form to write a review from yet.** Submission only
exists as an application action (`App\Actions\Reviews\SubmitReview`), not
an HTTP endpoint — the same way spec 002's staff console logic exists
before its console UI does. A signed-in user's in-progress review
(rating, title, text, question answers) autosaves server-side as they
type, one draft per business (or per business + location); there's
nothing to lose if they navigate away, once a submission form exists to
restore it into.

## Automated screening

Every submission and edit is checked against a moderation word list — a
match rejects it outright with a reason. Text that's near-identical to
the same person's text on a *different* business within the last 30 days
is **held** for review instead of rejected, since that pattern signals
possible fraud rather than obviously bad content. Anything else
publishes immediately. All markup and scripts are stripped from title
and text at submission time — only plain text is ever stored; a link to
the reviewed business's own domain, when it's mentioned by name in the
text, is turned into a real link at display time (nowhere else).

## Reading reviews

Every business and location profile shows its published reviews, newest
first by default (or most-useful first) — sorted, filterable (by star
rating, source label, whether a review has a lifecycle update, language,
date range, and, on a business's own page, by location), and paginated
at 20 per page. Filtering on things that don't exist yet — Verified
Experience, a business reply, or an open case — is accepted in the URL
and silently ignored, since specs 004/007/010 aren't built.

Each review shows its author (name, photo, country, how many published
reviews they have), star rating, title, text, both dates (of the
experience and of publication), how many people found it useful, and its
**source label** — always "Organic" today, since Invited/Redirected both
need the invitation/link system (spec 005). A tooltip on the label
explains what each one means. Every review has its own permanent page,
independent of whichever page of the list it's currently showing on.

A business profile also has a **"Mentioned in reviews"** section (spec
002) listing published reviews of other businesses that tag this one,
each clearly marked with which business it's actually reviewing.

## Marking a review useful

Any signed-in reader except the review's own author can mark it useful;
tapping again removes their vote. The real count is shown on every
review card. There's a working endpoint for this
(`POST reviews/{review}/useful-vote`), but no button on the page reaches
it yet.

## Editing and deleting a review

Only the review's author can edit or delete it — not even a member of
the reviewed business can, and every other reader gets a 403. Editing
re-runs the same screening rules as a fresh submission (so an edit can
move a review between published/held/rejected), and the card then shows
"Edited" with the date. A review's tag can be added, changed, or removed
on the same edit; an edit fully replaces the tag (an edit that leaves the
field out clears any existing tag), and only a newly-set or changed tag
sends a fresh notification. Deleting soft-deletes the review — it
disappears from every public view immediately, but nothing is destroyed.
Both are real endpoints (`PATCH`/`DELETE reviews/{review}`); there's no
edit/delete button on a review card yet.

## Lifecycle updates

A published review can receive up to three dated follow-ups from its own
author: **30 days**, **6 months**, and **1 year** after publication.
Each window opens on its milestone day and stays open until the next one
opens (the 1-year window, having no window after it, stays open 90
days). An update needs a fresh 1–5 rating and 20–2,000 characters of
text, is re-screened the same way as the original review, and can't be
submitted for a milestone that's already been used, or outside every
open window (the rejection names the date the next window opens).

The **current rating** shown and used everywhere is the latest
*published* update's rating, or the original rating if there are none.
After every published update, a **durability signal** (improved,
unchanged, or declined) is stored, comparing the current rating back to
the original one. The review card shows the original review and every
published update as a dated timeline.

The day a window opens for a review, its author gets an email (and an
in-app notification) inviting them to add an update — sent exactly once
per review per milestone, however many times the daily check runs. An
author can opt out of these in their own account (there's no
account-settings UI for it yet, same "endpoint exists, nothing links to
it" situation as the rest of this page). Spec 007's own notification
preference rules don't apply yet — this is the platform's own first
opt-out, not tied into 007.

In the demo environment, `REVIEW_LIFECYCLE_WINDOWS_ALWAYS_OPEN=true`
(constitution §5.6) skips this date math entirely: the next unused
milestone counts as open immediately, so a demo reviewer doesn't have to
wait 30 real days to show off the feature. It's off by default and
always ignored in production, whatever `.env` says.

## Score recalculation (hook only)

Every submission, edit, delete, and lifecycle update calls a
recalculation hook for the business actually being reviewed — never for
a business a review merely tags. The hook itself
(`App\Actions\Businesses\RecalculateBusinessScore`) is a deliberate
no-op today: spec 008 owns the real Review Score/Trust Index formula and
storage, and neither exists yet. The hook exists and is called from
every trigger point so spec 008 has nothing left to wire up when it
lands, only a formula to fill in.

## What's not built yet

- There is no page to write, edit, delete, or vote a review from — every
  action above is real and independently tested, but only reachable
  today as an application action or a direct HTTP call, not from any
  button or form.
- Invited and Redirected source labels (spec 005), Verified Experience
  (spec 004), replies and the tagged business's one reply (spec 007),
  case tracking (spec 010), and both scores (spec 008) are all "coming
  soon" placeholders wherever they'd otherwise appear.
- Spec 002's `MergeDuplicateBusinesses` moves reviews to the surviving
  business on a merge (T16) — see
  `docs/system-overview/business-profiles.md`'s merge section.
