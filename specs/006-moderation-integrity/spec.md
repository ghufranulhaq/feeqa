# Spec 006: Moderation, Integrity & Transparency

**Status:** Draft · **Phase:** 1 (MVP) · **Depends on:** 001, 002, 003
**Client requirements:** GTM Radical transparency (published moderation policies, appeal stats, ranking factors)

## 1. Goal

Keep fake, harmful, and manipulated content off the Platform, and make every decision explainable and appealable. Automated detection catches most abuse before it goes live. People handle flags, grey areas, and appeals. Businesses and reviewers who game the system face a published, graduated set of penalties. Everything is reported publicly.

## 2. User Scenarios

1. **Automated catch.** When a new account on a known VPN posts a 5★ review that is 92% similar to three other reviews posted in the last hour, the review is **held**. A moderator confirms coordinated fraud, the reviews are removed, the accounts are blocked, and the author gets a statement of reasons with an appeal link.
2. **Consumer flags harmful content.** When a reader flags a review for including a staff member's phone number, the review is **temporarily blurred** in under a minute. A moderator removes the phone number (edits it as `[removed]`) and restores the review. The author is notified.
3. **Business flags a review.** When a business flags a review as "not a genuine experience", it must pick a reason and can attach evidence. The review stays visible while it is assessed. If the flag is rejected, the business sees the decision and reason, and can appeal once.
4. **Business misuse.** A business is found buying reviews. After the enforcement ladder is followed, the profile shows a **Consumer Warning**, the Review Score and Trust Index are hidden, the fake reviews are removed, the paid plan is suspended, and the warning stays for at least 6 months.
5. **Appeal.** A reviewer whose review was removed appeals with extra context. A **different** moderator re-assesses it within 7 days, restores the review, and the decision is logged.
6. **Transparency.** Anyone can open the Transparency Center and see the current guidelines, enforcement actions (with counts), the quarterly transparency report (removals by reason, detection method, appeal outcomes, median handling times), and the ranking and score methodology.

## 3. Functional Requirements

### Guidelines
- **FR-006-01** The Platform must publish versioned **Reviewer Guidelines** and **Business Guidelines**. They cover: genuine experience, 12-month window, one review per experience, 18+, no incentives, no conflicts of interest, and prohibited content (FR-006-02). Each version is dated, and old versions stay available.
- **FR-006-02** Prohibited content categories (reason codes): `harmful_illegal` (hate, threats, violence, obscenity, defamation, terrorism), `personal_info`, `advertising_spam`, `not_genuine`, `incentivised`, `conflict_of_interest`, `wrong_business`, `off_topic` (political/religious advocacy unrelated to the experience), `ai_generated_deceptive`, `ip_infringement`, `other_illegal`.

### Automated screening
- **FR-006-03** Every review, update, reply, case message, profile edit, and media item must be screened before publication. The screening produces a risk score (0–1), triggered rule IDs, and a recommendation (`publish`, `hold`, `reject`).
- **FR-006-04** Screening signals must include at least: account age and history, device/network reputation, velocity (reviews per account, per business, per IP range), text similarity across reviews, known fake-review vendor patterns, incentive language, personal-info detection, toxicity, links, and rating pattern anomalies on the business.
- **FR-006-05** Automatic **reject** is allowed only for documented high-precision rules (e.g., exact duplicate text across ≥ 3 accounts, known fraud device fingerprint, malware, CSAM hash match). Each rule has a precision measured at ≥ 99% on the audit sample (FR-006-20). Everything else that is risky goes to **hold**.
- **FR-006-06** Business-level anomaly detection must run at least hourly. It looks for review spikes (> 5× the 30-day daily median with ≥ 20 reviews), sudden rating shifts, and clusters of new accounts. It raises an **incident** for staff. Staff may freeze new reviews on the Business for up to 72 hours while investigating, and the profile shows a neutral notice "We're checking unusual activity on this profile".

### Flagging (notice-and-action)
- **FR-006-07** Anyone (signed in or not) may flag a review, reply, media item, or profile by choosing a reason code, with optional details (≤ 1,000 characters) and optional evidence files. Non-signed-in reporters must give an email to receive the outcome (required for EU DSA notices).
- **FR-006-08** Flags with reason `harmful_illegal` or `personal_info` must **blur** the item automatically, pending review, when the reporter is trusted or ≥ 3 distinct reporters flag it. They must be handled within **24 hours**. All other flags are handled within **7 days**.
- **FR-006-09** Business flags follow the same reasons. A flag of `not_genuine` must also run the automated analysis again. **A business flag must never hide a review by itself.**
- **FR-006-10** Each Business may have at most **50 open flags** at a time, plus per-plan rate limits. Businesses whose flags are rejected more than 80% of the time (≥ 20 flags in 90 days) get an educational notice and then flagging restrictions (FR-006-14).

### Moderation console (staff)
- **FR-006-11** Staff queues: held content, flags, verification proofs (004), business incidents, appeals, and profile change approvals (002). Each queue can be filtered by priority, SLA, locale, and category. Items can be assigned.
- **FR-006-12** Staff actions: publish, remove, redact (replace a span with `[removed]`), request verification, mark as not genuine, block account, restrict business feature, apply or lift a Consumer Warning, merge duplicates, freeze reviews. Every action requires a reason code and writes a **compliance log** entry (constitution §5.1).
- **FR-006-13** Every action against a user's content or account must send a **statement of reasons**: what was affected, reason code, the guideline section, whether automation was used, and how to appeal.

