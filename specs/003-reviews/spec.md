# Spec 003: Review Submission & Lifecycle

**Status:** Draft · **Phase:** 1 (MVP) · **Depends on:** 001, 002
**Client requirements:** F2 Review Lifecycle Tracking, F7 Context-Aware Review Prompts

## 1. Goal

Let consumers write honest, useful reviews of a business, a location, or a product. Category-specific questions make the feedback richer, and follow-up updates show whether the experience held up over time. Every review shows where it came from, and only its author can change it.

## 2. User Scenarios

1. **Organic service review.** When a signed-in consumer opens a hotel's profile and clicks "Write a review", they choose 1–5 stars, answer the hotel question set (cleanliness, check-in speed, noise level), write a title and text, set the date of experience, and submit. Within about a minute the review appears on the profile with the label **Organic**, and the Review Score updates.
2. **Invited review.** When a consumer clicks a unique invitation link from an airline (005), the form opens with the business and reference number pre-filled. After publishing, the review shows **Invited**, and it also shows **Verified Experience** if the invitation came from a transaction-linked method (004).
3. **Product review.** When a consumer invited after a purchase follows the product review link, they rate each purchased product (stars, title, text). Each product review appears on that product's widget and on the business's product tab.
4. **Lifecycle update.** Thirty days after publishing, the consumer gets an email: "Still happy with SkyHop? Add an update." They add a 3-star update saying the refund took too long. The review now shows a timeline (original 5★ on 10 Mar → update 3★ on 9 Apr). The current rating shown and used in scores is 3★.
5. **Author edits and deletes.** When the author fixes a typo, the review shows "Edited". When the author deletes it, it disappears from the profile and the score is recalculated.
6. **Useful vote.** A reader who finds a review helpful taps "Useful". The count goes up by one, and tapping again removes the vote.
7. **Filtering.** A reader filters a profile's reviews to "1–2 stars, Verified Experience only, with replies, last 6 months" and sorts by most recent.

## 3. Functional Requirements

### Submission
- **FR-003-01** Review types: **service** (the Business), **location** (a Location of the Business), **product** (a Product of the Business).
- **FR-003-02** A service or location review must include: star rating (integer 1–5), title (5–100 characters), text (30–5,000 characters), and date of experience. Optional: reference/order number (≤ 64 characters) and answers to the category question set (002).
- **FR-003-03** A product review must include: star rating, text (10–2,000 characters), and an optional title. Product reviews may be submitted **only** through a product invitation (005) tied to that product.
- **FR-003-04** The date of experience must not be in the future, and must be within the **last 12 months** of the submission date.
- **FR-003-05** The form must show the question set for the Business's primary category (or the Location's category, if set). Required questions must be answered, and optional ones may be skipped. Answers are stored with the question-set version.
- **FR-003-06** The author must confirm, with a checkbox recorded per review, that the review describes their own genuine experience and that they received no incentive from the business.
- **FR-003-07** A user holding any membership (001) in the Business must be blocked from submitting a customer review of that Business.
- **FR-003-08** A consumer may publish at most **one review per Business per 30 days**, unless the new review is tied to a different invitation or a different verified transaction (004). A new review about a different experience does not replace the earlier one, but **only the consumer's most recent review of a Business counts toward that Business's scores** (008).
- **FR-003-09** The system must store the review's language, detected automatically and correctable by the author.
- **FR-003-10** Draft reviews must be saved automatically (on the device, and on the server for signed-in users) and restored if the user signs in part-way through.

### Publication pipeline
- **FR-003-11** Every submitted review must pass automated screening (006) and end up as `published`, `held` (awaiting human moderation), or `rejected` (with a reason shown to the author).
- **FR-003-12** At least 95% of reviews must get an automated decision within 60 seconds. Held reviews must show "Pending" to the author only.
- **FR-003-13** The system must **never** let the reviewed Business delay, hold, or approve a review before it is published.

### Source labels
- **FR-003-14** Every review must carry exactly one source label:
  - `Invited`: submitted through a unique invitation tied to a customer record (005).
  - `Redirected`: submitted through the business's generic review link or QR code (005).
  - `Organic`: submitted without any business-originated link.
- **FR-003-15** The source label must be set by the system from how the review arrived. It cannot be edited by anyone. It is shown on every review card, with a tooltip explaining it.
- **FR-003-16** The **Verified Experience** badge (004) is shown in addition to the source label and can apply to any source.

