# Spec 007: Business Replies & Engagement

**Status:** Draft · **Phase:** 1 (MVP) · **Depends on:** 002, 003
**Related:** 010 (cases), 011 (AI), 015 (analytics)

## 1. Goal

Let businesses respond to reviews publicly and quickly, so consumers can see how a business treats customers when things go wrong. Show reply behaviour on the profile as a trust signal. Give businesses notifications and AI-assisted drafting so replying is easy, while a person always approves what gets published.

## 2. User Scenarios

1. **Reply to a negative review.** When a Responder opens a 1★ review in the business dashboard and posts a reply, the reply appears under the review with the business's name and logo and the date. The reviewer gets an email.
2. **AI-drafted reply.** A Responder clicks "Suggest reply", gets a draft that fits the review's content and the brand's tone settings, edits it, and posts it. The draft is never published unless someone clicks Post.
3. **Notifications.** An Admin sets "email me instantly for 1–2★ reviews, daily digest for the rest". Instant alerts arrive within 5 minutes of a review being published.
4. **Reply behaviour signals.** A consumer on the profile sees "Replied to 86% of negative reviews · Typically replies within 2 days".
5. **Edit or delete a reply.** A Responder fixes a reply, and it shows "Edited". An Admin deletes a reply, and the review shows no reply.
6. **Reviewer reacts to a reply.** After a reply that resolves the issue, the reviewer adds a lifecycle update (003) or opens a case (010) if it isn't resolved.

## 3. Functional Requirements

- **FR-007-01** A business user with the Responder role or above may post **one public reply** per review (and one reply per lifecycle update) on reviews of their Business.
- **FR-007-02** Reply text must be 2–3,000 characters of plain text. It is screened like other content (006) and must not contain personal data of the reviewer beyond their display name.
- **FR-007-03** Replies publish right away after automated screening (target ≤ 60 s). They are shown under the review with the business name, logo, publication date, and "Edited" if changed.
- **FR-007-04** Replies may be edited or deleted by a Responder or above at any time.
- **FR-007-05** The reviewer must be notified (email + in-app) when a reply is posted or edited, subject to their notification preferences.
- **FR-007-06** **AI reply suggestions** (plan-gated, 017): given the review text, rating, question answers, prior replies, and the Business's tone settings (formal/friendly, sign-off, do/don't phrases), the system generates a draft. Drafts must:
  - never be posted automatically;
  - be labelled internally as AI-assisted, with model version logged;
  - never make up facts about refunds, compensation, or case outcomes (the prompt forbids it, and output checks look for monetary amounts not present in the input).
- **FR-007-07** **Reply templates**: Admins may save up to 50 templates with placeholders (`{reviewer_name}`, `{business_name}`). A template-based reply must be edited or confirmed before posting. Posting the same identical reply text to more than 10 reviews in 24 hours triggers a warning to the business.
- **FR-007-08** **Notifications**: per business user, configurable per star rating: instant / daily digest / weekly digest / off, by email and in-app. Instant alerts must be sent within 5 minutes of the review being published.
- **FR-007-09** **Reply-behaviour signals** on the public profile, computed daily over the last 12 months:
  - *Negative reply rate* = replied 1–2★ reviews ÷ all 1–2★ reviews (shown when ≥ 5 negative reviews).
  - *Typical reply time* = median time from review publication to first reply, among replied reviews, shown in buckets: "within 24 hours", "within 2 days", "within 1 week", "within 1 month", "more than a month".
  - When the negative reply rate is 0% with ≥ 5 negative reviews: "Hasn't replied to negative reviews".
- **FR-007-10** A business reply must never change the review's rating, visibility, or ordering (P2, P3).
- **FR-007-11** Businesses may connect a helpdesk (Phase 2, via 016 webhooks/API) to receive reviews and post replies from the helpdesk. The same rules apply as for the dashboard.
- **FR-007-12** Business users can view an inbox of reviews with filters: unreplied, rating, verified, has case, location, date, language, and assigned-to. Admins can assign reviews to team members.

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Empty reply or only whitespace | Reject. |
| Reply > 3,000 characters | Reject. |
| Reply contains the reviewer's email, phone, order address, or full name (not public) | Reject with a personal-data error. |
| Reply contains threats, insults, or asks the reviewer to remove or change the review in exchange for something | Reject (006 `incentivised`/`harmful_illegal`). This behaviour is also a business ladder signal. |
| Reply on a removed or deleted review | Not possible. Existing replies are hidden along with the review. |
| Two Responders reply at the same time | The first one wins. The second gets a conflict error and sees the existing reply. |
| Business under Consumer Warning | Replies still allowed (reply/flag-only access). |
| Review deleted by the author after a reply | The reply is deleted with it. |
| AI suggestion service unavailable | The button shows "unavailable". Manual reply still works. |
| AI suggestion includes a money amount or promise not present in the input | Draft blocked and regenerated once; if it fails again, no draft is shown. |
| Unauthorized: an Analyst posts a reply | 403. |
| Reply to a review of a different Business the user has no membership in | 403. |

## 5. Out of Scope

- Fully automatic replies without human approval.
- Private messaging between the business and the reviewer outside of cases (010).
- Replies from a business to reviews of competitors.
- Public "reactions" (likes) on replies.

## 6. Acceptance Criteria

- [ ] Replies can be posted, edited, and deleted by permitted roles only (authorization tests per role).
- [ ] Screening rejects personal data and pressure/incentive language (test corpus).
- [ ] AI drafts are never auto-posted, and the invented-amount check blocks unsafe drafts (tests).
- [ ] Notifications arrive within 5 minutes for instant alerts in staging load tests.
- [ ] Reply-behaviour signals match hand-computed fixtures, including buckets and the "hasn't replied" state.
- [ ] Replies never change review scores or ordering (test).

## 7. Dependencies & Open Questions

- **Q1:** Should the reviewer be able to post one follow-up comment under a business reply (Trustpilot does not)? *Proposed:* no. Use lifecycle updates or cases instead.
