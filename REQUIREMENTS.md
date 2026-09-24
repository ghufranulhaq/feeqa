# Trust & Review Platform: Client Requirements

> This file collects the client's business model and feature requirements.
> Part 1 is the business model plan. Part 2 is the second list of differentiators the client provided.
> Part 3 shows where the two lists overlap.

---

## Table of Contents

- [Part 1: Business Model Plan](#part-1-business-model-plan)
  - [1.1 Revenue Streams](#11-revenue-streams)
  - [1.2 Unique & Differentiating Features](#12-unique--differentiating-features)
  - [1.3 Go-to-Market Strategy](#13-go-to-market-strategy)
- [Part 2: Additional Unique Features (New & Differentiated)](#part-2-additional-unique-features-new--differentiated)
- [Part 3: Feature Overlap Map](#part-3-feature-overlap-map)

---

## Part 1: Business Model Plan

### 1.1 Revenue Streams

| # | Revenue Stream | Description |
|---|----------------|-------------|
| 1 | **Freemium SaaS for Businesses** | Businesses get a free basic listing. Paid tiers unlock analytics, response tools, review invitations, and verified badges. |
| 2 | **Subscription Plans** | Tiered pricing (**Starter → Pro → Enterprise**) based on review volume, integrations, and support level. |
| 3 | **Advertising & Promoted Listings** | Businesses pay for visibility in search results and category pages. |
| 4 | **API & Data Licensing** | Partners such as e-commerce platforms and comparison sites pay for access to review data and sentiment analytics. |
| 5 | **Transaction / Lead Fees** | The platform takes a cut when reviews drive trackable conversions (clicks, bookings, purchases). |

### 1.2 Unique & Differentiating Features

#### 1. Proof-of-Experience Verification

Verification goes beyond email. Reviewers must prove they actually used the service, using one of:

- Receipt upload
- Order ID matching
- Integration with payment providers

Such reviews show a **"Verified Experience"** badge that is **cryptographically tied to the transaction**.

#### 2. Review Lifecycle Tracking

- Reviewers can update their review over time: at **30 days, 6 months, and 1 year**.
- This shows how the experience holds up over time.
- **Businesses** see retention signals.
- **Consumers** see how durable the experience was, not just first impressions.

#### 3. AI-Powered Review Summaries & Sentiment Trends

- Each business gets an auto-generated **"What People Love / What Needs Work"** summary.
- **Sentiment trendlines** show whether a company is improving or declining.

#### 4. Anonymous Employee & Insider Reviews (Verified)

- Employees and insiders can leave reviews with **verified employment status**, checked via one of:
  - LinkedIn
  - Payroll integration
  - Work email domain
- These reviews show consumers the company's culture and ethics. This is very valuable for **B2B buyers and job seekers**.

#### 5. Dispute Resolution & Mediation Layer

- An **optional mediation flow** for cases where a reviewer and a business disagree.
- A neutral party (or **AI-assisted triage**) helps resolve the issue **before the review goes public**.
- This builds trust with both reviewers and businesses.

#### 6. Review Portability & Ownership

- Users own their review history.
- Users can **export reviews**.
- Reviews link to a **personal reputation profile**.
- Credibility carries across platforms, like a **"reviewer passport"**.

#### 7. Context-Aware Review Prompts

- Instead of the generic "How was your experience?", users get **smart questions based on the business type**.
  - *Example (hotel):* cleanliness, check-in speed, noise level.
- This produces richer, more actionable feedback.

#### 8. Reward Honest, Detailed Reviews

- Gamification rewards **quality, not volume**.
- Reviewers earn perks (discounts, early access) based on:
  - Helpfulness votes
  - Level of detail
  - Follow-up updates
- Star ratings alone do not earn rewards.

#### 9. Competitor Benchmarking Dashboard (B2B)

- Businesses compare their review metrics against **anonymized industry averages** or **direct competitors**.
- The focus is actionable insight, not vanity metrics.

#### 10. Open API for Embeddable Trust Widgets

- Any website can embed **live, tamper-proof review widgets**.
- Businesses can display reviews anywhere.
- Consumers know the data is **authentic and up-to-date**.

### 1.3 Go-to-Market Strategy

- **Vertical-first launch:** Launch in one vertical (e.g., SaaS tools, local services, or e-commerce) to build density and trust before expanding.
- **Strategic partnerships:** Partner with payment and e-commerce platforms so proof-of-experience works seamlessly.
- **Radical transparency:** Publish moderation policies, appeal stats, and algorithmic ranking factors.

---

## Part 2: Additional Unique Features (New & Differentiated)

> These are the most important differentiators.

### A) Verified Transaction Reviews

**Problem:** Fake reviews are the biggest problem with review sites.

**Requirement:** The reviewer must provide proof, using one of:

- Invoice
- Booking reference
- Receipt
- Order ID

Verified reviews are marked as **"Verified Experience"**.

### B) AI Dispute Timeline

Beyond the review itself, users can track the full complaint lifecycle:

| Milestone | Tracked Date |
|-----------|--------------|
| Complaint raised | ✅ |
| Company reply | ✅ |
| Resolution | ✅ |
| Refund completed | ✅ |

**Example:**

```
Complaint raised : 10 Mar
Response         : 12 Mar
Resolved         : 18 Mar
```

### C) Trust Score Beyond Star Rating

Alongside the star rating (e.g., 4.5/5), each business gets a **Trust Index score out of 100**.

**Score inputs:**

- Review score
- Verification %
- Complaint resolution rate
- Refund speed
- Repeat customer satisfaction
- Dispute frequency

**Example:**

```
Trust Score: 87/100
```

### D) Industry Comparison Engine

Users can compare companies side by side (e.g., 3 airlines or agencies).

**Example:**

| Metric | Company A | Company B |
|--------|-----------|-----------|
| Avg response | 4 hrs | 2 days |
| Refund success | 92% | 61% |
| Repeat use | 71% | 44% |

### E) Voice / Video Reviews

Users can upload:

- Short **video feedback**
- **Voice note** reviews

### F) Resolution Rating

The user rates how the business handled a problem, in addition to rating the service.

- **Question:** *"How well did they resolve the issue?"*
- **Purpose:** Helps businesses measure and improve their **recovery quality**.

---

## Part 3: Feature Overlap Map

Several features in Part 2 overlap with Part 1. This table shows where to consolidate them during design.

| Part 2 Feature | Related Part 1 Feature(s) | Notes |
|----------------|---------------------------|-------|
| A) Verified Transaction Reviews | 1. Proof-of-Experience Verification | Same core idea. Both use the **"Verified Experience"** badge. Part 2 adds invoice and booking reference as proof types. |
| B) AI Dispute Timeline | 5. Dispute Resolution & Mediation Layer | The timeline could be the public, tracked side of the mediation flow. |
| C) Trust Score (Trust Index /100) | 3. AI Summaries & Sentiment Trends, 1. Verification | The Trust Score uses verification %, resolution, and refund data from other features. |
| D) Industry Comparison Engine | 9. Competitor Benchmarking Dashboard (B2B) | Part 1 is for businesses (B2B). Part 2 is a side-by-side comparison for consumers. |
| E) Voice / Video Reviews | 7. Context-Aware Review Prompts | New media formats. No direct equivalent in Part 1. |
| F) Resolution Rating | 5. Dispute Resolution, B) AI Dispute Timeline | Feeds the "complaint resolution rate" input of the Trust Score. |
