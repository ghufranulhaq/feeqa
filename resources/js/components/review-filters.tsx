import { router } from '@inertiajs/react';

export interface ReviewFiltersState {
    sort: string;
    star_rating: number[];
    source_label: string[];
    has_update: boolean;
    language: string | null;
    date_from: string | null;
    date_to: string | null;
    location: string | null;
}

export interface ReviewFilterLocationOption {
    slug: string;
    name: string;
}

const SOURCE_LABEL_OPTIONS = [
    { value: 'organic', label: 'Organic' },
    { value: 'invited', label: 'Invited' },
    { value: 'redirected', label: 'Redirected' },
];

function toggle<T>(list: T[], value: T): T[] {
    return list.includes(value) ? list.filter((item) => item !== value) : [...list, value];
}

/**
 * FR-003-28. Verified Experience, has-reply, and has-case aren't offered
 * here — the backend accepts them but has nothing to filter on until specs
 * 004, 007, and 010 exist, so a control for them would do nothing.
 */
export function ReviewFilters({
    url,
    filters,
    locations,
}: {
    url: string;
    filters: ReviewFiltersState;
    locations?: ReviewFilterLocationOption[];
}) {
    function apply(next: Partial<ReviewFiltersState>) {
        const merged = { ...filters, ...next };

        router.get(
            url,
            {
                sort: merged.sort !== 'recent' ? merged.sort : undefined,
                star_rating: merged.star_rating.length > 0 ? merged.star_rating : undefined,
                source_label: merged.source_label.length > 0 ? merged.source_label : undefined,
                has_update: merged.has_update ? '1' : undefined,
                language: merged.language || undefined,
                date_from: merged.date_from || undefined,
                date_to: merged.date_to || undefined,
                location: merged.location || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <div className="mt-4 flex flex-wrap items-end gap-4 rounded border border-neutral-200 p-4 text-sm">
            <label className="flex flex-col gap-1">
                <span className="text-neutral-600">Sort by</span>
                <select
                    value={filters.sort}
                    onChange={(event) => apply({ sort: event.target.value })}
                    className="rounded border border-neutral-300 px-2 py-1"
                >
                    <option value="recent">Most recent</option>
                    <option value="useful">Most useful</option>
                </select>
            </label>

            <fieldset className="flex flex-col gap-1">
                <legend className="text-neutral-600">Star rating</legend>
                <div className="flex gap-2">
                    {[1, 2, 3, 4, 5].map((rating) => (
                        <label key={rating} className="flex items-center gap-1">
                            <input
                                type="checkbox"
                                checked={filters.star_rating.includes(rating)}
                                onChange={() => apply({ star_rating: toggle(filters.star_rating, rating) })}
                            />
                            {rating}
                        </label>
                    ))}
                </div>
            </fieldset>

            <fieldset className="flex flex-col gap-1">
                <legend className="text-neutral-600">Source</legend>
                <div className="flex gap-2">
                    {SOURCE_LABEL_OPTIONS.map((option) => (
                        <label key={option.value} className="flex items-center gap-1">
                            <input
                                type="checkbox"
                                checked={filters.source_label.includes(option.value)}
                                onChange={() => apply({ source_label: toggle(filters.source_label, option.value) })}
                            />
                            {option.label}
                        </label>
                    ))}
                </div>
            </fieldset>

            <label className="flex items-center gap-2">
                <input type="checkbox" checked={filters.has_update} onChange={(event) => apply({ has_update: event.target.checked })} />
                Has an update
            </label>

            <label className="flex flex-col gap-1">
                <span className="text-neutral-600">Experienced from</span>
                <input
                    type="date"
                    value={filters.date_from ?? ''}
                    onChange={(event) => apply({ date_from: event.target.value || null })}
                    className="rounded border border-neutral-300 px-2 py-1"
                />
            </label>

            <label className="flex flex-col gap-1">
                <span className="text-neutral-600">Experienced to</span>
                <input
                    type="date"
                    value={filters.date_to ?? ''}
                    onChange={(event) => apply({ date_to: event.target.value || null })}
                    className="rounded border border-neutral-300 px-2 py-1"
                />
            </label>

            {locations && locations.length > 0 && (
                <label className="flex flex-col gap-1">
                    <span className="text-neutral-600">Location</span>
                    <select
                        value={filters.location ?? ''}
                        onChange={(event) => apply({ location: event.target.value || null })}
                        className="rounded border border-neutral-300 px-2 py-1"
                    >
                        <option value="">All locations</option>
                        {locations.map((location) => (
                            <option key={location.slug} value={location.slug}>
                                {location.name}
                            </option>
                        ))}
                    </select>
                </label>
            )}
        </div>
    );
}
