import { Head, Link } from '@inertiajs/react';

interface Address {
    line1: string;
    line2?: string;
    city: string;
    postal_code?: string;
    country: string;
}

interface LocationDetails {
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

export default function LocationProfile({
    business,
    location,
    pending_features: pendingFeatures,
}: {
    business: { name: string; slug: string };
    location: LocationDetails;
    pending_features: PendingFeature[];
}) {
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
