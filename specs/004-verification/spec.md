# Spec 004: Proof-of-Experience Verification ("Verified Experience")

**Status:** Draft · **Phase:** 1 (MVP), with payment-provider linking in Phase 2 · **Depends on:** 003, 005
**Client requirements:** F1 Proof-of-Experience Verification, A) Verified Transaction Reviews, GTM payment/e-commerce partnerships

## 1. Goal

Fake reviews are the biggest problem with review sites. Any reviewer can prove they really had the experience they are reviewing, using an invoice, receipt, booking reference, order ID, a transaction-linked invitation, or (later) a payment-provider link. Reviews that pass get a **Verified Experience** badge backed by a signed attestation that anyone can check. The proof itself stays private.

## 2. User Scenarios

1. **Automatic verification by invitation.** When a consumer reviews through an invitation created from a real order (BCC, API, or e-commerce integration, see 005), the review is published with **Verified Experience** and the reviewer doesn't have to do anything.
2. **Receipt upload.** When a consumer writing an organic review of an airline taps "Verify this experience" and uploads their e-ticket PDF, the system extracts the merchant, date, and booking reference, matches them to the airline and the date of experience, and within minutes the review shows **Verified Experience**. The PDF is deleted within 30 days, and only the attestation is kept.
3. **Order ID match.** When a consumer enters order number `SK-448812` and the business has shared hashed order references through its integration, the system finds a match (reference + the consumer's email hash) and verifies the review instantly.
4. **Low-confidence proof.** A consumer uploads a blurry photo of a receipt. Extraction confidence is low, so it goes to a verification agent. The agent approves it within 2 business days, and the consumer is notified.
5. **Business requests verification.** A business flags a negative organic review as "can't find this customer" and requests verification. The reviewer gets a request. They can (a) upload proof privately to the Platform, which the business never sees, or (b) also choose to share the reference number with the business so it can resolve the issue. If the reviewer doesn't respond, the review stays up and remains unverified.
6. **Public check.** A reader taps the Verified Experience badge and sees: "Verified on 12 Mar 2026 · Method: booking confirmation · Experience month: Mar 2026 · Attestation ID … · Check signature". The check confirms the attestation is authentic and not revoked.

## 3. Functional Requirements

### Methods
- **FR-004-01** Supported verification methods:
  1. `transaction_invitation`: the review came from a unique invitation created from a transaction record (005 automatic methods).
  2. `reference_match`: the consumer's reference matches a transaction reference the business supplied (hashed) through integration or API.
  3. `document_proof`: the consumer uploads an invoice, receipt, booking confirmation, e-ticket, or order confirmation (PDF, JPEG, PNG, HEIC, or `.eml`).
  4. `payment_link` *(Phase 2)*: the consumer links a payment-provider or open-banking transaction to the review.
- **FR-004-02** Verification can be requested when the review is submitted, or at any time afterwards while the review is published. Verifying a review does not re-open it for moderation unless the proof contradicts the review, for example a different business.
- **FR-004-03** Verification must be available on **all** plans, including businesses that are unclaimed or on the free plan (P1).

### Document proof processing
- **FR-004-04** Uploads: max 3 files per verification, each ≤ 10 MB. Files must be malware-scanned and type-checked by content, not by extension.
- **FR-004-05** The system must extract the merchant name/domain, transaction/booking date, a reference number, and (optionally) the amount and currency, and must record a confidence score for each field.
- **FR-004-06** A proof is **auto-approved** only if all of these hold: the merchant matches the Business (name, domain, or a registered alias) with high confidence; the transaction date is within 12 months and not after the date of experience + 30 days; the reference hasn't been used before (FR-004-10); and tamper checks pass (FR-004-07).
- **FR-004-07** Tamper checks must include at least: file metadata inconsistencies, editing-software signatures, duplicate or near-duplicate image hashes across different accounts, and templates known to be fake. Any failure sends the proof to human review.
- **FR-004-08** Proofs that fail auto-approval go to a **verification queue** (006 tooling). Target: decision within 2 business days. Staff see the proof, the extracted fields, and the review, and approve or reject with a reason code.
- **FR-004-09** The consumer must be able to redact parts of an image (e.g., card numbers, addresses) before uploading. The system must automatically mask anything that looks like a full card number (PAN) before storing it.

### Anti-reuse
- **FR-004-10** Each verification stores a **proof fingerprint**: a keyed hash (HMAC with a secret key) of normalised `business_id + reference` (and, for documents, a perceptual hash of the file). One fingerprint can verify **only one** review, except that lifecycle updates inherit their parent review's verification.
- **FR-004-11** Reuse attempts must be rejected and sent to fraud signals (006).

### Reference matching
- **FR-004-12** Businesses may submit transaction records through the **Business API** or with **BCC** email forwarding (005) in Phase 1. Travel booking-engine/GDS connectors come in Phase 2, after partners are signed. Records contain: HMAC hash of the reference, HMAC hash of the customer email, transaction date, and optionally product SKUs. Plaintext references are never required.
- **FR-004-13** A consumer-entered reference matches when both the reference hash and the consumer's verified-email hash match a record dated within 12 months. A reference match without an email match goes to human review instead.

### Attestation (cryptographic tie)
- **FR-004-14** Every approved verification must produce an **attestation record** signed with a Platform signing key (asymmetric, key ID included). It contains: attestation ID, review ID, business ID, method, proof fingerprint, experience month (not the exact date), decision time, and methodology version.
- **FR-004-15** A public endpoint and page must let anyone check an attestation's signature and revocation status given the attestation ID. The public view must not include the fingerprint in a form that can be reversed, or any personal data.
- **FR-004-16** Signing keys must be rotatable. Old public keys stay published so older attestations can still be checked.
- **FR-004-17** An attestation may be **revoked** by staff (e.g., fraud found later) with a reason code. Revocation removes the badge within 60 s and is recorded in a public revocation list (ID + date + reason category).

### Business verification requests
- **FR-004-18** A business user (Responder or above) may request verification on any **unverified** review of their Business, at most once per review.
- **FR-004-19** The reviewer is notified and can (a) verify privately, (b) verify **and** consent to share a specific field (reference number only) with the business, or (c) ignore the request.
- **FR-004-20** Ignoring a request must **not** remove, hide, or down-rank the review. After 14 days without a response, the review page may show nothing new. The business may then flag it under 006 rules.
- **FR-004-21** A business may send at most 20 verification requests per 1,000 published reviews in any rolling 30 days (minimum 5). Requests targeting negative reviews disproportionately (> 80% of requests on 1–2★ reviews over 30 days, with ≥ 10 requests) must be surfaced to staff for misuse review.

### Display & data
- **FR-004-22** The badge text is **"Verified Experience"** everywhere. The badge links to the attestation check page (FR-004-15).
- **FR-004-23** Raw proof files must be deleted within **30 days** of the decision (approved or rejected). Extracted fields other than those in the attestation must be deleted at the same time.
- **FR-004-24** The Business's **verification percentage** (verified published reviews ÷ all published customer reviews, rolling 12 months) must be computed and made available to 008 and 015.
- **FR-004-25** The public methodology page must describe each method, what is checked, and what is kept (P4).

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Upload is empty, corrupt, password-protected PDF, or > 10 MB | Reject with a specific message. |
| Proof names a different business | Reject verification. Offer to move the review to the correct business (the author confirms). |
| Proof date is after the date of experience + 30 days, or > 12 months old | Reject, or send to human review if the difference is ≤ 7 days (timezone or processing delays). |
| E-ticket or booking confirmation issued by an **agency** for a flight operated by an airline | Accepted for either business: the agency (as seller) or the airline (as operator, matched by flight number/airline code on the document). |
| Proof shows a **refund or cancellation** only | Accepted for verification (it still shows a real interaction). The method is recorded as `document_proof:refund`. |
| Same receipt uploaded by two different accounts | Second use rejected. Both accounts go to fraud review. |
| Reviewer tries to verify a review that is held or removed | Not allowed until it is published. |
| Business submits transaction records with plaintext emails | Reject the API request (400) and tell them to hash first. |
| Business submits a huge batch (> 10,000 records per request) | Reject. Max 10,000 per request, 1M per day per Business. |
| Duplicate transaction records | Upsert by reference hash. |
| Attestation check for an unknown ID | 404 with no information about whether the ID ever existed. |
| Staff member approves their own review's proof | Blocked (conflict rule). |
| Consumer deletes their account | Attestations are revoked with the reason `review_deleted`, and fingerprints are kept (without personal data) to prevent reuse. |
| Unauthorized: a business tries to view an uploaded proof | 403. Proofs are never exposed to businesses. |

## 5. Out of Scope

- Checking consumers' legal identity.
- Storing full payment card data. Any detected PAN is masked, and the Platform is not in PCI scope for storing card data.
- Blockchain/distributed-ledger anchoring of attestations. Signed attestations with a public key and revocation list are enough.
- Payment-provider linking (`payment_link`) in Phase 1. It is specified here only so the data model supports it.
- Verifying **insider/employee** status (013).

## 6. Acceptance Criteria

- [ ] All Phase 1 methods produce a correct signed attestation and badge.
- [ ] Auto-approval rules are covered by tests for every condition in FR-004-06, and each failure path lands in the verification queue.
- [ ] Reusing a proof fingerprint is rejected (test with the same receipt from 2 accounts).
- [ ] The attestation check page confirms valid signatures, detects tampered payloads, shows revocations, and exposes no personal data.
- [ ] Key rotation keeps old attestations checkable.
- [ ] A scheduled job deletes raw proof files ≤ 30 days after the decision (tested with time travel).
- [ ] Business verification requests: limits enforced, ignored requests never hide reviews, and disproportionate-targeting alerts fire.
- [ ] Businesses cannot access proofs (authorization test).
- [ ] The verification percentage matches a hand-calculated fixture.

## 7. Dependencies & Open Questions

- **Decided (2026-09-24):** Phase 1 transaction sources are **document upload + Business API + BCC only**. There are no platform-specific connectors.
- **Q1:** Which travel booking-engine/GDS and payment partners come first in Phase 2? This is a commercial decision.
- **Q2:** Should document verification use an OCR/extraction vendor (processor under GDPR) or run in-house? Decide in plan.md, and include it in the DPIA.
- **Q3:** Should reviews verified with a refund-only proof get a distinct public sub-label?
