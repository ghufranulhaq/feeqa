# feeqa — Trust & Review Platform

An open consumer review platform with proof-of-experience verification,
complaint/resolution tracking, a composite Trust Index, and reviewer
ownership of their own data. See [`CONSTITUTION.md`](CONSTITUTION.md) for
the mission and non-negotiable principles, and [`specs/`](specs/README.md)
for what's being built and why.

This is a **client demo** project: everything runs on a single server,
behind a shared password, with fictional data. Constitution §5.6 lists
exactly what's relaxed for the demo and why.

## Prerequisites

- Docker and Docker Compose. Nothing else — PHP, Node, and Postgres all run
  in containers; you don't need them installed on your machine.

## Setup from scratch

```bash
git clone <repo-url> trust-review-platform
cd trust-review-platform

cp .env.example .env

docker compose up -d
make install      # composer install, npm install, php artisan storage:link
make key          # generates APP_KEY
make migrate
```

The app is now at `http://localhost` (Caddy, `docker/Caddyfile`), Vite's
dev server hot-reloads on `http://localhost:5173`, and Mailpit (catches
every outgoing email locally — nothing is ever sent to a real inbox in
local/demo) is at `http://localhost/_mail`.

Run `php artisan signing:generate-key` once if you need signed attestations
(spec 004/016) — everything else works without it.

## Everyday commands

| Command | What it does |
|---|---|
| `make up` / `make down` | Start / stop the local stack |
| `make shell` | Shell into the app container |
| `make logs` (or `make logs S=app`) | Follow container logs |
| `make migrate` | Run migrations |
| `make fresh` | Drop everything and re-migrate (asks to confirm) |
| `make test` | Run the full Pest suite |
| `make test-filter F=SomeTest` | Run tests matching a name |
| `make test-browser` | Playwright-backed browser/accessibility tests |
| `make ci` | Lint (Pint, ESLint, tsc) + Larastan + the full test suite — what the pre-push hook runs |
| `make hooks` | Enable the pre-push git hook (run once per clone) |
| `make docs-check` | Verify README/user-guides/system-overview are present and current (constitution §8 rule 9) |

## `.env` reference

`.env.example` is the template; every value below either has a safe local
default already or is documented inline in that file.

- **App**: `APP_NAME`, `APP_ENV` (`local` here; `demo` or `production`
  elsewhere — see `App\Support\Environment`), `APP_KEY`, `APP_URL`.
- **Database**: `DB_*` — PostgreSQL 17, container hostname `postgres`.
- **Session/cache/queue**: all `database`-backed (plan D10). `SESSION_LIFETIME`
  is the outer cookie/GC bound (30 days); the real per-user idle limit is
  `App\Http\Middleware\EnforceSessionLifetime` (FR-001-16).
- **Mail**: routed to Mailpit locally (`MAIL_HOST=mailpit`); its inbox UI is
  at `/_mail`.
- **External providers** (`AI_DRIVER`, `VERIFICATION_EXTRACTOR`,
  `TRANSCRIPTION_DRIVER`, `MALWARE_SCANNER`, `BILLING_DRIVER`): each
  defaults to `fake` for local/demo. Production refuses to boot with any of
  them still `fake` (`Environment::assertProductionIsSafe()`).
- **`STAFF_ALLOWED_IPS`**: comma-separated CIDR/IP allow-list for the staff
  console. Only enforced when `APP_ENV=production` — ignored everywhere
  else, so leave it blank locally.
- **`SIGNING_KEYS_PATH` / `SIGNING_ACTIVE_KID`**: Ed25519 keys for signed
  attestations (plan D16). Leave blank to use the default path and
  whichever key was generated first.
- **`PASSWORD_CHECK_BREACHED`**: whether registration/password-reset checks
  a password against the public "Have I Been Pwned" API (FR-001-04).
  Production always checks regardless of this value; set it to `false`
  locally for offline development. Automated tests always force it off.
- **Social sign-in** (`GOOGLE_*`, `FACEBOOK_*`, `APPLE_*`): FR-001-01. Each
  provider's button only appears once its `_CLIENT_ID` is set — all three
  are blank by default, so none show up until you add real credentials.

## Testing

- `make test` runs the whole Pest suite against a real Postgres database
  (`feeqa_test`, created automatically on first `docker compose up` via
  `docker/postgres/init-test-db.sh`).
- Every functional requirement (`FR-<spec>-<nn>`) that's implemented has at
  least one test that references its ID — see constitution §2 and §6.
- `make test-browser` runs the Playwright-backed accessibility/browser
  tests (dev machines only, plan D20).

## Demo deployment

`compose.demo.yaml` and `docker/Caddyfile.demo` build a self-contained image
and serve it behind Let's Encrypt HTTPS and a shared site password
(constitution §5.6, plan D27). **This has not been deployed yet** —
constitution §12 (v1.6) records that deployment needs the client's explicit
approval after local development and testing are complete. The deploy
script and `make deploy-demo` target come with plan §8 build-order step 10,
once that approval is given.

## Project map

- Constitution (the non-negotiable rules): [`CONSTITUTION.md`](CONSTITUTION.md)
- Specifications: [`specs/README.md`](specs/README.md), one folder per spec,
  each with a `spec.md` (what/why) and, once build starts, a `tasks.md`
  (the ordered implementation steps)
- Cross-cutting technical plan: [`specs/plan.md`](specs/plan.md)
- What the system does: [`docs/system-overview/`](docs/system-overview/)
- One guide per user type: [`docs/user-guides/`](docs/user-guides/)
- Research: [`docs/research/`](docs/research/)
