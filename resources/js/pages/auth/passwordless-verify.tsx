import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

export default function PasswordlessVerify({ email }: { email: string }) {
    const { data, setData, post, processing, errors } = useForm({ email, code: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login.passwordless.verify.store'));
    };

    return (
        <AuthLayout title="Enter your code" description={`We sent a 6-digit code and a sign-in link to ${email}`}>
            <Head title="Enter your code" />
            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-2">
                    <Label htmlFor="code">6-digit code</Label>
                    <Input
                        id="code"
                        type="text"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        required
                        autoFocus
                        maxLength={6}
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        placeholder="123456"
                    />
                    <InputError message={errors.code} />
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Continue
                </Button>
            </form>
        </AuthLayout>
    );
}
