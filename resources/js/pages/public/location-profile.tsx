import { Head, Link } from '@inertiajs/react';
import { ReviewCard, type ReviewCardData } from '@/components/review-card';
import { ReviewFilters, type ReviewFiltersState } from '@/components/review-filters';

interface Address {
    line1: string;
    line2?: string;
    city: string;
    postal_code?: string;
    country: string;
}

interface LocationDetails {
    slug: string;
    name: string;
    address: Address;
    latitude: string | null;
    longitude: string | null;
    phone: string | null;
    hours: Record<string, { open: string; close: string }> | null;
}

interface PendingFeature {
    key: string;
    label: string;
    spec: string;
}

interface ReviewsPage {
    data: ReviewCardData[];
    current_page: number;
    last_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

export default function LocationProfile({
    business,
    location,
    reviews,
    review_filters: reviewFilters,
    pending_features: pendingFeatures,
}: {
    business: { name: string; slug: string };
    location: LocationDetails;
    reviews: ReviewsPage;
    review_filters: ReviewFiltersState;
    pending_features: PendingFeature[];
}) {
    const hasActiveFilters =
        reviewFilters.star_rating.length > 0 ||
        reviewFilters.source_label.length > 0 ||
        reviewFilters.has_update ||
        reviewFilters.language !== null ||
        reviewFilters.date_from !== null ||
        reviewFilters.date_to !== null;
    return (
        <>
            <Head title={`${location.name} — ${business.name}`} />

            <main className="mx-auto max-w-2xl px-4 py-12">
                <p className="text-sm text-neutral-600">
                    <Link href={route('businesses.show', business.slug)} className="underline">
                        {business.name}
                    </Link>
                </p>
                <h1 className="mt-1 text-2xl font-semibold">{location.name}</h1>

                <section className="mt-6 space-y-1 text-sm text-neutral-700">
                    <p>{location.address.line1}</p>
                    {location.address.line2 && <p>{location.address.line2}</p>}
                    <p>
                        {location.address.city}
                        {location.address.postal_code ? `, ${location.address.postal_code}` : ''}
                    </p>
                    <p>{location.address.country}</p>
                    {location.phone && <p>Phone: {location.phone}</p>}
                </section>

                {location.hours && (
                    <section className="mt-8">
                        <h2 className="text-lg font-medium">Opening hours</h2>
                        <ul className="mt-2 space-y-1 text-sm text-neutral-700">
                            {Object.entries(location.hours).map(([day, hours]) => (
                                <li key={day}>
                                    {day}: {hours.open}–{hours.close}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section className="mt-8">
                    <h2 className="text-lg font-medium">Reviews ({reviews.total})</h2>

                    <ReviewFilters url={route('businesses.locations.show', [business.slug, location.slug])} filters={reviewFilters} />

                    {reviews.data.length === 0 ? (
                        <p className="mt-2 text-sm text-neutral-600">
                            {hasActiveFilters ? 'No reviews match these filters.' : 'No published reviews yet.'}
                        </p>
                    ) : (
                        <div className="mt-2 space-y-4">
                            {reviews.data.map((review) => (
                                <ReviewCard key={review.id} review={review} />
                            ))}
                        </div>
                    )}

                    {(reviews.prev_page_url || reviews.next_page_url) && (
                        <div className="mt-4 flex gap-4 text-sm">
                            {reviews.prev_page_url && (
                                <Link href={reviews.prev_page_url} className="underline" preserveScroll>
                                    Previous
                                </Link>
                            )}
                            {reviews.next_page_url && (
                                <Link href={reviews.next_page_url} className="underline" preserveScroll>
                                    Next
                                </Link>
                            )}
                        </div>
                    )}
                </section>

                <section className="mt-8">
                    <h2 className="text-lg font-medium">Coming soon</h2>
                    <ul className="mt-2 space-y-1 text-sm text-neutral-500">
                        {pendingFeatures.map((feature) => (
                            <li key={feature.key}>{feature.label}</li>
                        ))}
                    </ul>
                </section>
            </main>
        </>
    );
}
