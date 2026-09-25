import { Button } from '@/components/ui/button';

const LABELS: Record<string, string> = {
    google: 'Continue with Google',
    apple: 'Continue with Apple',
    facebook: 'Continue with Facebook',
};

/**
 * FR-001-01, plan D6: a button per provider that has credentials configured
 * — `providers` comes from SocialLoginController::available() and is empty
 * unless keys are set, so nothing renders in the demo by default.
 */
export default function SocialLoginButtons({ providers }: { providers: string[] }) {
    if (providers.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-2">
            {providers.map((provider) => (
                <Button key={provider} type="button" variant="outline" className="w-full" asChild>
                    <a href={`/login/${provider}/redirect`}>{LABELS[provider] ?? `Continue with ${provider}`}</a>
                </Button>
            ))}
        </div>
    );
}
