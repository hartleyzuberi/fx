import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Brain,
    CheckCircle2,
    Clock3,
    Search,
    Target,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type ReviewItem = {
    id: string;
    conceptId: string;
    concept: string;
    definition: string | null;
    reason: string;
    priority: number;
    dueAt: string;
    feedback: string | null;
    missingConcepts: string[];
    misconceptions: string[];
    gradingStatus: string | null;
    unit: { slug: string; title: string } | null;
};

type Props = {
    items: ReviewItem[];
    summary: { total: number; misconceptions: number; highPriority: number };
};

export default function ReviewQueue({ items, summary }: Props) {
    return (
        <>
            <Head title="Review queue" />
            <main className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-8">
                <header className="grid gap-5 rounded-2xl border bg-card p-5 md:grid-cols-[1fr_auto] md:items-end md:p-7">
                    <div className="max-w-3xl">
                        <p className="text-xs font-semibold tracking-[0.18em] text-amber-700 uppercase dark:text-amber-300">
                            Deliberate review
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight md:text-4xl">
                            Fix weak concepts before they become trading mistakes.
                        </h1>
                        <p className="mt-3 leading-7 text-muted-foreground">
                            This queue is created from your own assessment evidence.
                            Review the lesson, correct the misconception, then submit
                            fresh mastery evidence. AI is not required.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/course">
                            <Search /> Search the course
                        </Link>
                    </Button>
                </header>

                <section className="grid gap-4 sm:grid-cols-3">
                    <Metric label="Open reviews" value={summary.total} icon={Brain} />
                    <Metric
                        label="Misconceptions"
                        value={summary.misconceptions}
                        icon={AlertTriangle}
                        attention={summary.misconceptions > 0}
                    />
                    <Metric
                        label="High priority"
                        value={summary.highPriority}
                        icon={Target}
                        attention={summary.highPriority > 0}
                    />
                </section>

                {items.length === 0 ? (
                    <Card className="border-emerald-200 dark:border-emerald-900">
                        <CardContent className="flex flex-col items-center px-6 py-14 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                                <CheckCircle2 className="size-6" />
                            </div>
                            <h2 className="mt-4 text-xl font-semibold">
                                Your review queue is clear.
                            </h2>
                            <p className="mt-2 max-w-xl text-sm leading-6 text-muted-foreground">
                                Keep studying normally. If a future assessment exposes
                                a weak concept or misconception, it will appear here
                                automatically.
                            </p>
                            <Button className="mt-5" asChild>
                                <Link href="/dashboard">Return to dashboard</Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <section className="space-y-4">
                        <div className="flex items-end justify-between gap-3">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase">
                                    Current priorities
                                </p>
                                <h2 className="mt-1 text-xl font-semibold">
                                    Review highest-risk misunderstandings first
                                </h2>
                            </div>
                            <Badge variant="outline">{items.length} open</Badge>
                        </div>

                        {items.map((item, index) => (
                            <ReviewCard key={item.id} item={item} index={index} />
                        ))}
                    </section>
                )}
            </main>
        </>
    );
}

function ReviewCard({ item, index }: { item: ReviewItem; index: number }) {
    const misconception = item.reason === 'misconception';
    const due = new Date(item.dueAt);
    const dueLabel = Number.isNaN(due.getTime())
        ? item.dueAt
        : due.toLocaleDateString(undefined, {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          });

    return (
        <Card
            className={
                misconception
                    ? 'border-amber-300 dark:border-amber-900'
                    : undefined
            }
        >
            <CardHeader className="gap-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant={misconception ? 'secondary' : 'outline'}>
                            {misconception ? 'Misconception' : 'Needs review'}
                        </Badge>
                        {item.priority >= 90 && (
                            <Badge variant="destructive">High priority</Badge>
                        )}
                    </div>
                    <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <Clock3 className="size-3.5" /> Due {dueLabel}
                    </span>
                </div>
                <div className="flex items-start gap-3">
                    <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground">
                        {index + 1}
                    </span>
                    <div>
                        <CardTitle className="text-lg leading-7">
                            {item.concept}
                        </CardTitle>
                        {item.definition && (
                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                {item.definition}
                            </p>
                        )}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {item.feedback && (
                    <div className="rounded-xl bg-muted/50 p-4">
                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            What the assessment found
                        </p>
                        <p className="mt-2 text-sm leading-6">{item.feedback}</p>
                    </div>
                )}

                {(item.misconceptions.length > 0 ||
                    item.missingConcepts.length > 0) && (
                    <div className="grid gap-3 md:grid-cols-2">
                        {item.misconceptions.length > 0 && (
                            <EvidenceList
                                title="Misconceptions to correct"
                                values={item.misconceptions}
                                attention
                            />
                        )}
                        {item.missingConcepts.length > 0 && (
                            <EvidenceList
                                title="Missing pieces"
                                values={item.missingConcepts}
                            />
                        )}
                    </div>
                )}

                <div className="flex flex-wrap gap-2 border-t pt-4">
                    {item.unit ? (
                        <>
                            <Button asChild>
                                <Link href={`/course/${item.unit.slug}`}>
                                    Review lesson <ArrowRight />
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={`/course/${item.unit.slug}/quiz`}>
                                    Retake mastery check
                                </Link>
                            </Button>
                        </>
                    ) : (
                        <Button variant="outline" asChild>
                            <Link href="/course">Find related lesson</Link>
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function EvidenceList({
    title,
    values,
    attention = false,
}: {
    title: string;
    values: string[];
    attention?: boolean;
}) {
    return (
        <div
            className={`rounded-xl border p-4 ${
                attention
                    ? 'border-amber-200 bg-amber-50/50 dark:border-amber-900 dark:bg-amber-950/20'
                    : ''
            }`}
        >
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {title}
            </p>
            <ul className="mt-2 space-y-1.5 text-sm leading-6">
                {values.map((value) => (
                    <li key={value} className="flex gap-2">
                        <span className="mt-2 size-1.5 shrink-0 rounded-full bg-current" />
                        <span>{value}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function Metric({
    label,
    value,
    icon: Icon,
    attention = false,
}: {
    label: string;
    value: number;
    icon: typeof Brain;
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
            <CardContent className="flex items-center justify-between pt-5">
                <div>
                    <p className="text-2xl font-semibold">{value}</p>
                    <p className="mt-1 text-xs text-muted-foreground">{label}</p>
                </div>
                <Icon className="size-5 text-emerald-600" />
            </CardContent>
        </Card>
    );
}
