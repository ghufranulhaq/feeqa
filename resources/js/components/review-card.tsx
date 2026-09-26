import { Link } from '@inertiajs/react';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

export interface ReviewCardAuthor {
    name: string;
    avatar: string | null;
    country: string | null;
    published_reviews_count: number;
}

export interface ReviewCardBusiness {
    name: string;
    slug: string;
}

export interface ReviewCardQuestionAnswer {
    label: string;
    type: string;
    value: unknown;
}

export interface ReviewCardData {
    id: number;
    url: string;
    business: ReviewCardBusiness;
    author: ReviewCardAuthor;
    star_rating: number;
    title: string;
    text: string;
    date_of_experience: string;
    published_at: string | null;
    source_label: string;
    useful_count: number;
    question_answers: ReviewCardQuestionAnswer[];
}

const dateFormatter = new Intl.DateTimeFormat('en-GB', { dateStyle: 'long' });

function formatDate(value: string): string {
    return dateFormatter.format(new Date(value));
}

/**
 * FR-003-15: the tooltip explaining what each source label means.
 */
const SOURCE_LABEL_DESCRIPTIONS: Record<string, string> = {
    organic: 'Written without any involvement from the business.',
    invited: 'Submitted through a unique invitation tied to a customer record.',
    redirected: "Submitted through the business's generic review link or QR code.",
};

function formatSourceLabel(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function formatAnswer(answer: ReviewCardQuestionAnswer): string {
    if (answer.type === 'yes_no') {
        return answer.value ? 'Yes' : 'No';
    }

    if (answer.type === 'rating_1_5') {
        return `${answer.value} / 5`;
    }

    return String(answer.value);
}

/**
 * FR-003-26. Reply, case summary, and Verified Experience badge stay
 * "coming soon" (007/010/004). Useful count is real (FR-003-27); the
 * tap-to-vote endpoint (`reviews.useful-vote.store`) exists but no button
 * reaches it here yet, same "endpoint before UI" situation as spec 003
 * T5's drafts.
 */
export function ReviewCard({
    review,
    showAuthor = true,
    showBusiness = false,
    linkToPermanentUrl = true,
}: {
    review: ReviewCardData;
    showAuthor?: boolean;
    showBusiness?: boolean;
    linkToPermanentUrl?: boolean;
}) {
    const title = linkToPermanentUrl ? (
        <Link href={review.url} className="underline">
            {review.title}
        </Link>
    ) : (
        review.title
    );

    return (
        <article className="rounded border border-neutral-200 p-4">
            {showAuthor && (
                <div className="flex items-center gap-3">
                    {review.author.avatar ? (
                        <img src={review.author.avatar} alt="" className="h-8 w-8 rounded-full object-cover" />
                    ) : (
                        <div
                            aria-hidden="true"
                            className="flex h-8 w-8 items-center justify-center rounded-full bg-neutral-200 text-sm font-semibold text-neutral-600"
                        >
                            {review.author.name.charAt(0).toUpperCase()}
                        </div>
                    )}
                    <div className="text-sm">
                        <p className="font-medium">{review.author.name}</p>
                        <p className="text-neutral-500">
                            {review.author.country ? `${review.author.country} · ` : ''}
                            {review.author.published_reviews_count} review{review.author.published_reviews_count === 1 ? '' : 's'}
                        </p>
                    </div>
                </div>
            )}

            {showBusiness && (
                <p className="mt-2 text-sm text-neutral-600">
                    <Link href={route('businesses.show', review.business.slug)} className="underline">
                        {review.business.name}
                    </Link>
                </p>
            )}

            <p className="mt-2 text-sm font-medium" aria-label={`${review.star_rating} out of 5 stars`}>
                {'★'.repeat(review.star_rating)}
                {'☆'.repeat(5 - review.star_rating)}
            </p>

            <h3 className="mt-1 font-medium">{title}</h3>
            <p className="mt-1 text-sm whitespace-pre-line text-neutral-700">{review.text}</p>

            <p className="mt-2 text-xs text-neutral-500">
                Experienced {formatDate(review.date_of_experience)}
                {review.published_at && ` · Published ${formatDate(review.published_at)}`}
                {' · '}
                <TooltipProvider delayDuration={0}>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span tabIndex={0} className="underline decoration-dotted">
                                {formatSourceLabel(review.source_label)}
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>{SOURCE_LABEL_DESCRIPTIONS[review.source_label] ?? formatSourceLabel(review.source_label)}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </p>

            <p className="mt-2 text-xs text-neutral-500">
                {review.useful_count} {review.useful_count === 1 ? 'person' : 'people'} found this useful
            </p>

            {review.question_answers.length > 0 && (
                <dl className="mt-3 space-y-1 text-sm">
                    {review.question_answers.map((answer) => (
                        <div key={answer.label} className="flex gap-2">
                            <dt className="text-neutral-500">{answer.label}:</dt>
                            <dd>{formatAnswer(answer)}</dd>
                        </div>
                    ))}
                </dl>
            )}
        </article>
    );
}
