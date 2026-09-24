# Spec 009: Search, Discovery & Comparison

**Status:** Draft · **Phase:** 1 (MVP) · **Depends on:** 002, 008
**Client requirements:** D) Industry comparison engine, Revenue: promoted listings (display rules), GTM vertical-first

## 1. Goal

Help consumers find the right business fast and compare candidates on the things that matter: rating, trust, response time, refunds, and repeat use. Do it with transparent, published ranking rules. Paid visibility is always clearly labelled and never changes organic rank or scores.

## 2. User Scenarios

1. **Search by name.** When a consumer types "skyh" in the header search, they see up to 8 suggestions (logo, name, domain, Review Score, review count) within 200 ms. Pressing Enter opens a full results page.
2. **Browse a category.** When a consumer opens *Travel → Airlines*, they see businesses ranked by the published category ranking, can filter by country, minimum Review Score, minimum Trust Index, "Verified Experience ≥ 50%", and "Replies to negative reviews", and can sort by relevance, Review Score, Trust Index, or most reviewed.
3. **Compare side by side.** When a consumer ticks "Compare" on three airlines and opens the comparison, they see a table:

   | Metric | Airline A | Airline B | Airline C |
   |--------|-----------|-----------|-----------|
   | Review Score | 4.3 | 3.1 | 3.9 |
   | Trust Index | 87 | 52 | 74 |
   | Avg first response | 4 hrs | 2 days | 9 hrs |
   | Refund success | 92% | 61% | 80% |
   | Median refund time | 6 days | 34 days | 12 days |
   | Repeat use | 71% | 44% | 58% |
   | Verified reviews | 64% | 22% | 48% |
   | Top praised / top complaint | on-time / baggage | crew / refunds | seats / delays |

   Plus category question averages (e.g., on-time performance 4.1 vs 3.2). The best value in each row is highlighted.
4. **Shareable comparison.** The consumer copies the comparison URL and sends it to a friend, who sees the same comparison.
5. **Sponsored result.** In a category page, the first slot shows a business marked **Sponsored**. The organic list below it is unchanged by the sponsorship, and the sponsored business also appears in its own organic position.
6. **Similar businesses.** At the bottom of a profile, "People also looked at" shows 6 businesses in the same category with their scores.

## 3. Functional Requirements

### Search
- **FR-009-01** Search must match business name, domain, alias, and location city, and be tolerant of typos (≥ 1 edit for terms of ≥ 5 characters). Autocomplete suggestions must return within p95 200 ms.
- **FR-009-02** Results show: logo, name, domain, primary category, location (if relevant), Review Score + stars + label, review count, Trust Index (or "Not enough data"), Consumer Warning badge if applicable, and "Claimed" label.
- **FR-009-03** Search results are ordered by text relevance first. Among equally relevant results, businesses with more eligible reviews rank higher. Businesses in `pending` or `suspended` status are excluded. Closed businesses are shown with a "Closed" label.

### Category pages & rankings
- **FR-009-04** Category pages list businesses in that category and its sub-categories, for **launched** categories only (002).
- **FR-009-05** The default **category ranking** ("Best in <category>") must use a published, plan-independent formula. It includes only businesses with ≥ 25 eligible reviews in the last 12 months and no Consumer Warning, ordered by Trust Index, then Review Score, then eligible review count in the last 12 months.
- **FR-009-06** Filters: country, city (for businesses with locations), minimum Review Score, minimum Trust Index, verified % ≥ 50, replies to negative reviews ≥ 50%, claimed only. Sorts: relevance/ranking (default), Review Score, Trust Index, most reviewed, most recently reviewed.
- **FR-009-07** Category pages must be paginated (page size 20) with stable ordering across pages for a given snapshot.

