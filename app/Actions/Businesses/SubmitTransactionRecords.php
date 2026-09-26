<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Verification\TransactionRecordHash;
use App\Models\Business;
use App\Models\BusinessTransactionRecord;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * FR-004-12: the "Business API" data shape for submitting hashed
 * transaction records this FR asks for. The actual external,
 * token-authenticated Business API contract is spec 016's job — same
 * relationship spec 005 has with its own bcc/api invitation methods — this
 * is the real upsert path underneath it, reachable today from the business
 * dashboard.
 */
class SubmitTransactionRecords
{
    /**
     * @param  list<array{reference_hash: string, email_hash: string, transaction_date: string, skus?: array<int, string>|null}>  $records
     *
     * @throws AuthorizationException
     */
    public function handle(Business $business, User $actor, array $records): int
    {
        if (! $business->userCan($actor, BusinessPermission::ManageIntegrations)) {
            throw new AuthorizationException('You cannot submit transaction records for this business.');
        }

        if ($records === []) {
            throw ValidationException::withMessages(['records' => 'Please submit at least one record.']);
        }

        $maxPerRequest = (int) config('platform.verification.transaction_records.max_per_request', 10000);

        if (count($records) > $maxPerRequest) {
            throw ValidationException::withMessages([
                'records' => "You can submit at most {$maxPerRequest} records per request.",
            ]);
        }

        foreach ($records as $record) {
            $this->guardHashedNotPlaintext($record);
        }

        $this->guardDailyLimit($business, count($records));

        $now = now();

        $rows = array_map(fn (array $record) => [
            'business_id' => $business->id,
            'reference_hash' => $record['reference_hash'],
            'email_hash' => $record['email_hash'],
            'transaction_date' => Carbon::parse($record['transaction_date'])->toDateString(),
            'skus' => isset($record['skus']) ? json_encode($record['skus']) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $records);

        BusinessTransactionRecord::upsert(
            $rows,
            uniqueBy: ['business_id', 'reference_hash'],
            update: ['email_hash', 'transaction_date', 'skus', 'updated_at'],
        );

        return count($rows);
    }

    /**
     * FR-004-12: "Plaintext references are never required" — a hash this
     * class or a real Business API integration would produce is always a
     * 64-character hex sha256 digest, so anything else in either hash
     * field can only be a plaintext value someone forgot to hash.
     *
     * @param  array{reference_hash: string, email_hash: string}  $record
     *
     * @throws ValidationException
     */
    private function guardHashedNotPlaintext(array $record): void
    {
        if (! TransactionRecordHash::looksHashed($record['reference_hash'])
            || ! TransactionRecordHash::looksHashed($record['email_hash'])) {
            throw ValidationException::withMessages([
                'records' => 'Records must contain hashed references and emails, not plaintext values.',
            ]);
        }
    }

    private function guardDailyLimit(Business $business, int $incomingCount): void
    {
        $maxPerDay = (int) config('platform.verification.transaction_records.max_per_day', 1_000_000);

        $submittedToday = BusinessTransactionRecord::where('business_id', $business->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($submittedToday + $incomingCount > $maxPerDay) {
            throw ValidationException::withMessages([
                'records' => 'This business has reached its daily transaction record submission limit.',
            ]);
        }
    }
}
