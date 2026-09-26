import { Head } from '@inertiajs/react';
import { ReviewCard, type ReviewCardData } from '@/components/review-card';

/**
 * FR-003-29: the permanent URL a single review resolves to.
 */
export default function ReviewPage({ review }: { review: ReviewCardData }) {
    return (
        <>
            <Head title={`${review.title} — ${review.business.name}`} />

            <main className="mx-auto max-w-2xl px-4 py-12">
                <ReviewCard review={review} showBusiness linkToPermanentUrl={false} />
            </main>
        </>
    );
}
