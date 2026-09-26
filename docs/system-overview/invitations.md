# Review Invitations

What the platform does today for spec [005](../../specs/005-invitations/spec.md).
This is a plain-English description of behaviour, not an implementation
guide — see the spec and `specs/plan.md` for the "why" and "how". Check
the spec's `tasks.md` for exactly what's done.

## Methods

Six methods are modelled (FR-005-01): `bcc`, `integration`, `api`, `csv`,
`manual`, and `link`. Five are usable today:

- **`bcc`** — a Business forwards (BCC's) its own order-confirmation
  emails to a unique address (`Business::generateUniqueBccAddress()`,
  rotatable). There's no real inbound-mail infrastructure yet (plan D14 —
  no SMTP/SPF/DKIM server), so a demo-only `.eml` upload endpoint stands
  in for it.
- **`api`** — a booking system calls the Invitation API shape directly
  with email, name, reference, and optionally a travel date or an
  explicit send time. Idempotent by reference (a repeat call with the
  same reference returns the existing invitation) and a past send time is
  clamped to now. This mirrors the data shape spec 016's real
  token-authenticated Business API contract will expose — the same
  documented relationship spec 004 has with its own "Business API" stand-in.
- **`csv`** — upload up to 50,000 rows / 20 MB, UTF-8 only. Malformed
  emails and duplicate rows in the same file are rejected/collapsed with a
  downloadable row-level error report; every valid row goes through the
  same `CreateInvitation` engine as every other method.
- **`manual`** — a single entry form (business dashboard endpoint) for one
  recipient at a time.
- **`link`** — a stable, QR-encodable URL per Business
  (`Business::reviewLinkUrl()`), needing no `review_invitations` row per
  use.
- **`integration`** (booking-engine/GDS/e-commerce connectors) is
  **Phase 2** by design (spec.md §7, decided 2026-09-24) — modelled in the
  `InvitationMethod` enum so the data model already supports it, never
  produced in Phase 1, the same relationship `payment_link` has with
  spec 004.

Every method funnels through one engine, `App\Actions\Invitations\CreateInvitation`
(FR-005-05), so the rules below can't be bypassed by a different write
path: it checks the recipient isn't a member of the Business being
invited (suppressed + a fraud-signal `Log::warning` if so), the
suppression list (business-level and global), the 30-day-per-recipient
and one-per-reference rules (FR-005-09 — a repeat within 30 days is
folded into the existing row with its reference updated, not a second
row), the monthly plan limit (FR-005-20, see below), and computes when to
send it.

## Source labels and verification

`bcc`, `integration`, `api`, `csv`, and `manual` all produce **Invited**
reviews; `link` produces **Redirected** (FR-005-03, FR-005-04). Whether a
review is also **Verified Experience** depends on how trustworthy the
reference is:

- **`bcc`, `integration`, `api`** are **transaction-linked** (FR-005-02):
  the reference came through a channel the Platform already validated (a
  real inbound email that passed SPF/DKIM, or an authenticated API call),
  so submitting the review through that invitation's link issues a
  `transaction_invitation` attestation directly — no separate consumer
  confirmation step, fingerprinted and anti-reuse-checked the same as
  every other verification method (004). This closes 004's own
  `transaction_invitation` gap noted in its T10 acceptance sweep.
- **`csv` and `manual`** are typed by hand, so they're never
  auto-verified — only spec 004's own reference-matching rules (a
  consumer separately entering their reference number) can verify them
  (FR-005-03).
- **`link`** never auto-verifies (FR-005-04) — there's no reference to
  trust at all.

## Templates and neutrality

A Business can set one active template per locale
(`POST business/{business}/invitation-templates`, Responder permission or
above via `SendInvitations`, FR-005-13). Every template must include
`{review_link}` and `{unsubscribe_link}`, and is checked by
`App\Domain\Invitations\GuardNeutralTemplate` before it's saved — a pure,
lexicon-based rule set (no external provider, nothing to fake) that
rejects a template that:

- mentions an incentive (discount, coupon, prize, gift, refund, points,
  "in return", etc.) — the lexicon covers `en-GB`, `fr`, `de`, and `es`;
- asks only for positive reviews or suggests a rating;
- pre-selects a rating or sentiment question before the review link
  ("gating", FR-005-14 — the same rule also satisfies FR-005-14's "no
  business-configurable branching on sentiment," since there's no review
  form yet for the other half of that requirement to apply to);
- links anywhere other than the review link, the Business's own
  registered domain, or unsubscribe.

A locale outside the four above isn't silently allowed or rejected — it's
held for staff review before first use (`Log::warning`, the same
real-signal-now/full-006-console-later shape used throughout this spec
and 004). A Business that hasn't configured a template yet still sends —
`ResolveInvitationTemplate` falls back to a platform default that already
passes the same guard. A template's optional sender name and reply-to
address are applied to the outgoing mail's Reply-To header (the
platform's own From address never changes, for deliverability); replies
route back to the Business, not the Platform.

## Scheduling and sending

