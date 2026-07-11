import { Head, useForm } from '@inertiajs/react';

export default function Submit() {
    const { data, setData, post, processing, errors } = useForm({
        url: '',
        tagline: '',
        author_name: '',
        x_handle: '',
        website: '', // honeypot
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/entries');
    };

    return (
        <>
            <Head title="Submit Your App — Shipped This Weekend" />
            <div className="mx-auto max-w-lg px-4 py-12">
                <h1 className="mb-6 text-2xl font-bold">Submit your weekend ship</h1>
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Your laravel.cloud URL" error={errors.url}>
                        <input type="url" required value={data.url} onChange={(e) => setData('url', e.target.value)} placeholder="https://yourapp.laravel.cloud" className="input" />
                    </Field>
                    <Field label="Tagline (max 80)" error={errors.tagline}>
                        <input type="text" required maxLength={80} value={data.tagline} onChange={(e) => setData('tagline', e.target.value)} className="input" />
                    </Field>
                    <Field label="Your name" error={errors.author_name}>
                        <input type="text" required value={data.author_name} onChange={(e) => setData('author_name', e.target.value)} className="input" />
                    </Field>
                    <Field label="X handle (optional)" error={errors.x_handle}>
                        <input type="text" value={data.x_handle} onChange={(e) => setData('x_handle', e.target.value)} placeholder="@you" className="input" />
                    </Field>
                    {/* honeypot */}
                    <input type="text" tabIndex={-1} autoComplete="off" value={data.website} onChange={(e) => setData('website', e.target.value)} className="hidden" aria-hidden="true" />
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-orange-500 px-5 py-2.5 font-medium text-white transition hover:bg-orange-600 disabled:opacity-60">
                        {processing ? 'Submitting…' : 'Submit'}
                    </button>
                </form>
            </div>
        </>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-medium">{label}</span>
            {children}
            {error && <span className="mt-1 block text-sm text-red-500">{error}</span>}
        </label>
    );
}
