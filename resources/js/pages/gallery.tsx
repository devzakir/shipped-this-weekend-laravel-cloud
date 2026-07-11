import { Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { EntryCard } from '@/components/entry-card';
import type { Entry } from '@/types/entry';

interface Props {
    entries: Entry[];
    tab: 'top' | 'new';
}

export default function Gallery({ entries, tab }: Props) {
    const pending = entries.some((e) => e.has_pending_shot);

    useEffect(() => {
        if (!pending) return;
        const id = setInterval(() => router.reload({ only: ['entries'] }), 3000);
        return () => clearInterval(id);
    }, [pending]);

    return (
        <>
            <Head title="Shipped This Weekend" />
            <div className="mx-auto min-h-screen max-w-6xl px-4 py-10">
                <header className="mb-8 text-center">
                    <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">Shipped on Laravel Cloud this weekend.</h1>
                    <p className="mt-2 text-neutral-500">Paste your <code>laravel.cloud</code> URL. Get a card. Get votes.</p>
                    <Link href="/submit" className="mt-5 inline-block rounded-lg bg-orange-500 px-5 py-2.5 font-medium text-white transition hover:bg-orange-600">
                        Submit your app
                    </Link>
                </header>

                <div className="mb-6 flex justify-center gap-2">
                    <TabLink active={tab === 'top'} href="/?tab=top">Top</TabLink>
                    <TabLink active={tab === 'new'} href="/?tab=new">New</TabLink>
                </div>

                {entries.length === 0 ? (
                    <p className="py-20 text-center text-neutral-400">No entries yet. Be the first.</p>
                ) : (
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {entries.map((e) => <EntryCard key={e.id} entry={e} />)}
                    </div>
                )}
            </div>
        </>
    );
}

function TabLink({ active, href, children }: { active: boolean; href: string; children: React.ReactNode }) {
    return (
        <Link
            href={href}
            className={`rounded-full px-4 py-1.5 text-sm font-medium transition ${active ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800'}`}
        >
            {children}
        </Link>
    );
}
