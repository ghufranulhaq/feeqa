# Spec 001 Tasks: Accounts, Identity & Roles

Each task is implemented, tested, and committed on its own before the next
one starts (constitution §2, §8). Task order follows dependency, not FR
number. `businesses` gets a minimal stand-in table here (id, name only) so
memberships have something to point at; spec 002 owns and extends it.

- [x] **T1. Consumer profile columns.** Extend `users` (country, locale,
      avatar_path, date_of_birth_confirmed_at) via a new migration; update
      `User` model casts/fillable and factory. FR-001-03, FR-001-05.
- [x] **T2. Password rules.** Wire `Environment::passwordMinLength()` and
      `Password::uncompromised()` into registration, password reset, and the
      password settings form. FR-001-04.
- [x] **T3. Display name validation + age confirmation.** A reusable
      `DisplayName` validation rule (2-40 chars, no blank/whitespace-only, no
      URL/email/phone, no staff-role impersonation); an `over_18` checkbox
      required at registration, timestamped into
      `date_of_birth_confirmed_at`. FR-001-03, FR-001-05, edge cases table.
- [x] **T4. Country + locale in profile settings.** Validate against ISO
      3166-1 alpha-2 in `ProfileUpdateRequest`; locale restricted to the
      locales the platform ships. FR-001-05, FR-001-08.
- [x] **T5. Public consumer profile page.** Route + controller + Inertia page
      showing display name, avatar, country, member-since, published review
      count (0 for now — reviews are spec 003), list placeholder. Test it
      never serializes email or proof data. FR-001-07.
- [x] **T6. Duplicate sign-up handling.** Registering with an existing
      verified email sends a "sign in instead" notification instead of a
      validation error that reveals the account exists. Edge case table.
- [x] **T7. Consents.** `consents` table (terms_version, privacy_version,
      marketing_opt_in) recorded at registration with timestamps; a
      `ConsentRequirement` check that forces re-consent when versions in
      config change. FR-001-21.
- [x] **T8. Passwordless sign-in.** 6-digit email code and magic-link
      sign-in as an alternative to password, sharing the rate limiting built
      in T12. FR-001-01.
- [x] **T9. Social sign-in.** Socialite (Google, Apple, Facebook) wired
      behind config presence; `user_providers` table; link-by-verified-email
      onto an existing account instead of duplicating; unverified provider
      email is treated as unverified. FR-001-01, FR-001-02, FR-001-06, edge
      cases.
- [x] **T10. Avatar upload.** Size/type/malware-scan/EXIF-strip pipeline
      using the existing `MalwareScanner` driver; reject animated images.
      Edge cases table.
- [x] **T11. Sessions: list + revoke, differentiated expiry.** Consumers 30
      days inactivity, staff 12 hours; a settings page listing active
      sessions with revoke. FR-001-16.
- [x] **T12. Rate limiting.** 5 failed attempts / 15 min per account and per
      IP on sign-in, code verification, and password reset, with a
      cool-down past the limit. FR-001-17.
- [x] **T13. Device/network signal capture.** Privacy-safe IP/user-agent
      already on `sessions`; add a note to the privacy documentation.
      FR-001-18.
- [x] **T14. Business roles.** `spatie/laravel-permission` in teams mode
      (`team_id` = `business_id`), minimal `businesses` stand-in table,
      Owner/Admin/Responder/Analyst roles + the FR-001-10 permission matrix.
      Built as `Business::userCan()`/`hasBusinessRole()` (direct
      `model_has_roles` queries) plus `App\Support\Businesses\
      BusinessMembershipGuard` rather than a formal Laravel Policy class —
      there's no single Eloquent model these authorization checks hang off
      (a "membership" is a role assignment, not a row), and the guard is
      reused identically by direct assignment (T14) and invitations (T15).
      `SetPermissionTeam` middleware, last-Owner protection.
      FR-001-09, FR-001-10, FR-001-11.
- [x] **T15. Business invitations.** Invite-by-email-and-role, accept flow,
      unauthorized-role rejection. FR-001-09 scenario 3.
- [x] **T16. Membership vs. reviewing conflict guard.** Satisfied inside T14:
      `Business::hasMembership(User $user): bool`, tested in
      `BusinessPermissionMatrixTest`. FR-001-13 (unit-level only — review
      submission is spec 003).
- [ ] **T17. Staff accounts.** Staff roles (Moderator, Senior Moderator,
      Mediator, Support, Admin) as global Spatie roles; creation restricted
      to staff Admin; `StaffIpAllowList` middleware using
      `Environment::staffIpAllowlistEnforced()`; `compliance_log` table with
      an entry written when a staff account is created. FR-001-14, FR-001-15.
- [ ] **T18. Data export.** An `ExportsUserData` collector interface plus an
      `Actions\Account\ExportUserData` action producing a JSON archive
      (media as files) via a queued job, a 7-day-expiring signed download
      link, delivered within 24h, and de-duplication of a second request
      inside 24h. FR-001-19, edge case.
- [ ] **T19. Account deletion.** Hide public content immediately (no-op
      today — nothing public exists until spec 003), a `deletion_requested_at`
      flag, a scheduled command that erases/pseudonymises after 30 days,
      confirmation email. FR-001-20.
- [ ] **T20. Docs pass.** README, `docs/user-guides/`, `docs/system-overview/`
      updated for everything above (constitution §8 rule 9); `make
      docs-check` passes.
- [ ] **T21. Acceptance sweep.** Re-check every box in spec.md §6 against
      what's built; `make ci` green.
