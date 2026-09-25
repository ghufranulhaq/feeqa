import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AuthLayout from '@/layouts/auth-layout';

interface CompleteProfileForm {
    name: string;
    country: string;
    over_18: boolean;
}

export default function CompleteProfile({ email, countries }: { email: string; countries: Record<string, string> }) {
    const { data, setData, post, processing, errors } = useForm<CompleteProfileForm>({
        name: '',
        country: '',
        over_18: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('signup.complete.store'));
    };

    return (
        <AuthLayout title="Finish creating your account" description={`Almost done, ${email}`}>
            <Head title="Complete your profile" />
            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-2">
                    <Label htmlFor="name">Display name</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autoFocus
                        minLength={2}
                        maxLength={40}
                        autoComplete="nickname"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="What other reviewers will see"
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="country">Country</Label>
                    <Select value={data.country} onValueChange={(value) => setData('country', value)}>
                        <SelectTrigger id="country" className="w-full">
                            <SelectValue placeholder="Select your country" />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(countries).map(([code, name]) => (
                                <SelectItem key={code} value={code}>
                                    {name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.country} />
                </div>

                <div className="grid gap-2">
                    <div className="flex items-start space-x-3">
                        <Checkbox
                            id="over_18"
                            checked={data.over_18}
                            onCheckedChange={(checked) => setData('over_18', checked === true)}
                        />
                        <Label htmlFor="over_18" className="font-normal">
                            I confirm that I am 18 years of age or older
                        </Label>
                    </div>
                    <InputError message={errors.over_18} />
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Create account
                </Button>
            </form>
        </AuthLayout>
    );
}
