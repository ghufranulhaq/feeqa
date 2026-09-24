# Spec 010: Cases, Dispute Timeline & Mediation

**Status:** Draft · **Phase:** 1 (MVP), with mediation in Phase 1 limited to staff mediators · **Depends on:** 003, 007
**Client requirements:** F5 Dispute Resolution & Mediation Layer, B) AI dispute timeline, F) Resolution rating

## 1. Goal

Reviews show how an experience felt. Cases show what the business **did about it**. A consumer can raise a complaint as a tracked case, and the Platform records each milestone (raised, responded, resolved, refunded). If the parties get stuck, a neutral mediator can step in. At the end the consumer rates how well the issue was resolved. These facts feed the Trust Index and comparisons, which rewards businesses that fix problems.

## 2. User Scenarios

1. **Case from a review.** When a consumer publishes a 2★ review of an airline about a cancelled flight, they tick "I also want this resolved", pick "Refund" as the desired outcome, and a case opens. The review card shows a public timeline:
   ```
   Complaint raised : 10 Mar
   Company response : 12 Mar
   Resolved         : 18 Mar
   Refund completed : 25 Mar
   ```
2. **Resolve before publishing (optional).** When a consumer writing a 1★ review chooses "Give the business a chance to resolve this first", the review is saved **privately** and a case opens. The business has up to 14 days. **The consumer can publish the review at any moment**. After 14 days (or when the case closes), the consumer is asked to publish, edit, or discard. The review is never discarded automatically.
3. **Private conversation.** The business and consumer exchange messages and attachments inside the case. Messages are never public. Only milestone dates, the status, and the resolution rating are public.
4. **Refund confirmation.** The business marks "Refund issued" with an optional reference. The consumer confirms "Refund received". The timeline shows *Refund completed: 25 Mar*.
5. **AI triage.** When a case opens, the system (labelled as AI) classifies it (e.g., *refund – cancelled flight – high urgency*), summarises it for the business, and suggests next steps. The business sees the triage, and nothing is decided automatically.
6. **Escalation to mediation.** The business hasn't responded in 7 days. The consumer clicks "Request mediation". A Platform mediator reviews the case, proposes a non-binding resolution within 10 business days, and both parties accept or decline. The outcome is recorded on the timeline as *Mediated: resolved* or *Mediated: not resolved*.
7. **Resolution rating.** When the case closes, the consumer is asked "How well did they resolve the issue?" (1–5) with an optional comment. The rating shows on the review card and feeds the business's resolution metrics.

## 3. Functional Requirements

### Case creation
- **FR-010-01** A signed-in consumer may open a case (a) from their own published review, (b) while writing a review ("resolve first", FR-010-06), or (c) from the business profile without a review ("Raise a complaint").
- **FR-010-02** Case fields: business, optional location, linked review (optional), category (from a Platform list: refund, cancellation, delay, damaged/missing item, billing, service quality, account/access, other), description (30–5,000 characters), desired outcome (refund, replacement, repair, apology/explanation, compensation, other), optional amount + currency, optional reference number, attachments (≤ 5 files, ≤ 10 MB each).
- **FR-010-03** A consumer may have at most **one open case per Business per experience** (reference or linked review) and at most **5 open cases in total**.
- **FR-010-04** The business is notified immediately (email + dashboard) to all members with Responder role or above. Cases can be opened against **unclaimed** businesses. The Platform then tries to reach the business using public contact details, and the timeline shows "Business not yet on the Platform".

### Timeline (dispute timeline)
- **FR-010-05** The system records these **milestones** with timestamps. Each milestone records who set it, and some need confirmation from the other party:

  | Milestone | Set by | Confirmed by |
  |-----------|--------|--------------|
  | Complaint raised | system (case creation) | none |
  | Company first response | system (first business message) | none |
  | Resolution proposed | business or mediator | none |
  | Resolved | consumer (accepts resolution) | none (consumer-only) |
  | Refund requested | consumer (desired outcome = refund) or business | none |
  | Refund issued | business | consumer confirms "received" → **Refund completed** |
  | Escalated to mediation | consumer or business | none |
  | Mediation outcome | mediator | none |
  | Closed | see FR-010-11 | none |

- **FR-010-06** "Resolve first" mode: the review stays **private** (visible only to the author) while the case is open, for **up to 14 days**. The consumer may publish at any time. When the case closes, or at day 14, the consumer is asked to publish, edit, or discard. After day 14 the review stays a private draft until the consumer acts. The business **cannot** start, extend, or influence this mode, and cannot see the private review's content or rating. The system must record that the review was deferred, and tell the consumer this is their choice (P3, FTC/DMCC anti-suppression).
- **FR-010-07** The **public** timeline on a linked review card shows milestone names and dates, the current status, and the resolution rating. It must **never** show messages, attachments, amounts, or reference numbers.
- **FR-010-08** If "Refund issued" isn't confirmed or disputed by the consumer within 14 days, the timeline shows "Refund issued (reported by business, not confirmed)". It does **not** count as "Refund completed" in any metric.

### Messaging
- **FR-010-09** Case messages between consumer, business, and (if escalated) mediator: plain text ≤ 3,000 characters plus attachments (≤ 5 per message, ≤ 10 MB each). They are screened for harmful content and malware (006), private to case participants and staff, and kept for 24 months after closure.
- **FR-010-10** Businesses may not ask for, and the consumer may not be pressured into, removing or changing a review as a condition of resolution. Messages that contain such conditions are detected (006 lexicon) and trigger a warning. Confirmed cases trigger the business enforcement ladder.

