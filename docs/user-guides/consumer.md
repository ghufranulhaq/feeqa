# Consumer guide

For anyone signing up to write and manage reviews. Business and staff
guides land in this same folder once there's a dashboard/console UI for
them to use (see `docs/system-overview/business-profiles.md` and
`docs/system-overview/reviews.md` in the meantime for what a business
owner, staff member, or reviewer can already do).

## Creating an account

Go to `/register` and pick one of:

- **Email and password** — fill in a display name, email, and password,
  confirm you're 18 or older, and you're in.
- **Continue with email** (`/login/passwordless`) — enter just your email.
  You'll get a 6-digit code and a one-click link; use either one. If it's
  your first time, you'll then be asked for a display name, country, and
  to confirm you're 18+, and your account is created at that point.
- **Google / Apple / Facebook** — only shown if the platform has that
  provider turned on. One click, no password to remember.

Your display name is what other people see next to your reviews — it
doesn't have to be your real name, and the platform never asks for one. It
needs to be 2–40 characters and can't look like a URL, an email address, a
phone number, or the name of a staff role.

## Managing your profile

From **Settings → Profile** (`/settings/profile`) you can change your
display name, email, country, and upload a photo (JPEG/PNG/WebP, up to
5 MB, not animated). From **Settings → Password** you can set or change
your password — including adding one for the first time if you signed up
with a passwordless or social sign-in and don't have one yet.

Your public profile (`/reviewers/{you}`) shows your display name, photo,
country, how long you've been a member, and your published reviews to
anyone — it never shows your email address.

## Looking up a business

Every business has a public profile at `/business/{slug}` — you don't need
an account to see it. It shows the business's name, logo, description,
contact details, whether it's **Claimed** or **Unclaimed** (an unclaimed
profile says so plainly), links to its branch locations if it has any,
and its published reviews (see **Reading reviews** below). Its Review
Score, Trust Index, and everything else a later spec owns still show as
"coming soon" until their own specs land.

If a business isn't listed, **Add a business** (`/businesses/new`,
sign-in required) adds it by its website domain, or by name, country, and
city if it has none. If it looks like something already listed, you're
pointed at that profile instead of creating a duplicate. A category is
required — pick "Other / Uncategorised" if nothing fits.

## Reading reviews

A business or location profile's review list can be sorted (most recent
or most useful first) and filtered by star rating, source label,
whether a review has a lifecycle update, language, and date range — a
business's own page can also filter to one of its locations. Every
review shows who wrote it, their rating, title, text, both dates, how
many people found it useful, and its source label with a tooltip
explaining what the label means. A review that's received a dated
follow-up (30 days, 6 months, or 1 year after it published) shows its
full timeline, and its headline rating is always the most recent one.
Every review also has its own permanent link.

**Writing, editing, deleting, or voting on a review isn't reachable from
any page yet** — see `docs/system-overview/reviews.md` for exactly what
exists behind the scenes today (real, tested application logic with no
form in front of it yet, the same situation spec 002's own dashboard/
console pages are in).

## Sessions

**Settings → Sessions** lists every device currently signed in to your
account, with roughly what it is and when it was last active. You can
revoke any of them except the one you're using right now. A session left
untouched for 30 days signs itself out.

## Your data

**Settings → Profile → Download my data** requests a full export of
everything the platform holds about you — profile, consents, sessions,
business memberships, linked sign-in providers, and your photo — as a JSON
file (plus your photo) in a ZIP archive. You'll get an email with a
download link once it's ready (usually within seconds, always within 24
hours); the link works for 7 days. Asking again within 24 hours just
returns the export already in progress rather than starting a new one.

## Deleting your account

**Settings → Profile → Delete account** (confirm with your password, if you
have one). Your public profile disappears immediately. Your personal data
is permanently erased or anonymised within 30 days — you get a
confirmation email right away, and you're signed out on every device.
There's no way to undo this after the 30 days are up.

## If you get locked out

Signing in, entering a passwordless code, and resetting your password are
all limited to 5 attempts every 15 minutes. If you hit that limit, wait for
the cool-down shown on screen before trying again.
