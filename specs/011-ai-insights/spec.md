# Spec 011: AI Summaries, Topics & Sentiment Trends

**Status:** Draft · **Phase:** 2 · **Depends on:** 003, 006, 008
**Client requirements:** F3 AI-Powered Review Summaries & Sentiment Trends

## 1. Goal

Help consumers understand hundreds of reviews in seconds, and help businesses see what to fix. Each business gets a faithful, AI-generated **"What People Love / What Needs Work"** summary, topic breakdowns backed by real quotes, and sentiment trendlines that show whether the business is getting better or worse. Every claim can be traced back to real reviews and is clearly labelled as AI.

## 2. User Scenarios

1. **Summary on the profile.** When a consumer opens an airline profile, they see a box labelled "AI summary of 2,418 reviews · updated 3 days ago":
   - **What People Love:** friendly crew, on-time departures on short-haul routes.
   - **What Needs Work:** slow refunds after cancellations, baggage delays at hub airports.

   Each point links to the reviews behind it.
2. **Topic drill-down.** A consumer clicks the topic "Refunds" and sees its sentiment (72% negative), trend (improving), and 5 representative quotes, filtered from the review list.
3. **Trendline.** A consumer views the Sentiment Trend chart: monthly positive/neutral/negative share and Review Score over 24 months, with a trend label "Improving" (+0.4 over 6 months).
4. **Business insight.** A business Analyst sees topic sentiment per location and per month, and the topics that moved the most in the last 30 days.
5. **Wrong summary reported.** A business reports that the summary claims "no refunds are ever paid". A moderator checks the cited reviews, finds the claim is overstated, and regenerates the summary with the correction. The report is logged.

## 3. Functional Requirements

### Summaries
- **FR-011-01** A summary is generated for a Business (and each Location with ≥ 50 eligible reviews) once it has **≥ 20 eligible reviews** in the last 12 months. Otherwise no summary is shown.
- **FR-011-02** The summary contains an overall one-sentence overview (≤ 40 words), **What People Love** (2–5 bullets), and **What Needs Work** (2–5 bullets). Each bullet is ≤ 20 words.
- **FR-011-03** Every bullet must cite **at least 3** supporting eligible reviews (IDs stored). Bullets that can't be supported by at least 3 reviews must be dropped. The UI must link each bullet to its supporting reviews.
- **FR-011-04** Inputs are limited to published, eligible customer reviews (including lifecycle updates) from the last 12 months, weighted towards the last 3 months. Excluded inputs: insider reviews (013, which get a separate summary in that spec), removed content, business replies, and anything from paid features.
- **FR-011-05** Summaries must be regenerated at least **weekly**, and whenever eligible review count changes by ≥ 10% or the Review Score changes by ≥ 0.2 since the last generation.
- **FR-011-06** Output checks before publishing:
  - no personal data (names, contact details);
  - no claims absent from the cited reviews (automated checker compares entailment against the cited reviews);
  - no defamatory absolutes ("always", "never", "scam") unless ≥ 20% of cited reviews use that wording;
  - no mention of star ratings that contradict the Review Score.
  A failed check blocks publication and the previous summary stays.
- **FR-011-07** Summaries must show the label "AI-generated summary", the generation date, the number of reviews considered, and a "Report a problem" link.
- **FR-011-08** A business **cannot** edit, suppress, or pay to change its summary. It can only report errors, which go to the 006 moderation queue. Staff may regenerate a summary, or hide it for up to 14 days while checking a report.

### Topics & sentiment
- **FR-011-09** Each eligible review (and update) is tagged with 0–5 **topics** from a per-category **topic taxonomy** (staff-managed, e.g., Airlines: refunds, delays, baggage, crew, seating, booking, customer service, price/value). Each tag gets a sentiment (`positive`, `neutral`, `negative`) and a confidence score.
- **FR-011-10** Tags with confidence below a threshold (per methodology) are not shown publicly but are kept for analytics.
- **FR-011-11** The profile shows up to 6 topics with mention count, % positive/negative, and 3–5 representative quotes (short excerpts ≤ 200 characters, linked to the full review). Topics are ordered by mention count.
- **FR-011-12** Review-level sentiment (overall) is computed from the text, not just the star rating, and stored for trend and inconsistency analysis. A strong mismatch (e.g., 5★ with strongly negative text) is sent to fraud signals (006) as a weak signal only.

### Trends
- **FR-011-13** Sentiment trendlines: monthly series for up to 36 months of Review Score (from 008 snapshots), share of positive/neutral/negative reviews, and per-topic sentiment.
- **FR-011-14** Trend label computed monthly: **Improving** if the 6-month Review Score slope is ≥ +0.2 **and** at least 30 eligible reviews fall in that window; **Declining** if the slope is ≤ −0.2 with the same sample rule; otherwise **Stable**. Below the sample rule: no label.
- **FR-011-15** Charts must be accessible: a data table alternative, colour-blind-safe palette, and keyboard focusable points.

### Governance
- **FR-011-16** Every AI output (summary, topic tag, sentiment) stores model identifier, prompt/template version, input review IDs, and generation time. Previous summaries are kept for 24 months for audit.
- **FR-011-17** A monthly quality audit samples ≥ 50 summaries and ≥ 500 topic tags for human review. Faithfulness target ≥ 95% of bullets supported, and topic precision ≥ 85%. Results are published in aggregate in the transparency report (006).
- **FR-011-18** Review content sent to any external AI provider must go through a processor agreement that forbids training on Platform data, and must be listed in the privacy notice.

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Fewer than 20 eligible reviews | No summary. Show "Summary available after 20 reviews". |
| All reviews positive (nothing "needs work") | Show "Reviewers didn't raise consistent issues" instead of inventing bullets. The same applies the other way round. |
| Reviews in several languages | Summarise across all languages. Output in the viewer's locale if supported, otherwise English. Quotes stay in their original language and are marked. |
| Reviews contain prompt-injection text ("ignore previous instructions…") | Inputs are treated as data. The output checker rejects outputs that follow injected instructions. Such reviews are flagged to 006. |
| Very long reviews (5,000 characters) | Truncate per review input to a fixed budget. Citations still point to the full review. |
| Burst of suspicious reviews under investigation (006 incident) | Freeze summary regeneration for the business until the incident is closed. |
| Business under Consumer Warning | Summary still shown (it informs consumers), with the warning above it. |
| AI provider outage | Keep the last valid summary. Show its date. |
| Unauthorized: a business calls a summary-regeneration endpoint | 403. |

## 5. Out of Scope

- Chatbot / conversational Q&A over reviews ("Ask about this business"). Phase 3.
- AI-written reviews or AI assistance for consumers writing reviews.
- Machine translation of full reviews.
- Real-time (per-review) summary updates.

## 6. Acceptance Criteria

- [ ] Summaries appear only above thresholds, with correct labels, dates, counts, and citations.
- [ ] Output checks block personal data, unsupported claims, and injected instructions (adversarial fixture set of ≥ 100 cases).
- [ ] Topic tagging and sentiment stored with confidence. Only high-confidence tags shown.
- [ ] Trend labels match fixture series (improving/declining/stable/none).
- [ ] Businesses cannot edit or suppress summaries. Reports reach the moderation queue.
- [ ] Charts pass accessibility checks, including a table alternative.
- [ ] Provenance (model, template, inputs) is stored for every output.

## 7. Dependencies & Open Questions

- **Q1:** AI provider selection (decided in plan.md). Must meet FR-011-18.
- **Q2:** Who writes the topic taxonomy for the launch vertical?
