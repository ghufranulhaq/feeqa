<?php

namespace App\Models;

use Database\Factories\BusinessTransactionRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FR-004-12: a hashed transaction record a business supplies for reference
 * matching (FR-004-13). Plaintext references and emails never reach this
 * table — only their HMAC hashes (TransactionRecordHash) do.
 */
class BusinessTransactionRecord extends Model
{
    /** @use HasFactory<BusinessTransactionRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'business_id',
        'reference_hash',
        'email_hash',
        'transaction_date',
        'skus',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'skus' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
