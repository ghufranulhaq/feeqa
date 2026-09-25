# Spec 001: Accounts, Identity & Roles

**Status:** Draft · **Phase:** 1 (MVP) · **Depends on:** none

## 1. Goal

Give every person a single, secure identity on the Platform. Consumers can review under a public display name. Business users can manage profiles with the right permissions. Staff can moderate with audited power. Because people own their accounts and data, the rest of the Platform can trust who did what.

## 2. User Scenarios

1. **Consumer sign-up with email.** A visitor who taps "Write a review" and picks "Continue with email" enters an email, gets a 6-digit code or magic link, confirms that they are 18 or older, chooses a display name and country, and lands back on the review form they started from, with their draft kept.
2. **Social sign-in.** A visitor who picks Google, Apple, or Facebook and grants consent gets an account linked to that provider's verified email. If an account with the same verified email already exists, the provider is linked to it and no duplicate account is created.
3. **Business user invitation.** When a business admin invites a colleague by email with the role "Responder", the colleague gets an email, accepts the invitation, and can reply to reviews but can't change billing or invite others.
4. **Data export.** When a consumer requests "Download my data", they get an email link within 24 hours to a JSON archive of their profile, reviews, updates, media, cases, votes, flags, and consents.
5. **Account deletion.** When a consumer deletes their account and confirms, their reviews are removed from public view immediately, their personal data is erased within 30 days, and they get a confirmation email.
6. **Staff access.** A moderator signs in with their staff account (from an allowed network) and can see the moderation queue. Every action they take is recorded with their staff ID.

## 3. Functional Requirements

### Consumer accounts
- **FR-001-01** The system must let a person create a consumer account with: email + password, passwordless email code/link, Google, Apple, or Facebook.
- **FR-001-02** The system must require the email address to be verified before the account can publish any content.
- **FR-001-03** The system must require the user to confirm they are **18 or older** during sign-up, and must record the time of that confirmation.
- **FR-001-04** Passwords must be at least 12 characters and must be rejected if they appear in a known-breached password list.
- **FR-001-05** Each account must have: a unique ID, a display name (2–40 characters), a country (ISO 3166-1), an optional avatar, a locale, and a created date. The display name does **not** have to be unique.
- **FR-001-06** The system must enforce **one consumer account per verified email**. Linking a social provider whose verified email matches an existing account must link to that account, not create a new one.
- **FR-001-07** A consumer's public profile page must show: display name, avatar, country, member-since date, number of published reviews, and the list of their published reviews. It must **never** show their email, real name (unless it is their display name), or proof data.
- **FR-001-08** Users must be able to change display name, avatar, country, locale, password, linked providers, and notification preferences.

### Business users and roles
- **FR-001-09** A business user account must be tied to one or more Businesses through a **membership** with exactly one role per Business: `Owner`, `Admin`, `Responder`, `Analyst`.
- **FR-001-10** Permission matrix (enforced on the server side for every endpoint):

  | Capability | Owner | Admin | Responder | Analyst |
  |------------|:-----:|:-----:|:---------:|:-------:|
  | Edit profile info | ✅ | ✅ | ❌ | ❌ |
  | Reply to reviews / cases | ✅ | ✅ | ✅ | ❌ |
  | Flag reviews | ✅ | ✅ | ✅ | ❌ |
  | Send invitations / manage integrations | ✅ | ✅ | ❌ | ❌ |
  | View analytics | ✅ | ✅ | ✅ | ✅ |
  | Manage members | ✅ | ✅ (not Owners) | ❌ | ❌ |
  | Billing & plan | ✅ | ❌ | ❌ | ❌ |
  | Transfer ownership / delete business account | ✅ | ❌ | ❌ | ❌ |

- **FR-001-11** Every Business must have at least one Owner at all times. The system must reject removing or demoting the last Owner.
- **FR-001-12** *(Removed 2026-09-25: two-factor authentication is not required.)*
- **FR-001-13** One person may hold both a consumer identity and business memberships under the same login. **A user must not be able to publish a customer review on a Business where they hold a membership** (see 003).

