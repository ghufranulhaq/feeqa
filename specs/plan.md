# Platform Technical Plan (Cross-Cutting)

**Status:** Proposed, awaiting review (revision 6) · **Date:** 2026-09-25
**Applies to:** all specs 001–017. Each spec's own `plan.md` builds on this document and may only deviate from it through a recorded decision.
**Constitution:** v1.5 (demo rules in §5.6, documentation rule in §8 rule 9). Once this plan is approved, the stack (§2) is recorded in [`CONSTITUTION.md` §10](../CONSTITUTION.md).
**Current focus:** the **client demo**, showing **all features**, at `https://feeqa.appsarray.com`. Production-only items are marked *(production, later)*.

---

## 1. Summary

The Platform is **one Laravel 13 application**, started from Laravel's official **React starter kit**: Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui, and Fortify authentication (no two-factor).

- **One app, four areas:** public site, consumer account, business dashboard, and staff console. All are Inertia pages. Public pages are **server-side rendered** (SSR).
- **PostgreSQL**, with its built-in fuzzy search (no search library).
- **Spatie Laravel Permission** for roles.
- **Laravel AI SDK** with an OpenAI-compatible driver. The **demo uses the DeepSeek API**. Every provider is switched in `.env`, and `AI_DRIVER=fake` turns AI off with working fallbacks.
- **Pest 4** for all tests, including Playwright-powered browser and accessibility tests. Tests run **on developer machines only**, never on the server.
- **Laravel Boost** during development.
- **Demo hosting:** one **Vultr** shared-CPU VPS (4 vCPU, 8 GB RAM, 160 GB SSD) running **7 Docker containers** from `compose.demo.yaml`, behind **Caddy** with a free **Let's Encrypt** certificate.
  - The VPS **pulls code from GitHub** (read-only deploy key) and **builds images itself**.
  - No CI service and no paid services.
- **Demo data** comes from **separate demo seeders**: realistic, fictional businesses, reviews, cases, and so on.
- **`APP_ENV=demo`** turns on the demo-only relaxations (constitution §5.6). These are impossible in production.
- The Platform is **fully independent**: its own repo, database, server, and staff logins.

---

## 2. Stack

| Layer | Choice | Notes |
|-------|--------|-------|
| Runtime | **PHP 8.4** | Laravel 13 needs ≥ 8.3. The Laravel AI SDK needs 8.4 |
| Framework | **Laravel 13** | |
| Frontend | **Inertia 3 + React 19 + TypeScript + Tailwind 4** | From the official React starter kit |
| UI components | **shadcn/ui** (free, Radix-based, copied into `resources/js/components/ui`) | No Metronic, no paid templates |
| Routing helpers | **Laravel Wayfinder** | Type-safe routes in React; comes with the starter kit |
| Public page rendering | **Inertia SSR** (Node 22 process) | D3 |
| Authentication | **Laravel Fortify** (login, register, reset, email verification) + **Laravel Socialite** (Google, Apple, Facebook) | The starter kit's 2FA feature is **removed** (D6) |
| Permissions | **spatie/laravel-permission** (teams mode) + Laravel Policies | D7 |
| Database | **PostgreSQL 17** with `pg_trgm` and full-text search | No search library (D5) |
| Queue / cache / sessions | Laravel `database` drivers on PostgreSQL | No Redis (D10) |
| AI | **Laravel AI SDK** (`laravel/ai`), `openai-compatible` driver. **Demo: DeepSeek API** | `AI_DRIVER=fake` disables AI (D11) |
| Receipt/document reading | `fake` in demo. Real mode: `pdftotext` + **Tesseract OCR**, then the LLM | D12 |
| Voice/video transcripts | `fake` in demo. Real provider chosen before launch | D12a |
| Files | Laravel filesystem: local disks on a Docker volume | S3-compatible later via `.env` |
| Malware scanning | `fake` in demo (**no ClamAV container**). ClamAV *(production, later)* | D13 |
| Email | SMTP. The demo uses **Mailpit** (captures all mail) | D14 |
| Payments | `BILLING_DRIVER=fake` in demo: **every payment succeeds**, no gateway contacted. Stripe via Cashier *(production, later)* | D15 |
| Signatures | PHP's built-in **sodium** (Ed25519), JWS format | D16 |
| Widget | Plain TypeScript web component, separate small Vite build | D17 |
| Tests | **Pest 4** (unit, feature, architecture, **browser via Playwright** with `assertNoAccessibilityIssues()`) | D20 |
| Code quality | Pint, Larastan, ESLint, Prettier, `tsc` | |
| AI-assisted development | **Laravel Boost** (dev only) | D21 |
| Containers | Docker + Docker Compose: `compose.yaml` (local), **`compose.demo.yaml`** (demo), `compose.prod.yaml` *(production, later)* | D22 |
| Web server + HTTPS | **Caddy** with automatic **Let's Encrypt** certificates | D25 |
| Environments | `local`, `testing`, `demo`, `production` | D26 |
| Demo hosting | **Vultr** shared-CPU VPS (4 vCPU / 8 GB / 160 GB), Ubuntu 24.04, 2 GB swap | D24 |
| Deployment | `make ci` + git pre-push hook locally. `make deploy-demo`: the VPS pulls from GitHub, builds, and restarts | D23 |

---

## 3. Repository Structure

A single standard Laravel application at the repo root, following the starter kit and Boost conventions, with specs and docs alongside.

