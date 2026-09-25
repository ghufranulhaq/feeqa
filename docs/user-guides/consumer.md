# Consumer guide

For anyone signing up to write and manage reviews. Business and staff
guides land in this same folder once those parts of spec 001 are built.

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

## If you get locked out

Signing in, entering a passwordless code, and resetting your password are
all limited to 5 attempts every 15 minutes. If you hit that limit, wait for
the cool-down shown on screen before trying again.
