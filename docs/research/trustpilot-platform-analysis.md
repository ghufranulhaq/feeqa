# Trustpilot Platform Analysis

> **Purpose:** Background research for building a Trustpilot-class review platform ("the Platform").
> It describes what Trustpilot does so the specs in `specs/` can match its core behaviour, and then exceed it with the client's differentiators in `add-to-trustpilot.md`.
>
> **Researched:** 2026-09-24. Public sources only (listed at the end). Some details, such as TrustScore weights, are not officially published. Those are marked *reported*, which means they come from third-party analysis.
>
> **IP note:** "Trustpilot", "TrustScore", and "TrustBox" are Trustpilot trademarks. The Platform must not use them. See the brand constraints in `CONSTITUTION.md`.

---

## 1. What Trustpilot Is

Trustpilot is an open, two-sided online review platform:

- **Consumers** search for businesses, read reviews, and write reviews about service, product, and location experiences.
- **Businesses** claim their profile for free and reply to reviews. They pay for subscriptions that add review-collection automation, widgets, analytics, AI tools, and APIs.

"Open" means anyone with a genuine experience can review any business without the business's permission. Businesses **cannot delete reviews**. They can only reply and flag reviews that breach the guidelines.

The main revenue source is **B2B SaaS subscriptions**. Consumers use the platform for free.

---

## 2. Actors

| Actor | What they do |
|-------|--------------|
| Visitor (anonymous) | Browse categories, search businesses, read profiles and reviews |
| Consumer (reviewer) | Create account (email, Google, Facebook, Apple), write/edit/delete reviews, mark reviews useful, flag reviews, respond to verification requests |
| Business user | Claim profile, edit profile info, reply to reviews, flag reviews, send invitations, view analytics, install widgets, manage users/integrations |
| Content Integrity Team (internal) | Review flagged content, investigate fraud, enforce guidelines, handle disputes and appeals |
| Integration partners | E-commerce/CRM platforms (Shopify, WooCommerce, Magento, BigCommerce, Salesforce) that trigger invitations and display reviews via apps and APIs |

---

## 3. Consumer-Facing Features

### 3.1 Home and discovery
- Navigation: *Write a review*, *Categories*, *Blog*, *Log in*, *For businesses*.
- Search for businesses by name or domain.
- Category grid (Banks, Travel Insurance, Car Dealers, Furniture Stores, Electronics, and 20+ more), with "Best in &lt;category&gt;" lists.
- A recent reviews feed.
- Country/language selector. Localised sites.

### 3.2 Business profile page
Each profile page (e.g., `/review/<domain>`) contains:
- **Header:** business name, TrustScore (one decimal, e.g., 1.6), star rating image (half-star precision), a word label (Bad / Poor / Average / Great / Excellent), total review count, categories, and a **"Claimed profile"** label with the claim date.
- **Rating distribution:** percentage bars for 5 stars down to 1 star.
- **AI review summary:** a generated paragraph describing overall sentiment and recurring themes.
- **Topics / top mentions:** themes such as *Delivery*, *Customer Service*, *Product Quality*, each with example quotes.
- **Company details:** website, description, location, contact.
- **Reply behaviour signals:** e.g., "Replied to 80% of negative reviews", "Typically replies within 1 week", or "Hasn't replied to negative reviews".
- **Integrity notice:** "We use technology to protect platform integrity, but we don't fact-check reviews."
- **Review list:** sorting (most recent, most relevant), filters (star rating, verified, with replies, language, date), and pagination.
- **"People also looked at":** similar businesses with their scores and review counts.
- **Consumer Warning banner:** shown when the business has been caught misusing the platform, for example by buying fake reviews.

### 3.3 Review card
Each review card shows the reviewer's display name, avatar, country, and number of reviews they have written. It also shows the star rating, title, body, **date of experience**, posting date, and **label** (Verified / Invited / Redirected / Unprompted). A **Useful** button, **Share**, and a **Report/Flag** action appear on the card, and the business reply is nested under the review.

### 3.4 Writing a review
- Requires a genuine experience, **typically within the last 12 months**. The reviewer must be **18 or older**.
- Fields: star rating (1–5), title, text, date of experience, optional **reference/order number**.
- The reviewer can start the review from search or from a business invitation.
- **One review per experience.** A new experience needs a new review. **Only the most recent review from a consumer counts toward the TrustScore.**
- Authors can **edit or delete their review at any time**. Businesses may not pressure them to do so.
- One account per person. Display names and avatars must not impersonate anyone or advertise.

