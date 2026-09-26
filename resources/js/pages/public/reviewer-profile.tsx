import { Head } from '@inertiajs/react';
import { ReviewCard, type ReviewCardData } from '@/components/review-card';

interface Reviewer {
    id: number;
    name: string;
    avatar_path: string | null;
    country: string | null;
    member_since: string;
    reviews_count: number;
    reviews: ReviewCardData[];
}

export default function ReviewerProfile({ reviewer }: { reviewer: Reviewer }) {
    const memberSince = new Intl.DateTimeFormat('en-GB', { year: 'numeric', month: 'long' }).format(new Date(reviewer.member_since));

    return (
        <>
            <Head title={reviewer.name} />

            <main className="mx-auto max-w-2xl px-4 py-12">
                <div className="flex items-center gap-4">
                    {reviewer.avatar_path ? (
                        <img src={reviewer.avatar_path} alt="" className="h-16 w-16 rounded-full object-cover" />
                    ) : (
                        <div
                            aria-hidden="true"
                            className="flex h-16 w-16 items-center justify-center rounded-full bg-neutral-200 text-xl font-semibold text-neutral-600"
                        >
                            {reviewer.name.charAt(0).toUpperCase()}
                        </div>
                    )}

                    <div>
                        <h1 className="text-2xl font-semibold">{reviewer.name}</h1>
                        <p className="text-sm text-neutral-600">
                            {reviewer.country ? `${reviewer.country} · ` : ''}
                            Member since {memberSince}
                        </p>
                    </div>
                </div>

                <section className="mt-8">
                    <h2 className="text-lg font-medium">{reviewer.reviews_count} published reviews</h2>

                    {reviewer.reviews.length === 0 ? (
                        <p className="mt-2 text-sm text-neutral-600">No published reviews yet.</p>
                    ) : (
                        <div className="mt-2 space-y-4">
                            {reviewer.reviews.map((review) => (
                                <ReviewCard key={review.id} review={review} showAuthor={false} showBusiness />
                            ))}
                        </div>
                    )}
                </section>
            </main>
        </>
    );
}