The send delay defaults per category (`travel-content.md` §4: Airlines
wait 1 day after the trigger — the travel date if known, else the
invitation's own creation time; Travel Agencies/OTAs wait 3 days without a
travel date or 1 day with one; Airports and everything else fall back to
the spec's plain 7-day default) or a per-Business override (FR-005-08). A
signed, single-use token is issued for every invitation, expiring 60 days
out (FR-005-10).

A scheduled command (`review-invitations:send-due`, hourly) sends every
`queued` invitation whose `scheduled_at` has arrived through the
configured mailer (Mailpit locally/demo), re-checking suppression at send
time — not just at creation — since a recipient can unsubscribe or bounce
in between. `review-invitations:send-reminders` (daily) sends exactly one
reminder, 3–7 days after an unopened invitation
(`INVITATIONS_REMINDER_AFTER_DAYS`). `review-invitations:expire` (daily)
moves any non-terminal invitation past its `expires_at` to `expired`.

The full status lifecycle (FR-005-11): `queued → sent → delivered →
opened → clicked → reviewed`, plus `bounced`, `complained`, `unsubscribed`,
`suppressed`, `cancelled`, and `expired`. A 1×1 tracking pixel
(`GET review-invitations/{token}/pixel`) and the click-through link
(`GET review-invitations/{token}`) move `sent → delivered → opened →
clicked`. `ResolveInvitationToken` is what the click-through link calls —
it returns the business, location, product SKUs, reference, recipient
name, and locale a review form would pre-fill (FR-005-10), or
`expired: true` for a link past its expiry so the caller can offer to
write an Organic review instead (edge case table). **No review-submission
page reads this yet** — same situation as every other endpoint below with
no UI in front of it.

A Business can cancel a `queued` invitation before it sends
(`DELETE review-invitations/{reviewInvitation}`, FR-005-12) — cancelling
is just a real, queryable `cancelled` row, counted directly by the
neutrality report below with nothing to keep in sync separately.

## Consent and suppression

One-click unsubscribe needs no sign-in — the invitation's own signed
token in the email footer is both the identity check and the
authorization (`GET/POST invitations/{token}/unsubscribe?global=`,
`App\Actions\Invitations\Unsubscribe`, FR-005-15). "This Business" or
"every Platform invitation" both write to the same
`invitation_suppressions` table (`business_id` null for global); every
method's `CreateInvitation` call and the send-time re-check both enforce
it, and clicking twice is a no-op rather than an error. A hard bounce or
spam complaint suppresses automatically and platform-wide
(`RecordBounce`/`RecordComplaint`, FR-005-17) — a bounced mailbox doesn't
exist for any Business, not just the one that triggered it.

## Plan limits (FR-005-20)

Spec 017 (Plans & Billing) doesn't exist yet, so `App\Domain\Businesses\BusinessPlan`
(every Business defaults to Free) and a config-driven monthly limit
(`config('platform.invitations.plan_limits.monthly_invitations')`,
mirroring 017's own draft entitlement matrix) are the minimal placeholder
this spec needs. A Business at its monthly limit keeps a new invitation
`queued` with `queued_reason = 'plan_limit'` — never dropped — and it's
skipped by the sending command until released. A daily sweep
(`review-invitations:release-plan-limited`) releases held invitations
oldest-first once a new calendar month gives headroom back, and
`App\Actions\Businesses\ChangeBusinessPlan` (Owner only, `ManageBilling`)
releases immediately when a plan change is recorded rather than waiting
for the next day's sweep — the same "real mechanism, no checkout UI yet"
relationship `StripeBillingDriver` has with a real charge.

## Neutrality report and anti-gaming alerts (FR-005-18)

A daily command (`review-invitations:neutrality-report`) computes a
per-Business snapshot for every Business with at least one invitation
(`App\Actions\Invitations\ComputeNeutralityReport`): invitations per
method over the last 30 days, the 30-day cancellation rate (only once
≥ 50 invitations exist in that window), the percentage of the Business's
own submitted transaction records matched to an invitation by reference
(`null` rather than 0% when the Business hasn't submitted any), and the
average rating of Invited vs. Organic published reviews. Two thresholds
log a staff alert (`Log::warning`, real today, a real staff-visible list
once spec 006 exists):

- cancellation rate > 20% over 30 days with ≥ 50 invitations in that
  window;
- Invited average > 1.5 stars above Organic average, with ≥ 50 reviews of
  each, **and** the transactions-invited percentage is known and below
  50% (a Business with no transaction data at all can't have this half
  evaluated, so it never fires from rating alone).

## Analytics (FR-005-19)

`GET business/{business}/review-invitations/analytics` (Analyst
permission or above — `ViewAnalytics`) returns, computed live with
nothing cached: the full funnel (queued/sent/delivered/opened/clicked/
reviewed counts), conversion (reviewed ÷ sent), a by-method and
by-template breakdown (total and reviewed count each), and a 30-day daily
series of invitations created.

## What's not built yet

- **`integration`** (Phase 2 by design) — modelled, never produced.
- **Send window (local hours)** — FR-005-08 also asks for a per-Business
  send window; nothing computes or enforces one yet (every invitation
  sends as soon as it's due, regardless of local time of day). Not
  covered by any `tasks.md` task or by spec.md §6's acceptance criteria,
  so it's a known, documented gap rather than a silent one.
- **FR-005-16** (once-per-method lawful-basis / consistent-invitation
  confirmation) was never broken into a task and isn't built at all — see
  spec.md §7 Q2, added by this doc pass. It touches legal compliance
  directly (constitution §4 L3/L4), so it needs a decision before
  building, not a guess.
- No business dashboard UI, consumer-facing unsubscribe/review-prefill
  page, or staff console anywhere — every endpoint above is real and
  independently tested, just not linked from any page yet, the same
  situation specs 002's and 004's own endpoints are in. The BCC `.eml`
  upload is itself a demo-only stand-in for real inbound mail (plan D14).
- A full spec 006 staff console for the neutrality alerts and BCC
  diagnostics counters — today both are a real signal (a log line and two
  counters on the Business row respectively), no queue or UI.
