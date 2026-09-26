import { Head, Link } from '@inertiajs/react';
import { ReviewCard, type ReviewCardData } from '@/components/review-card';
import { ReviewFilters, type ReviewFiltersState } from '@/components/review-filters';

interface Category {
    slug: string;
    name: string;
}

interface LocationSummary {
    slug: string;
    name: string;
    city: string | null;
}

interface Business {
    id: number;
    name: string;
    slug: string;
    logo_path: string | null;
    status: string;
    is_claimed: boolean;
    is_closed: boolean;
    claimed_at: string | null;
    description: string | null;
    website: string | null;
    email: string | null;
    phone: string | null;
    address: Record<string, string> | null;
    social_links: Record<string, string> | null;
    country: string | null;
    primary_category: Category | null;
    secondary_categories: Category[];
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

export default function BusinessProfile({
    business,
    locations,
    mentions,
    reviews,
    review_filters: reviewFilters,
    pending_features: pendingFeatures,
}: {
    business: Business;
    locations: LocationSummary[];
    mentions: ReviewCardData[];
    reviews: ReviewsPage;
    review_filters: ReviewFiltersState;
    pending_features: PendingFeature[];
}) {
    const categories = [business.primary_category, ...business.secondary_categories].filter((category): category is Category => category !== null);

    const hasActiveFilters =
        reviewFilters.star_rating.length > 0 ||
        reviewFilters.source_label.length > 0 ||
        reviewFilters.has_update ||
        reviewFilters.language !== null ||
        reviewFilters.date_from !== null ||
        reviewFilters.date_to !== null ||
        reviewFilters.location !== null;

    return (
        <>
            <Head title={business.name} />

            <main className="mx-auto max-w-2xl px-4 py-12">
                <div className="flex items-center gap-4">
                    {business.logo_path ? (
                        <img src={business.logo_path} alt="" className="h-16 w-16 rounded object-contain" />
                    ) : (
                        <div
                            aria-hidden="true"
                            className="flex h-16 w-16 items-center justify-center rounded bg-neutral-200 text-xl font-semibold text-neutral-600"
                        >
                            {business.name.charAt(0).toUpperCase()}
                        </div>
                    )}

                    <div>
                        <h1 className="text-2xl font-semibold">{business.name}</h1>
                        <p className="text-sm text-neutral-600">
                            {business.is_claimed ? (
                                <span className="font-medium text-emerald-700">Claimed{business.claimed_at ? ` on ${business.claimed_at}` : ''}</span>
                            ) : (
                                <span className="font-medium text-neutral-500">Unclaimed</span>
                            )}
                            {categories.length > 0 && ` · ${categories.map((category) => category.name).join(', ')}`}
                        </p>
                    </div>
                </div>

                {business.is_closed && (
                    <p className="mt-4 rounded border border-neutral-300 bg-neutral-100 px-4 py-3 text-sm font-medium text-neutral-800">Closed</p>
                )}

                {!business.is_claimed && (
                    <p className="mt-4 rounded border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-700">
                        This business has not claimed its profile.
                    </p>
                )}

                <section className="mt-8">
                    <h2 className="text-lg font-medium">About</h2>
                    <p className="mt-2 text-sm text-neutral-700">{business.description ?? 'No description provided.'}</p>
                </section>

                <section className="mt-8 space-y-1 text-sm text-neutral-700">
                    {business.website && <p>Website: {business.website}</p>}
                    {business.email && <p>Email: {business.email}</p>}
                    {business.phone && <p>Phone: {business.phone}</p>}
                    {business.country && <p>Country: {business.country}</p>}
                </section>

                {locations.length > 0 && (
                    <section className="mt-8">
                        <h2 className="text-lg font-medium">Locations</h2>
                        <ul className="mt-2 space-y-1 text-sm text-neutral-700">
                            {locations.map((location) => (
                                <li key={location.slug}>
                                    <Link href={route('businesses.locations.show', [business.slug, location.slug])} className="underline">
                                        {location.name}
                                    </Link>
                                    {location.city && ` · ${location.city}`}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section className="mt-8">
                    <h2 className="text-lg font-medium">Reviews ({reviews.total})</h2>

                    <ReviewFilters url={route('businesses.show', business.slug)} filters={reviewFilters} locations={locations} />

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
                    <h2 className="text-lg font-medium">Mentioned in reviews</h2>
                    {mentions.length === 0 ? (
                        <p className="mt-2 text-sm text-neutral-600">No mentions yet.</p>
                    ) : (
                        <div className="mt-2 space-y-4">
                            {mentions.map((mention) => (
                                <ReviewCard key={mention.id} review={mention} showBusiness />
                            ))}
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
