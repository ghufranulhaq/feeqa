<?php

use App\Actions\Accounts\Export\AccountDataCollector;

// Every external provider is selected here, from .env only (constitution
// §5.1). Each has a `fake` driver. Password rules and staff IP allow-listing
// are NOT read from .env — see App\Support\Environment — so production can't
// be relaxed by a stray environment variable (plan D6, D26).

return [

    /*
    |--------------------------------------------------------------------------
    | AI (constitution §5.1, plan D11)
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'driver' => env('AI_DRIVER', 'fake'),
        'base_url' => env('AI_BASE_URL'),
        'api_key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL'),
        'model_fast' => env('AI_MODEL_FAST'),
        'timeout' => (int) env('AI_TIMEOUT', 60),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 4000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipt / document reading for Verified Experience (plan D12)
    |--------------------------------------------------------------------------
    */
    'verification' => [
        'extractor' => env('VERIFICATION_EXTRACTOR', 'fake'),
        'ocr_languages' => env('OCR_LANGUAGES', 'eng'),
        // FR-004-10: the HMAC key behind every proof fingerprint. Never
        // logged, never derived from APP_KEY (rotating APP_KEY must not
        // silently make every stored fingerprint unmatchable).
        'fingerprint_key' => env('VERIFICATION_FINGERPRINT_KEY'),
        // FR-004-07 tamper checks.
        'editing_tool_blocklist' => array_filter(explode(',', env(
            'VERIFICATION_EDITING_TOOL_BLOCKLIST',
            'photoshop,gimp,photopea,pixlr,paint.net',
        ))),
        'known_fake_template_hashes' => array_filter(explode(',', env('VERIFICATION_KNOWN_FAKE_TEMPLATE_HASHES', ''))),
        // FR-004-06 auto-approval date window.
        'auto_approval' => [
            'max_age_months' => (int) env('VERIFICATION_MAX_AGE_MONTHS', 12),
            'grace_days_after_experience' => (int) env('VERIFICATION_GRACE_DAYS_AFTER_EXPERIENCE', 30),
        ],
        // FR-004-14: bump when the attestation payload shape or the rules
        // behind an approval decision change, same idea as spec 008's
        // score methodology version.
        'methodology_version' => 1,
        // FR-004-12 batch limits for a business submitting transaction
        // records.
        'transaction_records' => [
            'max_per_request' => (int) env('TRANSACTION_RECORDS_MAX_PER_REQUEST', 10000),
            'max_per_day' => (int) env('TRANSACTION_RECORDS_MAX_PER_DAY', 1000000),
        ],
        // FR-004-23, constitution §5.6: see App\Support\Environment::rawProofFilesDeletedOnSchedule().
        'delete_raw_proof_files_on_schedule' => (bool) env('VERIFICATION_DELETE_RAW_PROOF_FILES_ON_SCHEDULE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voice/video transcripts (plan D12a)
    |--------------------------------------------------------------------------
    */
    'transcription' => [
        'driver' => env('TRANSCRIPTION_DRIVER', 'fake'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Malware scanning (plan D13)
    |--------------------------------------------------------------------------
    */
    'malware' => [
        'scanner' => env('MALWARE_SCANNER', 'fake'),
        'clamav_host' => env('CLAMAV_HOST'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments (plan D15)
    |--------------------------------------------------------------------------
    */
    'billing' => [
        'driver' => env('BILLING_DRIVER', 'fake'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Staff console (plan D7). Allow-list is only enforced in production —
    | see App\Support\Environment::staffIpAllowlistEnforced().
    |--------------------------------------------------------------------------
    */
    'staff' => [
        'allowed_ips' => array_filter(explode(',', (string) env('STAFF_ALLOWED_IPS', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signed attestations (plan D16) — sodium Ed25519, JWS format
    |--------------------------------------------------------------------------
    */
    'signing' => [
        'keys_path' => env('SIGNING_KEYS_PATH', storage_path('app/signing/keys.json')),
        'active_kid' => env('SIGNING_ACTIVE_KID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Account security (spec 001, plan D6)
    |--------------------------------------------------------------------------
    | check_breached_passwords can be switched off for offline development
    | (it calls the public "Have I Been Pwned" k-anonymity API). Production
    | always checks regardless of this value — see Environment::checkBreachedPasswords().
    */
    'security' => [
        'check_breached_passwords' => env('PASSWORD_CHECK_BREACHED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data export (FR-001-19)
    |--------------------------------------------------------------------------
    | Every App\Contracts\ExportsUserData implementation listed here
    | contributes a section to a user's export. Add to this list, don't
    | change BuildDataExport, when a new spec adds personal data.
    */
    'export' => [
        'collectors' => [
            AccountDataCollector::class,
        ],
        'download_link_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales (constitution §5.5, FR-001-05, FR-001-08)
    |--------------------------------------------------------------------------
    | Launch locale is en-GB only. Adding a locale is a config change, not a
    | code change — the architecture must not need code changes to add one.
    */
    'locales' => [
        'default' => 'en-GB',
        'supported' => ['en-GB'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Business listing checks (FR-002-10)
    |--------------------------------------------------------------------------
    | Not one of constitution §5.1's named external providers (no vendor to
    | swap), so it isn't covered by Environment::assertProductionIsSafe() —
    | but it still defaults to `fake` locally/demo, `dns` in production.
    */
    'business_listing' => [
        'checker' => env('BUSINESS_LISTING_CHECKER', 'fake'),
        'blocklist_domains' => array_filter(explode(',', env('BUSINESS_LISTING_BLOCKLIST_DOMAINS', ''))),
        'blocklist_keywords' => array_filter(explode(',', env(
            'BUSINESS_LISTING_BLOCKLIST_KEYWORDS',
            'porn,xxx,casino,escort',
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Business claiming (FR-002-11 through FR-002-15)
    |--------------------------------------------------------------------------
    | Not one of constitution §5.1's named providers either — same
    | fake-locally/demo, real-in-production shape as business_listing above.
    */
    'business_claiming' => [
        'domain_checker' => env('BUSINESS_CLAIM_DOMAIN_CHECKER', 'fake'),
        // FR-002-12: a business whose own domain is one of these can never
        // be claimed by method (a) — anyone could get an address there.
        'free_email_domains' => array_filter(explode(',', env(
            'BUSINESS_CLAIM_FREE_EMAIL_DOMAINS',
            'gmail.com,yahoo.com,outlook.com,hotmail.com,icloud.com,aol.com,protonmail.com,mail.com,yandex.com,gmx.com',
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Review screening (FR-003-11 through FR-003-13)
    |--------------------------------------------------------------------------
    | Rules only (word list, near-identical text, speed checks) — specs/
    | plan.md keeps this off the driver pattern deliberately, since there is
    | no external provider to swap. That's why it's a plain env-configurable
    | list here rather than a `.env`-selected driver like ai/verification
    | above.
    */
    'reviews' => [
        'screening' => [
            'blocklist_words' => array_filter(explode(',', env(
                'REVIEW_SCREENING_BLOCKLIST_WORDS',
                'fuck,shit,bastard,cunt,asshole',
            ))),
            // Edge case table: "Same text posted to several businesses" —
            // held for fraud review (006), not rejected outright.
            'near_identical_window_days' => (int) env('REVIEW_SCREENING_NEAR_IDENTICAL_WINDOW_DAYS', 30),
            'near_identical_similarity_threshold' => (int) env('REVIEW_SCREENING_NEAR_IDENTICAL_THRESHOLD', 90),
            // FR-006-04, FR-006-05, User Scenario 1: near-identical text
            // (not exact) shared by several distinct reviewers on the same
            // Business within a short window is held as coordinated fraud.
            // Exact text shared by accounts is a separate, auto-reject-
            // eligible rule below with its own, wider window.
            'coordinated_cluster_window_hours' => (int) env('REVIEW_SCREENING_COORDINATED_CLUSTER_WINDOW_HOURS', 1),
            'coordinated_cluster_min_accounts' => (int) env('REVIEW_SCREENING_COORDINATED_CLUSTER_MIN_ACCOUNTS', 3),
            // FR-006-05's own example: "exact duplicate text across >= 3
            // accounts" is one of the few rules allowed to auto-reject.
            'exact_duplicate_window_hours' => (int) env('REVIEW_SCREENING_EXACT_DUPLICATE_WINDOW_HOURS', 24),
            'exact_duplicate_min_accounts' => (int) env('REVIEW_SCREENING_EXACT_DUPLICATE_MIN_ACCOUNTS', 3),
            // FR-006-04: account age/history and velocity signals.
            'new_account_days' => (int) env('REVIEW_SCREENING_NEW_ACCOUNT_DAYS', 2),
            'reviewer_velocity_24h_threshold' => (int) env('REVIEW_SCREENING_REVIEWER_VELOCITY_24H_THRESHOLD', 5),
            'business_velocity_1h_threshold' => (int) env('REVIEW_SCREENING_BUSINESS_VELOCITY_1H_THRESHOLD', 10),
            // FR-006-05: everything that isn't a registered auto-reject
            // rule only ever pushes the recommendation to `hold`, once the
            // weighted risk score (0-1) from every signal below reaches
            // this line.
            'hold_risk_score_threshold' => (float) env('REVIEW_SCREENING_HOLD_RISK_SCORE_THRESHOLD', 0.5),
        ],
        'lifecycle_updates' => [
            // Constitution §5.6: "Review-update windows: always open" in
            // the demo environment. Off by default so every existing
            // date-math test keeps testing real windows — `App\Support\
            // Environment::lifecycleUpdateWindowsAlwaysOpen()` is what
            // actually enforces this being impossible in production,
            // whatever this flag says.
            'always_open_windows' => (bool) env('REVIEW_LIFECYCLE_WINDOWS_ALWAYS_OPEN', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Review invitations (spec 005)
    |--------------------------------------------------------------------------
    | The BCC SPF/DKIM-alignment check is rules only (FR-005-06) — same
    | "no external provider to swap" shape as review screening above, not a
    | driver.
    */
    'invitations' => [
        'bcc' => [
            'domain' => env('INVITATIONS_BCC_DOMAIN', 'bcc.feeqa.appsarray.com'),
            // FR-005-07's example pattern. A Business overrides this on its
            // own `bcc_reference_pattern` column.
            'default_reference_pattern' => env('INVITATIONS_BCC_DEFAULT_REFERENCE_PATTERN', '/[A-Z]{2}-\d{6}/'),
        ],
        // FR-005-13: used whenever a Business hasn't set up its own
        // template for the invitation's locale (T2's UpsertInvitationTemplate
        // is opt-in) — already passes GuardNeutralTemplate itself, so
        // sending never blocks on a Business having configured anything.
        'default_template' => [
            'subject' => 'How was your experience?',
            'body' => "We'd love to hear about your recent experience. Leave a review: {review_link}\n\nDon't want these emails? Unsubscribe: {unsubscribe_link}",
        ],
        // FR-005-08: "reminder ... sent 3-7 days after an unopened invitation."
        'reminder_after_days' => (int) env('INVITATIONS_REMINDER_AFTER_DAYS', 3),
        // FR-005-20: 017 (Plans & Billing) doesn't exist yet — this mirrors
        // its draft entitlement matrix (specs/017-billing-monetization/
        // spec.md FR-017-02) as a config placeholder, same "data-driven,
        // not hard-coded" shape FR-017-01 itself asks for. A null limit
        // means uncapped — Enterprise is "custom" per that matrix, so
        // there's no default number to guess at.
        'plan_limits' => [
            'monthly_invitations' => [
                'free' => (int) env('INVITATIONS_MONTHLY_LIMIT_FREE', 50),
                'starter' => (int) env('INVITATIONS_MONTHLY_LIMIT_STARTER', 500),
                'pro' => (int) env('INVITATIONS_MONTHLY_LIMIT_PRO', 5000),
                'enterprise' => env('INVITATIONS_MONTHLY_LIMIT_ENTERPRISE') !== null
                    ? (int) env('INVITATIONS_MONTHLY_LIMIT_ENTERPRISE')
                    : null,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Moderation & integrity (spec 006)
    |--------------------------------------------------------------------------
    | Network reputation is rules-only, same reasoning as reviews.screening
    | above: there's no external provider configured yet (a real IP-
    | reputation lookup is future work, same honest gap 006 T2 documents
    | for the missing IP address itself at every current screening call
    | site) — just a configured list of ranges to treat as suspicious,
    | matching User Scenario 1's "known VPN".
    */
    'moderation' => [
        'network' => [
            'known_bad_ranges' => array_filter(explode(',', env('MODERATION_NETWORK_KNOWN_BAD_RANGES', ''))),
        ],
        // FR-006-06's own numbers, run at least hourly (routes/console.php).
        'anomalies' => [
            'review_spike_multiplier' => (float) env('MODERATION_REVIEW_SPIKE_MULTIPLIER', 5),
            'review_spike_min_reviews' => (int) env('MODERATION_REVIEW_SPIKE_MIN_REVIEWS', 20),
            // Not in the FR text — a documented implementation choice,
            // same shape as 005/006's own arbitrary-but-recorded constants.
            'rating_shift_threshold' => (float) env('MODERATION_RATING_SHIFT_THRESHOLD', 1.5),
            'rating_shift_min_reviews' => (int) env('MODERATION_RATING_SHIFT_MIN_REVIEWS', 10),
            'new_account_cluster_min_reviews' => (int) env('MODERATION_NEW_ACCOUNT_CLUSTER_MIN_REVIEWS', 5),
            'new_account_cluster_account_age_days' => (int) env('MODERATION_NEW_ACCOUNT_CLUSTER_ACCOUNT_AGE_DAYS', 7),
            'max_freeze_hours' => (int) env('MODERATION_MAX_FREEZE_HOURS', 72),
        ],
    ],

];
