# Spec 005: Review Invitations

**Status:** Draft · **Phase:** 1 (MVP) · **Depends on:** 002, 003
**Related:** 004 (transaction records → Verified Experience), 017 (plan limits)

## 1. Goal

Help businesses collect reviews from **all** their real customers, automatically and at the right moment, instead of only hearing from the angriest or happiest few. The system enforces neutrality: no cherry-picking, no incentives, and no steering unhappy customers away. As a result, collected reviews reflect real customer sentiment and can be verified.

## 2. User Scenarios

1. **BCC (email forwarding).** When an Admin adds their unique forwarding address as BCC on order-confirmation emails, the system reads each email's recipient name, email, and order reference, then sends a review invitation 7 days later (the configured delay). The resulting reviews are labelled **Invited** and **Verified Experience**.
2. **Booking-system integration (Phase 2).** When an agency connects its booking engine and a trip is completed, an invitation is queued automatically for 1 day after the travel date.
3. **API.** A booking system calls the Invitation API with email, name, reference, locale, template, and send time after each flight. Invitations go out 1 day after the flight date.
4. **CSV upload.** A small business uploads a CSV of 300 customers. 297 rows are valid and queued, 3 are rejected with row-level errors. Reviews from these invitations are labelled **Invited**. They are verified only if the rows carry references that pass the reference-matching rules in 004.
5. **Generic link / QR code.** A café prints a QR code linking to its review page. Reviews from it are labelled **Redirected**.
6. **Consumer unsubscribes.** A consumer clicks "Don't send me invitations from this business" (or "from any business"), and they get no further invitations of that kind.
7. **Funnel tracking.** An Admin views invitation stats: queued 1,200 → sent 1,180 → opened 640 → clicked 310 → reviewed 190 (16.1% conversion), plus bounces and unsubscribes.

## 3. Functional Requirements

### Methods
- **FR-005-01** Invitation methods: `bcc` (unique forwarding address per Business), `integration` (e-commerce/CRM connectors), `api` (Invitation API), `csv` (upload), `manual` (single entry form), and `link` (generic review link and QR code).
- **FR-005-02** Methods `bcc`, `integration`, and `api` are **transaction-linked** when the payload includes a transaction reference. Reviews submitted through them receive the Verified Experience attestation with method `transaction_invitation` (004).
- **FR-005-03** Methods `csv` and `manual` produce `Invited` reviews. They produce Verified Experience only through the 004 reference-matching rules, because the business typed the data by hand.
- **FR-005-04** The `link` method produces `Redirected` reviews and never auto-verifies.
- **FR-005-05** Each invitation must record: business, method, recipient email (encrypted), name, locale, reference (encrypted), template, scheduled time, product SKUs (optional), location (optional), and status.

### BCC processing
- **FR-005-06** The BCC address must be unique per Business and rotatable. Incoming emails must pass SPF/DKIM alignment with one of the Business's verified domains, or with a sender address the Business has registered. Emails that fail are dropped and counted in a diagnostics view.
- **FR-005-07** The parser must extract the recipient email and name from the headers, and a reference number from the subject/body using a business-configurable pattern (e.g., regex `SK-\d{6}`). Email bodies must be discarded right after extraction.

### Scheduling & sending
- **FR-005-08** Configurable per Business: send delay (0–30 days after the trigger; the default comes from the category, see [`travel-content.md`](../002-business-profiles/travel-content.md) §4, and is otherwise 7 days; a `travel_date` field on the transaction may be used as the trigger), send window (local hours), reminder (0 or 1, sent 3–7 days after an unopened invitation), sender name, reply-to, and template per locale.
- **FR-005-09** The system must never send more than **one invitation per recipient per Business per 30 days**, **one per unique transaction reference**, and never to recipients on the suppression list (FR-005-15).
- **FR-005-10** Invitation links must be unique, signed, single-purpose tokens that expire after 60 days. Opening a link pre-fills the review form (business, location, products, reference).
- **FR-005-11** Invitation status lifecycle: `queued → sent → delivered → opened → clicked → reviewed`, plus `bounced`, `complained`, `unsubscribed`, `suppressed`, `cancelled`, `expired`.
- **FR-005-12** A queued invitation can be cancelled by the Business before it is sent (e.g., the order was cancelled). Cancellations are counted and shown in the neutrality report (FR-005-18).

### Templates & neutrality
- **FR-005-13** Templates contain editable text blocks with required placeholders (`{review_link}`, `{unsubscribe_link}`). The system must reject a template that:
  - mentions any **incentive** (a discount, coupon, prize, gift, refund, points, "in return", etc., using a maintained multi-language lexicon, with staff review for borderline cases);
  - asks only for positive reviews or suggests a rating (e.g., "leave us 5 stars");
  - includes a pre-selected rating or sentiment question before the review link ("review gating");
  - links anywhere other than the Platform review link, the business website, or unsubscribe.
