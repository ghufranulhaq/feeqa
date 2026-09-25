# Project Constitution: Trust & Review Platform

> This file is the **constitution** of the project. It is imported into `CLAUDE.md`, so AI agents always load it. It holds the principles, constraints, and definition of done that every spec, plan, task, and line of code must follow.
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
| Stage | **Client demo** first, showing **all** features on a single server at `feeqa.appsarray.com`. Demo-only relaxations are defined in §5.6. |

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
- Least privilege, compliance logging of staff decisions (§5.1), encryption in transit and at rest.
- No secrets in the repo.
- Signed attestations for anything labelled "verified" or "tamper-proof".

### P10. Testable requirements
- If a requirement can't be tested, it isn't a requirement. Rewrite it.
- Every FR must be specific enough that an implementation that ignores it fails a test.

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
- **One application layer:** every user action (web UI, JSON API, jobs, console) runs through the same application actions. The web UI calls them from its page controllers. The external JSON API (spec 016) calls them from API controllers, and its contract is defined in OpenAPI before it is implemented.
- The technology stack is recorded in §10 of this file. Once recorded, changing it requires an amendment.
- Business logic lives in application actions and domain classes, not in UI components, controllers, or database triggers.
- Score calculations (Review Score, Trust Index) are **pure, deterministic, versioned functions**: the same inputs and methodology version always give the same output. Daily score snapshots are stored (spec 008).
- **Compliance log (minimal):** only **staff moderation and enforcement decisions**, the **statements of reasons** sent to users, **appeals** and their outcomes, and **legal sign-offs** are recorded (who, what, when, reason code). There is **no** general change/activity log.
- **External providers are configured by environment only.** AI, OCR/extraction, mail, storage, payments, and malware scanning are reached through a driver chosen in `.env`. Switching a provider or model needs no code change. Each has a `fake` driver for tests and demos.

### 5.2 Quality budgets
| Area | Budget |
|------|--------|
| Public profile page | p95 server response ≤ 500 ms; LCP ≤ 2.5 s on mid-range mobile over 4G |
| Public API read endpoints | p95 ≤ 300 ms at 200 req/s per node |
| Widget script | ≤ 30 KB gzipped; must not block host page rendering |
| Availability (public read paths) | 99.9% monthly |
| Review Score recalculation | Visible within 60 s of a review being published |

The **availability** and **per-node throughput** budgets apply from **public launch**. The single-server **client demo** is exempt from them. All other budgets apply to the demo too.

### 5.3 Data handling
- All personal data can be **exported** (machine-readable JSON) and **erased** within 30 days of a verified request.
- Raw proof files: delete **within 30 days after the verification decision**. Keep the signed attestation (hashes, proof type, issuer/merchant, decision, timestamp).
- Voice/video originals: keep while the review is live. Delete within 30 days after the review is deleted.
- Compliance log entries: keep for 6 years, with personal data pseudonymised when the account is erased.

### 5.4 Security
- Authentication: email + password (min 12 characters, or 6 in the demo environment per §5.6, checked against known-breached passwords), passwordless email link, and OAuth (Google, Apple, Facebook). Two-factor authentication is **not** part of the Platform.
- Role-based access control for business accounts and staff (spec 001).
- Rate limiting on every write endpoint and every public API.
- All uploads are scanned for malware and checked for content type before processing.
- Complete the OWASP ASVS Level 2 checklist before public launch.

### 5.5 Internationalisation
- All user-facing strings are externalised. Dates, numbers, and currencies are formatted per locale.
- **Launch locale:** English (`en-GB`). The architecture must support adding locales (including right-to-left scripts) without code changes.
- **Currencies:** GBP and EUR at launch (ISO 4217 throughout).
- Reviews keep their original language. Machine translation, when available, is labelled.

### 5.6 Demo environment (`APP_ENV=demo`)
The client demo runs with `APP_ENV=demo`. The relaxations below are allowed **only** when `APP_ENV` is `demo` (or `local`/`testing` for development). They must be **impossible** when `APP_ENV=production`, and an automated test must prove it.

**Conditions that make the relaxations acceptable:**
- The **whole demo site is behind one shared password**, and every page tells search engines not to index it.
- Demo data uses **fictional businesses and people only**. It never contains real companies, real reviews, or real personal data.
- Anyone given demo access is told not to enter real personal data, because the demo AI provider (DeepSeek API) processes data outside the UK/EU.

**Relaxations:**
| Area | Production rule | Demo |
|------|-----------------|------|
| Staff console access | IP allow-list | Any IP |
| Minimum password length | 12 characters | 6 characters |
| Proof extraction, transcription, malware scanning | Real providers | `fake` drivers allowed. Fake verification issues normal **Verified Experience** badges with real labels |
| Payments | Real payment gateway | **Simulated**: every payment attempt succeeds, and no gateway is contacted |
| Legally gated features (L5) | Need a recorded legal sign-off | Switched on without sign-off |
| Review-update windows (003) | 30 d / 6 m / 1 y windows | Always open |
| Inactive cases (010) | Auto-close after 30 days | Stay open |
| Daily score snapshots (008) | Required | Not taken (history is back-filled once by the demo seeders) |
| Proof-file deletion (§5.3) | Within 30 days | Not deleted |
| Backups | Nightly | None (demo data can be recreated from seeders) |
| Certificate expiry check | Daily | None |
| Availability budget (§5.2) | 99.9% | Not applicable |
| Demo seeders | Never run | Allowed |