### Staff
- **FR-001-14** Staff roles: `Moderator`, `Senior Moderator`, `Mediator`, `Support`, `Admin`. Staff accounts may be created only by a staff `Admin`, and the staff console is reachable only from allow-listed networks.
- **FR-001-15** Every staff moderation or enforcement action on user content or accounts must write a **compliance log** entry with staff ID, action, target, reason code, and timestamp (constitution §5.1). No other changes are logged.

### Sessions & security
- **FR-001-16** Sessions must expire after 30 days of inactivity for consumers and 12 hours for staff. Users must be able to see their active sessions and revoke them.
- **FR-001-17** Sign-in, code verification, and password reset must be rate-limited to 5 failed attempts per 15 minutes per account and per IP. Going over the limit must require a CAPTCHA or a cool-down.
- **FR-001-18** The system must record privacy-safe device and network signals at sign-up and sign-in for fraud detection (spec 006), and must disclose this in the privacy notice.

### Data rights
- **FR-001-19** A user must be able to request a **full data export**. It must be delivered as machine-readable JSON (media included as files) within 24 hours, through a download link that expires after 7 days.
- **FR-001-20** A user must be able to **delete their account**. Public content is hidden right away. Personal data is erased or irreversibly pseudonymised within 30 days. Compliance log entries keep only a pseudonymous ID.
- **FR-001-21** The system must record consents (terms version, privacy version, marketing opt-in) with timestamps. It must ask again for consent when the terms change in a material way.

## 4. Edge Cases & Rules

| Case | Rule |
|------|------|
| Empty display name or only whitespace | Reject with a field error. |
| Display name > 40 characters, contains a URL, an email, a phone number, or impersonates a staff role ("Admin", "Moderator") | Reject with a specific error. |
| Display name matches a claimed Business's name | Allowed, but flagged for review if the user also writes a review of that Business. |
| Duplicate sign-up with an existing email | Do not reveal that the account exists. Send a "sign in instead" email to that address. |
| Social provider returns an **unverified** email | Treat it as unverified and require email verification (FR-001-02). |
| Disposable email domains | Allow sign-up, but require the fraud check (006) before the account's first review is published. |
| User says they are under 18 | Block account creation. Do not store the email beyond the rate-limit window. |
| Avatar upload > 5 MB, not an image, animated, or fails the malware scan | Reject. Max 5 MB, JPEG/PNG/WebP, stored after stripping EXIF. |
| Account deleted while a case (010) is open | The case closes as "Withdrawn by consumer". The business keeps only the case metadata, no personal data. |
| Export requested twice within 24 hours | Return the pending export. Do not start a second one. |
| Unauthorized: a Responder calls the billing endpoint | 403. |
| Last Owner tries to leave the Business | Reject until another Owner is appointed. |
| Staff sign-in from a network that isn't allow-listed | Rejected. |

## 5. Out of Scope

- Government ID / KYC identity verification of consumers.
- Single sign-on (SAML/SCIM) for business accounts. Planned for the Enterprise plan later; tracked in 017.
- Username-based login (email and social only).
- Consumer-to-consumer messaging or following.
- Recovering a deleted account after the 30-day erasure.

## 6. Acceptance Criteria

- [ ] All five sign-up/sign-in methods work end to end and produce a single account per verified email.
- [ ] An unverified or under-18 user cannot publish anything (tests exist for both).
- [ ] The permission matrix (FR-001-10) is covered by an automated test for each cell.
- [ ] The last Owner cannot be removed.
- [ ] The staff console rejects requests from networks that aren't allow-listed.
- [ ] Data export contains every personal-data entity defined across all shipped specs (checked against the data inventory).
- [ ] Account deletion hides content right away and erases personal data within 30 days (checked by a scheduled-job test).
- [ ] Rate limiting and lockout behaviour are verified by tests.
- [ ] The public profile page never exposes an email or proof data (checked by a test).

## 7. Dependencies & Open Questions

- **Q1:** Should consumers be allowed to use a **pseudonym only**, or should the Platform ask for (but not show) a real name for the Verified Experience? *Proposed:* do not ask. Proof is linked to the experience, not to a legal identity.
- **Q2:** Which social providers matter most for UK + EU travellers? *Default:* Google, Apple, and Facebook as specified. Revisit after the first 3 months of sign-up data.
- **Decided (2026-09-24):** launch locale `en-GB`, markets UK + EU, responsive web only.