```
trust-review-platform/
├── CLAUDE.md                 # imports CONSTITUTION.md + Laravel Boost guidelines (D21)
├── CONSTITUTION.md           # the constitution
├── README.md                 # setup, demo deployment, .env reference, commands (D29)
├── specs/                    # specs and this plan
├── docs/
│   ├── system-overview/      # what the system is and what it does (D29)
│   ├── user-guides/          # one guide per user type (D29)
│   ├── demo/accounts.md      # demo logins for the client walkthrough (D28)
│   ├── runbooks/             # demo-server.md, caddy.md
│   └── research/
├── app/
│   ├── Actions/<Area>/       # one class per use case (SubmitReview, ClaimBusiness, OpenCase…)
│   ├── Domain/<Area>/        # pure PHP, no Laravel imports: Scoring, TrustIndex, rules, value objects
│   ├── Drivers/              # .env-selected adapters (real + fake): Ai, Extraction, Transcription,
│   │                         #   MalwareScan, Billing, Signing
│   ├── Http/
│   │   ├── Controllers/{Public,Account,Business,Staff,Api/V1,Webhooks}/
│   │   ├── Middleware/       # SetPermissionTeam, StaffIpAllowList, DemoGate, NoIndexInDemo
│   │   ├── Requests/  Resources/
│   ├── Models/  Policies/  Jobs/  Notifications/  Mail/  Console/
│   └── Support/Environment.php  # single place that answers "is this demo?" (D26)
├── config/platform.php       # feature flags, drivers, limits, demo relaxations, all read from .env
├── database/
│   ├── migrations/  factories/
│   └── seeders/
│       ├── DatabaseSeeder.php        # base data needed in every environment
│       ├── Base/                     # roles & permissions, industries, travel content,
│       │                             #   methodology v1, plans, staff admin
│       └── Demo/                     # demo-only seeders + data files (D28)
│           ├── DemoSeeder.php
│           ├── data/*.json           # curated realistic texts: reviews, replies, cases, summaries
│           └── media/                # small sample voice notes and video clips for media reviews
├── resources/
│   ├── js/
│   │   ├── pages/{public,account,business,staff,auth,settings}/
│   │   ├── layouts/{public,business,staff}/
│   │   ├── components/ui/    # shadcn
│   │   ├── components/{reviews,business,cases,scores,…}/
│   │   ├── app.tsx  ssr.tsx
│   └── widget/               # <trust-widget> web component (separate Vite build, ≤ 30 KB gz)
├── routes/  web.php public.php account.php business.php staff.php api_v1.php webhooks.php
├── tests/
│   ├── Unit/<Area>/  Feature/<Area>/  Browser/<Journey>/  Arch/  Golden/
│   └── Pest.php
├── contracts/openapi/v1.yaml # contract for the external JSON API only (spec 016)
├── docker/                   # Dockerfile (multi-stage), Caddyfile, php.ini, postgres tuning, entrypoints
├── compose.yaml              # local development
├── compose.demo.yaml         # demo VPS
├── compose.prod.yaml         # production (later; not needed for the demo)
├── Makefile                  # dev, test, ci, deploy-demo, demo-reset targets
├── scripts/                  # deploy-demo.sh, fr-coverage.php, docs-check.php, preflight.sh
└── .githooks/pre-push        # runs `make ci`
```

**URL areas (one domain):**
- `/`: public
- `/account`: consumer
- `/business/{business}/…`: business dashboard
- `/staff/…`: staff console
- `/api/v1/…`: external JSON API
- `/webhooks/…`
- `/_mail`: Mailpit inbox (demo)

---

## 4. Key Decisions and Trade-offs

### D1. Fully independent system
- **Choice:** own repo, database, server, and staff logins. No code, data, or infrastructure shared with any other company system.
- **Trade-off:** nothing to reuse, so everything starts from the starter kit. That is acceptable, because the kit covers the boilerplate.

