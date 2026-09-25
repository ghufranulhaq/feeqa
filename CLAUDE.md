# CLAUDE.md

This project follows a written constitution. It is imported below and is **authoritative**: if anything else in this file (including tool-generated guidelines further down) conflicts with it, the constitution wins.

@CONSTITUTION.md

## Project map

- Constitution: `CONSTITUTION.md`
- Specifications (spec-driven development): `specs/README.md`, one folder per spec
- Platform technical plan: `specs/plan.md`
- Client requirements: `add-to-trustpilot.md`
- Research: `docs/research/`
- System overview (what the system does): `docs/system-overview/`
- User guides (one per user type): `docs/user-guides/`
- Setup, demo deployment, `.env` and commands: `README.md`

When you change behaviour, setup, configuration, commands, or deployment, update `README.md`, the affected user guides, and the system overview **in the same change** (constitution §8 rule 9).

## Laravel Boost guidelines

Laravel Boost (plan D21, dev-only) writes its guidelines to `AGENTS.md`, updated by `php artisan boost:update`. Do not edit that file by hand.

@AGENTS.md
