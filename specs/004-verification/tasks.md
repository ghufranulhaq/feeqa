# Spec 004 Tasks: Proof-of-Experience Verification ("Verified Experience")

Each task is implemented, tested, and committed on its own before the next
one starts (constitution §2, §8). Task order follows dependency, not FR
number. This spec depends on 005 (invitations) for the `transaction_invitation`
method's real trigger and on 006 (moderation) for the full staff queue UI —
neither is built yet. Both are built as honest placeholders here, same
pattern as specs 002 and 003: a minimal, real decision path exists (staff
can approve/reject a proof with a reason code today, through a plain
endpoint), and the richer console/automation lands when those specs are
built. `app/Drivers/Verification` (extraction) and `app/Drivers/Signing`
(attestation signing) already exist from earlier plan work — this spec
wires them up rather than creating them.

- [x] **T1. Verification schema & domain model.** `review_verifications`
      table (review_id, method, status, business_verification_request_id
      nullable, reference_number nullable, extracted fields json nullable,
      confidence nullable, proof_fingerprint nullable+unique, decided_by
      nullable, decided_at nullable, decision_reason_code nullable,
      timestamps). `verification_attestations` table (uuid id, review_id,
      business_id, verification_id, method, experience_month (date, first
      of month), decision_time, methodology_version, jws text, revoked_at
      nullable, revoked_by nullable, revoked_reason_code nullable).
      `business_transaction_records` table (business_id, reference_hash,
      email_hash, transaction_date, skus json nullable, unique
      business_id+reference_hash for upsert). `business_verification_requests`
      table (business_id, review_id unique, requested_by, requested_at,
      status, responded_at nullable, consumer_response nullable,
      shared_reference_number nullable). Domain enums in
      `app/Domain/Verification/`: `VerificationMethod`
      (transaction_invitation/reference_match/document_proof/payment_link),
      `VerificationStatus` (pending/approved/rejected). Models + factories
      for `ReviewVerification` and `VerificationAttestation`. FR-004-01.
- [x] **T2. Proof fingerprint & tamper signals (pure domain).** Pure,
      unit-tested classes: `ProofFingerprint` (keyed HMAC of normalised
      business_id+reference, and a document variant that folds in a
      perceptual hash), `PerceptualHash` (average-hash over GD, reused
      from the existing image pipeline), `EvaluateAutoApproval` (FR-004-06:
      merchant match, date window, fingerprint-not-reused, tamper checks
      all hold → approve; otherwise → hold, with the specific failing
      reason recorded). Tamper checks (FR-004-07): EXIF `Software` tag
      against an editing-tool blocklist, near-duplicate perceptual hash
      across *different* accounts, and a configurable known-fake-template
      hash list. FR-004-06, FR-004-07, FR-004-10.
- [x] **T3. Document proof upload.** `RequestDocumentVerification` action:
      file count/size validation (≤3, ≤10MB), content-type sniffing (not
      extension) for PDF/JPEG/PNG/HEIC/.eml, corrupt/password-protected
      PDF rejected with a specific message, malware scan (existing
      `MalwareScanner`), PAN detection on any extracted text (reject
      storage rather than attempt pixel-level masking — documented MVP
      limitation, see T3 docs note) per FR-004-09, calls
      `VerificationExtractor`, computes the fingerprint and runs T2's
      auto-approval evaluation, issues an attestation on approval (T4) or
      files into the pending queue. Reused fingerprints are rejected for
      both accounts (edge case table). Route + controller (consumer,
      `auth` + author-only, review must be published). FR-004-02 through
      FR-004-09, FR-004-11, edge cases (empty/corrupt/oversized upload,
      wrong business named, date out of window, agency/airline shared
      e-ticket, refund-only proof, reused receipt, unpublished review).
