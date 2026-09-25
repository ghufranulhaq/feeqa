import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Sessions',
        href: '/settings/sessions',
    },
];

interface SessionRow {
    id: string;
    ip_address: string | null;
    device: string;
    last_active_at: string;
    is_current_device: boolean;
}

export default function Sessions({ sessions }: { sessions: SessionRow[] }) {
    const revoke = (id: string) => {
        router.delete(route('sessions.destroy', id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sessions" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Active sessions" description="Devices currently signed in to your account" />

                    <ul className="divide-border divide-y rounded-lg border">
                        {sessions.map((session) => (
                            <li key={session.id} className="flex items-center justify-between gap-4 p-4">
                                <div>
                                    <p className="text-sm font-medium">
                                        {session.device}
                                        {session.is_current_device && (
                                            <span className="text-muted-foreground ml-2 text-xs">(this device)</span>
                                        )}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {session.ip_address} · last active {new Date(session.last_active_at).toLocaleString('en-GB')}
                                    </p>
                                </div>

                                {!session.is_current_device && (
                                    <Button type="button" variant="outline" size="sm" onClick={() => revoke(session.id)}>
                                        Revoke
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