---

## 6. Testing Standards

- **Unit tests** for all domain logic. Score algorithms need property-based tests plus fixed golden datasets.
- **Contract tests** for every external JSON API endpoint (spec 016) against its OpenAPI definition.
- **Integration tests** for each user scenario in the specs (one test per scenario, at minimum).
- **End-to-end tests** for the critical journeys: sign up → write review → verify → publish; claim business → invite → reply; flag → moderate → appeal; open case → resolve → resolution rating.
- **Accessibility tests** (automated browser check for serious WCAG issues + manual keyboard/screen-reader check) for every new page.
- Coverage target: **≥ 85% line coverage for domain modules**. Coverage is not the goal. FR coverage is (every FR ID appears in at least one test).
- Test data must be synthetic. Never use real people's reviews or data scraped from other platforms.

---

## 7. Definition of Done (Project-Wide)

A task, feature, or spec counts as **done** only when **all** of the following are true, in addition to the spec's own acceptance criteria:

- [ ] Every FR in scope has passing automated tests that reference its FR ID.
- [ ] Every user scenario in the spec has a passing integration or E2E test.
- [ ] Edge cases listed in the spec are covered by tests.
- [ ] API changes are reflected in the OpenAPI contract and contract tests pass.
- [ ] Lint, type-check, and all test suites pass with `make ci`. No skipped tests without a linked issue.
- [ ] Security: no new high/critical findings from dependency and static analysis scans. Authorization checked for every new endpoint.
- [ ] Accessibility: WCAG 2.2 AA automated checks pass. Manual keyboard check done for new UI.
- [ ] Performance budgets (§5.2) are still met.
- [ ] Every new staff moderation or enforcement action writes a compliance log entry and sends a statement of reasons.
- [ ] Personal data added by the feature is included in export and erasure (§5.3).
- [ ] All user-facing strings are externalised (§5.5). AI output is labelled (P7).
- [ ] Docs updated **in the same change** (§8 rule 9): `README.md`, the affected user guides in `docs/user-guides/`, the system overview in `docs/system-overview/`, spec status, public methodology/help pages if user-visible behaviour changed, and the changelog. `make docs-check` passes.
- [ ] Code reviewed and approved by at least one other person.
- [ ] Deployed to the demo/staging server and smoke-tested.

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
9. **Keep the documentation current automatically, as part of every change, without being asked.** Whoever changes behaviour, setup, configuration, commands, or deployment updates the matching documents in the **same change**:
   - `README.md`: developer setup from scratch, demo deployment and updates, `.env` settings for local and demo, and common commands;
   - `docs/user-guides/`: one guide per user type, for anything users see or do differently;
   - `docs/system-overview/`: what the system is and what it does, for any new or changed feature.

   `make docs-check` (part of `make ci`) enforces what can be checked automatically. A change whose docs are out of date is **not done** (§7).

---

## 9. Amendments

- To change this constitution, open a PR titled `constitution: <change>`. The PR states the reason, the principles affected, and the specs that need updating.
- The PR needs approval from the product owner and the tech lead.
- Record every amendment in §12 with the date and a summary.

---

## 10. Recorded Technical Decisions

| Date | Decision | Spec / Plan |
|------|----------|-------------|
| _Pending approval_ | Technology stack (language, framework, database, hosting), as proposed in the platform plan. Recorded here once approved. | [`specs/plan.md`](specs/plan.md) |
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
| 2026-09-24 | v1.1 after the client interview: launch scope (travel, UK+EU, English, web only); P11 operator neutrality and data firewall (removed in v1.2); L3 narrowed to launch markets + UK261/EU261; L5 rewards made status-only and legal gate added for "resolve first". |
| 2026-09-25 | v1.2: P11 (operator neutrality) removed, because the Platform is fully independent and the operator has no stake in listed businesses. Append-only audit events replaced by a minimal compliance log. "API-first" reworded to "one application layer" (the web UI uses page controllers; OpenAPI applies to the external JSON API). Provider-by-environment rule added. Availability budget deferred to public launch. |
| 2026-09-25 | v1.3: two-factor authentication removed from all requirements (client correction). |
| 2026-09-25 | v1.3.1: constitution moved from `CLAUDE.md` to `CONSTITUTION.md` (content unchanged). `CLAUDE.md` imports it so Laravel Boost can manage its own section. |
| 2026-09-25 | v1.4: §5.6 demo environment added (`APP_ENV=demo` relaxations, their conditions, and the rule that they are impossible in production). Password minimum 6 in demo only. Launch-scope stage row updated. |
| 2026-09-25 | v1.5: documentation rule added (§8 rule 9, §7): README, user guides (`docs/user-guides/`) and system overview (`docs/system-overview/`) must be updated in the same change as the behaviour they describe. `make docs-check` enforces what it can. |
