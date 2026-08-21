import { Head, Link } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import {
    CheckCircle2,
    CircleDashed,
    LockKeyhole,
    RotateCcw,
    Search,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Status =
    | 'locked'
    | 'available'
    | 'in_progress'
    | 'needs_review'
    | 'passed'
    | 'mastered';
type Session = {
    id: string;
    slug: string;
    title: string;
    schedule: string | null;
    status: Status;
    lockReason: string | null;
};
type Phase = { id: string; title: string; status: Status; sessions: Session[] };
type SearchResult = {
    id: string;
    slug: string;
    unit_title: string;
    document: string;
    page: number;
    relevance: number;
    snippet: string;
};

export default function CourseMap({ phases }: { phases: Phase[] }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [searching, setSearching] = useState(false);
    const [searched, setSearched] = useState(false);

    async function searchCourse(event: FormEvent) {
        event.preventDefault();

        if (query.trim().length < 2) {
            return;
        }

        setSearching(true);

        try {
            const response = await fetch(
                `/course-search?q=${encodeURIComponent(query)}`,
                { headers: { Accept: 'application/json' } },
            );
            const data = await response.json();
            setResults(data.results || []);
            setSearched(true);
        } finally {
            setSearching(false);
        }
    }

    return (
        <>
            <Head title="Course map" />
            <main className="mx-auto w-full max-w-6xl p-4 md:p-8">
                <div className="mb-8 max-w-3xl">
                    <p className="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                        THE COMPLETE JOURNEY
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                        Course map
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        See every stage without skipping the evidence required
                        to reach it. Locked sessions show what must happen next.
                    </p>
                </div>
                <Card className="mb-6 border-emerald-200 dark:border-emerald-900">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Search className="size-5" /> Search Course
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Find authoritative lessons and source passages even
                            when every AI provider is off.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={searchCourse} className="flex gap-2">
                            <Input
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="e.g. why margin is not the same as risk"
                                aria-label="Search course"
                            />
                            <Button
                                disabled={searching || query.trim().length < 2}
                            >
                                {searching ? 'Searching…' : 'Search'}
                            </Button>
                        </form>
                        {searched && (
                            <div className="mt-4 space-y-3">
                                {results.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No mapped course passage matched that
                                        wording.
                                    </p>
                                ) : (
                                    results.map((result) => (
                                        <Link
                                            key={result.id}
                                            href={`/course/${result.slug}`}
                                            className="block rounded-lg border p-3 hover:border-emerald-400"
                                        >
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="font-medium">
                                                    {result.unit_title}
                                                </span>
                                                <Badge variant="outline">
                                                    {result.relevance}% match
                                                </Badge>
                                            </div>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {result.snippet}
                                            </p>
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                {result.document}, physical page{' '}
                                                {result.page}
                                            </p>
                                        </Link>
                                    ))
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
                <div className="space-y-5">
                    {phases.map((phase, index) => (
                        <Card key={phase.id}>
                            <CardHeader className="flex-row items-center justify-between">
                                <div>
                                    <div className="text-xs font-medium text-muted-foreground">
                                        PHASE {index + 1}
                                    </div>
                                    <CardTitle className="mt-1 text-xl">
                                        {phase.title}
                                    </CardTitle>
                                </div>
                                <StatusBadge status={phase.status} />
                            </CardHeader>
                            <CardContent className="grid gap-3 md:grid-cols-2">
                                {phase.sessions.map((session) => (
                                    <SessionRow
                                        key={session.id}
                                        session={session}
                                    />
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </main>
        </>
    );
}

function SessionRow({ session }: { session: Session }) {
    const locked = session.status === 'locked';
    const content = (
        <div
            className={`group flex h-full gap-3 rounded-xl border p-4 transition ${locked ? 'bg-muted/30 text-muted-foreground' : 'hover:border-emerald-400 hover:bg-emerald-500/5'}`}
        >
            <StatusIcon status={session.status} />
            <div className="min-w-0">
                <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {session.schedule}
                </div>
                <div className="mt-1 leading-snug font-medium text-foreground">
                    {session.title}
                </div>
                {locked && (
                    <p className="mt-2 text-xs leading-5">
                        {session.lockReason}
                    </p>
                )}
            </div>
        </div>
    );

    return locked ? (
        content
    ) : (
        <Link href={`/course/${session.slug}`}>{content}</Link>
    );
}

function StatusIcon({ status }: { status: Status }) {
    if (status === 'locked') {
        return <LockKeyhole className="mt-0.5 size-4 shrink-0" />;
    }

    if (status === 'mastered' || status === 'passed') {
        return (
            <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600" />
        );
    }

    if (status === 'needs_review') {
        return <RotateCcw className="mt-0.5 size-4 shrink-0 text-amber-600" />;
    }

    return <CircleDashed className="mt-0.5 size-4 shrink-0 text-emerald-600" />;
}
function StatusBadge({ status }: { status: Status }) {
    return (
        <Badge variant="outline" className="capitalize">
            {status.replace('_', ' ')}
        </Badge>
    );
}
