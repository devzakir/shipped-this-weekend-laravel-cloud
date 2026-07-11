import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { Entry } from '@/types/entry';

export function EntryCard({ entry }: { entry: Entry }) {
    const [votes, setVotes] = useState(entry.votes_count);
    const [voted, setVoted] = useState(false);

    const vote = () => {
        if (voted) return;
        setVoted(true);
        setVotes((v) => v + 1); // optimistic
        router.post(
            `/entries/${entry.id}/vote`,
            {},
            { preserveScroll: true, only: [], onError: () => { setVoted(false); setVotes((v) => v - 1); } },
        );
    };

    const shot = entry.screenshot_url ?? entry.og_image_url;

    return (
        <div className="group flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm transition hover:shadow-md dark:border-neutral-800 dark:bg-neutral-900">
            <a href={entry.url} target="_blank" rel="noopener noreferrer" className="block aspect-[16/10] overflow-hidden bg-neutral-100 dark:bg-neutral-800">
                {shot ? (
                    <img src={shot} alt={entry.title ?? entry.host} className="h-full w-full object-cover transition group-hover:scale-[1.02]" loading="lazy" />
                ) : (
                    <div className="h-full w-full animate-pulse bg-gradient-to-br from-neutral-200 to-neutral-100 dark:from-neutral-800 dark:to-neutral-700" />
                )}
            </a>
            <div className="flex flex-1 flex-col gap-2 p-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h3 className="truncate font-semibold text-neutral-900 dark:text-neutral-100">{entry.title ?? entry.host}</h3>
                        <p className="truncate text-sm text-neutral-500">{entry.tagline}</p>
                    </div>
                    <button
                        onClick={vote}
                        disabled={voted}
                        className="flex shrink-0 flex-col items-center rounded-lg border border-neutral-200 px-3 py-1.5 text-sm font-medium transition hover:border-orange-400 hover:text-orange-500 disabled:opacity-60 dark:border-neutral-700"
                        aria-label="Upvote"
                    >
                        <span>▲</span>
                        <span>{votes}</span>
                    </button>
                </div>
                <p className="mt-auto text-xs text-neutral-400">by {entry.author_name}</p>
            </div>
        </div>
    );
}
