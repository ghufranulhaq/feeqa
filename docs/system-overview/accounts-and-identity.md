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
itself out automatically. (Staff accounts, once spec 001's remaining
staff-console work lands, get a much shorter 12-hour idle limit instead —
see the spec's own task list for what's built so far.)

Sign-in, the passwordless code, and password reset are all limited to 5
failed attempts in 15 minutes, tracked both per account and per network
address — so an attacker can't work around the limit just by trying many
accounts from one place, or one account from many places.

For fraud-detection purposes (spec 006, not yet built), the platform
records the network address and browser/device string of every session —
the same information behind the "Sessions" list above. This is disclosed
here because there is no live privacy-notice page yet; one is expected
before public launch.

## What's not built yet

This page will grow as the rest of spec 001 lands: business user roles and
permissions, staff accounts and the staff console, and the data
export/account-deletion tools. Check the spec's `tasks.md` for exactly
what's done.
