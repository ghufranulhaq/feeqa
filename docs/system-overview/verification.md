# Proof-of-Experience Verification ("Verified Experience")

What the platform does today for spec [004](../../specs/004-verification/spec.md).
This is a plain-English description of behaviour, not an implementation
guide — see the spec and `specs/plan.md` for the "why" and "how". Check
the spec's `tasks.md` for exactly what's done.

## Methods

Four methods are modelled (FR-004-01): `transaction_invitation`,
`reference_match`, `document_proof`, and `payment_link`. Only the last
two are usable today:

- **`transaction_invitation`** depends on spec 005's invitation system,
  which doesn't exist yet — the method is defined so the data model is
  ready, but nothing produces it.
- **`payment_link`** is Phase 2 by design (spec.md §5) — modelled, never
  issued.

Verification is available on every plan, including an unclaimed or
free-plan business (P1, FR-004-03) — nothing here checks a plan or
claimed status before accepting a proof. Verifying a review doesn't
re-open it for moderation unless the proof contradicts it (e.g. names a
different business, see below).

## Document proof upload

A signed-in author of a **published** review can upload up to 3 files
(≤ 10 MB each; PDF, JPEG, PNG, HEIC, or `.eml`, sniffed by content, not
extension) as proof — `App\Actions\Verification\RequestDocumentVerification`.
Every file is malware-scanned; a corrupt or password-protected PDF is
rejected with a specific message. Only the first file is the proof of
record for matching and fingerprinting (a second/third file is accepted —
e.g. front/back of a document — but not separately processed).