### Comparison engine
- **FR-009-08** A consumer may compare **2 to 4 businesses**. Businesses from different categories may be compared, but category-specific question rows appear only for questions all of them share.
- **FR-009-09** Comparison metrics (rolling last 12 months, from 007, 008, 010, 003):
  - Review Score and label; Trust Index (and components);
  - eligible review count; % Verified Experience;
  - average first response time to reviews (007) and cases (010);
  - refund success rate = refunds completed ÷ refunds requested (010);
  - median refund time;
  - resolution rate and average resolution rating (010);
  - **repeat use** = % of verified reviewers with ≥ 2 verified experiences, or who answered "would use again = yes" (where the category question exists);
  - category question averages;
  - top 3 praised topics and top 3 complaint topics (011, Phase 2; hidden in Phase 1).
- **FR-009-10** Each metric must show "Not enough data" rather than a number when its minimum sample isn't met (same minimums as 008 components; ≥ 5 for rates).
- **FR-009-11** The best value per row is highlighted, but **no overall winner** is declared.
- **FR-009-12** Comparisons have a shareable URL encoding the business IDs. Opening it computes the latest values.
- **FR-009-13** Sponsored placements must **never** appear inside a comparison table, and plans must not change comparison values or order (order = the order the consumer chose).

### Similar businesses
- **FR-009-14** "People also looked at" shows up to 6 businesses in the same primary category, chosen from co-view data (consumers who viewed both within 7 days). If there isn't enough co-view data, it falls back to the category ranking. It must exclude businesses under Consumer Warning and must not be paid.

### Sponsored placements (display rules; commercial side in 017)
- **FR-009-15** Sponsored slots: up to **1** at the top of a category page, up to **1** in search results (for category-level queries only, never for a query matching a specific business's name/domain). There are **no** sponsored slots on business profile pages in v1 (see Q1).
- **FR-009-16** Every sponsored slot must show a visible **"Sponsored"** label, be visually distinct, and link to a "Why am I seeing this?" explanation.
- **FR-009-17** Sponsored placement must not remove or move the business in organic results. It must not be sold to businesses under Consumer Warning or with a Review Score below 3.0.

### SEO & discovery
- **FR-009-18** Profile, category, and review pages must be server-rendered, have canonical URLs, and include structured data (`Organization`/`LocalBusiness` with `AggregateRating`, `Review`). They are listed in XML sitemaps. Hidden scores (Consumer Warning) must not be in structured data.

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Empty search query | Show popular categories and recently reviewed businesses. |
| Query > 200 characters, or only special characters | Truncate to 200 characters, or return no results with suggestions. |
| Query with a URL | Normalise to domain and match exactly first. |
| No results | Offer "Add this business" (002). |
| Comparison with 1 or > 4 businesses | Reject with a message. |
| Comparison including the same business twice | Deduplicate. |
| Comparison with a business under Consumer Warning | Allowed, but its column shows the warning and hides both scores. |
| Comparison URL with an invalid or removed ID | Show the remaining valid businesses, and a note about the missing one. |
| Business has 0 cases | Case-based rows show "No cases recorded", which is different from "Not enough data". |
| Scraping-like traffic on search/compare | Rate-limited per IP, with bot protection. The public API (016) is the supported path. |
| Unauthorized: a business tries to pay to exclude competitors from "People also looked at" | No such option exists. |

## 5. Out of Scope

- Personalised or behaviour-based ranking for signed-in users.
- Map view and "near me" geolocation search (Phase 3).
- Price comparison of products or fares.
- An "overall winner" or recommendation badge in comparisons.
- Sponsored placement auctions and billing (017).

## 6. Acceptance Criteria

- [ ] Autocomplete meets p95 ≤ 200 ms on a 1M-business index in staging.
- [ ] Category ranking matches the published formula on fixture data. Changing plan or ad spend doesn't change organic order (test).
- [ ] Comparison shows all FR-009-09 metrics with correct values and "Not enough data" handling for 2, 3, and 4 businesses.
- [ ] No sponsored content appears in comparisons. Sponsored slots are labelled and don't change organic positions (test).
- [ ] Structured data validates, and hidden scores are omitted for warned businesses.
- [ ] Every filter and sort works on the seeded dataset.

## 7. Dependencies & Open Questions

- **Q1:** Are sponsored placements allowed on **competitors' profile pages**? This is common in the industry but could look like it undermines neutrality. *Proposed:* **no** for v1.
- **Q2:** Launch vertical determines which category-question rows the comparison shows first.
