import { Head } from '@inertiajs/react';

interface AttestationCheck {
    id: string;
    method: string;
    experience_month: string;
    decision_time: string;
    business_name: string;
    signature_valid: boolean;
    revoked: boolean;
    revoked_at: string | null;
    revoked_reason_code: string | null;
}

const METHOD_LABELS: Record<string, string> = {
    transaction_invitation: 'Transaction invitation',
    reference_match: 'Order reference match',
    document_proof: 'Document upload',
    payment_link: 'Payment link',
};

// FR-004-25: what each method checks and what it keeps (P4 transparency).
const METHOD_DESCRIPTIONS: Record<string, string> = {
    transaction_invitation:
        "The review came from an invitation created from a real transaction record the business supplied. We check that the invitation matches the review; we never keep the transaction record itself, only this attestation.",
    reference_match:
        "The reviewer's order reference and verified email were matched, as one-way hashes, against a transaction record the business supplied. We never see either in plain text, and never share them with the business unless the reviewer explicitly agrees to.",
    document_proof:
        'The reviewer uploaded a receipt, invoice, booking confirmation, or e-ticket. We check the merchant, transaction date, and reference against the review, and screen the file for signs of tampering. The file itself is deleted within 30 days of the decision; only this attestation is kept.',
    payment_link: 'The reviewer linked a payment-provider transaction to the review. Not yet available (Phase 2).',
};

function formatMethod(value: string): string {
    return METHOD_LABELS[value] ?? value;
}

const dateFormatter = new Intl.DateTimeFormat('en-GB', { dateStyle: 'long' });
const monthFormatter = new Intl.DateTimeFormat('en-GB', { year: 'numeric', month: 'long' });

/**
 * FR-004-15: the page behind every Verified Experience badge link.
 */
export default function VerificationCheckPage({ attestation }: { attestation: AttestationCheck }) {
    const status = attestation.revoked
        ? 'This attestation has been revoked.'
        : attestation.signature_valid
          ? 'Signature verified — this Verified Experience badge is genuine.'
          : "This attestation's signature could not be verified.";

    return (
        <>
            <Head title="Verified Experience check" />

            <main className="mx-auto max-w-xl px-4 py-12">
                <h1 className="text-2xl font-semibold">Verified Experience check</h1>

                <p role={attestation.revoked || !attestation.signature_valid ? 'alert' : 'status'} className="mt-4 font-medium">
                    {status}
                </p>

                {attestation.revoked && (
                    <p className="mt-1 text-sm text-neutral-600">
                        Revoked on {dateFormatter.format(new Date(attestation.revoked_at as string))}
                        {attestation.revoked_reason_code && ` · Reason: ${attestation.revoked_reason_code}`}
                    </p>
                )}

                <dl className="mt-6 space-y-2 text-sm">
                    <div className="flex justify-between gap-4">
                        <dt className="text-neutral-500">Verified on</dt>
                        <dd>{dateFormatter.format(new Date(attestation.decision_time))}</dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-neutral-500">Method</dt>
                        <dd>{formatMethod(attestation.method)}</dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-neutral-500">Experience month</dt>
                        <dd>{monthFormatter.format(new Date(attestation.experience_month))}</dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-neutral-500">Business</dt>
                        <dd>{attestation.business_name}</dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-neutral-500">Attestation ID</dt>
                        <dd className="font-mono text-xs break-all">{attestation.id}</dd>
                    </div>
                </dl>

                <h2 className="mt-8 text-sm font-medium">What this means</h2>
                <p className="mt-1 text-sm text-neutral-600">{METHOD_DESCRIPTIONS[attestation.method] ?? 'Method details are not available.'}</p>
            </main>
        </>
    );
}
