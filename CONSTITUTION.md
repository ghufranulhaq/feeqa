# Project Constitution: Trust & Review Platform

> This file is the **constitution** of the project. It holds the principles, constraints, and definition of done that every spec, plan, task, and line of code must follow.
> If a spec conflicts with this file, **this file wins** until someone changes it on purpose (see §9 Amendments).

---

## 1. Mission

We are building an open consumer review platform ("**the Platform**") with the core capabilities of Trustpilot, and we are adding features that make reviews harder to fake and more useful:

- proof-of-experience verification
- complaint and resolution tracking
- a composite **Trust Index**
- side-by-side comparison
- rich media reviews
- reviewer ownership of their own data

We win on **authenticity, transparency, and resolution**, not on review volume.

### Launch scope (decided 2026-09-24)
| Decision | Value |
|----------|-------|
| Launch vertical | **Travel**: airlines, travel agencies / OTAs, airports. Businesses from **any** industry can be listed and reviewed. Only Travel is launched (shown in navigation and rankings) and seeded. Staff open further industries from the console (spec 002). |
| Launch markets | **UK + EU**. English only (`en-GB`). Prices in GBP and EUR. |
| Clients | **Responsive web only**: consumer site, business dashboard, staff console. Native apps come later on the same API. |
| Operator | The Platform is operated by **AeroTickets**, which also sells travel and is listed on the Platform (see P11). |

Key references:
- Client requirements: [`add-to-trustpilot.md`](add-to-trustpilot.md)
- Market research: [`docs/research/trustpilot-platform-analysis.md`](docs/research/trustpilot-platform-analysis.md)
- Specifications: [`specs/`](specs/README.md)

---

## 2. Development Method: Spec-Driven Development

1. **Spec first.** No feature code without an approved spec in `specs/NNN-name/spec.md`. The spec defines *what* and *why*. It must not name frameworks, databases, or vendors.
2. **Clarify.** Open questions stay in the spec's *Open Questions* section. They must be resolved (or explicitly deferred) before planning starts.
3. **Plan.** `specs/NNN-name/plan.md` defines *how*: architecture, data model, API contracts, and technology choices. It must follow §5 of this constitution.
4. **Tasks.** `specs/NNN-name/tasks.md` breaks the plan into small, ordered tasks you can check. Each task names the FR IDs it satisfies.
5. **Implement.** Build task by task. Tests come first, or together with the code (§6).
6. **Change control.** If a behaviour changes, **update the spec first**, then the plan, then the code. Code that does something the spec doesn't describe is a defect.

**Traceability:** every functional requirement has an ID, `FR-<spec#>-<nn>` (e.g., `FR-003-07`). Tests reference the FR IDs they cover. Commits and PRs reference the spec number.

---

## 3. Core Principles (Non-Negotiable)

### P1. Authenticity over volume
- A review must come from a real person who had a real experience.
- Verification is available to **every** reviewer. It is never limited to businesses on paid plans.
- The Platform never creates, buys, or ghost-writes reviews. It never sells services that do.

### P2. Money never buys trust
- Paid plans, ads, promoted listings, lead fees, and API contracts **must never** change these:
  - Review Score or Trust Index values
  - Review ordering on a profile
  - Moderation outcomes
  - Consumer Warnings
  - Comparison results
- Paid placements are always labelled **"Sponsored"** and kept visually separate from organic results.
- Ranking and score algorithms are the same for every business in the same category, whatever plan it is on.

### P3. Reviewers own their voice
- Businesses **cannot** edit, delete, hide, or delay publication of a review. They can only reply, flag it, request verification, or open a case.
- Only the author (edit/delete) or moderation (for a documented guideline breach) can change a review's visibility.
- Reviewers can export all their data and delete their account (§5.3).

### P4. Radical transparency
- These are always public: moderation guidelines, enforcement actions, how the Review Score and Trust Index are calculated (including weights and methodology version), the meaning of every label, and a periodic transparency report.
- Every moderation action against a user gives them a **statement of reasons** and a way to **appeal**.
- Every review shows its **source label** (see glossary).