### D2. One Laravel app from the official React starter kit
- **Choice:** `laravel new` with the React starter kit (Laravel's built-in authentication, not WorkOS). Every area is an Inertia page in the same app. There is no separate React application.
- **Why:** the kit already provides Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui, Fortify, Wayfinder, and SSR support.
- **Trade-off:**
  - The web UI talks to Laravel controllers through Inertia, so the external API (016) is a **separate set of JSON controllers**. Both call the **same Actions**, so no logic is duplicated.
  - We own the starter-kit code, so there are no upstream updates to it.

### D3. Public pages: Inertia + React **SSR** (decided)

| Criterion | Blade | **Inertia + React SSR** ✅ | Next.js SSR |
|---|---|---|---|
| Full HTML for search engines (FR-009-18) | ✅ | ✅ | ✅ |
| Same components as dashboards (shadcn) | ❌ Built twice | ✅ One component set | ⚠️ Separate app |
| Separate application | No | No | Yes |
| Extra production process | None | One Node 22 SSR container | Node server + API + shared login |
| Page weight / speed | Lightest | Heavier (React hydration) | Medium |
| Interactive parts | Extra JavaScript bolted on | Built in | Built in |
| Data access | Direct | Direct (controller props) | Over HTTP to the API |
| If the SSR process fails | n/a | Pages still work in the browser, but search engines get an empty page | Site down |

- **Mitigations:**
  - The SSR container has a Docker health check and automatic restart, and `make smoke` checks that a public page returns rendered HTML.
  - Public pages use a lean layout, split code per page, and import only the shadcn components they need.
- **Demo note:** the demo is hidden from search engines (D27), but SSR still runs so the demo behaves like production.

### D4. Standard Laravel structure with Actions
- **Choice:** Laravel's default folders, grouped by area. Pure calculation code lives in `app/Domain` and must not import Laravel.
- **Why:** matches the starter kit, Boost's guidelines, and what AI agents and new developers expect.
- **Trade-off:** weaker boundaries than a modular design. Mitigated by Pest `arch()` tests:
  - `Domain` must not use `Illuminate`;
  - writes go through Actions;
  - scoring can't access billing.

### D5. PostgreSQL. **No search library.**
- **Choice:** PostgreSQL 17. `pg_trgm` trigram indexes handle typo-tolerant name/domain search and autocomplete. Full-text search uses `tsvector` with GIN indexes. Plain Eloquent queries, no Scout.
- **Trade-off:** less relevance tuning than a search engine. **Revisit trigger:** autocomplete p95 > 200 ms in load tests. Adding a search library would need your approval.

### D6. Authentication (no two-factor)
- **Choice:**
  - **Fortify**: registration, login, password reset, email verification. The starter kit enables two-factor by default, so we **remove `Features::twoFactorAuthentication()`** and its pages, routes, and database columns.
  - **Socialite** (+ the Apple provider package): Google/Apple/Facebook buttons appear only when their keys are set in `.env`. They're left empty in the demo unless you add keys.
  - **Passwordless email codes**: a small custom action.
  - **Password rules:** minimum **12** characters in production, **6 in demo** (constitution §5.6). The minimum is read from the environment rules (D26), not from a free-standing `.env` number, so production can't be set lower by mistake. Breached-password check through `Password::uncompromised()` (a free public API), which can be switched off for offline development.
- **Trade-off:** without two-factor, a stolen password gives full account access. We reduce the risk with login rate limiting and lockout (FR-001-17), breached-password checks, session listing and revocation (FR-001-16), the production staff IP allow-list, and 12-hour staff sessions. Two-factor can be switched back on later through Fortify's config.

### D7. Permissions: Spatie Laravel Permission (teams mode) + Policies
- **Choice:**
  - Spatie **teams mode** with `team_id = business_id` for business roles (Owner, Admin, Responder, Analyst).
  - **Global roles** for staff (Moderator, Senior Moderator, Mediator, Support, Admin).
  - A `SetPermissionTeam` middleware sets the business from the URL and checks membership.
  - **Policies** handle record-level rules (e.g., a member can't review their own business).
  - **Staff IP allow-list:** enforced in production (`STAFF_ALLOWED_IPS`), **off in demo**, so staff can log in from any IP (§5.6).
- **Trade-off:** teams mode needs the team set on every request and queued job, and forgetting it breaks the check. Mitigated by the middleware and a test that every `/business` route runs it.

### D8. Minimal compliance log
- **Choice:** one `compliance_log` table for staff moderation and enforcement decisions, statements of reasons, appeals, legal sign-offs, and sealed-identity access (013). No change log, no activity-log package.
- **Trade-off:** we can't answer "who changed this profile field". Accepted (constitution v1.2).

### D9. Scores
- **Choice:**
  - Review Score and Trust Index are **pure PHP classes** with methodology versions stored in the database.
  - Recalculation is a unique, debounced queued job per business (≤ 30 s delay), which meets the 60 s target.
  - Production also takes **daily snapshots**. The **demo skips daily snapshots** (§5.6). The demo seeders **back-fill 24 months of history once**, so trendlines and score history still show on day one.
- **Trade-off:** in the demo, score history won't grow day by day after seeding. Current scores still update live.

### D10. Queues, cache, sessions on the database
- **Choice:** Laravel `database` drivers on PostgreSQL. The demo runs **2 queue worker processes** in the `queue` container.
- **Trade-off:** fine at demo scale. A Redis container can be added later with an `.env` change only.

### D11. AI: Laravel AI SDK, switched entirely by `.env`
- **Choice:** the first-party `laravel/ai` SDK with its `openai-compatible` driver.

  ```
  AI_DRIVER=openai-compatible        # or: fake  (AI disabled → fallbacks below)
  AI_BASE_URL=https://api.deepseek.com
  AI_API_KEY=...
  AI_MODEL=deepseek-v4-pro
  AI_MODEL_FAST=                     # optional cheaper model for high-volume classification
  AI_TIMEOUT=60
  AI_MAX_TOKENS=4000
  ```

  **The demo uses the DeepSeek API.** Changing provider or model means editing `.env` (the deploy script re-caches config). No code changes.
- **Fallbacks when `AI_DRIVER=fake`.** Nothing breaks, and every AI feature still appears with its "AI-generated" label:

  | Feature | Fallback |
  |---------|----------|
  | AI review summary | Pre-written summary from the demo seeders (or "Summary not available yet" for businesses without one). Doesn't change with new reviews |
  | Topics & sentiment on new reviews | Keyword matching against the category's topic list. Sentiment from the star rating |
  | Content screening | Rules only: word lists, email/phone detection, duplicate text, speed checks |
  | Reply suggestions | Generic polite draft that the business edits |
  | Case triage | Category from the consumer's choice, default urgency, summary from the complaint's first lines |
  | Mediator digest | Plain timeline and message list |
  | "What changed" insights | Pre-written by the demo seeders |

- **Guardrails (P7):**
  - one AI wrapper stores the model, prompt version, and input IDs for every call;
  - output checks run before use;
  - outputs are labelled;
  - AI never changes state on its own.
- **Trade-offs:**
  - DeepSeek's API is hosted in China, which is acceptable for the demo only because demo data is fictional (constitution §5.6). Production switches to an EU host via `.env`.
  - DeepSeek V4 Pro can't read images, which is why receipt reading uses OCR when real (D12).
  - The AI SDK is young, and its API may change between minor versions.

### D12. Receipt reading for Verified Experience
- **Choice:** `VERIFICATION_EXTRACTOR=fake|ocr_llm`. **Demo: `fake`.**
  - `fake`: skips OCR and the LLM and returns **plausible values that match the reviewed business**: merchant = business name, a date within the experience window, a random booking reference, and a sensible amount. The normal FR-004-06 rules then approve it, and the review gets the **normal "Verified Experience" badge with real labels**. There is no "simulated" marker, per your instruction. This is acceptable only under the demo conditions in constitution §5.6 (password-protected site, fictional data).
  - `ocr_llm` *(production)*: `pdftotext` for text PDFs and Tesseract OCR for photos, then the LLM extracts merchant, date, reference, and amount with confidence scores.
- **Trade-off:** in the demo, any uploaded file (even an unrelated picture) will verify successfully. That is fine for showing the flow, but it isn't real verification.

### D12a. Voice/video transcripts
- **Choice:** `TRANSCRIPTION_DRIVER=fake|<provider>`. **Demo: `fake`**: new uploads get a placeholder transcript/captions that the author can edit (the spec allows editing). Seeded media reviews come with realistic, pre-written transcripts. A real speech-to-text provider is chosen before launch.
- **Trade-off:** in the demo, transcripts of *newly* uploaded media won't match what was said until the author edits them.

### D13. Files and malware scanning
- **Choice:**
  - Local disks on a Docker volume: `public` for logos, `private` for proofs and media. Private files are only served through authorised, signed, short-lived links. S3-compatible storage later via `.env`.
  - **Malware scanning:** `MALWARE_SCANNER=fake` in demo, which treats every upload as clean. **No ClamAV container in the demo.** ClamAV is added in production.
  - **Proof files are not deleted in demo** (§5.6). The 30-day deletion job runs only in production.
- **Trade-off:** demo uploads aren't scanned. Acceptable, because only invited people can reach the demo (D27).

### D14. Email
- **Choice:** SMTP from `.env`. **Demo:** Mailpit catches every email, and its inbox is at `https://feeqa.appsarray.com/_mail` behind the demo password. Nothing reaches real inboxes.
- **Inbound BCC (spec 005):** a signed webhook for raw emails, plus a staff tool to **upload a `.eml` file** to simulate BCC in the demo. Real inbound mail is set up at launch.
- **Trade-off:** SPF/DKIM checks on inbound mail (FR-005-06) can't be shown for real in the demo.

### D15. Payments: simulated in demo
- **Choice:** `BILLING_DRIVER=fake` in demo. **Every payment attempt succeeds** and **no payment gateway is contacted.**
  - The checkout shows a normal-looking card form. Whatever the user enters is accepted as correct and authentic.
  - The system **stores only the last 4 digits** (to show "Card ending 4242"). The full number, expiry, and CVC are **never stored or logged**, so if someone types a real card number into the demo, it isn't kept.
  - Everything money-related works end to end: plan upgrades and downgrades take effect instantly; invoices are generated (VAT calculated from configured rates); sponsored-slot purchases and lead-fee statements are created.
  - Failed payments and retry emails (FR-017-09) can't happen in the demo. They're shown by seeded example data instead.
  - *(Production, later)* `BILLING_DRIVER=stripe` uses Laravel Cashier + Stripe Tax.
- **Trade-off:** the demo proves the billing screens and flows, not a real gateway integration. That is tested separately before launch.

### D16. Signed attestations
- **Choice:** Ed25519 via PHP's built-in sodium, in **JWS** format, with key IDs. Keys are stored in a secret file on the storage volume. Public keys are published at `/.well-known/platform-keys.json`.
- **Trade-off:** a small in-house JWS helper (about 100 lines, fully unit-tested) instead of a JWT library.

### D17. Embeddable widget
- **Choice:** a plain TypeScript `<trust-widget>` custom element built separately and served by Caddy. The demo includes a **"widget showcase" page** that embeds the widgets as a business's own website would. The whole demo site is password-protected, so a widget on an outside website can't load demo data.
- **Trade-off:** can't reuse React/shadcn components (to stay under 30 KB).

### D18. External JSON API (spec 016)
- **Choice:** `/api/v1` JSON controllers. **Sanctum** tokens scoped to one business. A spec-first OpenAPI file checked in Pest tests. The demo shows it through the developer-portal pages (docs, API keys, sandbox calls).
- **Trade-off:** keeping the YAML up to date by hand. Contract tests catch drift.

### D19. Feature flags
- **Choice:** `config/platform.php` reads `FEATURE_*` flags from `.env`. Legally gated features (insider reviews, "resolve first") need a `legal_signoffs` record in production. **In demo they're on without sign-off** (§5.6), so the client sees all features.
- **Trade-off:** changing a flag needs a config reload, which the deploy does.

### D20. Testing: Pest for every scenario, on developer machines only
- **Choice:** Pest 4 only. Unit, feature, architecture, golden datasets, and **browser tests** (Playwright-powered, with `assertNoAccessibilityIssues()` on every page). Tests use a separate PostgreSQL test database whose name must contain `test`.
- **"All possible scenarios" means, for every spec:**
  1. every FR has a test named with its ID;
  2. every user scenario has a feature test, and the critical journeys also have browser tests;
  3. every edge-case row has a test;
  4. every permission-matrix cell is tested;
  5. every validation limit is tested at min−1, min, max, and max+1;
  6. every time rule is tested with time travel;
  7. every `.env` driver is tested in both `fake` and real mode;
  8. **every demo relaxation is tested to be active in `demo` and impossible in `production`.**
- `scripts/fr-coverage.php` (part of `make ci`) fails if any FR ID has no test.
- **Tests never run on the VPS.**
- **Trade-off:** a large suite. It runs in parallel, and browser tests are a separate `make test-browser` step.

### D21. Laravel Boost
- **Choice:** dev dependency. The constitution lives in `CONSTITUTION.md`, which `CLAUDE.md` imports (done). Boost adds its own section to `CLAUDE.md`.
- **Trade-off:** Boost's section is tool-managed and shouldn't be edited by hand.

### D22. Docker
- **Image:** one multi-stage `Dockerfile`:
  - `base`: PHP 8.4-FPM + extensions (pgsql, intl, sodium, gd, zip) + poppler + tesseract;
  - `dev`: adds Xdebug, Node 22, and the Playwright browsers (local only);
  - `assets`: Node builds the Vite assets, SSR bundle, and widget;
  - `demo`/`prod`: application code plus built assets, **no dev tools, no Node packages, no tests**.
- **Compose files:**

  | File | Used on | Containers |
  |------|---------|-----------|
  | `compose.yaml` | Your machine (local development) | caddy, app, ssr, queue, scheduler, postgres, mailpit, vite (live reload) |
  | **`compose.demo.yaml`** | **Demo VPS** | **caddy, app, ssr, queue, scheduler, postgres, mailpit (7)** |
  | `compose.prod.yaml` | Production server *(later)* | as demo, plus clamav; mailpit replaced by a real SMTP provider |

- **The 7 demo containers:**

  | Container | Purpose | Reachable from the internet |
  |-----------|---------|-----------------------------|
  | `caddy` | Front door: HTTPS certificate, HTTP→HTTPS redirect, static files, demo password gate, proxy to `app` and `/_mail` | **Yes (only one)** |
  | `app` | Laravel (PHP-FPM): every page, form, login, and API call | No |
  | `ssr` | Node process that renders React pages to HTML | No |
  | `queue` | 2 background workers: screening, scores, emails, AI calls, uploads, delayed invitations | No |
  | `scheduler` | Timed tasks that remain in demo (see below) | No |
  | `postgres` | Database, plus queue, cache, and sessions | No |
  | `mailpit` | Catches all outgoing email, with an inbox at `/_mail` | Only through Caddy + password |

- **Scheduler in demo:** runs only the tasks still needed. The rest are switched off by `APP_ENV=demo` (§5.6):

  | Task | Demo |
  |------|------|
  | Reply-rate and reply-time signals, case metrics, Trust Index (daily) | ✅ |
  | Fraud-spike detection (hourly) | ✅ |
  | AI summary refresh (weekly), trend labels and industry benchmarks (monthly) | ✅ |
  | Reviewer levels (daily) | ✅ |
  | Housekeeping (expired sessions, old failed jobs) | ✅ |
  | Closing review-update windows, closing inactive cases, daily snapshots, proof deletion, backups, certificate check | ❌ Off |

- **Volumes (data kept across restarts and deploys):** `postgres_data`, `app_storage` (uploads + signing keys), `caddy_data` (certificate).

### D23. Tests and deployment without CI services
- **Locally:** `make ci` runs in Docker: Pint, Larastan, ESLint, `tsc`, Pest (parallel), the FR-coverage check, and browser tests. The **`.githooks/pre-push`** hook runs `make ci` before every `git push` (enabled once per clone with `make hooks`).
- **Deploying to the demo** (`make deploy-demo`, run from your machine, which just SSHes into the VPS and runs `scripts/deploy-demo.sh` there):
  1. `git pull` from **GitHub** using a **read-only deploy key** installed on the VPS;
  2. `docker compose -f compose.demo.yaml build` **on the VPS**;
  3. `docker compose -f compose.demo.yaml up -d` (a few seconds of downtime);
  4. migrations, `config:cache`/`route:cache`, SSR restart;
  5. `make smoke`: home page, a business profile, login page, and `/_mail` respond over HTTPS;
  6. prune old images, keeping the previous one tagged for `make rollback-demo`.
- **One-time server setup** (documented in `docs/runbooks/demo-server.md`, checked by `make preflight`):
  - Docker installed;
  - deploy key added to GitHub (read-only) and to the VPS;
  - DNS A record;
  - firewall;
  - swap;
  - `.env` created from `.env.demo.example`.
- **Resetting demo data:** `make demo-reset` re-runs migrations with the base and demo seeders (asks for confirmation).
- **Trade-offs:**
  - **Tests never run on the VPS.** The pre-push hook is the gate, and it can be skipped deliberately with `--no-verify`.
  - Deploys depend on GitHub being reachable (your choice).
  - Building on the VPS makes the live demo slower for a few minutes during a deploy, so deploy outside client sessions.

### D24. Demo hosting: Vultr VPS
- **Server:** Vultr shared-CPU **vc2-4c-8gb** (4 vCPU, 8 GB RAM, 160 GB SSD), **Ubuntu 24.04 LTS**, London location, **2 GB swap file**.
- **Estimated memory use:**

  | Container | Approx. RAM |
  |-----------|-------------|
  | caddy | 30–50 MB |
  | app (PHP-FPM, up to ~10 workers) | 400–700 MB |
  | ssr | 150–250 MB |
  | queue (2 workers) | 250–600 MB |
  | scheduler | 60–100 MB |
  | postgres (`shared_buffers` 512 MB) | 500–800 MB |
  | mailpit | 30–50 MB |
  | Ubuntu + Docker | 400–500 MB |
  | **Running total** | **≈ 1.8–3 GB** |
  | **Image build during a deploy** | **+1.5–3 GB for a few minutes** |

  8 GB covers both, with room to spare. The swap file is a safety net.
- **Security:**
  - firewall allows only ports 22, 80, and 443;
  - SSH key-only login;
  - PostgreSQL, SSR, and queue are never exposed;
  - the whole site sits behind the demo password (D27).
- **No backups in demo** (§5.6). If the VPS is lost, a new VPS plus `make deploy-demo` and `make demo-reset` recreate everything from the repo and seeders. Anything the client entered by hand during the demo would be lost.
- **Trade-off:** no redundancy, a few seconds of downtime per deploy, and shared CPU can occasionally slow things down. All acceptable for a demo, as you agreed.

### D25. Web server: Caddy with free Let's Encrypt HTTPS

**Caddy vs nginx (decided: Caddy):**

| Criterion | nginx (+ certbot) | **Caddy** ✅ |
|---|---|---|
| Your experience | Familiar | New, but the config is short |
| Let's Encrypt HTTPS | certbot + a renewal schedule + a reload hook (3 extra pieces) | Built in: gets and renews certificates by itself |
| Config for our setup | ~60–80 lines | ~20–30 lines |
| Laravel via PHP-FPM | ✅ | ✅ (`php_fastcgi`) |
| Performance at our scale | Excellent | Excellent |
| Docs and community answers | Huge | Good, smaller |
| Containers | 2 | 1 |

- **Why Caddy:** zero extra parts for HTTPS. To make up for your unfamiliarity, the Caddyfile will be commented line by line, and `docs/runbooks/caddy.md` covers common tasks (logs, reload, certificate status, troubleshooting).
- **How it works in the demo:**
  - Caddy gets a Let's Encrypt certificate for **`feeqa.appsarray.com`** on first start (Let's Encrypt checks domain control over port 80) and renews it automatically about 30 days before its 90-day expiry.
  - HTTP is redirected to HTTPS.
  - TLS 1.2+ only. HSTS is set to 1 day, with no preload.
- **Demo `.env`:**

  | Key | Value |
  |-----|-------|
  | `APP_URL` | `https://feeqa.appsarray.com` |
  | `APP_DOMAIN` | `feeqa.appsarray.com` |
  | `ACME_EMAIL` | `ghufran@gmail.com` |
  | `ACME_CA` | `staging` for the very first deploy, then `production` |

- **Before the first deploy:**
  1. a DNS **A record** for `feeqa.appsarray.com` → the VPS IP (plus an AAAA record → the VPS, only if the domain uses IPv6);
  2. ports 80 and 443 open;
  3. nothing else on the VPS using ports 80/443.
- **Certificate storage:** the `caddy_data` volume keeps the certificate across deploys, so we never hit Let's Encrypt's rate limits (e.g., 5 duplicate certificates per week).
- **No daily certificate-check job and no extra container in the demo.** Caddy's own renewal is enough. `make smoke` still confirms HTTPS works after each deploy.
- **Local development:** plain **`http://localhost`**, with no certificate and no Let's Encrypt.
- **Trade-offs:**
  - port 80 must stay open for renewals;
  - Let's Encrypt needs the real domain, so it can't be tested on localhost;
  - the team needs to learn a new tool (mitigated by the runbook).

### D26. Environments (`APP_ENV`)

| | `local` | `testing` | `demo` | `production` *(later)* |
|---|---|---|---|---|
| **Where** | Your machine | Your machine (Pest) | Vultr VPS | Production servers |
| **URL** | `http://localhost` | n/a | `https://feeqa.appsarray.com` | TBD |
| **Debug details on errors** | Shown | Shown | Hidden (friendly error pages) | Hidden |
| **Assets and caching** | Live reload, no caching | n/a | Built image, cached config/routes | Same as demo |
| **SSR** | Optional | Off (browser tests on) | On | On |
| **Demo relaxations (§5.6)** | On | On where a test needs them | **On** | **Impossible** |
| **Demo seeders** | Allowed | Test factories instead | Allowed | Blocked |
| **AI** | DeepSeek API or `fake` | `fake` | DeepSeek API | EU host |
| **Payments** | `fake` | `fake` | `fake` (always succeeds) | Stripe |
| **Receipt reading / transcripts / malware scan** | `fake` | `fake` + real adapters in dedicated tests | `fake` | Real |
| **Email** | Mailpit | Faked in memory | Mailpit | Real SMTP |
| **Site password gate** | Off | Off | **On** | Off |

- **How relaxations are guarded:** one class (`App\Support\Environment`) answers "are demo relaxations allowed?". It returns **false whenever `APP_ENV=production`, whatever the other `.env` values say.** On startup in production, the app **refuses to boot** if any `fake` driver or demo setting is configured. Pest tests check both behaviours.
- **Trade-off:** a fourth environment to maintain. It's worth it, so demo shortcuts can't leak into production.

### D27. Demo access gate and privacy
- **Choice:**
  - The **whole demo site** sits behind one shared username/password (HTTP basic auth in Caddy, set as `DEMO_GATE_USER` / `DEMO_GATE_PASSWORD` in `.env`), on top of the app's own logins.
  - Every response carries `X-Robots-Tag: noindex, nofollow`, and `robots.txt` disallows everything.
  - Demo data uses **fictional businesses and people only** (D28).
- **Why:** the demo shows real "Verified Experience" labels on fake verifications (D12) and simulated payments. The gate and fictional data make sure no stranger or search engine can mistake them for real reviews of real companies.
- **Trade-off:** the client enters one extra password (the browser remembers it). External widget embeds can't load demo data, so the showcase page covers that (D17).

### D28. Demo seeders ("real-like" data)
- **Choice:** demo seeders live **separately** in `database/seeders/Demo`.
  - They run only in `local`/`demo` with `php artisan db:seed --class=Database\\Seeders\\Demo\\DemoSeeder` (wrapped by `make demo-reset`).
  - They're blocked in production.
  - The base `DatabaseSeeder` (roles, industries, travel content, methodology, plans) runs everywhere.
- **Realism:** review, reply, case, and summary texts come from **curated JSON files written in natural English for travel** (`data/*.json`), not placeholder Latin. Seeders combine them with a fixed random seed, so every reset produces the **same dataset**.
- **What the demo data covers** (all features visible on day one):

  | Area | Demo data |
  |------|-----------|
  | Businesses | ~40 **fictional** travel businesses (airlines, OTAs, agencies, airports) with logos, locations, categories, claimed/unclaimed mix; a few in draft industries (e.g., Finance) |
  | Reviews | ~1,500 reviews over **24 months** with a realistic rating spread; category answers; Invited/Redirected/Organic labels; **Verified Experience** badges; lifecycle updates; tags to a second business ("Mentioned in reviews") |
  | Scores | Review Scores, Trust Indexes with breakdowns, **24 months of back-filled history** for trendlines |
  | Engagement | Business replies, Useful votes, reply-rate signals |
  | Cases | Complaints with full timelines (raised → replied → resolved → refunded / compensation paid); unconfirmed refunds; mediations with outcomes; resolution ratings; a "resolve first" example |
  | Moderation | Flags in the queue, held reviews, a removed review with statement of reasons and appeal, **one business under Consumer Warning**, **one business with a staged review spike** (fraud incident) |
  | AI | Pre-written "What People Love / What Needs Work" summaries, topics, and sentiment (fallback when AI is off; overwritten by DeepSeek when AI is on) |
  | Media | A few reviews with sample voice notes and video clips (short, self-made or royalty-free, stored in the repo) and realistic transcripts |
  | Insider | Insider reviews on businesses marked ≥ 50 employees, with the separate Insider Rating |
  | Reviewers | ~200 fictional consumers with passports, levels (New → Expert), and exports available |
  | Invitations | Templates, a sent-invitation history with funnel stats, suppression-list entries |
  | Business side | Businesses on Free/Starter/Pro/Enterprise plans, simulated invoices and a failed-payment example, analytics with industry benchmark and named competitors, sponsored slots, lead-fee statements, API keys and webhook logs |
  | Staff | One account per staff role |
- **Demo logins:** the seeder creates **ready-to-use accounts** (a consumer, a business Owner/Admin/Responder/Analyst, and each staff role) with simple demo passwords (≥ 6 characters). They're listed in `docs/demo/accounts.md` for the client walkthrough. They only work where the seeders ran, and never in production.
- **Trade-off:** writing realistic curated content takes effort up front, but it's what makes the demo convincing. A fixed seed means the demo looks the same after every reset.

### D29. Documentation: README, user guides, system overview
- **Choice:** three sets of Markdown documents, kept up to date **in the same change** as the code (constitution §8 rule 9, §7), with an automated check.

**1. `README.md` (repo root), for developers and whoever runs the demo server:**

| Section | Contents |
|---------|----------|
| What this is | Two-paragraph summary, with links to the system overview, specs, and constitution |
| Prerequisites | What to install on a fresh machine: Git, Docker Desktop / Docker Engine + Compose, Make. No local PHP or Node needed, because everything runs in containers |
| Local setup from scratch | Clone → `cp .env.example .env` → `make up` → `make install` (composer + npm inside containers) → `make key` → `make migrate` → `make demo-reset` → open `http://localhost`. Then `make hooks` (pre-push test hook) and Laravel Boost setup for AI-assisted development |
| `.env` reference | Every `.env` key in one table: meaning, **local value**, **demo value**, and allowed values (e.g., `AI_DRIVER=openai-compatible\|fake`). Grouped as in §5 of this plan |
| Everyday development | `make up/down/logs/shell`, running Artisan/Composer/npm through Make, live reload, Mailpit at `http://localhost:8025` |
| Testing | `make test`, `make test-filter F=…`, `make test-browser`, `make ci`, the FR-coverage check, what the pre-push hook does |
| Demo server: first-time setup | Create the Vultr VPS → DNS A record for `feeqa.appsarray.com` → SSH hardening and firewall → swap file → install Docker → generate the deploy key and add it to GitHub (read-only) → clone → create `.env` from `.env.demo.example` → `make preflight` → first deploy with `ACME_CA=staging` → switch to `production` → `make demo-reset` |
| Demo server: updating to new code | Push to GitHub (the hook runs tests) → `make deploy-demo` → what it does step by step → how to check it worked → `make rollback-demo` if not |
| Demo server: common commands | Status, logs per container, restart one container, open a shell, run Artisan, reset demo data, clear caches, check certificate status, check disk and memory, prune old images, open the Mailpit inbox, change the demo gate password |
| Troubleshooting | Certificate not issued, SSR down (empty pages), queue stuck, out of memory, migrations failing |
| Links | Constitution, specs, plan, runbooks, user guides, system overview |

**2. `docs/user-guides/`, one Markdown guide per user type**, written for non-technical readers, with step-by-step tasks and screenshots added once the UI exists:

| File | User type | Covers (examples) |
|------|-----------|-------------------|
| `index.md` | n/a | Which guide to read, by role |
| `consumer.md` | Visitor & consumer (reviewer) | Searching, comparing businesses, reading scores and labels, writing reviews, Verified Experience, updates at 30 d / 6 m / 1 y, voice/video, complaints and mediation, resolution rating, flagging, reviewer passport and export, account deletion |
| `insider-reviewer.md` | Employee / insider | Verifying employment, anonymity protections, writing insider reviews |
| `business-owner.md` | Business Owner | Claiming a profile, plans and billing, members and roles, ownership transfer, plus everything an Admin can do |
| `business-admin.md` | Business Admin | Profile and locations, invitations (BCC, CSV, API, link/QR), templates, widgets, API keys and webhooks, sponsored slots, lead fees |
| `business-responder.md` | Business Responder | Review inbox, replying (with AI suggestions), requesting verification, flagging, handling cases and mediation |
| `business-analyst.md` | Business Analyst | Dashboards, durability, topics, industry benchmarks, competitors, exports and scheduled reports |
| `staff-moderator.md` | Moderator | Moderation queues, held content, flags, statements of reasons, verification queue |
| `staff-senior-moderator.md` | Senior Moderator | Consumer Warnings, enforcement ladder, appeals, fraud incidents, editing question sets and topics |
| `staff-mediator.md` | Mediator | Taking cases, case digest, recommendations, recording outcomes |
| `staff-support.md` | Support | Account and business help, data export/erasure requests |
| `staff-admin.md` | Staff Admin | Staff accounts, industries (create/launch/pause), methodology versions, plans and entitlements, legal sign-offs, transparency report |
| `api-integrator.md` | Developer integrating via the API/widgets | API keys, endpoints, webhooks, widget embedding, rate limits |

Shared steps (e.g., logging in, resetting a password) live in `docs/user-guides/common/` and are linked from each guide, not copied. Each guide lists the pages it covers, which the docs check uses.

**3. `docs/system-overview/`: what the system is and what it does**, for the client, new team members, and stakeholders. Non-technical:

| File | Contents |
|------|----------|
| `README.md` | What the Platform is, who it's for, and how it differs from other review sites |
| `features.md` | Every feature grouped by area (reviews, verification, scores, cases, search/compare, AI, media, insider, reputation, analytics, widgets/API, plans), each with a short description and a link to its spec |
| `user-types.md` | Each user type and what they can do (links to the user guides) |
| `how-scores-work.md` | Review Score and Trust Index in plain English |
| `trust-and-safety.md` | Verification, moderation, enforcement, transparency |
| `demo-vs-production.md` | What differs in the demo (constitution §5.6) |
| `glossary.md` | Platform terms (from the constitution glossary) |

**How "kept up to date automatically" works:**
- **Rule:** every change updates the affected docs in the same commit (constitution §8 rule 9). Claude does this as part of every task, without being asked. The definition of done (§7) makes stale docs a blocker.
- **`make docs-check`** (part of `make ci`, so the pre-push hook runs it) fails when:
  1. an `.env.example` or `.env.demo.example` key isn't documented in the README `.env` table;
  2. a `make` target isn't listed in the README commands sections;
  3. an Inertia page in `resources/js/pages/` isn't covered by any user guide's "pages covered" list;
  4. a spec (001–017) has no entry in `docs/system-overview/features.md`;
  5. a Markdown link in `README.md` or `docs/` points to a file that doesn't exist.
- **What can't be checked automatically** (whether the wording is still accurate) is covered by the §7 definition-of-done checklist and code review.
- **Trade-off:** keeping 13 guides current adds work to every UI change. Linking shared steps from `common/` instead of copying them keeps this manageable. The docs check catches the most common drift (new pages, new settings, new commands), but not every wording mistake.

---

## 5. Configuration Summary (`.env`)

| Concern | Keys | Demo value |
|---------|------|------------|
| Environment | `APP_ENV`, `APP_DEBUG`, `APP_URL` | `demo`, `false`, `https://feeqa.appsarray.com` |
| HTTPS | `APP_DOMAIN`, `ACME_EMAIL`, `ACME_CA` | `feeqa.appsarray.com`, `ghufran@gmail.com`, `staging` → `production` |
| Demo gate | `DEMO_GATE_USER`, `DEMO_GATE_PASSWORD` | set per demo |
| AI | `AI_DRIVER`, `AI_BASE_URL`, `AI_API_KEY`, `AI_MODEL`, `AI_MODEL_FAST`, `AI_TIMEOUT`, `AI_MAX_TOKENS` | `openai-compatible`, DeepSeek API URL + key, `deepseek-v4-pro` |
| Receipt reading | `VERIFICATION_EXTRACTOR`, `OCR_LANGUAGES` | `fake` |
| Transcripts | `TRANSCRIPTION_DRIVER` | `fake` |
| Malware scan | `MALWARE_SCANNER`, `CLAMAV_HOST` | `fake` |
| Payments | `BILLING_DRIVER`, `STRIPE_*` | `fake` |
| Mail | `MAIL_*`, `MAILPIT_UI_PATH` | Mailpit, `/_mail` |
| Storage | `FILESYSTEM_DISK`, `PRIVATE_DISK` | `local` |
| Social login | `GOOGLE_*`, `APPLE_*`, `FACEBOOK_*` | empty (buttons hidden) unless you add keys |
| Staff access | `STAFF_ALLOWED_IPS` | ignored in demo |
| Signing | `SIGNING_KEYS_PATH`, `SIGNING_ACTIVE_KID` | generated on first deploy |
| Feature flags | `FEATURE_*` | all on |
| Queue | `QUEUE_WORKERS` | `2` |

---

## 6. Dependencies

**Composer (production):** `laravel/framework` 13, `inertiajs/inertia-laravel`, `laravel/fortify`, `laravel/wayfinder`, `laravel/socialite` + `socialiteproviders/apple`, `spatie/laravel-permission`, `laravel/ai`, `laravel/sanctum`, `laravel/cashier` *(used only when `BILLING_DRIVER=stripe`)*.

**Composer (dev):** `pestphp/pest` 4 + `pest-plugin-laravel` + `pest-plugin-browser`, `laravel/boost`, `laravel/pint`, `larastan/larastan`, `league/openapi-psr7-validator`.

**npm:** what the React starter kit brings, plus `playwright` (local tests only).

**System packages in the image:** `poppler-utils`, `tesseract-ocr` + English data.

**Container images (demo):** `caddy`, `postgres:17`, `axllent/mailpit`, and our own image (PHP 8.4 / Node 22 build stages).

**Not used:** GitHub Actions or any CI service, paid services, Metronic, activity-log packages, an append-only event store, a search library, Redis, Pennant, two-factor authentication, a separate React SPA, Next.js, ClamAV (in demo), a payment gateway (in demo), a certificate-check job (in demo).

---

## 7. Constitution Conformance

| Constitution item | How the plan satisfies it |
|-------------------|---------------------------|
| §2 Spec-driven development, FR traceability | FR-ID test names + `fr-coverage` check in `make ci` |
| P2 Money never buys trust | Scoring in `app/Domain` with an arch test (no billing access), plus invariance tests |
| P3 / P5 | Policies: no business write path to reviews. Invitation template checks |
| P4 Transparency | Methodology versions as data. Report job built on the compliance log (D8) |
| P6 Privacy | Private disk + signed links, export/erasure Actions, retention jobs (production) |
| P7 Supervised AI | D11 wrapper: provenance, output checks, labels, `fake` fallbacks |
| P8 Accessibility | `assertNoAccessibilityIssues()` on every page, plus manual keyboard checks |
| P9 Security | Rate limiting, breached-password checks, Spatie + Policies, signed attestations, staff IP allow-list and malware scanning in production |
| §5.1 One application layer, compliance log, provider-by-env | D2, D8, D11–D15 |
| §5.6 Demo environment | D26 guard class + production boot refusal + tests (D20 rule 8), D27 gate, D28 fictional data |
| §7 / §8 rule 9 Documentation kept current | D29: README, user guides, system overview, `make docs-check` in `make ci` |

---

## 8. Demo Build Order (all features)

1. **Foundation:** starter kit (2FA removed), Docker + `compose.yaml` / `compose.demo.yaml`, Makefile, git hook, `Environment` guard, all drivers with `fake` implementations, test setup, Boost.
2. **001** Accounts & roles → **002** Businesses & industries (base seeders).
3. **003** Reviews → **006** Moderation (screening, flags, staff console).
4. **008** Review Score & Trust Index → **009** Search & compare.
5. **004** Verification → **010** Cases & mediation → **007** Replies.
6. **005** Invitations (Mailpit + `.eml` upload) → **017** Plans & simulated billing, sponsored slots, lead fees.
7. **011** AI summaries/topics/trends → **012** Voice/video (fake transcripts) → **013** Insider reviews → **014** Reviewer passport & levels.
8. **015** Analytics & benchmarking → **016** Widgets (showcase page), API & developer portal, webhooks, licensing screens.
9. **Demo seeders** (D28) and `docs/demo/accounts.md`.
   *Throughout steps 1–9:* README, user guides, and system overview are written and updated alongside each feature (D29), not at the end.
10. **Demo server:** VPS setup, `make preflight`, first deploy with Let's Encrypt staging → production, smoke test, `make demo-reset`.

---

## 9. Spec Amendments This Plan Needs (for your approval)

| Spec | Change | Reason |
|------|--------|--------|
| 016 FR-016-11 | Business API authorisation = **Sanctum tokens** scoped to one business. OAuth2 later when third-party apps arrive | D18 |
| 005 FR-005-06/07 | Demo supports inbound BCC through `.eml` upload. Real inbound mail and SPF/DKIM checks at launch | D14 |
| 014 Q3 / 004 FR-004-14 | Attestation and credential format = **JWS (EdDSA)** | D16 |
| All specs | Specs describe **production** behaviour. Demo deviations are governed only by constitution §5.6 (already added) | D26 |

---

## 10. Open Questions

None. Everything raised so far has been decided. Assumptions you may want to double-check while reviewing:
1. **Demo data volume** (D28): ~40 businesses, ~1,500 reviews, ~200 consumers, 24 months of history.
2. **Site password gate** (D27): the client uses one shared username/password to reach the demo, in addition to the demo accounts.
3. **Social login buttons** stay hidden in the demo unless you create Google/Apple/Facebook app keys.