### Closure
- **FR-010-11** A case closes when:
  - (a) the consumer marks it **Resolved**;
  - (b) the consumer **withdraws** it;
  - (c) the mediator records an outcome;
  - (d) the case is inactive for 30 days after a business "Resolution proposed" message, closing as *Closed – no response from consumer*;
  - (e) the case is inactive for 30 days with no business response, closing as *Closed – business did not respond*.
  A closed case may be re-opened once by the consumer within 30 days.
- **FR-010-12** When a case closes, except by withdrawal, the consumer is asked for the **resolution rating**: "How well did they resolve the issue?" 1–5 stars, plus an optional comment (≤ 500 characters, screened). It may be changed within 30 days.

### AI triage (labelled AI, advisory only)
- **FR-010-13** When a case is created, the system produces: predicted category (if the consumer picked "other"), urgency (low/medium/high), a 2–3 sentence neutral summary, suggested next steps for the business, and a suggestion whether escalation is likely to be needed. All are labelled AI-generated, stored with the model version, editable/dismissible, and **never change status, milestones, or visibility automatically** (P7).
- **FR-010-14** For mediators, the system generates a case digest (timeline, key claims from each side, evidence list), with a link from each point to its source message.

### Mediation
- **FR-010-15** Either party may request mediation when: the business has not responded within **7 days**, or the case has been open **14 days** without resolution, or either party rejected a proposed resolution.
- **FR-010-16** Mediation is **free** for consumers. For businesses it is included by plan (017). Unclaimed and free-plan businesses may take part at no cost in Phase 1.
- **FR-010-17** A mediator (staff with the `Mediator` role, with no conflict of interest) reviews the case and may request information from each party (response window 5 business days). The mediator issues a **non-binding** recommendation within **10 business days** of accepting the case. Each party accepts or declines within 7 days.
- **FR-010-18** Mediation outcomes: `resolved` (both accept), `not_resolved` (either declines or doesn't answer), `withdrawn`. The outcome and date appear on the public timeline. The recommendation text stays private.
- **FR-010-19** Mediation must show clear disclaimers: it is not legal advice, it does not replace statutory rights or chargeback/ADR schemes, and either party may leave at any time.

### Metrics
- **FR-010-20** Per Business, rolling 12 months, computed daily and exposed to 008, 009, and 015:
  - cases opened;
  - % with first response within 48 h;
  - median first response time;
  - resolution rate (008 T3 definition);
  - average resolution rating;
  - refund success rate;
  - median refund time;
  - mediation escalations per 100 eligible reviews;
  - mediation resolved %.
- **FR-010-21** The profile must show a **Case record** panel with these metrics (subject to "Not enough data" minimums of ≥ 5 cases).

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Empty description or below 30 characters | Reject. |
| Amount negative, zero, or in an unsupported currency | Reject. Currencies are ISO 4217. |
| A 6th open case | Reject with "You have 5 open cases". |
| Duplicate case for the same reference | Reject, and link to the existing case. |
| Business replies publicly (007) instead of in the case | The public reply is allowed, but it doesn't set "Company first response" on the case. |
| Consumer deletes the linked review | The case continues. Its timeline is not public any more (no review to show it on), but metrics still count it. |
| Consumer deletes their account | Case closes as withdrawn (001). Excluded from resolution metrics, but included in "cases opened". |
| Business disputes a "Resolved"-type statement | Businesses cannot set "Resolved". They may request mediation. |
| Consumer confirms "Refund received" and later says it was reversed | Consumer may re-open within 30 days. The milestone is recorded as reversed. |
| Abusive messages from either side | Screened (006). Repeat offenders go through their ladder. The mediator may end the mediation. |
| Case against a business under Consumer Warning | Allowed. |
| Unauthorized: a business Analyst reads case messages | 403 (Responder or above only). |
| Unauthorized: a business attempts to read a "resolve first" private review | 403. Content is never exposed. |

## 5. Out of Scope

- Binding arbitration, legal judgements, or collecting and paying out money through the Platform.
- Chargeback filing or integration with card networks.
- Automated (AI-only) mediation decisions.
- Public display of case messages or evidence.
- Class/collective complaints across many consumers (Phase 3 idea).
- Cases between two businesses (B2B disputes).

## 6. Acceptance Criteria

- [ ] Cases can be opened in all three ways. Limits are enforced.
- [ ] Every milestone records who set it and when. Refund completion requires consumer confirmation (test for the unconfirmed state).
- [ ] The "resolve first" private review can be published by the consumer at any time, is never visible to the business, and is never auto-discarded (tests).
- [ ] The public timeline shows only milestone names, dates, status, and resolution rating (test asserts no messages, amounts, or references leak).
- [ ] Closure rules (a)–(e) trigger correctly under time-travel tests. The resolution rating is prompted and editable for 30 days.
- [ ] AI triage output is labelled, stored with model version, and cannot change case state (test).
- [ ] The mediation flow works end to end with SLAs tracked and conflicts blocked.
- [ ] Metrics match hand-computed fixtures and are consumed by 008 and 009.

## 7. Dependencies & Open Questions

- **Q1:** Is mediation included for free on all plans at launch, or is it a paid add-on for businesses? (Consumers always free.)
- **Q2:** Mediator staffing capacity, which sets the 10-business-day SLA.
- **Q3:** Legal review of "resolve first" in each launch jurisdiction, because it touches anti-suppression rules. The consumer-only control is designed to satisfy them.