### P5. Neutral collection
- Invitation features must not allow selective invitations, sentiment-based routing ("review gating"), or incentives from businesses.
- The system enforces these rules where it can. It does not only document them.

### P6. Privacy by design
- Collect the minimum data needed.
- Proof documents (receipts, invoices, employment proofs) are **private by default**. They are never shown publicly or given to businesses without the reviewer's explicit consent for that specific proof.
- Keep only derived attestations. Delete raw proof files on a fixed schedule (§5.3).

### P7. Explainable, supervised AI
- Every AI output (summaries, triage, fraud scores, reply suggestions, topic labels) is **labelled as AI-generated** where users see it.
- AI output can be traced to its inputs and model version.
- A human can override any AI output.
- AI never makes a final **punitive** decision on its own (removing a review, blocking an account, placing a Consumer Warning) unless a documented high-confidence automatic rule allows it. Those rules must be appealable and sampled for audit.

### P8. Accessible and inclusive
- Every user-facing surface meets **WCAG 2.2 AA**.
- Audio/video reviews need transcripts or captions before they are published.

### P9. Secure by default
- Least privilege, audit logging, encryption in transit and at rest.
- No secrets in the repo.
- Signed attestations for anything labelled "verified" or "tamper-proof".

### P10. Testable requirements
- If a requirement can't be tested, it isn't a requirement. Rewrite it.
- Every FR must be specific enough that an implementation that ignores it fails a test.

### P11. Operator neutrality
AeroTickets (the operator) sells travel and competes with businesses listed on the Platform.
- AeroTickets' own profile is treated **exactly like any other business**: same algorithms, moderation rules, and plan entitlements. Its profile carries a permanent public label, **"Owned by the Platform operator"**.
- AeroTickets may buy sponsored placements only **at list price**, and its slots are labelled **"Sponsored · Platform operator"**.
- **Data firewall:** only Platform staff roles (001) can access non-public data (invitations, cases, proofs, private analytics, customer data of any business). AeroTickets' commercial staff get **exactly** the access of a normal business account on the same plan. Every staff access to another business's non-public data is audit-logged, and access is reviewed each quarter.
- This commitment is published in the Transparency Center (006).

---

## 4. Brand, IP & Legal Constraints

| # | Constraint |
|---|------------|
| L1 | **No Trustpilot IP.** Do not use the names "Trustpilot", "TrustScore", or "TrustBox", Trustpilot's logos, star artwork, colour scheme, page copy, or help-centre text, and do not use data scraped from Trustpilot. We copy *capabilities*, never *assets*. |
| L2 | Platform terminology: **Review Score** (1.0–5.0 star-based), **Trust Index** (0–100), **Trust Widgets**, **Verified Experience**. |
| L3 | Follow the rules on fake reviews in the launch markets: **UK DMCC Act 2024 / CMA208** and **EU DSA + Omnibus Directive** (notice-and-action, statements of reasons, transparency reporting, disclosure of how reviews are verified). Design must also satisfy **US FTC 16 CFR Part 465** so that a US launch later needs no redesign. |
| L3a | Travel passenger-rights context: **UK261 / EU Regulation 261/2004** (delay/cancellation compensation, refunds within 7 days). The Platform does not give legal advice, but cases track refunds and compensation separately (010). |
| L4 | Follow data protection law: **GDPR / UK GDPR / CCPA-CPRA**. Keep a record of processing activities. Do a DPIA before launching proof uploads, insider verification, or voice/video. |
| L5 | **Insider reviews (013) and "resolve first" (010) need written UK + EU legal sign-off before their feature flags are switched on in production.** Rewards (014) are **status-only**: no perks with monetary value. Adding monetary perks later needs a constitution amendment and legal sign-off. Business-funded or sentiment-based incentives are always forbidden. |
| L6 | Reviewers must be **18 or older**. The experience must be within **12 months** of the review, except for lifecycle updates to an existing review. |
| L7 | Payment, receipt, and employment proofs must never be sent to third parties other than processors named in the privacy notice. |

