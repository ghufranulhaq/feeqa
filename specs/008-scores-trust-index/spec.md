# Spec 008: Review Score & Trust Index

**Status:** Draft · **Phase:** 1 (MVP) · **Depends on:** 003, 004, 010
**Client requirements:** C) Trust score beyond star rating, F) Resolution rating (as an input), GTM radical transparency

## 1. Goal

Summarise a business's reputation honestly, with two published numbers. The **Review Score** (1.0–5.0) says how customers rate the business, using the familiar stars. The **Trust Index** (0–100) says how far the business can be trusted when things go wrong: verification, complaint resolution, refunds, repeat satisfaction, and disputes. Both are calculated the same way for every business, can be replayed from stored data, and cannot be bought.

## 2. User Scenarios

1. **Reading the header.** When a consumer opens a profile, they see "**4.3** ★★★★½ Excellent · 2,418 reviews" and "**Trust Index 87/100**". Tapping the Trust Index shows each component, its value, its weight, and "Based on data from the last 12 months · Methodology v1.0".
2. **New business.** A business with 2 reviews (5★ and 5★) shows a Review Score of 3.8 rather than 5.0, because of the published starting prior: (7 × 3.5 + 2 × 5) / 9 = 3.83. Its Trust Index shows "Not enough data yet (needs 20 reviews)".
3. **Score changes after a review.** A new 1★ review is published. Within 60 seconds the Review Score is recalculated and the change is visible.
4. **Consumer Warning.** A business under Consumer Warning (006) shows "Scores hidden: see warning" instead of both numbers, in every place the numbers would appear, including widgets and the API.
5. **Methodology change.** Staff publish methodology v1.1 with a changed weight. The change is announced 30 days ahead on the Transparency Center, and after the effective date each Trust Index shows the new version label.

## 3. Functional Requirements

### Review Score
- **FR-008-01** The Review Score of a Business (service), Location, or Product is calculated from the **current rating** (003) of each **eligible review**:
  - status `published`;
  - customer review (insider reviews from 013 are excluded);
  - only the **most recent** eligible review per consumer per scored entity counts.
- **FR-008-02** Each eligible rating `rᵢ` gets a time weight `wᵢ`, based on the age of the rating version in effect (original or latest update):

  | Age | Weight |
  |-----|--------|
  | ≤ 12 months | 1.00 |
  | > 12 and ≤ 24 months | 0.50 |
  | > 24 and ≤ 36 months | 0.25 |
  | > 36 months | 0.10 |

- **FR-008-03** Bayesian prior: `m = 7` pseudo-reviews with rating `p = 3.5` (both configurable per methodology version, same for all businesses).
- **FR-008-04** Formula:
  `ReviewScore = (m·p + Σ wᵢ·rᵢ) / (m + Σ wᵢ)`, rounded **half-up to one decimal** and clamped to [1.0, 5.0].
- **FR-008-05** Star display: round the Review Score to the nearest 0.5 (half-up) for the star image. The word label uses these bands:

  | Review Score | Label |
  |--------------|-------|
  | 1.0 – 1.7 | Bad |
  | 1.8 – 2.7 | Poor |
  | 2.8 – 3.7 | Average |
  | 3.8 – 4.2 | Great |
  | 4.3 – 5.0 | Excellent |

- **FR-008-06** A Business with zero eligible reviews shows no Review Score ("No reviews yet").
- **FR-008-07** The Review Score must be recalculated and visible within **60 seconds** of any triggering event (publish, edit, update, delete, moderation, merge). A full nightly recalculation also runs to catch any drift.
- **FR-008-08** The rating distribution (percentage of eligible reviews at each star, **unweighted**, current ratings) is shown next to the score.

### Trust Index
- **FR-008-09** The Trust Index is a 0–100 integer per Business (service level only). It is computed daily from data in the **rolling last 12 months**, from these components (each normalised to 0–100):

  | # | Component | Definition | Weight |
  |---|-----------|------------|--------|
  | T1 | Review Score | `(ReviewScore − 1) / 4 × 100` | 25 |
  | T2 | Verification | % of eligible reviews with an active Verified Experience attestation (004) | 15 |
  | T3 | Complaint resolution | `0.5 × resolution rate + 0.5 × ((avg resolution rating − 1) / 4 × 100)`. Resolution rate = cases the consumer confirmed resolved ÷ cases that were resolved, closed unresolved, or open > 30 days (010) | 20 |
  | T4 | Refund speed | Median days from "refund requested" to "refund completed" (consumer-confirmed) on cases with refunds; ≤ 7 days → 100, ≥ 60 days → 0, linear in between. Refunds still pending after 60 days count as 60. Compensation (UK261/EU261) is **not** included here (010) | 15 |
  | T5 | Repeat customer satisfaction | Average current rating of reviews that have ≥ 1 lifecycle update **or** come from consumers with ≥ 2 verified experiences with the Business, normalised `(avg − 1)/4 × 100` | 10 |
  | T6 | Dispute frequency | Cases escalated to mediation (010) per 100 eligible reviews; 0 → 100, ≥ 10 → 0, linear in between | 15 |

