# Spec 017: Plans, Billing & Monetization

**Status:** Draft · **Phase:** 1 (plans & billing) / 2 (sponsored placements, lead fees, licensing billing) · **Depends on:** 001, 002
**Client requirements:** Revenue streams 1–5 (Freemium SaaS, Starter → Pro → Enterprise, Advertising & promoted listings, API & data licensing, Transaction/lead fees)

## 1. Goal

Fund the Platform by selling businesses **tools and reach**, never **reputation**. Businesses start free, upgrade to Starter, Pro, or Enterprise for automation, analytics, and integrations, and can buy clearly labelled sponsored placements or pay for leads they can measure. None of this ever changes a score, a review, or a moderation outcome (P2).

## 2. User Scenarios

1. **Free to Starter.** An Owner on the Free plan tries to set up BCC invitations, sees "Available on Starter", compares plans, pays by card, and invitations work right away. An invoice with the correct tax is emailed.
2. **Hitting a limit.** A Starter business reaches its monthly invitation limit. New invitations queue (never dropped, 005), and the Owner is prompted to upgrade or wait for the next period.
3. **Downgrade.** A Pro business downgrades to Free at renewal. Paid widgets fall back to the free badge, invitation automations pause, analytics history stays but only the free views are available, and **no review, score, or label changes**.
4. **Failed payment.** A card is declined. The system retries over 14 days with emails. The business keeps paid features during the grace period, then moves to Free.
5. **Sponsored placement.** A Pro business creates a sponsored campaign for the "Airlines" category with a monthly budget. Its ad appears in the labelled top slot on the category page. Performance (impressions, clicks, spend) shows in the dashboard.
6. **Lead fees.** A business on the lead-fee programme gets billed for each **attributed conversion**: a consumer clicks "Visit website" on the profile and the business's server-side postback reports a booking within 7 days. The consumer consented to tracking.
7. **Enterprise.** Sales sets up an Enterprise contract with custom limits, multiple brands, and invoice billing through the admin console.

## 3. Functional Requirements

### Plans & entitlements
- **FR-017-01** Plans: **Free**, **Starter**, **Pro**, **Enterprise**. Entitlements are data-driven (a configuration table), not hard-coded, and versioned. Existing subscribers keep their plan version until renewal.
- **FR-017-02** Default entitlement matrix (limits and prices set by the client, Q1):

  | Capability | Free | Starter | Pro | Enterprise |
  |------------|:----:|:-------:|:---:|:----------:|
  | Claim profile, edit info, reply, flag, request verification | ✅ | ✅ | ✅ | ✅ |
  | Cases & resolution tracking (010) | ✅ | ✅ | ✅ | ✅ |
  | Review invitations | link + 50 manual/mo | + BCC, CSV, API; 500/mo | + integrations, product reviews; 5,000/mo | custom |
  | Widgets (016) | badge, micro star | + carousel | all | all + custom |
  | AI reply suggestions (007) | none | 50/mo | unlimited | unlimited |
  | Analytics (015) | basic, 90 days | 12 months | 36 months + industry benchmark | + custom exports |
  | Named competitors (015) | none | 1 | 5 | 5+ |
  | Business API / webhooks (016) | none | webhooks | full | full + higher limits |
  | Users | 2 | 5 | 20 | unlimited |
  | Locations | 1 | 10 | 100 | unlimited |
  | Mediation for businesses (010) | ✅ (Phase 1) | ✅ | ✅ | ✅ |
  | Support | help center | email | priority | dedicated manager, SLA |
  | SSO (SAML) | none | none | none | ✅ (later phase) |

- **FR-017-03** Entitlements are checked on the **server** on every gated action. The UI shows the required plan for locked features.
- **FR-017-04** **Plan invariance:** no entitlement may affect scores, review visibility/ordering, labels, moderation, Consumer Warnings, comparisons, rankings, or AI summaries. This is checked by the automated invariance suites in 008 and 009.
- **FR-017-05** Verification for consumers (004), flagging, replying, and cases are **free for all businesses** (P1).

### Billing
- **FR-017-06** Billing periods: monthly and annual (annual discount configurable). Payment methods: card (Phase 1), and invoice/bank transfer (Enterprise).
- **FR-017-07** Taxes are calculated by the business's billing country and tax ID (VAT/GST reverse charge where applicable). Invoices are issued as PDF with sequential numbering and kept for 10 years.
- **FR-017-08** Upgrades take effect immediately with a pro-rated charge. Downgrades take effect at the end of the period. Cancellation stops renewal.
- **FR-017-09** Failed payment (dunning): retries on days 1, 3, 7, 14 with emails. After day 14 the business moves to Free. The payment status is visible to Owners only.
- **FR-017-10** Downgrade effects: features above the new plan stop working, and their data is kept for 12 months. Queued invitations over the new limit stay queued. **No public content changes.**
- **FR-017-11** Payment card data must be handled only by a PCI-DSS-compliant payment processor. The Platform stores only tokens and the last 4 digits.
- **FR-017-12** Businesses under Consumer Warning have paid plans suspended (006), with a pro-rated refund or credit per the terms.