### 3.5 Review types
- **Service reviews:** about the company overall.
- **Location reviews:** about a specific branch or store.
- **Product reviews:** about a specific product/SKU. These are collected through invitations and shown in widgets on product pages.

### 3.6 Review labels (source of the review)
| Label | Meaning |
|-------|---------|
| **Verified** | Trustpilot has confirmed a genuine experience, usually because the review came from an **automatic** invitation method (e.g., AFS) that is tied to a real transaction, or because the reviewer supplied proof. |
| **Invited** | The review came from an invitation that was sent with a **manual** method (e.g., a CSV upload). |
| **Redirected** | The business sent the consumer to the profile through a generic link (not tied to a transaction). |
| **Unprompted** | The consumer wrote the review without any business involvement. |

The review's source **does not change its weight** in the TrustScore.

### 3.7 Flagging and verification requests
- Anyone can flag a review. Flag reasons: harmful/illegal content, personal information, advertising/promotional content, not based on a genuine experience, wrong business.
- Reviews flagged for harmful content may be **blurred temporarily** while they are assessed.
- A business can ask the reviewer for **proof of experience** (email, reference number). If the reviewer shares it, the business sees it. If the reviewer declines, the review is **not** removed automatically.
- The reviewer can dispute a removal decision by replying to the notification email or using the dispute form.

---

## 4. Rating Algorithm (TrustScore)

- Only the 1–5 star ratings are used.
- **Bayesian prior:** every business starts as if it had about **7 reviews at 3.5 stars** (reported; some analyses say 9). The prior's influence fades as real reviews accumulate.
- **Time weighting:** newer reviews weigh more. Reported decay: 0–12 months ≈ 100%, 12–24 months ≈ 70%, 24–36 months ≈ 45%, 36+ months ≈ 30%. Other analyses say reviews under 3 months carry about double weight. The exact weights are **not officially published**.
- **Recalculation:** immediately after each new review for businesses with fewer than 10,000 reviews, daily for larger ones.
- The same formula applies to all industries and all subscription tiers. Paying does **not** affect the score.
- The result is shown with one decimal, as half-star images, and as one of five word labels (Bad / Poor / Average / Great / Excellent).

**Gap the client wants to close:** the TrustScore ignores verification, complaint resolution, refunds, and repeat custom. The client's **Trust Index (0–100)** adds these dimensions.

---

## 5. Business-Facing Features

### 5.1 Plans (2026, reported public pricing)
| Plan | Approx. price | Highlights |
|------|---------------|------------|
| Free | $0 | Claim profile, reply to reviews, flag reviews, basic TrustScore badge, limited manual invites |
| Plus | ~$259/mo | Automated review collection, basic widgets, basic branding |
| Premium | ~$629/mo | Higher/unlimited invitations, product reviews, advanced analytics |
| Advanced | ~$1,059/mo | Advanced API, custom integrations, AI features, dedicated support |
| Enterprise | Custom | Multi-domain/brand, unlimited users/invites, custom integrations, AI response and insights |

Contracts are annual and billed upfront, with quarterly payment available on request.

### 5.2 Review collection (invitations)
- **Automatic Feedback Service (AFS):** the business BCCs a unique address on its order/invoice emails. The platform extracts the customer's name, email, and reference, then queues an invitation a configurable number of days later. Reviews from this method are **Verified**.
- **E-commerce/CRM integrations:** Shopify, WooCommerce, Magento, BigCommerce, and Salesforce trigger invitations when an order is completed.
- **Invitation API:** requires the business unit ID, customer email, name, locale, reference number, and template ID. There is also an **invitation link API** that returns unique review links.
- **Manual methods:** CSV upload and copy-paste. These reviews are labelled **Invited**.
- **Generic review link / QR code:** these reviews are labelled **Redirected**.
- Configurable templates, sender name, delay, and reminders. Invitation status tracking (queued, sent, opened, clicked, reviewed, bounced, unsubscribed).

**Invitation rules:** invite **all** customers at the same point in the customer journey, **one invite per experience**, neutral language, **no incentives**, **no selective invitations**, and no steering unhappy customers elsewhere.

### 5.3 Engagement
- Public **replies** to reviews: one reply per review, editable. Replies must be polite with no personal data.
- **AI-suggested replies:** tailored to the review's content and sentiment, editable before sending, and trained on the brand's voice.
- **Email notifications** for new reviews, with configurable frequency. Helpdesk integrations (Zendesk, Salesforce).

