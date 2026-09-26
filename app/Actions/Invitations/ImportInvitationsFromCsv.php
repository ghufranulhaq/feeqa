<?php

namespace App\Actions\Invitations;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Invitations\InvitationMethod;
use App\Drivers\Malware\Contracts\MalwareScanner;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * FR-005-01 (csv), FR-005-03: every valid row funnels through T3's
 * `CreateInvitation`, so a hand-typed row gets exactly the same dedup,
 * suppression, and scheduling rules as any other method — it only ever
 * produces `Invited` reviews, verified later through 004's own
 * reference-matching rules rather than anything this action does. A row
 * that collapses into an already-processed recipient (this file's own
 * duplicate, or a recipient already invited in the last 30 days) is not
 * an error: `CreateInvitation` already returns the same row, so it is
 * simply not counted twice (edge case table: "duplicate rows ... collapse
 * to one").
 */
class ImportInvitationsFromCsv
{
    private const MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024;

    private const MAX_ROWS = 50_000;

    private const REQUIRED_COLUMNS = ['recipient_email'];

    public function __construct(
        private readonly CreateInvitation $createInvitation,
        private readonly MalwareScanner $scanner,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Business $business, User $actor, UploadedFile $file): ImportInvitationsFromCsvResult
    {
        if (! $business->userCan($actor, BusinessPermission::SendInvitations)) {
            throw new AuthorizationException('You cannot upload an invitation CSV for this business.');
        }

        $this->guardFile($file);

        $contents = file_get_contents($file->getRealPath());

        if ($contents === false || trim($contents) === '') {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw ValidationException::withMessages(['file' => 'The file must be UTF-8 encoded.']);
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($contents));
        $header = array_map(fn (string $column) => trim($column), str_getcsv(array_shift($lines)));

        foreach (self::REQUIRED_COLUMNS as $column) {
            if (! in_array($column, $header, true)) {
                throw ValidationException::withMessages(['file' => "The file is missing the required column '{$column}'."]);
            }
        }

        $rows = array_values(array_filter($lines, fn (string $line) => trim($line) !== ''));

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'A CSV file can have at most 50,000 rows.']);
        }

        $created = collect();
        $errors = [];
        $seenIds = [];
        $collapsed = 0;

        foreach ($rows as $index => $line) {
            $rowNumber = $index + 2; // header is row 1
            $columns = array_pad(str_getcsv($line), count($header), null);
            $row = array_combine($header, $columns);

            $email = trim((string) ($row['recipient_email'] ?? ''));

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $rowNumber, 'email' => $email !== '' ? $email : null, 'reason' => 'malformed_email'];

                continue;
            }

            $invitation = $this->createInvitation->handle(
                $business,
                InvitationMethod::Csv,
                $this->invitationData($email, $row),
                createdBy: $actor,
            );

            if (in_array($invitation->id, $seenIds, true)) {
                $collapsed++;

                continue;
            }

            $seenIds[] = $invitation->id;
            $created->push($invitation);
        }

        return new ImportInvitationsFromCsvResult($created, $errors, $collapsed);
    }

    /**
     * @param  array<string, ?string>  $row
     * @return array{recipient_email: string, recipient_name?: string, locale?: string, reference?: string, travel_date?: Carbon, product_skus?: list<string>}
     */
    private function invitationData(string $email, array $row): array
    {
        $data = ['recipient_email' => $email];

        foreach (['recipient_name', 'locale', 'reference'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));

            if ($value !== '') {
                $data[$field] = $value;
            }
        }

        $travelDate = trim((string) ($row['travel_date'] ?? ''));

        if ($travelDate !== '') {
            $data['travel_date'] = Carbon::parse($travelDate);
        }

        $skus = trim((string) ($row['product_skus'] ?? ''));

        if ($skus !== '') {
            $data['product_skus'] = array_values(array_filter(array_map('trim', explode(';', $skus))));
        }

        return $data;
    }

    private function guardFile(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw ValidationException::withMessages(['file' => 'The file must be 20 MB or smaller.']);
        }

        $scan = $this->scanner->scan($file->getRealPath());

        if (! $scan->clean) {
            throw ValidationException::withMessages(['file' => 'The file failed a malware scan.']);
        }
    }
}