### Sponsored placements (Phase 2)
- **FR-017-13** Campaigns: target category (+ optional country), daily/monthly budget, CPC or CPM pricing, schedule, creative (logo, name, short tagline ≤ 60 characters, screened by 006).
- **FR-017-14** Eligibility (same as 009 FR-009-17): claimed, no Consumer Warning, Review Score ≥ 3.0, no restriction ladder step ≥ 4 in the last 6 months.
- **FR-017-15** Slot allocation among eligible campaigns uses bid × a **relevance factor that excludes Review Score and Trust Index**, so ads can't be seen as "bought scores". The allocation rules are published (006 Transparency Center).
- **FR-017-16** Advertisers see impressions, clicks, CTR, and spend. They get no personal data about consumers.

### Lead / transaction fees (Phase 2)
- **FR-017-17** Participating businesses get tracked outbound links ("Visit website", "Book now") on their profile. A click creates an attribution token. A conversion is attributed when the business reports it (server-side postback with the token) within **7 days** of the click.
- **FR-017-18** Tracking needs the consumer's consent where the law requires it. Without consent, the links still work, with no token.
- **FR-017-19** Fees per attributed conversion (flat or % of the order value, per contract) are invoiced monthly, with a per-conversion statement. Businesses can dispute conversions within 30 days.
- **FR-017-20** Lead-fee participation must not affect scores, ranking, placement, or which links appear on non-participating businesses' profiles, beyond each business's own link (P2).

### Data licensing billing (Phase 2)
- **FR-017-21** Licence contracts (016 FR-016-19) are billed as fixed fees plus usage overage, based on metered API usage per licence key.

### Admin
- **FR-017-22** Staff admin console for plans: create or modify plan versions, apply credits/discounts, set up Enterprise contracts, view revenue reports (MRR, churn, by plan). Every change is audit-logged.

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Upgrade/downgrade several times in one period | Pro-ration computed per change. Only one pending downgrade at a time. |
| Business deletes the account with an active subscription | Cancel renewal. No refund beyond the terms. Public profile stays (reviews belong to reviewers), and it becomes unclaimed. |
| Invalid tax ID | Charge tax as for a non-business customer, and prompt to fix. |
| Currency mismatch | Price is shown and charged in the currency of the billing country, from a fixed price list per currency (no live FX). |
| Chargeback on a subscription | Account moves to Free while it is investigated. |
| Advertiser budget reached mid-day | Stop serving right away. No overspend beyond 5%. |
| Click fraud on sponsored slots or lead links | Invalid-click filtering (bots, repeats within 30 min from the same device). Invalid clicks aren't billed. |
| Postback with an unknown or expired token | Reject. No fee. |
| Business under Consumer Warning with live ad campaigns | Campaigns stop immediately. |
| Unauthorized: an Admin (not Owner) changes the plan | 403 (Owner only, 001). |

## 5. Out of Scope

- Consumer subscriptions or paid consumer features.
- Paid removal, hiding, or moderation of reviews (never offered).
- Affiliate commissions on consumer purchases outside the lead-fee programme.
- Programmatic/third-party ad networks on Platform pages.
- Crypto payments.

## 6. Acceptance Criteria

- [ ] Plans and entitlements are configuration-driven. Every gated action checks entitlement on the server (tests per gate).
- [ ] The plan invariance suite passes: switching plans on fixtures changes no public score, order, label, or moderation outcome.
- [ ] Upgrade, downgrade, cancellation, dunning, and tax invoicing work end to end in the processor's test mode.
- [ ] Sponsored slots follow eligibility, labelling, and allocation rules, and never appear in comparisons (tests with 009).
- [ ] Lead attribution: 7-day window, consent handling, disputes, and invalid-click filtering (tests).
- [ ] Admin plan changes are audit-logged.

## 7. Dependencies & Open Questions

- **Q1:** **Pricing and limits** for Starter/Pro/Enterprise (the client's decision). For reference, Trustpilot's public pricing is roughly $259 / $629 / $1,059 per month (see research doc).
- **Q2:** Is Phase 2 advertising pricing CPC, CPM, or fixed monthly slots?
- **Q3:** Lead-fee model: flat per conversion or % of order value? Which verticals?
- **Q4:** Payment processor and tax engine choice (decided in plan.md).