### 5.4 Showcasing
- **Embeddable widgets:** trust badges (stars, score, count), review carousels/lists, combination widgets, product-review widgets, and rich-snippet/SEO widgets. They can be customised by size, colour, language, and filters.
- Marketing assets: social images and email signatures, subject to brand guidelines.
- **Google Seller Ratings** / rich snippets through a partnership.

### 5.5 Analytics and insights
- Ratings over time, review volume by source/language, invitation funnel (sent → clicked → reviewed = conversion rate), reply rate and time, and TrustScore insights.
- **AI topic analysis:** themes and sentiment per topic, quote grouping.
- **Competitor benchmarking:** compare with competitors and categories (higher tiers).
- Visitor insights: what people search for, what drives traffic to the profile, how the business compares.

### 5.6 APIs
Business Units API (profile and score data), Service/Product Reviews APIs, Consumer API, Invitation API, Private Reviews API (includes reviewer email and reference for the business), and **webhooks** for new and updated reviews. OAuth2 for private endpoints and API keys for public ones.

---

## 6. Trust & Safety Model

### 6.1 Detection
- Automated fraud detection (behavioural, device, network, and content signals) screens every review. Trustpilot reports removing **4.5 million fake reviews in 2024, ~90% automatically before publication**.
- Human **Content Integrity Team** handles flags, investigations, and appeals.
- Reviews flagged as "not a genuine experience" go through automated detection analysis.

### 6.2 Prohibited content
Hate speech/discrimination, threats and violence, obscenity, defamation, personal information (names of staff, phone numbers, emails, photos of others), advertising/spam, political/religious advocacy unrelated to the experience, fake reviews, incentivised reviews, reviews by people with a conflict of interest (owners, employees, family, shareholders, competitors), and reviews on the wrong business.

### 6.3 Enforcement ladder
**Businesses:** educational email → warning → cease-and-desist → account restrictions (e.g., flagging blocked) → **Consumer Warning with TrustScore hidden, paid plan ended, and account downgraded for about 6 months** → legal action / referral to authorities.

**Reviewers:** educational email → warning → account blocked.

Triggers include fake reviews, biased or incentivised invitations, flagging misuse, misleading profile info, brand misuse, and scraping.

**Appeals:** through the dispute link in each enforcement email.

### 6.4 Transparency
Trustpilot publishes an annual **Trust/Transparency Report** (fake reviews removed, flags handled, enforcement actions), public guidelines for reviewers and businesses, an "Action we take" page, and an explanation of how the TrustScore works.

---

## 7. Regulatory Context (affects requirements)

| Regulation | Relevance |
|------------|-----------|
| **US FTC Rule on Consumer Reviews and Testimonials (16 CFR Part 465, 2024)** | Bans fake reviews, undisclosed insider reviews, incentives **conditioned on sentiment**, review suppression through threats or false accusations, and misrepresenting a company-controlled review site as independent. |
| **UK Digital Markets, Competition and Consumers Act 2024** (in force 6 Apr 2025) | Bans fake and concealed incentivised reviews and publishing reviews in a misleading way. **Review platforms must take "reasonable and proportionate steps" to prevent, detect, and remove fake reviews.** Fines up to 10% of global turnover. CMA guidance CMA208. |
| **EU Digital Services Act / Omnibus Directive** | Notice-and-action for illegal content, statements of reasons for moderation decisions, internal complaint handling, transparency reporting, and disclosure of how the platform ensures reviews come from real consumers. |
| **GDPR / UK GDPR / CCPA** | Lawful basis, data subject rights (access, export, erasure), minimisation, retention. This is especially relevant for receipts, invoices, employment proofs, and voice/video. |
| **Accessibility (EAA 2025, ADA)** | Public web experience should meet WCAG 2.2 AA. |

---

## 8. Gap Analysis: Trustpilot vs. Client Differentiators

