import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpenCheck,
    Brain,
    CheckCircle2,
    Clock3,
    Download,
    Map,
    NotebookPen,
    Search,
    ShieldCheck,
    Target,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    course: { title: string; version: string };
    currentUnit: {
        title: string;
        slug: string;
        schedule: string | null;
        estimatedMinutes: number | null;
    } | null;
    progress: { completed: number; total: number; percent: number };
    mastery: { mastered: number; needsReview: number };
    profile: { pace: string; studyHours: number; aiTutorEnabled: boolean };
};

export default function Dashboard({
    course,
    currentUnit,
    progress,
    mastery,
    profile,
}: Props) {
    return (
        <>
            <Head title="Learning dashboard" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8">
                <header className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <p className="text-xs font-semibold tracking-[0.18em] text-emerald-700 uppercase dark:text-emerald-300">
                            Forex mastery workspace
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight md:text-4xl">
                            Learn one concept at a time.
                        </h1>
                        <p className="mt-2 max-w-2xl text-muted-foreground">
                            Your course is structured around understanding,
                            recall, practice and evidence — not passive reading.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/review">
                                <Brain />
                                Review queue
                                {mastery.needsReview > 0 && (
                                    <Badge variant="secondary">
                                        {mastery.needsReview}
                                    </Badge>
                                )}
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/course">
                                <Search />
                                Search course
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/course">
                                <Map />
                                Course map
                            </Link>
                        </Button>
                    </div>
                </header>

                <section className="grid gap-4 xl:grid-cols-[1.8fr_1fr]">
                    <Card className="overflow-hidden border-emerald-200 bg-gradient-to-br from-emerald-950 via-emerald-950 to-slate-950 text-white dark:border-emerald-900">
                        <CardHeader className="pb-2">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <Badge className="bg-emerald-400/20 text-emerald-100 hover:bg-emerald-400/20">
                                    {currentUnit?.schedule ?? 'Ready to begin'}
                                </Badge>
                                {currentUnit?.estimatedMinutes && (
                                    <span className="flex items-center gap-1.5 text-xs text-emerald-100/80">
                                        <Clock3 className="size-3.5" />
                                        About {currentUnit.estimatedMinutes} min
                                    </span>
                                )}
                            </div>
                            <p className="mt-5 text-xs font-medium tracking-wide text-emerald-200 uppercase">
                                Continue where you left off
                            </p>
                            <CardTitle className="max-w-3xl pt-1 text-2xl leading-tight md:text-3xl">
                                {currentUnit?.title ??
                                    'Your first guided lesson is ready'}
                            </CardTitle>
                            <CardDescription className="max-w-2xl text-sm leading-6 text-emerald-100/75">
                                Work through the lesson in short concept blocks,
                                explain the idea back in your own words, practise
                                it, then prove mastery before progression.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap items-center gap-3 pt-2">
                            <Button
                                className="bg-emerald-400 text-emerald-950 hover:bg-emerald-300"
                                asChild
                                disabled={!currentUnit}
                            >
                                <Link
                                    href={
                                        currentUnit
                                            ? `/course/${currentUnit.slug}`
                                            : '#'
                                    }
                                >
                                    Resume lesson <ArrowRight />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                className="text-white hover:bg-white/10 hover:text-white"
                                asChild
                            >
                                <Link href="/course">See full journey</Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Course progress
                            </CardTitle>
                            <CardDescription>
                                Completion and mastery are deliberately tracked
                                separately.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-2 flex items-end justify-between">
                                <span className="text-3xl font-semibold">
                                    {progress.percent}%
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {progress.completed} of {progress.total}{' '}
                                    sessions
                                </span>
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full rounded-full bg-emerald-500"
                                    style={{ width: `${progress.percent}%` }}
                                />
                            </div>
                            <div className="mt-5 grid grid-cols-2 gap-3">
                                <Metric
                                    label="Mastered"
                                    value={mastery.mastered}
                                    icon={CheckCircle2}
                                />
                                <Metric
                                    label="Needs review"
                                    value={mastery.needsReview}
                                    icon={Brain}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <section>
                    <div className="mb-3 flex items-end justify-between gap-3">
                        <div>
                            <p className="text-xs font-medium text-muted-foreground uppercase">
                                Today’s study flow
                            </p>
                            <h2 className="mt-1 text-xl font-semibold">
                                Understand → recall → prove
                            </h2>
                        </div>
                        <Badge variant="outline">
                            {profile.studyHours} hrs/week · {profile.pace}
                        </Badge>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <FlowCard
                            number="01"
                            icon={BookOpenCheck}
                            title="Learn actively"
                            text="Read the current concept in small sections and focus on what the idea means, not memorising wording."
                        />
                        <FlowCard
                            number="02"
                            icon={NotebookPen}
                            title="Explain it back"
                            text="Use the Concept Notebook to rewrite the idea in your own language before checking yourself."
                        />
                        <FlowCard
                            number="03"
                            icon={ShieldCheck}
                            title="Prove mastery"
                            text="Complete structured questions, calculations and practical evidence before the next gate unlocks."
                        />
                    </div>
                </section>

                <section className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Target className="size-5 text-emerald-600" />
                                What matters next
                            </CardTitle>
                            <CardDescription>
                                Progress is evidence-driven, not calendar-driven.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            <SmallCard
                                icon={Brain}
                                title="Review queue"
                                text={
                                    mastery.needsReview > 0
                                        ? `${mastery.needsReview} concepts need fresh mastery evidence. Fix the highest-priority misconceptions first.`
                                        : 'No concepts are currently flagged for review.'
                                }
                                href="/review"
                            />
                            <SmallCard
                                icon={ShieldCheck}
                                title="Mastery standard"
                                text="Critical ideas need more than one lucky answer. The course combines multiple evidence points."
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Tutor availability
                            </CardTitle>
                            <CardDescription>
                                AI enhances explanations but never controls your
                                ability to finish the course.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div className="flex items-center justify-between rounded-lg bg-muted/50 p-3">
                                <span>Generated tutor</span>
                                <Badge
                                    variant={
                                        profile.aiTutorEnabled
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {profile.aiTutorEnabled
                                        ? 'Enabled'
                                        : 'Off'}
                                </Badge>
                            </div>
                            <p className="text-xs leading-5 text-muted-foreground">
                                Lessons, course search, notebooks, calculations,
                                structured exams and progression continue to work
                                when every AI provider is unavailable.
                            </p>
                        </CardContent>
                    </Card>
                </section>

                <section className="rounded-2xl border bg-card p-5">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <div className="flex items-center gap-2">
                                <Download className="size-5 text-emerald-600" />
                                <h2 className="font-semibold">
                                    Your learning evidence
                                </h2>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Your notes, quiz history, strategies, backtests
                                and trading journal remain yours and can be
                                exported.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {[
                                ['progress', 'Progress'],
                                ['notes', 'Notes'],
                                ['quiz-history', 'Quiz history'],
                                ['strategies', 'Strategies'],
                                ['backtests', 'Backtests'],
                                ['journal', 'Trade journal'],
                            ].map(([key, label]) => (
                                <Button
                                    key={key}
                                    variant="outline"
                                    size="sm"
                                    asChild
                                >
                                    <a href={`/exports/${key}`}>{label}</a>
                                </Button>
                            ))}
                        </div>
                    </div>
                    <p className="mt-4 text-xs text-muted-foreground">
                        Curriculum version: {course.version} · {course.title}
                    </p>
                </section>
            </main>
        </>
    );
}

function Metric({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: number;
    icon: typeof Brain;
}) {
    return (
        <div className="rounded-xl bg-muted/70 p-3">
            <div className="flex items-center justify-between gap-2">
                <div className="text-2xl font-semibold">{value}</div>
                <Icon className="size-4 text-emerald-600" />
            </div>
            <div className="mt-1 text-xs text-muted-foreground">{label}</div>
        </div>
    );
}

function FlowCard({
    number,
    icon: Icon,
    title,
    text,
}: {
    number: string;
    icon: typeof Brain;
    title: string;
    text: string;
}) {
    return (
        <Card className="gap-3">
            <CardHeader className="pb-0">
                <div className="flex items-center justify-between">
                    <div className="flex size-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                        <Icon className="size-4.5" />
                    </div>
                    <span className="text-xs font-semibold text-muted-foreground">
                        {number}
                    </span>
                </div>
                <CardTitle className="pt-2 text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent className="text-sm leading-6 text-muted-foreground">
                {text}
            </CardContent>
        </Card>
    );
}

function SmallCard({
    icon: Icon,
    title,
    text,
    href,
}: {
    icon: typeof Brain;
    title: string;
    text: string;
    href?: string;
}) {
    const content = (
        <div className="group h-full rounded-xl border p-4 transition hover:border-emerald-400 hover:bg-emerald-500/5">
            <div className="flex items-start justify-between gap-2">
                <Icon className="mb-3 size-5 text-emerald-600" />
                {href && (
                    <ArrowRight className="size-4 text-muted-foreground opacity-0 transition group-hover:opacity-100" />
                )}
            </div>
            <h3 className="text-sm font-semibold">{title}</h3>
            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                {text}
            </p>
        </div>
    );

    return href ? <Link href={href}>{content}</Link> : content;
}
