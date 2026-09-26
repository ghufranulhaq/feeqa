# Moderation, Integrity & Transparency

What the platform does today for spec [006](../../specs/006-moderation-integrity/spec.md).
This is a plain-English description of behaviour, not an implementation
guide — see the spec and `specs/plan.md` for the "why" and "how". Check
the spec's `tasks.md` for exactly what's done.

## Guidelines and reason codes

Ten reason codes (FR-006-02) cover every prohibited-content category:
`harmful_illegal`, `personal_info`, `advertising_spam`, `not_genuine`,
`incentivised`, `conflict_of_interest`, `wrong_business`, `off_topic`,
`ai_generated_deceptive`, `ip_infringement`, `other_illegal`. They're used
everywhere a staff decision needs a reason: screening, flags, moderation
verbs, and the enforcement ladder.

Three audiences of versioned, never-deleted guidelines exist: **Reviewer**,
**Business**, and the **enforcement policy** (the ladder itself) — the
policy reuses the same `GuidelineVersion` machinery as the other two
rather than a parallel table, since it's versioned the same way. An Admin
publishes a new version with `PublishGuidelineVersion`; the previous one
stays available, just marked not current.

## Automated screening (FR-006-03 through FR-006-05)

Every review, edit, and lifecycle update is screened before it can go
live — `ScreenReviewSubmission` computes a 0–1 risk score and a
`publish`/`hold`/`reject` recommendation from signals computed on the
spot (no external provider): account age and history, submission
velocity (per reviewer, per business), an exact-duplicate-text cluster
across ≥ 3 accounts, a near-identical match to the reviewer's own review
of a *different* business, a near-identical cluster from several
*distinct* reviewers about the *same* business in a short window
(coordinated fraud), incentive language, personal-info detection (phone
numbers, emails), a link check, and — only once a real IP address reaches
a call site — a configured list of known-bad network ranges.

**Automatic rejection is narrow by design**: only an exact-duplicate-text
cluster and a blocklist-word match are registered as auto-reject-eligible
rules (FR-006-05). Every other signal only ever pushes the recommendation
to `hold`, past a configurable weighted risk-score threshold. If a staff-
ordered freeze is active on the Business (see below), everything holds
immediately, with a neutral message, before any other signal runs.

Every screening decision is written to an immutable `screenings` row
naming exactly what it judged — the Transparency Center and the weekly
audit both read from this table, never from a separate summary.

## Business anomaly detection (FR-006-06)

An hourly job checks every Business with recent review activity for a
review spike (> 5× the 30-day daily median with ≥ 20 reviews), a sudden
rating shift, or a cluster of reviews from newly-created accounts, and
raises a `ModerationIncident` (deduped against an already-open one of the
same type). Staff can freeze new reviews on a Business for up to 72
hours while investigating (`FreezeBusinessReviews`) — every submission
holds immediately, with the public-facing message "We're checking
unusual activity on this profile," until the freeze is lifted or expires.

## Flagging: notice-and-action (FR-006-07 through FR-006-10)

Anyone — a signed-in user, a guest (with an email), or a business acting
through its own permission — can flag a review with a reason code,
optional details (≤ 1,000 characters), and up to 5 evidence files (≤ 10
MB each). A repeat flag from the same reporter on the same item is a
no-op. `harmful_illegal`/`personal_info` reports auto-blur the content
(a 24-hour SLA) once a trusted reporter files one, or once 3 distinct
reporters have — everything else gets a 7-day SLA. **A business's own
flag never hides content by itself**: it can only reach `open`, same as
anyone else's; the flagged review stays fully visible while a moderator
looks at it. A `not_genuine` business flag re-runs the screening engine
against the review's current text. A business with 50+ still-open flags
of its own is capped from filing more; one with a 90-day reject rate
above 80% (≥ 20 flags) raises a staff signal feeding the ladder's first
rung.