### Enforcement ladder
- **FR-006-14** Business ladder (each step is recorded):
  1. educational notice → 2. formal warning → 3. final notice → 4. feature restrictions (flagging, invitations, profile edits) → 5. **Consumer Warning**: public banner, Review Score and Trust Index hidden, paid features suspended, reply/flag-only access, minimum 6 months → 6. termination / legal referral.

  Staff may skip steps for severe or proven fraud (e.g., buying reviews) with Senior Moderator approval.
- **FR-006-15** Reviewer ladder: educational notice → warning → account block. Severe cases (fraud rings, threats) go straight to a block.
- **FR-006-16** A **Consumer Warning** must show on the profile header and in search results. It includes: the date applied, a plain-language reason (e.g., "We found evidence this business bought fake reviews"), and a link to the enforcement policy. It must stay for at least 6 months and can only be lifted by a Senior Moderator after a documented review.
- **FR-006-17** When content is removed for `not_genuine` or `incentivised`, it must be removed from all scores, and scores recalculated (008).

### Appeals
- **FR-006-18** Every affected party may appeal a moderation decision **once**, within 30 days of the decision, with a statement (≤ 2,000 characters) and optional evidence.
- **FR-006-19** Appeals must be reviewed by a staff member **other than** the original decision-maker, within **7 days**. The outcome (upheld / overturned) and reason are sent to the appellant. Overturned decisions are reversed fully, including scores.

### Quality & transparency
- **FR-006-20** At least 2% of automated decisions (publish and reject) must be randomly sampled every week for human audit. Precision/recall per rule is tracked, and any auto-reject rule that falls below 99% precision is disabled automatically.
- **FR-006-21** The **Transparency Center** must publish:
  - (a) guidelines (all versions);
  - (b) enforcement policy;
  - (c) score and ranking methodology (links to 008 and 009);
  - (d) verification methodology (004);
  - (e) a **quarterly transparency report** with: reviews submitted/published/removed by reason code, % detected automatically vs. by flag, flags received and handled by reporter type, median time to action, appeals and overturn rates, Consumer Warnings issued, accounts blocked, and government/legal requests;
  - (f) a list of businesses currently under Consumer Warning.
- **FR-006-22** Transparency report figures must be generated by a reproducible job from the compliance log, flags, appeals, and content records. Numbers must match the underlying records.

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Flag with no reason code | Reject. |
| Flag details > 1,000 characters or evidence > 5 files / 10 MB each | Reject. |
| The same user flags the same item twice | Second flag is a no-op; return the existing flag status. |
| Mass flagging by one account (> 20 flags / hour) | Rate-limit, and treat as a fraud signal. |
| Business flags every negative review | Covered by FR-006-10 thresholds and business ladder. |
| Reviewer edits a review while it is under a flag | The flag stays open. The moderator sees the diff. |
| Review mentions an employee by first name only | Allowed (not personal info) unless combined with other identifying data or harassment. |
| Review is defamatory under a legal notice from a court | Handle under `other_illegal` with a legal hold. Geo-restrict only if the order is jurisdiction-specific. Count in the transparency report. |
| Appeal after 30 days | Reject as late, except for staff override. |
| Moderator tries to moderate content about a business where they have a conflict | Blocked. Staff record conflicts in their profile. |
| Unauthorized: a Responder requests another Business's queue | 403. |
| Automated system unavailable | Reviews stay in `pending`. They are **never** auto-published without screening. |

## 5. Out of Scope

- Fact-checking the claims made in reviews. The Platform judges whether an experience is genuine and allowed, not whether the opinion is true (matches the public integrity notice).
- Hiding reviews based on legal threats that aren't valid court orders.
- Business-paid "review removal" services of any kind.
- Proactive monitoring of reviews on other platforms.

## 6. Acceptance Criteria

- [ ] Every content type is screened before publication, and nothing publishes when screening is down.
- [ ] Auto-reject only happens through registered high-precision rules. Weekly audit sampling and automatic disabling of rules work.
- [ ] Blur-on-flag and SLA timers work for harmful/personal-info flags.
- [ ] Business flags never hide content on their own (test).
- [ ] Each ladder step is enforceable and visible. The Consumer Warning hides both scores and shows on the profile and in search.
- [ ] Every punitive action sends a statement of reasons with an appeal link. Appeals go to a different staff member.
- [ ] The Transparency Center pages exist, and a generated quarterly report reconciles with the underlying records.

## 7. Dependencies & Open Questions

- **Decided (2026-09-24):** a small in-house team (2–4 moderators, UK business hours), with automation doing most of the work. SLAs stay as specified. The 24-hour harmful-content SLA holds overnight because of automatic blurring (FR-006-08). Launch locale is English only, so launch lexicons and models are English-first.
- **Q2:** Is the Platform expected to be designated under EU DSA thresholds? This affects out-of-court dispute settlement obligations.
- **Q3:** Should the list of businesses under Consumer Warning be searchable, or only visible on profiles?
