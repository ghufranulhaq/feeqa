# Spec 014: Reviewer Reputation, Portability & Rewards

**Status:** Draft · **Phase:** 2 · **Depends on:** 001, 003, 004
**Client requirements:** F6 Review Portability & Ownership ("reviewer passport"), F8 Reward Honest, Detailed Reviews
**Legal gate:** Rewards need written legal sign-off before launch (constitution L5).

## 1. Goal

Reviewers own their review history and should get credit for writing helpful, honest reviews. Give each reviewer a portable, verifiable **Reviewer Passport** that summarises their track record and can be exported. Reward review **quality**: helpfulness, detail, verification, and follow-up updates. Never reward volume or sentiment, so rewards cannot buy positive reviews.

## 2. User Scenarios

1. **Reputation profile.** When a consumer opens their profile, they see their passport: member since 2026, 24 published reviews, 83% Verified Experience, 312 "Useful" votes from other members, 9 lifecycle updates, level **Trusted Reviewer**, and "no guideline violations in 12 months".
2. **Export.** The consumer clicks "Export my reviews" and gets a ZIP with a JSON file (and a readable HTML/CSV version) of all their reviews, updates, answers, media, replies received, and a **signed passport credential**.
3. **Share passport.** The consumer shares their passport link or credential with another site. That site checks the signature with the Platform's public key and sees the reviewer's stats. Review texts are included only if the reviewer chose to include them.
4. **Earn quality points.** A consumer's detailed, verified review gets 15 "Useful" votes from distinct established members, and they add a 6-month update. They earn Quality Points and reach the next level, which unlocks a perk from the Platform's rewards catalogue (e.g., early access to features, or a partner discount funded by the Platform).
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

### Quality Points & rewards
- **FR-014-11** Quality Points are earned **only** from:
  - Useful votes received from distinct eligible voters (account ≥ 30 days old, not flagged, not the reviewed business's members);
  - Verified Experience on a review;
  - a lifecycle update added in its window;
  - the detail score of a review (text length band + number of category questions answered + media with transcript).
  The published points table is part of the methodology.
- **FR-014-12** Point calculations must **not** use star rating, sentiment, the business reviewed, whether the business replied, or the business's plan. An automated invariance test must prove that flipping a review's rating/sentiment doesn't change the points earned.
- **FR-014-13** Points from a review are **reversed** if the review is removed for a guideline breach. Points from Useful votes are reversed if the votes are later found fraudulent.
- **FR-014-14** Daily cap: at most 100 points per day. Votes on a single review count for points up to a maximum of 50.
- **FR-014-15** Rewards are **funded by the Platform** (or by partners through contracts with the Platform). A **business may never fund rewards linked to reviews of itself**, and a perk from a partner must not be redeemable in a way that is tied to reviewing that partner.
- **FR-014-16** Rewards catalogue: perks with a points cost, stock, eligibility (level), and expiry. Redemption is recorded, and perks are delivered as codes or feature unlocks.
- **FR-014-17** Reviews are not labelled individually as rewarded, because rewards are never tied to a specific review. Instead, the Platform discloses the rewards programme publicly in the guidelines and on the Transparency Center (FTC/DMCC disclosure; legal to confirm, see Q1).

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Export requested with no reviews | Export still produced, with empty lists and a passport credential with zero metrics. |
| Export with > 1,000 reviews and media | Split into multiple ZIP parts (≤ 2 GB each). Async delivery by email link. |
| Credential presented after expiry | The check says "expired". |
| Credential tampered with | The check fails. |
| Vote rings (accounts voting on each other) | Detected by 006. Votes are ignored for points and the rings are sent to the ladder. |
| Reviewer deletes a review | The points earned from it are removed. |
| Reviewer tries to redeem more points than they have | Reject. |
| Perk out of stock | Hide it from the catalogue, or show "Out of stock". |
| Partner business on the platform funds a perk | Allowed only through the Platform's pool. The partner's own profile reviews are excluded from earning points toward its perks. |
| Unauthorized: a business attempts to award points | No such endpoint exists. |

## 5. Out of Scope

- Importing reviews or reputation **from** other platforms (only exporting is in scope).
- Cash payouts or cryptocurrency/tokens.
- Leaderboards that rank reviewers by volume.
- Public "follow" features.
- Rewards for writing a review of a specific business.

## 6. Acceptance Criteria

- [ ] Passport shows correct metrics on fixture data, and respects privacy settings and insider exclusions.
- [ ] Export contains all content in three formats. The credential checks with the public key, and tampered or expired credentials fail.
- [ ] Levels recalculate daily with the correct thresholds.
- [ ] Points invariance test passes: rating, sentiment, business, and plan don't change points.
- [ ] Points reversal on removal works. Caps are enforced.
- [ ] The rewards programme disclosure is published, and legal sign-off is recorded before the feature flag goes live.

## 7. Dependencies & Open Questions

- **Q1:** **Legal review** of rewards under the FTC Rule (§465.5: incentives conditioned on sentiment are banned; unconditioned incentives may still need disclosure) and the DMCC Act "concealed incentivised reviews". Do we need to label reviews from reviewers who hold rewards? The design proposes programme-level disclosure. Legal must confirm.
- **Q2:** Who are the launch perk partners, and what is the budget?
- **Q3:** Should the credential format follow the W3C Verifiable Credentials data model? *Proposed:* yes; decide in plan.md.
