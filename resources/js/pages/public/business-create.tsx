import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Category {
    id: number;
    name: string;
    is_system: boolean;
}

interface CreateBusinessForm {
    domain: string;
    name: string;
    country: string;
    city: string;
    category_id: string;
}

export default function BusinessCreate({
    categories,
    countries,
    duplicate_suggestion: duplicateSuggestion,
}: {
    categories: Category[];
    countries: Record<string, string>;
    duplicate_suggestion: { slug: string; name: string } | null;
}) {
    const [hasNoWebsite, setHasNoWebsite] = useState(false);
    const { data, setData, post, processing, errors } = useForm<CreateBusinessForm>({
        domain: '',
        name: '',
        country: '',
        city: '',
        category_id: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('businesses.store'));
    };

    return (
        <>
            <Head title="Add a business" />

            <main className="mx-auto max-w-lg px-4 py-12">
                <h1 className="text-2xl font-semibold">Add a business</h1>
                <p className="mt-1 text-sm text-neutral-600">Can't find it? Add it and you'll be the first to review it.</p>

                {duplicateSuggestion && (
                    <div className="mt-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        We think this might already be listed:{' '}
                        <Link href={route('businesses.show', duplicateSuggestion.slug)} className="font-medium underline">
                            {duplicateSuggestion.name}
                        </Link>
                        . If that's not the same business, adjust the details below and try again.
                    </div>
                )}
                <InputError message={(errors as Record<string, string>).duplicate} />

                <form className="mt-6 flex flex-col gap-6" onSubmit={submit}>
                    <div className="flex items-start space-x-3">
                        <Checkbox
                            id="no_website"
                            checked={hasNoWebsite}
                            onCheckedChange={(checked) => {
                                setHasNoWebsite(checked === true);
                                setData('domain', '');
                            }}
                        />
                        <Label htmlFor="no_website" className="font-normal">
                            This business doesn't have a website
                        </Label>
                    </div>

                    {!hasNoWebsite ? (
                        <div className="grid gap-2">
                            <Label htmlFor="domain">Website domain</Label>
                            <Input
                                id="domain"
                                type="text"
                                autoFocus
                                placeholder="e.g. skyhop-travel.com"
                                value={data.domain}
                                onChange={(e) => setData('domain', e.target.value)}
                            />
                            <InputError message={errors.domain} />
                        </div>
                    ) : (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Business name</Label>
                                <Input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="country">Country</Label>
                                <Select value={data.country} onValueChange={(value) => setData('country', value)}>
                                    <SelectTrigger id="country" className="w-full">
                                        <SelectValue placeholder="Select a country" />
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
                                <Label htmlFor="city">City</Label>
                                <Input id="city" type="text" value={data.city} onChange={(e) => setData('city', e.target.value)} />
                                <InputError message={errors.city} />
                            </div>
                        </>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="category_id">Category</Label>
                        <Select value={data.category_id} onValueChange={(value) => setData('category_id', value)}>
                            <SelectTrigger id="category_id" className="w-full">
                                <SelectValue placeholder="Select a category" />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem key={category.id} value={String(category.id)}>
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.category_id} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Add business
                    </Button>
                </form>
            </main>
        </>
    );
}
