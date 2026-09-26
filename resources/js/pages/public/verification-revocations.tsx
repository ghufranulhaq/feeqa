import { Head } from '@inertiajs/react';

interface Revocation {
    id: string;
    revoked_at: string;
    revoked_reason_code: string | null;
}

const dateFormatter = new Intl.DateTimeFormat('en-GB', { dateStyle: 'long' });

/**
 * FR-004-17: "recorded in a public revocation list (ID + date + reason
 * category)" — the transparency side of a revoked Verified Experience badge.
 */
export default function VerificationRevocationsPage({ revocations }: { revocations: Revocation[] }) {
    return (
        <>
            <Head title="Revoked Verified Experience attestations" />

            <main className="mx-auto max-w-2xl px-4 py-12">
                <h1 className="text-2xl font-semibold">Revoked attestations</h1>
                <p className="mt-2 text-sm text-neutral-600">
                    Attestations revoked after fraud or another issue was found, listed here for transparency (constitution P4).
                </p>

                {revocations.length === 0 ? (
                    <p className="mt-6 text-sm text-neutral-600">No attestations have been revoked.</p>
                ) : (
                    <table className="mt-6 w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-neutral-200">
                                <th scope="col" className="py-2 font-medium">
                                    Attestation ID
                                </th>
                                <th scope="col" className="py-2 font-medium">
                                    Revoked on
                                </th>
                                <th scope="col" className="py-2 font-medium">
                                    Reason
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {revocations.map((revocation) => (
                                <tr key={revocation.id} className="border-b border-neutral-100">
                                    <td className="py-2 font-mono text-xs break-all">{revocation.id}</td>
                                    <td className="py-2">{dateFormatter.format(new Date(revocation.revoked_at))}</td>
                                    <td className="py-2">{revocation.revoked_reason_code ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </main>
        </>
    );
}
