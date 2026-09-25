# Specifications Index

These specs define the whole Platform, one capability per spec. Each one follows the spec-driven workflow in [`CLAUDE.md` §2](../CLAUDE.md), and the project-wide **Definition of Done** ([`CLAUDE.md` §7](../CLAUDE.md)) applies to every spec on top of its own acceptance criteria.

Each spec contains these sections:

1. **Goal**: the why, in two or three sentences.
2. **User Scenarios**: "when a user does X, they get Y" walkthroughs.
3. **Functional Requirements**: testable musts, with IDs `FR-NNN-nn`.
4. **Edge Cases & Rules**: input that is empty, huge, duplicate, malformed, or unauthorized.
5. **Out of Scope**: what the spec explicitly does not cover.
6. **Acceptance Criteria**: the checklist that says "done".
7. **Dependencies & Open Questions**: what it relies on and what still needs a decision.

---

## Spec Catalogue

| # | Spec | Phase | Depends on |
|---|------|-------|------------|
| 001 | [Accounts, Identity & Roles](001-accounts-identity/spec.md) | 1 (MVP) | none |
| 002 | [Business Profiles, Claiming & Categories](002-business-profiles/spec.md) | 1 (MVP) | 001 |
| 003 | [Review Submission & Lifecycle](003-reviews/spec.md) | 1 (MVP) | 001, 002 |
| 004 | [Proof-of-Experience Verification](004-verification/spec.md) | 1 (MVP) | 003, 005 |
| 005 | [Review Invitations](005-invitations/spec.md) | 1 (MVP) | 002, 003 |
| 006 | [Moderation, Integrity & Transparency](006-moderation-integrity/spec.md) | 1 (MVP) | 001–003 |
| 007 | [Business Replies & Engagement](007-business-replies/spec.md) | 1 (MVP) | 002, 003 |
| 008 | [Review Score & Trust Index](008-scores-trust-index/spec.md) | 1 (MVP) | 003, 004, 010 |
| 009 | [Search, Discovery & Comparison](009-search-comparison/spec.md) | 1 (MVP) | 002, 008 |
| 010 | [Cases, Dispute Timeline & Mediation](010-cases-mediation/spec.md) | 1 (MVP) | 003, 007 |
| 011 | [AI Summaries, Topics & Sentiment Trends](011-ai-insights/spec.md) | 2 | 003, 006 |
| 012 | [Voice & Video Reviews](012-media-reviews/spec.md) | 2 | 003, 006 |
| 013 | [Verified Insider Reviews](013-insider-reviews/spec.md) | 2 | 001, 003, 006 |
| 014 | [Reviewer Reputation, Portability & Rewards](014-reviewer-reputation/spec.md) | 2 | 001, 003, 004 |
| 015 | [Business Analytics & Benchmarking](015-analytics-benchmarking/spec.md) | 2 | 005, 007, 008, 010, 011 |
| 016 | [Trust Widgets & Public API](016-widgets-api/spec.md) | 1 (widgets) / 2 (API, licensing) | 008 |
| 017 | [Plans, Billing & Monetization](017-billing-monetization/spec.md) | 1 (plans) / 2 (ads, leads) | 001, 002 |

**Phase 1 (MVP)** matches Trustpilot's core loop and ships the client's three strongest differentiators: Verified Experience, the dispute timeline with resolution rating, and the Trust Index.
**Phase 2** adds the remaining differentiators.

---

## Client Requirement Traceability

Every item in [`add-to-trustpilot.md`](../add-to-trustpilot.md) maps to at least one spec.

| Client requirement | Spec(s) |
|--------------------|---------|
| Revenue: Freemium SaaS | 017, 002 |
| Revenue: Subscription plans (Starter → Pro → Enterprise) | 017 |
| Revenue: Advertising & promoted listings | 017, 009 |
| Revenue: API & data licensing | 016, 017 |
| Revenue: Transaction/lead fees | 017, 016 |
| F1 Proof-of-Experience Verification | 004 |
| F2 Review Lifecycle Tracking | 003 |
| F3 AI Summaries & Sentiment Trends | 011 |
| F4 Anonymous Employee & Insider Reviews | 013 |
| F5 Dispute Resolution & Mediation | 010 |
| F6 Review Portability & Ownership | 014, 001 |
| F7 Context-Aware Review Prompts | 003, 002 |
| F8 Reward Honest, Detailed Reviews | 014 |
| F9 Competitor Benchmarking Dashboard | 015 |
| F10 Open API & Embeddable Trust Widgets | 016 |
| GTM: Vertical-first launch | 002 (category configuration), 009 |
| GTM: Payment/e-commerce partnerships | 004, 005 |
| GTM: Radical transparency | 006, 008 |
| A) Verified transaction reviews | 004 |
| B) AI dispute timeline | 010 |
| C) Trust Index /100 | 008 |
| D) Industry comparison engine | 009 |
| E) Voice/video reviews | 012 |
| F) Resolution rating | 010, 008 |

