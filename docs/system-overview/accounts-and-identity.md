# Accounts, Identity & Roles

What the platform does today for spec [001](../../specs/001-accounts-identity/spec.md).
This is a plain-English description of behaviour, not an implementation
guide — see the spec and `specs/plan.md` for the "why" and "how".

## Signing up and signing in

Every person gets one account, reachable four ways:

- **Email + password.** Minimum length is 12 characters in production, 6 in
  the demo (constitution §5.6); every password is checked against a public
  list of known-breached passwords before it's accepted.
- **Passwordless email.** "Continue with email" sends a 6-digit code and a
  one-click sign-in link to the same address — no password involved at
  all. This is also how a brand-new visitor creates an account: after
  proving they control the inbox, they're asked for a display name,
  country, and to confirm they're 18 or older, and the account is created
  right then.
- **Google, Apple, or Facebook.** Only shown when the platform has been
  configured with that provider's credentials (none are configured by
  default in the demo). If the email the provider hands back matches an
  existing account, the two are linked rather than creating a duplicate —
  but only when the provider itself reports that email as verified;
  otherwise the platform treats it as a brand-new, not-yet-verified
  sign-up instead of trusting it enough to attach to someone else's
  account.

Whichever method is used, one email address maps to exactly one account.
Registering with an email that already has an account never says so out
loud — the visitor sees the same generic response either way, and the
account holder gets an email telling them someone tried, with a sign-in
link. This is deliberate: a chatty "that email is already taken" message is
exactly the kind of thing an automated tool can use to find out who has an
account and who doesn't.

## Your profile

A display name (2–40 characters — not a real name; nothing here ever asks
for one), an optional photo, a country, and a locale. The display name
can't contain a URL, an email address, a phone number, or impersonate a
staff role name like "Admin" or "Moderator". Photos are limited to 5 MB,
must be a real JPEG/PNG/WebP image (not animated), are scanned for malware,
and have their metadata (EXIF — this can include where and when a photo was
taken) stripped before they're stored.

Your public profile page (`/reviewers/{you}`) shows your display name,
photo, country, how long you've been a member, and your published reviews.
It never shows your email address or anything you've submitted as proof of
an experience (spec 004) — those stay private.

## Sessions

Every sign-in creates a session, visible and revocable individually from
Settings → Sessions — each one shows roughly what device/browser it's on
and when it was last active. A session that sits idle for 30 days signs
itself out automatically — staff accounts (below) get a much shorter
12-hour idle limit instead.

Sign-in, the passwordless code, and password reset are all limited to 5
failed attempts in 15 minutes, tracked both per account and per network
address — so an attacker can't work around the limit just by trying many
accounts from one place, or one account from many places.

For fraud-detection purposes (spec 006, not yet built), the platform
records the network address and browser/device string of every session —
the same information behind the "Sessions" list above. This is disclosed
here because there is no live privacy-notice page yet; one is expected
before public launch.

## Business roles

One person can hold a role on more than one business, and separately be an
ordinary consumer reviewer — the same login, different hats. A role only
ever applies to one business at a time:

| Capability | Owner | Admin | Responder | Analyst |
|---|:---:|:---:|:---:|:---:|
| Edit business profile | ✅ | ✅ | ❌ | ❌ |
| Reply to reviews / cases | ✅ | ✅ | ✅ | ❌ |
| Flag reviews | ✅ | ✅ | ✅ | ❌ |
| Send invitations / manage integrations | ✅ | ✅ | ❌ | ❌ |
| View analytics | ✅ | ✅ | ✅ | ✅ |
| Manage members | ✅ | ✅ (not Owners) | ❌ | ❌ |
| Billing & plan | ✅ | ❌ | ❌ | ❌ |
| Transfer ownership / delete business | ✅ | ❌ | ❌ | ❌ |

A business always keeps at least one Owner — the platform refuses to
demote or remove the last one. An Admin can add, remove, or change the role
of anyone except an Owner. Invitations (an Owner or Admin invites someone
by email and role) work end to end — the invitee gets an email, and
accepting it grants exactly the role they were invited as — but there's no
business dashboard UI yet to send one from (that's spec 002 and later); the
`/business/{business}/invitations` endpoint is real and usable today, just
not linked from anywhere in the UI. A business user-guide isn't written yet
either — the consumer guide above is everything currently reachable through
the UI.

## Staff

Staff roles are Moderator, Senior Moderator, Mediator, Support, and Admin —
separate from business roles, and a staff account is created only by
another staff Admin (`POST /staff/accounts`; no staff console UI to send
this from yet). A new staff account gets an unusable random password and
is immediately sent the normal "set your password" email. Every staff
account creation is written to the compliance log — who did it, when, and
why — the same log spec 006's moderation actions will use later.

Staff sessions expire after 12 hours idle, not the 30 days consumers get.
In production, the staff endpoints are only reachable from an IP allow-list
(`STAFF_ALLOWED_IPS`); everywhere else — including the demo — any network
can reach them, per constitution §5.6.

## Your data

**Settings → Profile → Download my data** gets you everything the platform
holds about you — profile, consents, sessions, business memberships,
linked sign-in providers, your photo — as a JSON file plus your photo in a
ZIP archive, emailed as a download link within seconds (spec says "within
24 hours"; a second request inside 24 hours of the first just returns
that one instead of starting over). The link works for 7 days.

**Settings → Profile → Delete account** hides your public profile
immediately and schedules your personal data to be permanently erased or
anonymised within 30 days; you're signed out everywhere right away and get
a confirmation email. There's no undo once the 30 days are up.

## What's not built yet

This page will grow as later specs add their own personal data to the
export, and as the staff console gets a real UI. Everything spec 001
itself describes is built — check the spec's `tasks.md` for the detail.
