<?php

namespace App\Actions\Staff;

use App\Models\Business;
use Illuminate\Support\Collection;

final class ImportBusinessesResult
{
    /**
     * @param  Collection<int, Business>  $created
     * @param  list<array{name: string, reason: string}>  $skipped
     */
    public function __construct(
        public readonly Collection $created,
        public readonly array $skipped,
    ) {}
}