The system extracts merchant, transaction date, a reference number, and
(optionally) amount/currency, each with a confidence score, using the
`VerificationExtractor` driver (`fake` in tests/demo, a real OCR/LLM
provider in production — never hard-coded, constitution §5.1). Anything
that looks like a full card number in the extracted text blocks storage
outright rather than attempting to mask it (FR-004-09's "automatically
mask" is implemented as "reject and ask the reviewer to redact and
re-upload" — a documented MVP simplification, since pixel-level masking
of an arbitrary receipt layout isn't reliable enough to trust).

**Auto-approval** (FR-004-06) needs all of these to hold:
merchant matches the business (name, domain, or a registered alias, or
its tagged business — e.g. the travel agency that sold a ticket for an
airline flight, matched by name/domain either side); the transaction date
is within `VERIFICATION_MAX_AGE_MONTHS` (12 by default) and no more than
`VERIFICATION_GRACE_DAYS_AFTER_EXPERIENCE` (30 by default) days after the
date of experience; the proof fingerprint hasn't been used before; and
tamper checks pass. **Any single failure holds the proof for the staff
queue with the specific reason recorded — never a silent, unreviewable
reject.** A proof naming a refund or cancellation is still accepted (it
shows a real interaction); it's recorded under the same `document_proof`
method with `extracted_fields.proof_subtype = "refund"`.

**Tamper checks** (FR-004-07): the JPEG `Software` EXIF tag against a
configurable editing-tool blocklist (`VERIFICATION_EDITING_TOOL_BLOCKLIST`),
a near-duplicate perceptual (average) hash against another account's
proof, and a configurable list of known-fake template hashes
(`VERIFICATION_KNOWN_FAKE_TEMPLATE_HASHES`). Any failure holds for staff,
same as any other auto-approval failure.

**Anti-reuse** (FR-004-10, FR-004-11): every verification stores a
**proof fingerprint** — a keyed HMAC (`VERIFICATION_FINGERPRINT_KEY`) of
the normalised business + reference, folding in a perceptual hash for
documents. One fingerprint verifies only one review; a second account
reusing the same receipt is rejected outright, and the reuse is logged
(`Log::warning`, FR-004-11's "sent to fraud signals" — a real log line
today, a real queue once spec 006 exists, same honest-placeholder pattern
as everywhere else this spec depends on unbuilt tooling).

## Reference matching

A business (Responder permission or above — `ManageIntegrations`) submits
transaction records — `POST business/{business}/transaction-records`
(`App\Actions\Businesses\SubmitTransactionRecords`, FR-004-12): an HMAC
hash of the reference, an HMAC hash of the customer's email, a
transaction date, and optional SKUs. Plaintext emails are rejected (400);
so is a batch over `TRANSACTION_RECORDS_MAX_PER_REQUEST` (10,000) or a
business's running total for the day over `TRANSACTION_RECORDS_MAX_PER_DAY`
(1,000,000). A duplicate reference for the same business upserts rather
than erroring.

A consumer then enters their reference number against their own
published review (`POST reviews/{review}/reference-match`,
`App\Actions\Verification\MatchReviewReference`, FR-004-13): if the
reference hash *and* the reviewer's verified-email hash both match a
record dated within the last 12 months, the review is verified instantly
(method `reference_match`, auto-issued — no staff involved). If the
reference matches but the email doesn't, it goes to the staff queue
instead of an outright reject (`extracted_fields.auto_approval_hold_reason
= "reference_matched_email_did_not"`). If the reference isn't found at
all, there's nothing for a human to review, so it's rejected immediately
(`decision_reason_code = "reference_not_found"`).

This covers the "Business API" data shape FR-004-12 asks for; the actual
external, token-authenticated Business API contract is spec 016's job —
same dependency relationship spec 005 has with the `bcc`/`api` invitation
methods.

## Staff verification queue

`GET staff/review-verifications` lists every `pending` proof (extracted
fields, reference number, confidence, the review and its business) for
any staff member; `POST .../{verification}/approve` or `.../reject`
(with an optional reason code) decides it —
`App\Actions\Staff\DecideVerificationProof`. Approving calls the same
`IssueAttestation` action every other approval path uses; rejecting
writes a compliance log entry and emails the reviewer. A staff member
can't decide their own review's proof (blocked in `IssueAttestation`
itself, so the rule holds for every caller, not just this one). This
**is** spec 006's "verification proofs" queue item type, built minimally
now — list and decide, no console UI — the same relationship spec 003's
screening-hold state has with 006's full moderation UI.

## Attestations

Every approved verification produces a signed attestation
(`App\Actions\Verification\IssueAttestation`, FR-004-14): attestation ID,
review ID, business ID, method, proof fingerprint, **experience month**
(not the exact date — deliberately less precise than the review itself),
decision time, and methodology version, signed as a JWS (sodium Ed25519)
under a rotatable key. The review's **Verified Experience** badge is
computed live from whether an unrevoked attestation exists
(`Review::isVerified()`) — never a cached flag, so a revocation takes
effect immediately, well inside FR-004-17's 60-second budget.

Anyone can check an attestation at `/verification-check/{id}` — it
re-verifies the signature on every request (so a tampered payload is
caught, not just trusted from the database), and shows the method (with
a short "what this means" line per method, FR-004-25), experience month,
decision date, business, and revocation status. It never exposes the
proof fingerprint or the raw JWS. An unknown ID 404s exactly like any
other lookup failure — no signal about whether it ever existed
(FR-004-15). `/verification-revocations` publicly lists every revoked
attestation (ID, date, reason category only — FR-004-17) and
`/.well-known/platform-keys.json` publishes every public signing key
that's ever been active, so an attestation signed under a rotated-out key
still checks out (FR-004-16).

Staff can **revoke** an attestation with a reason code
(`App\Actions\Verification\RevokeAttestation`) — this writes a compliance
log entry and emails the reviewer, same pattern as any other staff
enforcement action.

## Business verification requests

A business user (Responder permission or above) can request verification
on any unverified, published review of their own business, at most once
per review (`POST business/{business}/reviews/{review}/verification-request`,
`App\Actions\Businesses\RequestReviewVerification`, FR-004-18). It's
rate-limited to 20 requests per 1,000 published reviews in any rolling 30
days, with a floor of 5 — and if ≥ 10 requests land in 30 days with more
than 80% of them targeting 1–2★ reviews, it's flagged
(`Log::warning`, same honest-placeholder pattern as fingerprint reuse
above — a real staff-visible list once spec 006 exists) rather than
silently allowed (FR-004-21).

The reviewer is notified and can respond
(`POST verification-requests/{verificationRequest}/respond`,
`App\Actions\Verification\RespondToVerificationRequest`, FR-004-19): verify
privately, verify **and** share the reference number with the business, or
ignore. Verifying either way runs the same reference-matching flow as a
reviewer-initiated verification above; the only difference is whether the
reference number is copied onto the request for the business to see.
**Ignoring never hides, removes, or down-ranks the review** — the action
only ever updates the request row, and never touches the review
(FR-004-20).

## Verification percentage

`Business::verificationPercentage()` (FR-004-24) is verified published
customer reviews ÷ all published customer reviews, over a rolling 12
months — a pure, hand-calculation-tested method exposed as a hook for
specs 008 (Trust Index) and 015 (benchmarking) to read once they exist,
the same "built now, filled later" shape as
`App\Actions\Businesses\RecalculateBusinessScore`. There's no
insider-review concept yet (spec 013), so every publicly visible review
counts as a customer review today.

## Data retention

Raw proof files and any extracted fields beyond what the attestation
keeps (merchant, date, amount, currency, confidence, perceptual hash) are
deleted 30 days after a verification's decision — approved or rejected —
by a daily scheduled job (`verification:delete-expired-proofs`,
`App\Actions\Verification\DeleteExpiredProofFiles`, FR-004-23). The
signed attestation itself is unaffected; it never held that data. In the
demo environment, `VERIFICATION_DELETE_RAW_PROOF_FILES_ON_SCHEDULE=false`
turns this off (constitution §5.6) so demo proofs stay in place for
repeat walkthroughs — production always deletes on schedule, whatever
`.env` says.

If a reviewer deletes their account, their verification attestations are
revoked (reason `review_deleted`) as part of the same 30-day account
erasure job that pseudonymises the rest of their data — not a staff
decision, so it skips the compliance log and the staff-role guard that a
manual revocation goes through. The proof fingerprint itself is left
untouched (it holds no personal data) so the same receipt still can't be
reused after the account is gone.

Proofs are never shown to the business that's reviewed, or to anyone
other than the reviewer and staff — there's no endpoint anywhere that
serves a raw proof file or its extracted fields to a business account.

## What's not built yet

- **`transaction_invitation`** verification (spec 005's invitation system
  isn't built), and **`payment_link`** (Phase 2 by design).
- A full staff console for the verification queue (spec 006) — today it's
  a real, working JSON list + approve/reject endpoint, no UI.
- No business dashboard UI for submitting transaction records or
  requesting verification, and no consumer-facing page for uploading a
  document proof, entering a reference, or responding to a business's
  verification request — every action above is a real, independently
  tested endpoint with nothing linking to it yet, the same situation
  specs 002 and 003's own endpoints are in. The public attestation check
  and revocation-list pages (FR-004-15, FR-004-17) are the exception —
  both have real pages today.
- "Proof names a different business" (spec.md's edge case table) holds
  for the staff queue like any other merchant mismatch, but the "offer to
  move the review to the correct business" half of that rule isn't built
  — there's no review-reassignment flow yet.
- A dedicated public methodology page (FR-004-25) doesn't exist for any
  spec yet (006/008 own it) — what's checked and what's kept per method
  is described directly on the attestation check page instead, the
  nearest real public surface today.