---

## 5. Technical Constraints

### 5.1 Architecture
- **API-first:** every user action in the UI goes through the same versioned public/private API. Contracts are defined in OpenAPI before they are implemented.
- The technology stack is chosen in the first `plan.md` (spec 001) and recorded in §10 of this file. Once recorded, changing it requires an amendment.
- Business logic lives in domain services, not in UI components or database triggers.
- Score calculations (Review Score, Trust Index) are **pure, deterministic, versioned functions** that can be replayed from stored events.
- Every state change to reviews, replies, cases, moderation decisions, and scores writes an **append-only audit event** (who, what, when, before/after, reason).

### 5.2 Quality budgets
| Area | Budget |
|------|--------|
| Public profile page | p95 server response ≤ 500 ms; LCP ≤ 2.5 s on mid-range mobile over 4G |
| Public API read endpoints | p95 ≤ 300 ms at 200 req/s per node |
| Widget script | ≤ 30 KB gzipped; must not block host page rendering |
| Availability (public read paths) | 99.9% monthly |
| Review Score recalculation | Visible within 60 s of a review being published |

### 5.3 Data handling
- All personal data can be **exported** (machine-readable JSON) and **erased** within 30 days of a verified request.
- Raw proof files: delete **within 30 days after the verification decision**. Keep the signed attestation (hashes, proof type, issuer/merchant, decision, timestamp).
- Voice/video originals: keep while the review is live. Delete within 30 days after the review is deleted.
- Audit events: keep for 6 years, with personal data pseudonymised when the account is erased.

### 5.4 Security
- Authentication: email + password (min 12 characters, checked against known-breached passwords), passwordless email link, and OAuth (Google, Apple, Facebook). TOTP/WebAuthn MFA is **required** for business admins and staff.
- Role-based access control for business accounts and staff (spec 001).
- Rate limiting on every write endpoint and every public API.
- All uploads are scanned for malware and checked for content type before processing.
- Complete the OWASP ASVS Level 2 checklist before public launch.

### 5.5 Internationalisation
- All user-facing strings are externalised. Dates, numbers, and currencies are formatted per locale.
- **Launch locale:** English (`en-GB`). The architecture must support adding locales (including right-to-left scripts) without code changes.
- **Currencies:** GBP and EUR at launch (ISO 4217 throughout).
- Reviews keep their original language. Machine translation, when available, is labelled.

---

## 6. Testing Standards

- **Unit tests** for all domain logic. Score algorithms need property-based tests plus fixed golden datasets.
- **Contract tests** for every API endpoint against its OpenAPI definition.
- **Integration tests** for each user scenario in the specs (one test per scenario, at minimum).
- **End-to-end tests** for the critical journeys: sign up → write review → verify → publish; claim business → invite → reply; flag → moderate → appeal; open case → resolve → resolution rating.
- **Accessibility tests** (automated axe scan + manual keyboard/screen-reader check) for every new page.
- Coverage target: **≥ 85% line coverage for domain modules**. Coverage is not the goal. FR coverage is (every FR ID appears in at least one test).
- Test data must be synthetic. Never use real people's reviews or data scraped from other platforms.

---

## 7. Definition of Done (Project-Wide)

A task, feature, or spec counts as **done** only when **all** of the following are true, in addition to the spec's own acceptance criteria:

- [ ] Every FR in scope has passing automated tests that reference its FR ID.
- [ ] Every user scenario in the spec has a passing integration or E2E test.
- [ ] Edge cases listed in the spec are covered by tests.
- [ ] API changes are reflected in the OpenAPI contract and contract tests pass.
- [ ] Lint, type-check, and all test suites pass in CI. No skipped tests without a linked issue.
- [ ] Security: no new high/critical findings from dependency and static analysis scans. Authorization checked for every new endpoint.
- [ ] Accessibility: WCAG 2.2 AA automated checks pass. Manual keyboard check done for new UI.
- [ ] Performance budgets (§5.2) are still met.
- [ ] Audit events are emitted for every new state change.
- [ ] Personal data added by the feature is included in export and erasure (§5.3).
- [ ] All user-facing strings are externalised (§5.5). AI output is labelled (P7).
- [ ] Docs updated: spec status, public methodology/help pages if user-visible behaviour changed, and the changelog.
- [ ] Code reviewed and approved by at least one other person.
- [ ] Deployed to staging and smoke-tested.