| Client requirement | Trustpilot today | Platform approach |
|--------------------|------------------|-------------------|
| Proof-of-experience with receipt/invoice/booking ref/order ID → "Verified Experience" | "Verified" only from automatic invites; optional reference number; business can request info | The reviewer can verify **any** review by uploading or matching proof, with a signed verification record |
| Review lifecycle updates (30d / 6m / 1y) | Edit anytime; new experience = new review | Structured follow-up updates with visible history |
| AI summaries & sentiment trends | AI summary and topics | Add "What People Love / What Needs Work" and sentiment trendlines |
| Verified anonymous employee/insider reviews | Prohibited (conflict of interest) | A separate, clearly labelled **Insider** stream kept out of customer scores |
| Dispute resolution & mediation, AI dispute timeline | Not offered | A complaint case with tracked milestones and optional mediation |
| Review portability / reviewer passport | Not offered | Export plus a portable signed reputation profile |
| Context-aware prompts | Generic form | Category-specific structured questions |
| Reward honest, detailed reviews | Incentives banned | Platform-funded rewards based on quality, never on sentiment (legal review required) |
| Competitor benchmarking | Higher-tier analytics | Included, with an anonymised industry baseline |
| Tamper-proof widgets / open API | Widgets and APIs | Signed widget payloads and a public API |
| Trust Index /100 | TrustScore 1–5 only | A composite Trust Index next to the Review Score |
| Industry comparison engine | "People also looked at" | Side-by-side comparison of 2–4 businesses |
| Voice/video reviews | Not offered (text only) | Short audio/video with transcripts and moderation |
| Resolution rating | Not offered | 1–5 "How well did they resolve the issue?" |
| Promoted listings / ads | Not a core feature | Clearly labelled "Sponsored" placements that never change scores |
| API & data licensing, lead fees | API only on higher tiers | Licensed data products and conversion attribution |

---

## Sources

- [TrustScore and star rating explained (Trustpilot Help)](https://help.trustpilot.com/s/article/TrustScore-and-star-rating-explained?language=en_US)
- [Trustpilot for Free users: How your TrustScore works](https://ie.business.trustpilot.com/blog/build-trusted-brand/trustpilot-for-free-users-how-your-trustscore-works-and-how-to-boost-it)
- [How Trustpilot's TrustScore is calculated (Reviewz)](https://reviewz.ai/blog/trustpilot-trustscore-calculated)
- [Guidelines for reviewers (Jun 2026)](https://corporate.trustpilot.com/legal/for-reviewers/guidelines-for-reviewers/jun-2026)
- [Guidelines for businesses (Jun 2026)](https://corporate.trustpilot.com/legal/for-businesses/guidelines-for-businesses/jun-2026)
- [Action We Take (Mar 2026)](https://corporate.trustpilot.com/legal/for-everyone/action-we-take/mar-2026)
- [How consumers can flag reviews](https://help.trustpilot.com/s/article/How-consumers-can-flag-reviews-that-breach-our-guidelines?language=en_US)
- [Trustpilot's review labels](https://help.trustpilot.com/s/article/About-Trustpilots-review-labels?language=en_US)
- [Trustpilot invitation methods](https://help.trustpilot.com/s/article/Trustpilot-invitation-methods?language=en_US)
- [Automatic Feedback Service (AFS)](https://support.trustpilot.com/hc/en-us/articles/213703667-How-to-use-Automatic-Feedback-Service-AFS)
- [Review invitations feature page](https://business.trustpilot.com/features/review-invitations)
- [Respond to reviews feature page](https://www.business.trustpilot.com/features/respond-to-reviews)
- [TrustBox widget overview](https://help.trustpilot.com/s/article/TrustBox-widget-overview?language=en_US)
- [Trustpilot developers: Invitation API](https://developers.trustpilot.com/invitation-api)
- [Trustpilot developers: Product Reviews API](https://developers.trustpilot.com/product-reviews-api/)
- [Trustpilot pricing 2026 (Capterra)](https://www.capterra.com/p/169618/Trustpilot/pricing/)
- [Trustpilot pricing 2026 (costbench)](https://costbench.com/software/review-management/trustpilot/)
- [The three types of Trustpilot reviews](https://help.trustpilot.com/s/article/The-three-types-of-Trustpilot-reviews?language=en_US)
- [Trustpilot fake reviews / transparency report summary](https://blog.newreputation.com/trustpilot-fake-reviews/)
- [The UK's new fake reviews law and Trustpilot](https://help.trustpilot.com/s/article/The-UK-s-new-fake-reviews-law-and-Trustpilot?language=en_US)
- [CMA208 Fake reviews guidance](https://assets.publishing.service.gov.uk/media/67eeb64fe9c76fa33048c790/CMA208_-_Fake_reviews_guidance.pdf)
- [DMCC Act 2024 and fake reviews (Lewis Silkin)](https://www.lewissilkin.com/insights/2025/03/10/dmcc-act-2024-and-fake-and-misleading-consumer-reviews)
- Trustpilot public pages: [homepage](https://www.trustpilot.com/) and [a sample business profile](https://www.trustpilot.com/review/www.amazon.com)
