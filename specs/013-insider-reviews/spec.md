# Spec 013: Verified Insider Reviews (Employees & Insiders)

**Status:** Draft · **Phase:** 2 · **Depends on:** 001, 003, 006
**Client requirements:** F4 Anonymous Employee & Insider Reviews (Verified)
**Legal gate:** Needs written legal sign-off before launch (constitution L5).

## 1. Goal

Consumers, B2B buyers, and job seekers want to know how a company behaves from the inside: its culture, its ethics, and how it treats customers. Let verified current and former employees and insiders publish reviews **anonymously to the public** but **verified by the Platform**. Keep these reviews clearly separate from customer reviews so they never distort customer scores.

## 2. User Scenarios

1. **Employee verifies by work email.** When an employee opens a company's profile, chooses "I work here, write an insider review", and enters `j.doe@skyhop-travel.com`, they get a code at that address. They then choose a status (current or former) and a broad role family (e.g., "Customer Operations") and write the review. It publishes as **"Insider review · Verified current employee · Customer Operations · 2026"**, with no name.
2. **Former employee verifies with a document.** A former employee without a work email uploads a payslip or employment letter. A verification agent checks it, approves it, and the document is deleted within 30 days.
3. **Reader filters.** On the profile, a reader switches to the **Insider** tab and sees the separate Insider Rating (e.g., 3.4 from 38 insider reviews), topics (culture, management, ethics, customer treatment), and the reviews. Customer reviews and scores are unaffected.
4. **Business replies.** The company replies publicly to an insider review. It cannot see who wrote it.
5. **Re-identification risk.** The only "Legal" role family review at a 20-person company would identify the author, so the system suggests a broader role family ("Corporate Functions") and delays publication until the business has 3 insider reviews.

## 3. Functional Requirements

### Verification
- **FR-013-01** Insider verification methods:
  - (a) **work email** code on one of the Business's verified domains (002);
  - (b) **document proof** (employment contract, payslip, letter), reviewed by staff and handled under 004's document retention rules;
  - (c) **professional network sign-in** (e.g., LinkedIn), matching the current/past employer, *Phase 2b*;
  - (d) **payroll/HRIS integration**, *later phase*, only if the client confirms a partner.
- **FR-013-01a** Insider reviews may be written only for Businesses whose employee size band (002 FR-002-25) is **50 or more**. Businesses with `<50` or `unknown` don't show the insider option.
- **FR-013-02** Insider types: `current_employee`, `former_employee` (left ≤ 3 years ago), `contractor`, `other_insider` (e.g., franchisee, supplier employee). Each is shown in the label.
- **FR-013-03** Verification is valid for 12 months (current employees) and must be renewed before writing another insider review of the same Business.
- **FR-013-04** The verification record stores only: account ID, Business ID, insider type, role family, verification method, verified month, and expiry. Work email addresses are stored only as a keyed hash to prevent reuse, and never in plain text after verification.

### Anonymity & safety
- **FR-013-05** Insider reviews must **never** show the author's display name, avatar, country, or profile link. They show a generated label only. They do not appear on the author's public profile (001) or reviewer passport (014) unless the author explicitly opts in **per review**.
- **FR-013-06** The link between an insider review and the author's account is stored **sealed**: access requires two staff members (Senior Moderator + Admin), for a logged reason (legal order, fraud investigation), and the author is notified unless the law prohibits it.
- **FR-013-07** Re-identification safeguards:
  - role family chosen from a coarse list of ≤ 12 families;
  - only the year is shown (no exact date);
  - insider reviews on a Business are published only once **≥ 3** have been approved (a batch); after that, new ones publish with a random delay of 0–7 days;
  - if a role family has fewer than 3 insider reviews on that Business, it is shown as "Other".
- **FR-013-08** Business users must not be able to see insider-review verification data or learn identity through any endpoint, including exports and analytics. Business analytics for insider reviews are shown in aggregate only (≥ 3 per group).

### Content & display
- **FR-013-09** An insider review contains: overall rating (1–5), title, text (50–5,000 characters), and optional ratings on insider topics (culture & values, leadership, ethics & compliance, how customers are treated, work-life balance). An optional "Would you recommend buying from this company?" yes/no.
- **FR-013-10** Insider reviews are shown in a separate **Insider** section/tab with their own **Insider Rating**. It uses the 008 Review Score formula, applied only to insider reviews and shown only when there are ≥ 5 insider reviews.
- **FR-013-11** Insider reviews and Insider Ratings must be **excluded** from the Review Score, Trust Index, category rankings, comparisons (except as a separate optional row), and customer AI summaries (P2, 008, 011).
- **FR-013-12** Each insider review card shows the badge "Insider review · Verified <insider type>" and a tooltip explaining what was verified. It never uses "Verified Experience".
- **FR-013-13** Insider-specific guideline rules (006): no confidential or trade-secret information, no personal data about colleagues, no allegations of criminal conduct against named individuals, no content about specific customers. Content is moderated before publication (all insider reviews are **held for human review** in the first 6 months after launch).
- **FR-013-14** Businesses may reply publicly (007 rules) and flag insider reviews (006 reasons + `confidential_info`). A business flag never hides the review by itself.
- **FR-013-15** Members of a Business (001) may not write insider reviews of it through a business account. Insider reviews must come from a consumer identity.
- **FR-013-16** Retaliation safeguard: a business message or reply that attempts to identify, threaten, or retaliate against an insider reviewer is a severe breach (006 ladder, may skip steps).

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Work email on a free email domain or not on the Business's verified domains | Reject method (a). Offer document proof. |
| Business has no verified domain (unclaimed) | Only document proof is available. |
| One person tries to verify for two competing businesses at once | Allowed only if both are verified. Flag to fraud if the timing suggests a conflict (e.g., a competitor's employee). |
| Same work email used by a second account | Rejected (hash match). Both accounts are sent for fraud review. |
| Former employee who left > 3 years ago | Reject: "Insider reviews are for the last 3 years". |
| Text names a colleague | Held, and the name is redacted by a moderator. |
| Author requests deletion | Deleted immediately, as with any review (003). |
| Business has fewer than 3 approved insider reviews | None are shown yet. The tab says "Insider reviews will appear once enough are collected". |
| Legal order to reveal identity | Handled only through FR-013-06, and counted in the transparency report (006). |
| Unauthorized: a business Owner calls an insider-verification endpoint | 403. |

## 5. Out of Scope

- Salary, interview, or benefits data (the Platform is not a jobs site).
- Whistleblower reporting channels or legal protection services.
- Payroll/HRIS integration in Phase 2 (only after a partner is confirmed).
- Insider reviews of individual managers or people.

## 6. Acceptance Criteria

- [ ] Work-email and document verification work. Hashes prevent reuse. No plain-text work emails are stored after verification (database inspection test).
- [ ] The public UI and all APIs never expose author identity for insider reviews (tests on every endpoint that returns reviews).
- [ ] Batch-publication threshold, random delay, and role-family collapsing work (fixture tests).
- [ ] Insider reviews are excluded from Review Score, Trust Index, rankings, and customer summaries (score invariance test).
- [ ] Sealed identity access requires two staff approvals and writes a compliance log entry.
- [ ] Legal sign-off recorded before the feature flag is enabled in production.

## 7. Dependencies & Open Questions

- **Q1:** Legal sign-off per launch jurisdiction (FTC insider-review disclosure rules, employment-law confidentiality, defamation risk).
- **Q2:** Does the client have a payroll/HRIS partner in mind for method (d)?
- **Decided (2026-09-24):** only businesses with ≥ 50 employees (FR-013-01a). The feature flag stays off until legal sign-off.