- **FR-005-14** Every invitation must show the same neutral rating entry for all recipients. There must be no business-configurable branching based on predicted or stated sentiment.

### Consent & suppression
- **FR-005-15** Recipients must be able to unsubscribe from (a) this Business or (b) all Platform invitations with one click, with no sign-in needed. Suppressions are global, permanent until reversed by the recipient, and apply across all methods.
- **FR-005-16** Businesses must confirm, once per method and recorded with a timestamp, that they have a lawful basis to share customer contact data for invitations, and that they invite **all** eligible customers at a consistent point in the journey.
- **FR-005-17** Hard bounces and spam complaints must add the address to suppression automatically.

### Monitoring & anti-gaming
- **FR-005-18** The system must compute a per-Business **neutrality report** every day: invitations per trigger, cancellations, percentage of transactions invited (when transaction data is available), and the rating distribution of invited reviews compared with organic ones. It must alert staff (006) when:
  - cancellation rate > 20% of queued invitations over 30 days (with ≥ 50 invitations), or
  - invited-review average exceeds organic average by > 1.5 stars with ≥ 50 reviews each, **and** less than 50% of known transactions are invited.
- **FR-005-19** Invitation analytics (funnel, conversion, by method, by template, over time) must be available to Business users with the Analyst role or above.
- **FR-005-20** Monthly invitation limits by plan are enforced (017). When a limit is reached, new invitations stay `queued` with the reason `plan_limit` and can be released after an upgrade or when the next period starts. They are never silently dropped.

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| CSV empty, wrong encoding, missing required columns, > 50,000 rows, or > 20 MB | Reject the file with a clear message. UTF-8 only. Max 50,000 rows per file. |
| Malformed emails, duplicate rows in one file | Reject those rows. Duplicates collapse to one. Row-level error report downloadable. |
| Same customer with 3 orders in a week | One invitation (30-day recipient rule). The latest reference is attached. |
| API called with a past send time | Send at the next allowed send window. |
| API called twice with the same reference | Idempotent: return the existing invitation. |
| Recipient email = a business member's email | Suppress, and record the event for fraud signals. |
| BCC email with multiple recipients | Invite only the first `To:` recipient. Ignore CC. |
| BCC email that isn't a transactional email (e.g., newsletter) | The reference pattern doesn't match, so no invitation is created and it is counted in diagnostics. |
| Invitation link opened after expiry | Show "This invitation expired". Offer to write an `Organic` review instead. |
| Business on the Free plan tries `bcc`, `integration`, or `api` | Blocked by plan (017), with an upgrade message. `link` and limited `manual` are available on Free. |
| Business under Consumer Warning / suspended (006) | All sending paused. Queued invitations stay queued until the restriction is lifted or expire. |
| Template with an incentive in a non-English language | Lexicon covers launch locales. Unknown locales go to staff review before first use. |
| Unauthorized: a Responder edits templates or uploads a CSV | 403. |

## 5. Out of Scope

- SMS and WhatsApp invitations (Phase 3).
- In-app/on-site review collection forms hosted on the business's website (reviews must be written on the Platform).
- Customer-data enrichment or storing customer data beyond what is needed to send invitations.
- Marketing emails of any kind to consumers on behalf of businesses.
- Any platform-specific connector (`integration` method) in Phase 1.
- Product review invitations (deferred with product reviews, 003).

## 6. Acceptance Criteria

- [x] Every method creates invitations with correct method metadata, and the resulting reviews get the correct source label and verification.
- [x] Recipient, reference, and suppression rules (FR-005-09, -15, -17) are enforced (tests per rule).
- [x] Templates with incentives, rating suggestions, gating, or foreign links are rejected (test corpus of ≥ 50 negative examples in launch locales).
- [x] BCC rejects emails that fail SPF/DKIM and discards bodies after parsing.
- [x] Neutrality report and alerts fire on the seeded threshold fixtures.
- [x] Plan limits queue invitations and never drop them.
- [x] One-click unsubscribe works without sign-in, both per-business and globally.

## 7. Dependencies & Open Questions

- **Decided (2026-09-24):** Phase 1 methods are `bcc`, `api`, `csv`, `manual`, and `link`. The `integration` method (booking engines/GDS/e-commerce connectors) moves to **Phase 2**. Default send delay is set per category (travel-content.md §4).
- **Q1:** Which booking-engine/GDS connectors come first in Phase 2?
- **Q2 (found by T11's acceptance sweep, 2026-09-26):** FR-005-16's lawful-basis/consistent-invitation confirmation was never broken into a `tasks.md` task and isn't built. It touches legal compliance directly (constitution §4 L3/L4 — GDPR lawful basis, DSA/DMCC disclosure), so before it's implemented someone needs to decide: does an unconfirmed method actually block sending, or is it record-keeping only; is the confirmation per Business or per Business-and-method; and who signs it off (any Owner/Admin, or does it need the same legal sign-off gate as L5's insider/resolve-first features)? Not guessed at per constitution §8 rule 3.
