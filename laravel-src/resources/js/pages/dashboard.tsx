import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpenCheck,
    Brain,
    Clock3,
    Download,
    Map,
    ShieldCheck,
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
            <main className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-8">
                <header className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <p className="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                            YOUR LEARNING DESK
                        </p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                            Continue with clarity.
                        </h1>
                        <p className="mt-2 text-muted-foreground">
                            {course.title}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/course">
                            <Map />
                            View complete course map
                        </Link>
                    </Button>
                </header>

                <section className="grid gap-4 lg:grid-cols-[1.7fr_1fr]">
                    <Card className="overflow-hidden border-emerald-200 bg-gradient-to-br from-emerald-950 to-slate-950 text-white dark:border-emerald-900">
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <Badge className="bg-emerald-400/20 text-emerald-100">
                                    {currentUnit?.schedule ?? 'Ready to begin'}
                                </Badge>
                                {currentUnit?.estimatedMinutes && (
                                    <span className="flex items-center gap-1 text-xs text-emerald-100">
                                        <Clock3 className="size-3.5" />~
                                        {currentUnit.estimatedMinutes} min
                                    </span>
                                )}
                            </div>
                            <CardTitle className="max-w-2xl pt-5 text-2xl leading-tight">
                                {currentUnit?.title ??
                                    'Your first session is being prepared'}
                            </CardTitle>
                            <CardDescription className="text-emerald-100/80">
                                Read in small steps, answer from memory, and
                                save the ideas that deserve a place in your
                                Concept Notebook.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-3">
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
                                    Resume learning <ArrowRight />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                className="text-white hover:bg-white/10 hover:text-white"
                                asChild
                            >
                                <Link href="/course">See prerequisites</Link>
                            </Button>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Course progress
                            </CardTitle>
                            <CardDescription>
                                Completion and mastery are measured separately.
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
                                />
                                <Metric
                                    label="Needs review"
                                    value={mastery.needsReview}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-4 md:grid-cols-3">
                    <SmallCard
                        icon={BookOpenCheck}
                        title="Today’s priority"
                        text="Complete the current guided concept and explain it back without looking."
                    />
                    <SmallCard
                        icon={Brain}
                        title="Study rhythm"
                        text={`${profile.studyHours} hours per week at a ${profile.pace} pace.`}
                    />
                    <SmallCard
                        icon={ShieldCheck}
                        title="Next gate"
                        text="Chapter 1 requires its notebook work, teach-back and at least 85% on the quiz."
                    />
                </section>

                <div className="rounded-xl border bg-muted/30 p-4 text-sm text-muted-foreground">
                    AI tutor:{' '}
                    <strong className="text-foreground">
                        {profile.aiTutorEnabled ? 'enabled by you' : 'off'}
                    </strong>
                    . Core lessons, calculations, objective grading and
                    progression work without AI. Curriculum version:{' '}
                    {course.version}.
                </div>
                <section className="rounded-xl border bg-card p-5">
                    <div className="flex items-center gap-2">
                        <Download className="size-5 text-emerald-600" />
                        <h2 className="font-semibold">Export your evidence</h2>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Download your own records as CSV. Private records are
                        filtered by your authenticated account.
                    </p>
                    <div className="mt-4 flex flex-wrap gap-2">
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
                </section>
            </main>
        </>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg bg-muted p-3">
            <div className="text-xl font-semibold">{value}</div>
            <div className="text-xs text-muted-foreground">{label}</div>
        </div>
    );
}
function SmallCard({
    icon: Icon,
    title,
    text,
}: {
    icon: typeof Brain;
    title: string;
    text: string;
}) {
    return (
        <Card className="gap-3">
            <CardHeader className="pb-0">
                <Icon className="mb-2 size-5 text-emerald-600" />
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent className="text-sm leading-6 text-muted-foreground">
                {text}
            </CardContent>
        </Card>
    );
}
