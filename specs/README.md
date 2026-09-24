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

## Key Decisions Needed From the Client

These block planning for the specs listed. Each spec's *Open Questions* section has the full list.

| # | Decision | Blocks |
|---|----------|--------|
| D1 | **Launch vertical.** The examples point to travel (airlines/agencies). Please confirm. | 002, 005, 009, 011 |
| D2 | **Trust Index weights**, proposed 30/15/15/10/15/15 | 008 |
| D3 | **Plan pricing and limits** for Starter / Pro / Enterprise | 017 |
| D4 | **Legal sign-off** on rewards (014), insider reviews (013), and "resolve first" (010) | 010, 013, 014 |
| D5 | Payment/e-commerce **integration partners** for verification and invitations | 004, 005 |
| D6 | Mediation: free for all businesses, or a paid add-on? | 010, 017 |
| D7 | Technology stack (recorded in `CLAUDE.md` §10 once decided in the plan phase) | all |

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