## Decision Log (client interview, 2026-09-24)

| # | Topic | Decision | Specs |
|---|-------|----------|-------|
| D1 | Launch vertical | **Travel**: airlines, travel agencies/OTAs, airports. Content drafted in [`travel-content.md`](002-business-profiles/travel-content.md) *(pending client approval)* | 002, 005, 009, 011 |
| D2 | Markets & clients | **UK + EU**, English (`en-GB`), GBP/EUR, **responsive web only** | Constitution §1 |
| D3 | Operator conflict | AeroTickets sells travel and is **listed like any other business**, with an "Owned by the Platform operator" label. **Strict published data firewall.** May buy ads at list price with a disclosed label | Constitution P11, 001, 002, 006, 009, 017 |
| D4 | Agency vs. airline | The reviewer picks one business and may **tag** the other. The tagged business sees the review and can reply once, with **no score effect** | 003, 002 |
| D5 | Trust Index weights | Travel-tuned: 25 / 15 / 20 / 15 / 10 / 15 | 008 |
| D6 | Compensation (UK261/EU261) | A **separate case milestone**. Excluded from refund speed; counts toward resolution. Shown in comparisons | 010, 008, 009 |
| D7 | Pricing & billing | Starter **£99 / €119**, Pro **£299 / €349**, Enterprise by quote. Monthly + annual (2 months free) | 017 |
| D8 | Mediation | **In-house, free for everyone** in Phase 1 | 010 |
| D9 | Rewards | **Status-only** (levels, badges, early access). No monetary perks | 014, Constitution L5 |
| D10 | Verification sources (Phase 1) | Document upload + Business API + BCC. Connectors in Phase 2 | 004, 005 |
| D11 | Directory seeding | Seed about 300–500 UK/EU airlines and major agencies/OTAs as unclaimed profiles | 002 |
| D12 | Moderation team | Small in-house team, SLAs as written | 006 |
| D13 | Product reviews | **Deferred** beyond Phase 1 | 002, 003, 005, 016 |
| D14 | Lifecycle clock | Counts from the **publication date** | 003 |
| D15 | Voice/video | Only on **Verified Experience** reviews | 012 |
| D16 | Insider reviews | Only businesses with **≥ 50 employees**. Legal-gated flag | 013 |
| D17 | Tech stack | Proposed in the first plan.md, recorded in Constitution §10 after approval | all |
| D18 | Unclaimed businesses | One notice to their public contact email. Complaint data counts in the Trust Index, **with an "Unclaimed" notice** | 010, 008, 009 |
| D19 | Ads model | **Fixed monthly slots**, 3 seats per category × country, even rotation | 017, 009 |
| D20 | Lead fees | **Flat fee per attributed booking** | 017 |
| D21 | Data licensing | Aggregates + excerpts of at most 200 characters (no full texts) | 016 |
| D22 | "Resolve first" | Built behind a feature flag. **Off until UK + EU legal sign-off** | 010, Constitution L5 |
| D23 | Other industries | Any business from any industry can be listed and reviewed. A full top-level taxonomy ships with only Travel launched. **Staff create and launch new industries from the console with no code change** (readiness checklist, preview, audit) | 002 |

### Still open (non-blocking for Phase 1 planning)
- Client approval of `travel-content.md` (question sets, topics, invitation timing).
- Plan limits (invitations, users, locations) in 017 FR-017-02, sponsored slot list prices, per-booking lead fee.
- Legal sign-off for insider reviews (013) and "resolve first" (010), which is needed before those flags are switched on.
- Seed-data source (002 Q1). Phase 2 connector partners (004/005).

## Trustpilot Parity Traceability

| Trustpilot capability | Spec(s) |
|-----------------------|---------|
| Consumer accounts, social login | 001 |
| Business profiles, claiming, categories, locations | 002 |
| Service, location & product reviews; edit/delete; useful votes | 003 |
| Review source labels | 003, 004, 005 |
| AFS/BCC, CSV, API, link invitations; templates; integrations | 005 |
| Flagging, fraud detection, enforcement ladder, Consumer Warnings, transparency report | 006 |
| Replies, AI reply suggestions, notifications, verification requests | 007, 004 |
| TrustScore-equivalent (Review Score) | 008 |
| Search, category rankings, "People also looked at" | 009 |
| AI review summary, topics | 011 |
| Analytics & competitor benchmarking | 015 |
| Widgets, APIs, webhooks | 016 |
| Plans & billing | 017 |
