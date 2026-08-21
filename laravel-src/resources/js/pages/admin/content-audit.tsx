import { Form, Head } from '@inertiajs/react';
import {
    AlertTriangle,
    Bot,
    CheckCircle2,
    FileCheck2,
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
    coverage: Record<string, number | boolean>;
    reviewCounts: Record<string, number>;
    documents: Array<{
        key: string;
        title: string;
        is_canonical: boolean;
        version_label: string;
        physical_page_count: number;
        file_sha256: string;
    }>;
    conflicts: Array<{
        id: string;
        conflict_type: string;
        description: string;
    }>;
    usage: {
        requests: number;
        inputTokens: number;
        outputTokens: number;
        estimatedCostUsd: number;
    };
    flags: Array<{ key: string; enabled: boolean }>;
    pendingGateReviews: Array<{
        id: string;
        title: string;
        unit_title: string;
        learner_name: string;
        passing_score: number;
        submitted_at: string;
    }>;
};

export default function ContentAudit({
    coverage,
    reviewCounts,
    documents,
    conflicts,
    usage,
    flags,
    pendingGateReviews,
}: Props) {
    const passing = Boolean(coverage.passes_machine_coverage);
    const parity = Boolean(coverage.passes_structured_parity);

    return (
        <>
            <Head title="Content audit" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8">
                <header>
                    <p className="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                        ADMINISTRATION
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                        Content integrity and operations
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Machine mapping, editorial review and conflict
                        resolution remain deliberately separate.
                    </p>
                </header>
                <section className="grid gap-4 md:grid-cols-4">
                    <Metric
                        icon={FileCheck2}
                        label="Physical pages"
                        value={String(coverage.source_pages)}
                    />
                    <Metric
                        icon={CheckCircle2}
                        label="Mapped segments"
                        value={`${coverage.coverage_percent}%`}
                    />
                    <Metric
                        icon={ShieldCheck}
                        label="Editorially approved"
                        value={String(reviewCounts.approved || 0)}
                    />
                    <Metric
                        icon={Bot}
                        label="AI requests"
                        value={String(usage.requests)}
                    />
                </section>
                <Card
                    className={
                        passing ? 'border-emerald-300' : 'border-red-400'
                    }
                >
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            {passing ? (
                                <CheckCircle2 className="size-5 text-emerald-600" />
                            ) : (
                                <AlertTriangle className="size-5 text-red-600" />
                            )}
                            Machine coverage {passing ? 'passes' : 'fails'}
                        </CardTitle>
                        <CardDescription>
                            {coverage.mapped_meaningful_segments} of{' '}
                            {coverage.meaningful_segments} meaningful segments
                            are mapped; {coverage.duplicate_guided_pages_linked}{' '}
                            of {coverage.expected_duplicate_guided_pages}{' '}
                            duplicate guided pages are linked.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="rounded-lg bg-muted p-3 text-sm">
                            <strong>Not an editorial approval claim.</strong>{' '}
                            Current review queue:{' '}
                            {Object.entries(reviewCounts)
                                .map(([key, value]) => `${key}: ${value}`)
                                .join(' · ') || 'empty'}
                            .
                        </div>
                    </CardContent>
                </Card>
                <Card
                    className={parity ? 'border-emerald-300' : 'border-red-400'}
                >
                    <CardHeader>
                        <CardTitle>
                            Structured source parity{' '}
                            {parity ? 'passes' : 'fails'}
                        </CardTitle>
                        <CardDescription>
                            Counts are checked against the supplied canonical
                            course during every audit.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-3 text-sm md:grid-cols-5">
                        {[
                            ['Chapters', coverage.chapter_units],
                            ['Quiz questions', coverage.chapter_quiz_questions],
                            ['Exercises', coverage.chapter_exercises],
                            ['Gate questions', coverage.gate_questions],
                            ['Final exam', coverage.final_exam_questions],
                            ['Appendices', coverage.appendix_units],
                            ['External URLs', coverage.external_urls],
                            ['Books', coverage.book_references],
                            ['Papers', coverage.academic_papers],
                        ].map(([label, value]) => (
                            <div
                                key={String(label)}
                                className="rounded-lg bg-muted p-3"
                            >
                                <div className="text-lg font-semibold">
                                    {String(value)}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {label}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
                <section className="grid gap-5 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Source versions</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {documents.map((doc) => (
                                <div
                                    key={doc.key}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex justify-between gap-3">
                                        <strong className="text-sm">
                                            {doc.title}
                                        </strong>
                                        {doc.is_canonical && (
                                            <Badge>Canonical</Badge>
                                        )}
                                    </div>
                                    <div className="mt-2 text-xs text-muted-foreground">
                                        {doc.physical_page_count} pages ·{' '}
                                        {doc.version_label} · SHA-256{' '}
                                        {doc.file_sha256.slice(0, 16)}…
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Open source conflicts</CardTitle>
                            <CardDescription>
                                Resolve with a written decision; never silently
                                overwrite.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {conflicts.length === 0 ? (
                                <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    No imported open conflicts.
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {conflicts.map((conflict) => (
                                        <div
                                            key={conflict.id}
                                            className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/20 dark:text-amber-100"
                                        >
                                            <strong>
                                                {conflict.conflict_type}
                                            </strong>
                                            <p className="mt-1">
                                                {conflict.description}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </section>
                <Card>
                    <CardHeader>
                        <CardTitle>Practical gate review queue</CardTitle>
                        <CardDescription>
                            Scores are applied server-side against each gate
                            threshold; every decision is audited.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {pendingGateReviews.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                No gate submissions are waiting for review.
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {pendingGateReviews.map((attempt) => (
                                    <Form
                                        key={attempt.id}
                                        action={`/admin/gate-attempts/${attempt.id}`}
                                        method="patch"
                                        className="grid gap-3 rounded-xl border p-4 md:grid-cols-[1fr_110px_2fr_auto] md:items-end"
                                    >
                                        <div className="text-sm">
                                            <strong>{attempt.title}</strong>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                {attempt.learner_name} ·
                                                threshold{' '}
                                                {attempt.passing_score}%
                                            </div>
                                        </div>
                                        <label className="space-y-1 text-xs">
                                            <span>Score</span>
                                            <input
                                                name="score"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                required
                                                className="w-full rounded-md border bg-background p-2"
                                            />
                                        </label>
                                        <label className="space-y-1 text-xs">
                                            <span>Written decision</span>
                                            <input
                                                name="feedback"
                                                required
                                                minLength={5}
                                                className="w-full rounded-md border bg-background p-2"
                                                placeholder="Evidence, strengths, and remediation…"
                                            />
                                        </label>
                                        <Button type="submit" size="sm">
                                            Record review
                                        </Button>
                                    </Form>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>AI feature and cost boundary</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-3">
                        <div className="rounded-lg bg-muted p-3">
                            <div className="text-xl font-semibold">
                                {usage.inputTokens + usage.outputTokens}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                Recorded tokens
                            </div>
                        </div>
                        <div className="rounded-lg bg-muted p-3">
                            <div className="text-xl font-semibold">
                                ${usage.estimatedCostUsd.toFixed(4)}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                Estimated usage cost
                            </div>
                        </div>
                        <div className="rounded-lg bg-muted p-3">
                            <div className="text-sm font-medium">
                                {flags.length
                                    ? flags
                                          .map(
                                              (flag) =>
                                                  `${flag.key}: ${flag.enabled ? 'on' : 'off'}`,
                                          )
                                          .join(', ')
                                    : 'Environment defaults active'}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                Feature flags
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof ShieldCheck;
    label: string;
    value: string;
}) {
    return (
        <Card className="gap-3">
            <CardHeader className="pb-0">
                <Icon className="size-5 text-emerald-600" />
                <CardTitle className="pt-2 text-2xl">{value}</CardTitle>
            </CardHeader>
            <CardContent className="text-xs text-muted-foreground">
                {label}
            </CardContent>
        </Card>
    );
}