### Lifecycle updates (F2)
- **FR-003-17** A published service or location review can receive up to **three lifecycle updates**, one per milestone: **30 days, 6 months (180 days), and 1 year (365 days)** after the original publication date.
- **FR-003-18** Each milestone window opens on the milestone day and closes when the next window opens. The 1-year window stays open for 90 days.
- **FR-003-19** When a milestone window opens, the system must send the author one reminder, by email and in-app, unless they have opted out.
- **FR-003-20** An update must include: star rating (1–5), text (20–2,000 characters), and optionally answers to the category questions. Updates go through the same screening as reviews (FR-003-11).
- **FR-003-21** The review card must show the original and all updates as a dated timeline. The **current rating** is the latest published update's rating, or the original rating if there are no updates. Scores use the current rating.
- **FR-003-22** The system must derive and store a **durability signal** per review: `improved`, `unchanged`, or `declined`, from comparing the current rating with the original. This signal feeds analytics (015) and the Trust Index "repeat satisfaction" input (008).

### Edit & delete
- **FR-003-23** Authors may edit a review or update at any time. Edited content is screened again. The card shows "Edited" with the date. The rating history of edits is kept for audit, but it is not public.
- **FR-003-24** Authors may delete their review at any time. Deletion removes the review and its updates from public view and from scores right away.
- **FR-003-25** Nobody other than the author (edit/delete) and staff moderation (006) can change a review's content or visibility.

### Reading & interaction
- **FR-003-26** The review card must show: author display name, avatar, country, the author's published review count, star rating (current), title, text, date of experience, published date, source label, Verified Experience badge (if any), question answers, lifecycle timeline, business reply (007), case summary (010, if linked), Useful count, Share, and Report.
- **FR-003-27** Signed-in users may mark any review except their own as **Useful**, once. Tapping again removes the vote.
- **FR-003-28** Profile review lists must support sorting by *Most recent* (default) and *Most useful*, and filtering by star rating (multi-select), Verified Experience, source label, has reply, has case, has update, language, date range, and location.
- **FR-003-29** Review lists must be paginated with a maximum page size of 50. Each review must have a permanent URL.
- **FR-003-30** Business Review Scores (008) must be recalculated after every publish, edit, delete, update, or moderation change.

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Empty title or text, or only whitespace/emoji | Reject with a field error. Emoji count toward length, but text must have ≥ 30 non-whitespace characters. |
| Text > 5,000 characters, pasted HTML, or scripts | Reject if over the limit. Strip all markup and store plain text only. Render links as non-clickable text unless they are to the reviewed business's own domain. |
| Rating missing or outside 1–5 | Reject. |
| Date of experience in the future or > 12 months ago | Reject with an explanation. |
| Duplicate submission (same user, business, and near-identical text within 30 days) | Reject as duplicate. Idempotency key on submit prevents double-post from double-click. |
| Same text posted to several businesses | Allowed at submit time, but flagged for fraud review (006). |
| Invitation link used twice | The second use opens the existing review for editing. It does not create a new review. |
| Invitation link used by a different signed-in account than the invited email | Allowed, but the label becomes `Organic`, the invitation's verification does not transfer, and the event is sent to fraud signals. |
| Review of a `closed` Business | Allowed until 12 months after the closure date. |
| Review of a Business with a Consumer Warning | Allowed. |
| Lifecycle update submitted outside a window | Reject: "Your next update opens on <date>". |
| Update submitted for a deleted or removed review | Reject. |
| Author account deleted | All their reviews are removed from public view and scores (001). |
| Business user tries to edit or delete a review through the API | 403 + audit event. |
| Useful vote spam (many votes from new accounts) | Votes from accounts flagged by fraud detection are not counted. |
| Unauthenticated user submits | Draft kept. Sign-in required before submit. |
| Question-set answer for a question not in the set | Ignore it and log it. |

## 5. Out of Scope

- Photo attachments on reviews. Voice and video are in 012; photos may be added later in a separate spec.
- Anonymous (no-account) reviews. Insider anonymity is handled in 013.
- Reviews of individual employees or people.
- Q&A (questions to the business from shoppers).
- Machine translation of reviews (possible Phase 3).
- Reviewer-to-reviewer comments or threads.

## 6. Acceptance Criteria

- [ ] Service, location, and product reviews can be created with all validation rules enforced (a test for each rule in §3 and §4).
- [ ] Product reviews can only be created through product invitations.
- [ ] Members of a Business cannot review it.
- [ ] Source labels are set correctly for the invitation, generic link, and organic paths, and cannot be edited.
- [ ] Lifecycle windows open and close on the right days (time-travel tests at days 29/30/179/180/364/365/455). The current rating and durability signal are correct.
- [ ] Edit and delete by the author work. Nobody else can edit or delete (API authorization tests).
- [ ] Scores recalculate within 60 s of each triggering event.
- [ ] Filters and sorts return correct results on a seeded dataset.
- [ ] No business-facing endpoint can delay or approve publication.

## 7. Dependencies & Open Questions

- **Q1:** Should lifecycle milestones count from the **date of experience** or the **publication date**? *Proposed:* publication date, because it is simpler and can't be gamed through the date field.
- **Q2:** Should the **original** rating also stay visible in the header summary (e.g., "Originally 5★")? *Proposed:* yes, on the card only.