- **FR-008-10** Minimum data per component, within the 12-month window:

  | Component | Minimum |
  |-----------|---------|
  | T1 | ≥ 20 eligible reviews (this is also the minimum for showing any Trust Index) |
  | T2 | same as T1 |
  | T3 | ≥ 5 eligible cases |
  | T4 | ≥ 5 refund cases |
  | T5 | ≥ 10 qualifying reviews |
  | T6 | same as T1 |

  Components below their minimum are **excluded**, and the remaining weights are scaled up proportionally. If the included weights add up to less than **60**, the Trust Index shows "Not enough data yet".
- **FR-008-10a** **Unclaimed businesses:** case-based components (T3, T4, T6) are calculated normally, but when the Business is `unclaimed`, the breakdown marks them "Unclaimed: this business hasn't joined, so response data may be incomplete". The same notice appears next to the Trust Index on the profile and in comparisons (009).
- **FR-008-11** Formula: `TrustIndex = round_half_up( Σ(weightₖ × componentₖ) / Σ weightₖ )` over included components.
- **FR-008-12** The breakdown view must show each component's value, its weight, whether it was included, and a one-sentence plain-language explanation, plus the methodology version and calculation date.
- **FR-008-13** The Trust Index must be **identical for any plan**. No input may come from data only available to paying businesses. For example, if transaction reference data improves verification, the free equivalent (document proof) must be available to every reviewer.

### Governance
- **FR-008-14** Both algorithms are **pure, deterministic functions** of their input data and a methodology version. Given the same inputs and version, they must produce identical outputs (determinism test).
- **FR-008-15** Every methodology version (parameters, weights, bands, minimums) is stored and published in the Transparency Center (006) with an effective date. Material changes must be announced ≥ 30 days before they take effect.
- **FR-008-16** Daily snapshots of Review Score, Trust Index, and component values must be stored for every Business for trendlines (011, 015). They are kept for at least 5 years.
- **FR-008-17** When a Business is under Consumer Warning (006), both scores must be hidden on every surface (profile, search, comparison, widgets, API). They are still calculated internally.
- **FR-008-18** Paid placements, plans, replies, flags, invitation volume, or ad spend must have **zero** effect on either score (P2). This is checked by an automated test that varies these inputs and asserts the scores don't change.

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| All reviews older than 36 months | Score still calculated with the 0.10 weight. The profile shows "No reviews in the last 12 months". |
| Single consumer with 10 reviews over time | Only the most recent eligible one counts. |
| Review with lifecycle update 3★ after an original 5★ | Only the current rating (3★) counts. Age is measured from the update date. |
| Review removed by moderation | Excluded immediately, and recalculated. |
| Businesses merged (002) | Scores recalculated from the merged review set. Duplicate consumers are deduplicated by the most-recent rule. |
| Case count 0 | T3, T4 excluded, and T6 = 100 if review minimum is met (zero disputes is a real value). |
| Division by zero anywhere | Must not happen. The component is marked excluded. |
| Rounding at a band edge (e.g., 4.25 → 4.3) | Use half-up rounding **before** choosing the band. The band is applied to the displayed value. |
| Location with < 1 review | No location score. The Business score is unaffected. |
| Insider reviews (013) | Never included in either score. |
| Unauthorized attempt to write scores directly | No write API exists. Scores are only derived. |

## 5. Out of Scope

- Personalised scores (different scores for different viewers).
- Ranking businesses **across** categories by Trust Index (rankings are per category, 009).
- Trust Index for Locations and Products (Review Score only).
- Letting businesses dispute their score as a number. They may only dispute the underlying content through 006.

## 6. Acceptance Criteria

- [ ] A golden dataset of ≥ 30 fixture businesses produces the expected Review Scores, labels, star images, and Trust Indexes, all calculated by hand.
- [ ] Property-based tests: the score is always within [1.0, 5.0] and [0, 100]; adding a 5★ review never lowers the Review Score; excluded components never affect the output.
- [ ] Determinism test: calculating twice from the same fixture data and methodology version gives identical results, and the nightly full recalculation matches the incremental values.
- [ ] Recalculation happens within 60 s of trigger events (integration test).
- [ ] The breakdown UI shows every component, weight, inclusion flag, version, and date.
- [ ] Consumer Warning hides scores on every surface (profile, search, comparison, widget, API tests).
- [ ] Money/plan invariance test passes (FR-008-18).
- [ ] Methodology page published and matches the implemented parameters (a test reads config and page source).

## 7. Dependencies & Open Questions

- **Decided (2026-09-24):** travel-tuned weights T1 25 · T2 15 · T3 20 · T4 15 · T5 10 · T6 15 (sum 100). Unclaimed businesses: complaint data counts, with a notice (FR-008-10a).
- **Q2:** Should the prior (`m`, `p`) differ per category, e.g., using the category's average? *Proposed:* no, for simplicity and neutrality in v1.
- **Q3:** Should the Trust Index be shown on search results cards, or only on profiles? *Proposed:* both.
