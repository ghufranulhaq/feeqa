import { Head, router, usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/button';

interface Invitation {
    token: string;
    business_name: string;
    role: string;
    inviter_name: string;
    is_for_current_user: boolean;
    already_accepted: boolean;
    expired: boolean;
}

export default function AcceptInvitation({ invitation }: { invitation: Invitation }) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;

    const accept = () => {
        router.post(route('business-invitations.accept', invitation.token));
    };

    return (
        <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4">
            <Head title="Business invitation" />

            <h1 className="text-xl font-semibold">Join {invitation.business_name}</h1>
            <p className="text-muted-foreground mt-2 text-sm">
                {invitation.inviter_name} invited you to join <strong>{invitation.business_name}</strong> as{' '}
                <strong>{invitation.role}</strong>.
            </p>

            {errors.invitation && <p className="mt-4 text-sm text-red-600">{errors.invitation}</p>}

            {invitation.already_accepted ? (
                <p className="mt-4 text-sm">This invitation has already been accepted.</p>
            ) : invitation.expired ? (
                <p className="mt-4 text-sm">This invitation has expired.</p>
            ) : !invitation.is_for_current_user ? (
                <p className="mt-4 text-sm">This invitation was sent to a different email address than the one you're signed in with.</p>
            ) : (
                <Button className="mt-4" onClick={accept}>
                    Accept invitation
                </Button>
            )}
        </div>
    );
}