---

## 8. Working Rules for Claude (and Any Contributor)

1. Read the relevant `specs/NNN-*/spec.md` **before** writing code. If there is no spec, write or extend one first.
2. Do not implement behaviour the spec doesn't describe. If you need it, propose a spec change.
3. If a spec is ambiguous, add it to the spec's *Open Questions* and ask. Don't guess on anything touching P1–P6, legal (§4), or money.
4. Never weaken a principle to make a test pass or to ship faster.
5. Keep specs tech-agnostic. Technology decisions go in `plan.md`.
6. Keep changes small and traceable. One spec per branch where practical. Branch names are `NNN-short-name`.
7. Write in plain English. Use the glossary terms in §11 consistently.
8. When a feature touches scoring, moderation, or verification, also update the public methodology text described in spec 008 or 006.

---

## 9. Amendments

- To change this constitution, open a PR titled `constitution: <change>`. The PR states the reason, the principles affected, and the specs that need updating.
- The PR needs approval from the product owner and the tech lead.
- Record every amendment in §12 with the date and a summary.

---

## 10. Recorded Technical Decisions

| Date | Decision | Spec / Plan |
|------|----------|-------------|
| _TBD_ | Technology stack (language, framework, database, hosting). No constraints from the client: the first plan proposes one with rationale, and it is recorded here after approval. | `specs/001-accounts-identity/plan.md` |
| 2026-09-24 | Clients: responsive web only in Phase 1 | Constitution §1 |

---

## 11. Glossary

| Term | Meaning |
|------|---------|
| **Business** | A company/brand listed on the Platform. It is identified by a primary **verified domain** or a unique slug. |
| **Location** | A physical branch of a Business that can be reviewed separately. |
| **Claimed** | A Business profile controlled by at least one verified business user. |
| **Review** | A consumer's rating (1–5), title, text, date of experience, and optional structured answers/media about one experience. |
| **Experience** | One purchase, booking, or service interaction. One review per experience. |
| **Lifecycle update** | A dated follow-up (30 days, 6 months, 1 year) attached to an existing review. |
| **Source label** | How the review arrived: **Invited** (tied to a transaction invitation), **Redirected** (generic business link/QR), or **Organic** (written without business involvement). |
| **Verified Experience** | A badge showing the Platform checked proof of the experience and issued a signed attestation. It is independent of the source label. |
| **Insider review** | A review by a verified current or former employee/insider. It is shown separately and never counts toward customer scores. |
| **Review Score** | A time-weighted Bayesian average of star ratings, 1.0–5.0, shown with one decimal. |
| **Trust Index** | A 0–100 composite score (spec 008) built from the Review Score, verification, resolution, refunds, repeat satisfaction, and disputes. |
| **Case** | A tracked complaint about an experience, with a timeline (raised → replied → resolved → refunded) and optional mediation. |
| **Resolution rating** | The consumer's 1–5 answer to "How well did they resolve the issue?" when a case closes. |
| **Consumer Warning** | A public banner on a Business profile after confirmed serious misuse. |
| **Sponsored** | A paid placement label. It never affects scores or review ordering. |
| **Staff** | Platform employees (moderators, mediators, admins). |

---

## 12. Amendment Log

| Date | Change |
|------|--------|
| 2026-09-24 | Constitution v1.0 created. |
| 2026-09-24 | v1.1 after the client interview: launch scope (travel, UK+EU, English, web only); P11 operator neutrality and data firewall; L3 narrowed to launch markets + UK261/EU261; L5 rewards made status-only and legal gate added for "resolve first". |
