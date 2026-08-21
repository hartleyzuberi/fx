import { Head, Link } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import {
    ArrowRight,
    CheckCircle2,
    CircleDashed,
    Compass,
    LockKeyhole,
    RotateCcw,
    Search,
    ShieldCheck,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    const sessions = useMemo(
        () => phases.flatMap((phase) => phase.sessions),
        [phases],
    );
    const mastered = sessions.filter((session) =>
        ['passed', 'mastered'].includes(session.status),
    ).length;
    const reviewCount = sessions.filter(
        (session) => session.status === 'needs_review',
    ).length;
    const nextSession =
        sessions.find((session) => session.status === 'in_progress') ??
        sessions.find((session) => session.status === 'available') ??
        null;
    const percent = sessions.length
        ? Math.round((mastered / sessions.length) * 100)
        : 0;

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
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8">
                <header className="grid gap-5 rounded-2xl border bg-card p-5 md:grid-cols-[1fr_auto] md:items-end md:p-7">
                    <div className="max-w-3xl">
                        <p className="text-xs font-semibold tracking-[0.18em] text-emerald-700 uppercase dark:text-emerald-300">
                            The complete mastery journey
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight md:text-4xl">
                            Know where you are — and what earns the next step.
                        </h1>
                        <p className="mt-3 leading-7 text-muted-foreground">
                            Every chapter is visible. Progression is not based on
                            clicking Continue: lessons, mastery checks and practical
                            evidence determine what unlocks next.
                        </p>
                    </div>
                    {nextSession && (
                        <Button asChild size="lg">
                            <Link href={`/course/${nextSession.slug}`}>
                                Continue learning <ArrowRight />
                            </Link>
                        </Button>
                    )}
                </header>

                <section className="grid gap-4 md:grid-cols-3">
                    <MetricCard
                        label="Course progress"
                        value={`${percent}%`}
                        detail={`${mastered} of ${sessions.length} sessions passed or mastered`}
                    />
                    <MetricCard
                        label="Needs review"
                        value={String(reviewCount)}
                        detail={
                            reviewCount > 0
                                ? 'Return to weak concepts before they compound.'
                                : 'No sessions are currently flagged for review.'
                        }
                        attention={reviewCount > 0}
                    />
                    <MetricCard
                        label="Progression rule"
                        value="Evidence"
                        detail="Laravel checks mastery and prerequisites; AI cannot unlock chapters."
                    />
                </section>

                <Card className="border-emerald-200 dark:border-emerald-900">
                    <CardHeader className="gap-2">
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Search className="size-5 text-emerald-600" />
                            Search the course
                        </CardTitle>
                        <p className="text-sm leading-6 text-muted-foreground">
                            Find authoritative lessons and source passages even when
                            every generated-AI provider is unavailable.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={searchCourse}
                            className="flex flex-col gap-2 sm:flex-row"
                        >
                            <Input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="e.g. why margin is not the same as risk"
                                aria-label="Search course"
                            />
                            <Button
                                disabled={searching || query.trim().length < 2}
                            >
                                {searching ? 'Searching…' : 'Search course'}
                            </Button>
                        </form>
                        {searched && (
                            <div className="mt-5 space-y-3">
                                {results.length === 0 ? (
                                    <div className="rounded-xl border border-dashed p-5 text-sm text-muted-foreground">
                                        No mapped course passage matched that wording.
                                        Try a shorter concept or a different phrase.
                                    </div>
                                ) : (
                                    results.map((result) => (
                                        <Link
                                            key={result.id}
                                            href={`/course/${result.slug}`}
                                            className="block rounded-xl border p-4 transition hover:border-emerald-400 hover:bg-emerald-500/5"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <span className="font-medium">
                                                    {result.unit_title}
                                                </span>
                                                <Badge variant="outline">
                                                    {result.relevance}% match
                                                </Badge>
                                            </div>
                                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
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

                <section className="rounded-2xl border bg-muted/20 p-4 md:p-5">
                    <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-muted-foreground">
                        <span className="font-medium text-foreground">
                            Status guide
                        </span>
                        <LegendItem status="in_progress" label="In progress" />
                        <LegendItem status="available" label="Available" />
                        <LegendItem status="needs_review" label="Needs review" />
                        <LegendItem status="mastered" label="Mastered" />
                        <LegendItem status="locked" label="Locked" />
                    </div>
                </section>

                <div className="space-y-5">
                    {phases.map((phase, index) => (
                        <PhaseCard key={phase.id} phase={phase} index={index} />
                    ))}
                </div>
            </main>
        </>
    );
}

function MetricCard({
    label,
    value,
    detail,
    attention = false,
}: {
    label: string;
    value: string;
    detail: string;
    attention?: boolean;
}) {
    return (
        <Card
            className={
                attention
                    ? 'border-amber-300 bg-amber-50/50 dark:border-amber-900 dark:bg-amber-950/20'
                    : undefined
            }
        >
            <CardContent className="pt-5">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </p>
                <p className="mt-2 text-2xl font-semibold">{value}</p>
                <p className="mt-2 text-xs leading-5 text-muted-foreground">
                    {detail}
                </p>
            </CardContent>
        </Card>
    );
}

function PhaseCard({ phase, index }: { phase: Phase; index: number }) {
    const complete = phase.sessions.filter((session) =>
        ['passed', 'mastered'].includes(session.status),
    ).length;
    const percent = phase.sessions.length
        ? Math.round((complete / phase.sessions.length) * 100)
        : 0;

    return (
        <Card className="overflow-hidden">
            <CardHeader className="border-b bg-muted/20">
                <div className="grid gap-4 md:grid-cols-[1fr_240px] md:items-center">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                Phase {index + 1}
                            </span>
                            <StatusBadge status={phase.status} />
                        </div>
                        <CardTitle className="mt-2 text-xl">
                            {phase.title}
                        </CardTitle>
                    </div>
                    <div>
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span>{complete} completed</span>
                            <span>{percent}%</span>
                        </div>
                        <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-emerald-500"
                                style={{ width: `${percent}%` }}
                            />
                        </div>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="grid gap-3 pt-5 md:grid-cols-2 xl:grid-cols-3">
                {phase.sessions.map((session) => (
                    <SessionRow key={session.id} session={session} />
                ))}
            </CardContent>
        </Card>
    );
}

function SessionRow({ session }: { session: Session }) {
    const locked = session.status === 'locked';
    const needsReview = session.status === 'needs_review';
    const content = (
        <div
            className={`group flex h-full gap-3 rounded-xl border p-4 transition ${
                locked
                    ? 'bg-muted/30 text-muted-foreground'
                    : needsReview
                      ? 'border-amber-300 bg-amber-50/40 hover:border-amber-400 dark:border-amber-900 dark:bg-amber-950/20'
                      : 'hover:border-emerald-400 hover:bg-emerald-500/5'
            }`}
        >
            <StatusIcon status={session.status} />
            <div className="min-w-0 flex-1">
                <div className="flex items-start justify-between gap-2">
                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {session.schedule || 'Guided session'}
                    </div>
                    {!locked && (
                        <ArrowRight className="size-3.5 shrink-0 text-muted-foreground opacity-0 transition group-hover:opacity-100" />
                    )}
                </div>
                <div className="mt-1 leading-snug font-medium text-foreground">
                    {session.title}
                </div>
                {locked && session.lockReason && (
                    <p className="mt-2 text-xs leading-5">
                        {session.lockReason}
                    </p>
                )}
                {needsReview && (
                    <p className="mt-2 text-xs leading-5 text-amber-800 dark:text-amber-200">
                        Review this session and submit fresh mastery evidence.
                    </p>
                )}
            </div>
        </div>
    );

    return locked ? content : <Link href={`/course/${session.slug}`}>{content}</Link>;
}

function LegendItem({ status, label }: { status: Status; label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <StatusIcon status={status} />
            {label}
        </span>
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

    if (status === 'in_progress') {
        return <Compass className="mt-0.5 size-4 shrink-0 text-emerald-600" />;
    }

    if (status === 'available') {
        return <CircleDashed className="mt-0.5 size-4 shrink-0 text-sky-600" />;
    }

    return <ShieldCheck className="mt-0.5 size-4 shrink-0 text-emerald-600" />;
}

function StatusBadge({ status }: { status: Status }) {
    return (
        <Badge variant="outline" className="capitalize">
            {status.replace('_', ' ')}
        </Badge>
    );
}