- [x] **T4. Attestation issuance, public check & revocation.** Built ahead
      of T3 (see T3's own note) since T3 depends on `IssueAttestation`. `IssueAttestation`
      action (signs the payload with the existing `SigningService`,
      stores `verification_attestations`, flips the review's verified
      badge on). Public endpoint + Inertia page: check an attestation by
      ID — verifies the JWS, reports revocation status, exposes no
      fingerprint or personal data; unknown ID → 404 with no signal about
      whether it ever existed. `RevokeAttestation` staff action (reason
      code, compliance log entry, badge gone within 60s — tested via the
      review's computed verified state, not a queue/worker). Key rotation
      test: an attestation signed under an old key id still checks out
      after a new key is generated and made active. FR-004-14 through
      FR-004-17, FR-004-22, edge cases (unknown attestation ID, staff
      approving their own review's proof — blocked).
- [ ] **T5. Verification queue (staff decision).** `DecideVerificationProof`
      action mirroring `ReviewBusinessClaim`'s shape: staff approve/reject
      a pending `review_verifications` row with a reason code, writes a
      compliance log entry, notifies the reviewer, and (on approve) calls
      T4's `IssueAttestation`. Route + controller under `routes/staff.php`.
      This *is* spec 006's "verification proofs" queue item type, built
      minimally now (list + decide) rather than waiting for 006's full
      console — same relationship as 003's screening-hold state waiting on
      006's moderation UI. FR-004-08, edge case (staff can't approve their
      own review's proof).
- [ ] **T6. Reference matching.** `SubmitTransactionRecords` action/endpoint
      under the business dashboard (`business/{business}/transaction-records`,
      `auth`+`SetPermissionTeam`): rejects plaintext emails (400), enforces
      the 10,000-per-request / 1M-per-day limits, upserts by reference
      hash. `MatchReviewReference` action: consumer-entered reference +
      verified-email hash matching a record within 12 months auto-verifies
      (method `reference_match`); a reference match without an email match
      goes to the T5 queue instead of auto-rejecting. Documented as
      covering the "Business API" data shape this FR asks for; the actual
      external, token-authenticated Business API contract is spec 016's
      job, same dependency relationship as spec 005 has with `bcc`/`api`
      invitation methods. FR-004-12, FR-004-13, edge cases (plaintext
      email batch, oversized batch, duplicate records).
- [ ] **T7. Business verification requests.** `RequestReviewVerification`
      (business side, Responder+, once per review, rate-limited 20 per
      1,000 published reviews per rolling 30 days with a 5-request floor,
      disproportionate-1★/2★-targeting flagged to a staff-visible list) and
      `RespondToVerificationRequest` (reviewer: verify privately / verify
      and share the reference / ignore — ignoring never hides or
      down-ranks the review). Notifications both ways. FR-004-18 through
      FR-004-21.
- [ ] **T8. Verification percentage.** A pure, tested
      `Business::verificationPercentage()` (verified published customer
      reviews ÷ all published customer reviews, rolling 12 months),
      exposed as a hook for specs 008/015 to read later — same "built now,
      filled later" shape as `RecalculateBusinessScore`. FR-004-24.
- [ ] **T9. Raw proof file deletion job + demo relaxation.** A scheduled
      command deletes stored proof files (and any extracted-field data
      beyond what the attestation keeps) 30 days after the verification
      decision. `Environment::rawProofFilesDeletedOnSchedule()` — always
      true in production whatever `.env` says, off in demo per constitution
      §5.6 (proof files aren't deleted in the demo), tested the same way
      as the existing lifecycle-window relaxation. FR-004-23, constitution
      §5.6.
- [ ] **T10. Docs pass + acceptance sweep.** `docs/system-overview/verification.md`,
      updates to the consumer and business user guides, README env vars
      (`VERIFICATION_FINGERPRINT_KEY` etc.), and the public methodology
      text addition for each method (FR-004-25) — placed wherever spec
      002/003's existing public pages already live, since 006/008's own
      methodology pages don't exist yet. Re-checks every acceptance
      criterion in spec.md §6, notes what's dependent on 005/006/008/016
      the same way spec 003's T17 did. FR-004-25, full acceptance sweep.
