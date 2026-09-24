# Spec 016: Trust Widgets & Public API

**Status:** Draft · **Phase:** 1 (widgets, webhooks, business API) / 2 (open data API, licensing) · **Depends on:** 008
**Client requirements:** F10 Open API for Embeddable Trust Widgets, Revenue: API & data licensing

## 1. Goal

Let businesses show their live, authentic reviews anywhere, and let partners build on Platform data. Widgets must be **tamper-evident**, fast, and accessible. They must always show the real current data and never a curated version. The API must be documented, versioned, and rate-limited. Commercial data licensing is on top of the same API.

## 2. User Scenarios

1. **Install a widget.** An Admin opens "Trust Widgets", picks "Mini badge", sets theme and language, copies a snippet, and pastes it into their site. The badge shows "Review Score 4.3 · Excellent · Trust Index 87 · 2,418 reviews" with a link to the profile.
2. **Carousel.** A business on a paid plan embeds a review carousel filtered to "4–5★, last 6 months". The carousel **always** discloses the filter ("Showing our 4 and 5 star reviews") and links to all reviews.
3. **Tamper check.** A visitor clicks "Verify" on a widget. A Platform-hosted page confirms the widget data is authentic, current, and matches the profile. A copy made from a screenshot or a modified HTML widget shows no valid verification.
4. **Webhook.** A business subscribes to `review.published` and `case.opened` webhooks. Each event arrives signed within 60 seconds.
5. **Partner API.** A comparison site with a data licence calls the public API for business scores in the "Airlines" category, within its rate limit and licence terms.

## 3. Functional Requirements

### Widgets
- **FR-016-01** Widget types:
  - **Mini badge**: Review Score, stars, label, count, optional Trust Index;
  - **Micro star**: stars + count only;
  - **Review carousel/list**;
  - **Product rating** (per SKU);
  - **Verified Experience highlight**: count and share of verified reviews;
  - **Case record**: response and resolution metrics.
- **FR-016-02** Free plan: mini badge and micro star. Other widgets are plan-gated (017).
- **FR-016-03** Widgets are loaded by a script (≤ 30 KB gzipped, async, no layout shift beyond reserved space) that renders inside an isolated container (shadow DOM or iframe). Widgets must meet WCAG 2.2 AA and support light/dark themes and all launch locales.
- **FR-016-04** Widget data is served from the Platform with a **signature** (Platform key, 004 key infrastructure) over the data payload and timestamp. The widget shows a "Verify" link that opens a Platform page confirming the signature and freshness.
- **FR-016-05** Data freshness: widget data must not be more than **15 minutes** old. Stale or unsigned data must render a neutral "Unable to load reviews" state rather than old numbers.
- **FR-016-06** Widget filters (star rating, topics, tags, location, product, language) are allowed, but **any** filter that excludes ratings must be disclosed inside the widget, with a link to the full profile (DMCC/FTC "not misleading" rule).
- **FR-016-07** Under Consumer Warning (006), all widgets show the warning and hide scores. Businesses cannot turn this off.
- **FR-016-08** Widgets are allowed only on domains the business registered (profile domains + up to 20 extra allowed domains). Embeds elsewhere render a "Profile on <Platform>" link only.
- **FR-016-09** Widget impressions and clicks are counted (without cookies, and without personal data) for analytics (015).
- **FR-016-10** Search-engine structured data for the business's own site: an optional JSON-LD snippet that uses **only** the current live values (no stale copies), in line with search-engine review-snippet policies.

### API
- **FR-016-11** API families:
  - **Public read API** (API key): businesses, categories, public reviews, scores, and public case metrics;
  - **Business API** (OAuth2, scoped to a Business and role): reviews inbox, replies, invitations (005), transaction records (004), analytics exports (015);
  - **Webhooks**: `review.published`, `review.updated`, `review.deleted`, `reply.published`, `case.opened`, `case.updated`, `case.closed`, `score.updated`, `invitation.status_changed`.
- **FR-016-12** All endpoints are described in an OpenAPI document, versioned in the URL (`/v1/...`). Breaking changes require a new version and ≥ 6 months' deprecation notice.
- **FR-016-13** Rate limits per key/client, returned in response headers. Defaults: public read 60 req/min (free key), business API 600 req/min. Licence/plan tiers raise limits. Exceeding a limit returns 429 with `Retry-After`.
- **FR-016-14** Webhooks are signed (HMAC with a per-subscription secret, timestamped), retried with exponential backoff for 24 hours, and have a delivery log and manual redelivery in the dashboard.
- **FR-016-15** The API must apply the same visibility rules as the UI: no removed or held content, no proofs, no insider identity, no private case data, and hidden scores under Consumer Warning.
- **FR-016-16** API responses containing reviews must include each review's permalink, source label, and verification status. API terms require displayers to show those labels.
- **FR-016-17** A developer portal must provide docs, API key management, sandbox data, and changelog.

### Data licensing (Phase 2)
- **FR-016-18** Licensed data products: bulk category exports (scores, metrics, anonymised topic/sentiment aggregates), historical score time series, and aggregated sentiment feeds. **Never** included: reviewer personal data, proofs, insider identities, case messages.
- **FR-016-19** Licences are contracts with usage terms (attribution to the Platform, no re-identification, no training of review-generating models, display of labels). Each licence has its own keys, quotas, and audit logs.
- **FR-016-20** Review **texts** in licensed bulk data are included only where the Platform's terms allow it and reviewers are informed in the privacy notice. Otherwise only aggregates are provided (legal to confirm, Q1).

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Widget embed on an unregistered domain | Show only the "Profile on <Platform>" link. |
| Widget snippet modified to change numbers | The signature check fails. The "Verify" page says "Not verified". |
| Business with 0 reviews | Widget shows "No reviews yet" + a link to write one. |
| Business hides the widget's filter disclosure through CSS | Rendered inside an isolated container. The disclosure is part of the signed layout, and hiding it is a guideline breach (006). |
| API key leaked | Keys can be rotated. Unusual usage triggers an automatic alert. |
| API request for > 100 items per page | Capped at 100. |
| Webhook endpoint down for 24 hours | Disable the subscription, and email the business. |
| Invalid OAuth scope for an endpoint | 403 with the required scope named. |
| Partner exceeds licence quota | 429, and an overage notice. No silent data truncation. |

## 5. Out of Scope

- Widgets showing reviews from other platforms.
- A consumer-facing mobile SDK (the web API is enough).
- A GraphQL API (v1 is REST + webhooks).
- Real-time streaming feeds (webhooks cover near-real-time).
- Sale of reviewer personal data (never).

## 6. Acceptance Criteria

- [ ] Every widget type renders correctly, meets the script-size and accessibility budgets, and shows filter disclosures.
- [ ] Signed payloads check correctly. Stale or modified data renders the neutral state (tests).
- [ ] Consumer Warning is shown in all widgets.
- [ ] OpenAPI is complete, and contract tests pass for every endpoint.
- [ ] Rate limits and webhook signature/retry behaviour are verified by tests.
- [ ] The API visibility rules match the UI (the same fixture checks run against both).
- [ ] Developer portal is live with sandbox.

## 7. Dependencies & Open Questions

- **Q1:** Legal: can full review texts be licensed to partners, and under what reviewer terms?
- **Q2:** Launch partners for data licensing and their required data products.
