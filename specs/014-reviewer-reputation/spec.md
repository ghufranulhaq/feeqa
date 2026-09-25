# Spec 014: Reviewer Reputation, Portability & Rewards

**Status:** Draft · **Phase:** 2 · **Depends on:** 001, 003, 004
**Client requirements:** F6 Review Portability & Ownership ("reviewer passport"), F8 Reward Honest, Detailed Reviews
**Decided (2026-09-24):** rewards are **status-only**: levels, badges, and early access to Platform features. There are no perks with monetary value (constitution L5).

## 1. Goal

Reviewers own their review history and should get credit for writing helpful, honest reviews. Give each reviewer a portable, verifiable **Reviewer Passport** that summarises their track record and can be exported. Recognise review **quality** (helpfulness, detail, verification, follow-up updates) with **status, not money**. Never reward volume or sentiment, so recognition cannot buy positive reviews.

## 2. User Scenarios

1. **Reputation profile.** When a consumer opens their profile, they see their passport: member since 2026, 24 published reviews, 83% Verified Experience, 312 "Useful" votes from other members, 9 lifecycle updates, level **Trusted Reviewer**, and "no guideline violations in 12 months".
2. **Export.** The consumer clicks "Export my reviews" and gets a ZIP with a JSON file (and a readable HTML/CSV version) of all their reviews, updates, answers, media, replies received, and a **signed passport credential**.
3. **Share passport.** The consumer shares their passport link or credential with another site. That site checks the signature with the Platform's public key and sees the reviewer's stats. Review texts are included only if the reviewer chose to include them.
4. **Earn quality points.** A consumer's detailed, verified review gets 15 "Useful" votes from distinct established members, and they add a 6-month update. They earn Quality Points and reach the next level. This unlocks a **level badge** on their reviews and passport, and **early access** to new Platform features (e.g., beta comparison tools). Nothing with monetary value is given.
5. **Rating makes no difference.** Two reviewers write equally detailed, equally verified reviews of the same business, one 1★ and one 5★. They earn exactly the same points.

## 3. Functional Requirements

### Reviewer passport (reputation profile)
- **FR-014-01** Every consumer has a passport showing: member-since date, published review count, % Verified Experience, total Useful votes received (from distinct eligible voters), lifecycle updates count, the categories they review most (top 3), reviewer level, and a "Good standing" indicator (no upheld guideline violations in the last 12 months).
- **FR-014-02** Passport metrics exclude insider reviews unless the reviewer opted in per review (013), and exclude removed content.
- **FR-014-03** Consumers may choose whether the passport is public on their profile. The default is public for stats only.

### Portability
- **FR-014-04** A consumer can export (a) all their reviews and related content (extends 001 FR-001-19) in JSON + CSV + a human-readable HTML file, and (b) a **signed passport credential**.
- **FR-014-05** The passport credential is a signed, machine-verifiable document containing: pseudonymous subject ID, issue date, expiry (≤ 12 months), metrics from FR-014-01, and optionally review summaries (business name, rating, date, verified flag, permalink) selected by the reviewer. The format must be openly documented, and the signature is checkable with the Platform's published public keys (same key infrastructure as 004).
- **FR-014-06** A public verification endpoint confirms a credential's signature, expiry, and revocation status. Credentials are revoked automatically when the account is deleted or blocked.
- **FR-014-07** Each exported review includes its permalink and, where it exists, its Verified Experience attestation ID (004), so third parties can check authenticity.

### Levels
- **FR-014-08** Levels (names can be changed; thresholds are part of a published methodology):

  | Level | Requirements (rolling 24 months) |
  |-------|----------------------------------|
  | New Reviewer | default |
  | Contributor | ≥ 3 published reviews, ≥ 10 Useful votes |
  | Trusted Reviewer | ≥ 10 published reviews, ≥ 50% verified, ≥ 50 Useful votes, good standing |
  | Expert Reviewer | ≥ 25 published reviews, ≥ 70% verified, ≥ 200 Useful votes, ≥ 5 lifecycle updates, good standing |

- **FR-014-09** Levels are recalculated daily. Losing good standing drops the level to Contributor at most until standing is restored.
- **FR-014-10** Reviewer level must **not** affect Review Score or Trust Index weights (008). It may be shown on review cards, and may be used as a "Most useful" sort signal only (003).

### Quality Points & recognition
- **FR-014-11** Quality Points are earned **only** from:
  - Useful votes received from distinct eligible voters (account ≥ 30 days old, not flagged, not the reviewed business's members);
  - Verified Experience on a review;
  - a lifecycle update added in its window;
  - the detail score of a review (text length band + number of category questions answered + media with transcript).
  The published points table is part of the methodology.
- **FR-014-12** Point calculations must **not** use star rating, sentiment, the business reviewed, whether the business replied, or the business's plan. An automated invariance test must prove that flipping a review's rating/sentiment doesn't change the points earned.
- **FR-014-13** Points from a review are **reversed** if the review is removed for a guideline breach. Points from Useful votes are reversed if the votes are later found fraudulent.
- **FR-014-14** Daily cap: at most 100 points per day. Votes on a single review count for points up to a maximum of 50.
- **FR-014-15** Recognition is **status-only**: level badges, "Trusted/Expert Reviewer" labels, and early access to Platform features (feature flags granted per level). The system must have **no** mechanism to give reviewers discounts, vouchers, cash, points redeemable for goods, or any benefit funded by or linked to a business.
- **FR-014-16** Quality Points are an internal progress measure toward levels. They cannot be redeemed, transferred, or exchanged.
- **FR-014-17** The Platform publicly describes the recognition programme (how points and levels work) in the guidelines and on the Transparency Center.
- **FR-014-18** Adding any benefit with monetary value later requires a constitution amendment (L5), a spec change, and legal sign-off.

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Export requested with no reviews | Export still produced, with empty lists and a passport credential with zero metrics. |
| Export with > 1,000 reviews and media | Split into multiple ZIP parts (≤ 2 GB each). Async delivery by email link. |
| Credential presented after expiry | The check says "expired". |
| Credential tampered with | The check fails. |
| Vote rings (accounts voting on each other) | Detected by 006. Votes are ignored for points and the rings are sent to the ladder. |
| Reviewer deletes a review | The points earned from it are removed. |
| Business offers reviewers a perk "for being an Expert Reviewer" off-platform | Business guideline breach (006, `incentivised`). |
| Unauthorized: a business attempts to award points or levels | No such endpoint exists. |

## 5. Out of Scope

- Importing reviews or reputation **from** other platforms (only exporting is in scope).
- Cash payouts or cryptocurrency/tokens.
- Leaderboards that rank reviewers by volume.
- Public "follow" features.
- Rewards for writing a review of a specific business.
- Any perk with monetary value: discounts, vouchers, partner offers, points redemption (not in scope without amendment, FR-014-18).

## 6. Acceptance Criteria

- [ ] Passport shows correct metrics on fixture data, and respects privacy settings and insider exclusions.
- [ ] Export contains all content in three formats. The credential checks with the public key, and tampered or expired credentials fail.
- [ ] Levels recalculate daily with the correct thresholds.
- [ ] Points invariance test passes: rating, sentiment, business, and plan don't change points.
- [ ] Points reversal on removal works. Caps are enforced.
- [ ] The recognition programme is published. No code path exists to issue monetary benefits (code review + test that the rewards catalogue/redeem endpoints don't exist).

## 7. Dependencies & Open Questions

- **Q3:** Should the credential format follow the W3C Verifiable Credentials data model? *Proposed:* yes; decide in plan.md.