`DecideFlag` resolves a flag `upheld` or `rejected`, notifying the
reporter either way. Moving a flag off `blurred` is what un-blurs a
review — a moderator typically redacts the offending text first
(`ModerateReview`'s `redact` verb) and then decides the flag.

## Staff moderation actions and queues (FR-006-11 through FR-006-13)

Three read queues exist (`App\Actions\Staff\Queues`): held reviews
(oldest first), flags (defaulting to unresolved, ordered by SLA due
date), and moderation incidents (defaulting to open/investigating).
`AssignQueueItem` hands any of the three (plus an audit sample, see
below) to a staff member without itself being a moderation decision, so
it doesn't write to the compliance log.

`ModerateReview` covers five verbs: publish, remove, redact a span,
mark not genuine, and request verification (delegating to 004's own
verification-request flow rather than duplicating it). `remove` and
`mark_not_genuine` both take the review off every public/scored surface,
but stay distinct: `mark_not_genuine` requires that reason code and
always recalculates the business's score; `remove` accepts any reason
code and only recalculates if the review had been published.
`BlockUserAccount` and `RestrictBusinessFeature` (flagging, invitations,
profile edits — only flagging is actually enforced elsewhere today) give
staff two direct, ad hoc actions outside the ladder below.

Every verb here requires a reason code, writes one compliance log entry,
and (except `request_verification`, which already sends its own notice)
sends the author a statement of reasons: what was affected, the reason,
the current guideline version, whether a human or automation made the
call, and how to appeal. A moderator with a declared conflict of interest
on a Business is blocked from acting on its content (`ModeratorConflict`).

## The enforcement ladder and Consumer Warning (FR-006-14 through FR-006-17)

Repeated or severe breaches escalate through an ordered ladder —
`ApplyEnforcementStep` records each rung and applies its side effects in
one call:

- **Business**: educational notice → warning → final notice → feature
  restriction (invitations and profile edits switch off; replying and
  flagging still work) → **Consumer Warning** → termination.
- **Reviewer**: educational notice → warning → account block.

A **Consumer Warning** is the serious one: `Business::trustSignalsHidden()`
turns true (a forward hook for 008/009's Review Score and Trust Index —
the public profile page already shows a banner and hides those scores
the moment they exist to hide), every feature except flagging switches
off, and the business's plan is forced to Free (this repo's only real
plan-tier column today — an honest stand-in for "paid features
suspended" until spec 017 exists). It stays for at least 6 months, and
only a Senior Moderator can lift it after that. Skipping ahead of a
subject's current rung — "severe or proven fraud" — needs a Senior
Moderator's approval too. `LiftEnforcementStep` reverses what it can
(un-restricts features, clears the warning, unblocks the account) but
never restores a suspended plan, since there's no billing record of the
prior tier to restore it to.

## Appeals (FR-006-18, FR-006-19)

The affected party — the reviewer, a flag's own filer, or an enforcement
subject — can appeal once, within 30 days of the decision (a staff
override lets a late one through). `DecideAppeal` must be a *different*
staff member than the original decision-maker, enforced by looking up
who actually decided: `applied_by`/`decided_by` for an enforcement
action or a flag, or the compliance log entry `ModerateReview` itself
wrote for a review (which has no such column of its own). `overturned`
reverses what it can — republishes a removed/mark-not-genuine'd review
and rescoring the business, flips a flag's verdict, or lifts an
enforcement step outright, bypassing the ladder's own Senior-Moderator-
plus-6-month gate, since the appeal itself already establishes the step
was wrong. A redacted span's exact original text can't be restored; it
was overwritten in place, never stored.

## Weekly audit sampling and auto-disable (FR-006-20)

A weekly job (`moderation:weekly-audit`) samples at least 2% of the
week's `publish`/`reject` screening decisions into an audit queue for a
staff correct/incorrect verdict, then measures each auto-reject rule's
precision over decided samples in a trailing 90-day window. Any rule
under 99% precision is disabled automatically — it still fires, but only
ever recommends `hold` from then on — with the measured numbers recorded
as the reason.

## Transparency Center (FR-006-21, FR-006-22)

Everything here is a public, unauthenticated read, generated or computed
from the compliance log, flags, appeals, screenings, and the enforcement
ledger — nothing hand-entered except a manually-recorded legal/government
request count with no source table yet:

- guidelines and the enforcement policy, all versions (`ListGuidelineVersions`);
- score/verification methodology links (`ListMethodologyLinks`) — 008's
  and 009's are `null` placeholders since those specs don't exist,
  verification's points at 004's own public attestation check page;
- generated quarterly reports (`ListTransparencyReports`), each one
  reproducible: recomputing the same period with `ComputeTransparencyReport`
  updates the same row from the same underlying tables;
- businesses currently under a Consumer Warning (`Business::underConsumerWarning()`).

## What's not built yet

- **No moderation console, flag button, or appeal form on any page.**
  Every action above is real and independently tested, the same
  situation specs 002/004/005's own actions were in before their own UI
  landed — staff and reviewers reach this today only through the
  actions/API directly. The one real UI hook this spec *does* wire up is
  the public business profile page's Consumer Warning banner, since that
  page already exists (spec 002).
- **No real IP address reaches screening.** `ScreenReviewSubmission`'s
  network-reputation signal takes an optional `?string $ipAddress`, but
  none of its three call sites (submit/edit/lifecycle-update) have a
  request to pull one from yet — wiring a real IP in is future work once
  a submission route exists, the same gap 005 T7 documented for its own
  token resolution.
- **No real network-reputation data source.** `known_bad_ranges` is a
  configured list of CIDR ranges (`.env`), not a live IP-reputation
  lookup or `fake`/real driver pair — there's no external provider to
  swap yet.
- Replies (007), case messages (010), media items (012), and insider
  reviews (013) don't exist, so FR-006-03's screening only covers
  reviews and lifecycle updates today — the screening/flagging contract
  is shaped so those specs plug in the same way 005's invitations plugged
  into 003.
