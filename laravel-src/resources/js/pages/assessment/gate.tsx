import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck, ShieldAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Question = { id: string; position: number; prompt: string };

export default function GateAssessment({
    unit,
    assessment,
    latestAttempt,
}: {
    unit: { slug: string; title: string };
    assessment: {
        title: string;
        passingScore: number;
        questions: Question[];
    };
    latestAttempt: {
        status: string;
        score: number | null;
        passed: boolean | null;
        summary: { feedback?: string };
    } | null;
}) {
    return (
        <>
            <Head title={assessment.title} />
            <main className="mx-auto w-full max-w-4xl p-4 md:p-8">
                <Link
                    href={`/course/${unit.slug}`}
                    className="inline-flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <ArrowLeft className="size-4" />
                    Return to session
                </Link>
                <header className="mt-7">
                    <div className="flex items-center gap-2 text-sm font-medium text-amber-700 dark:text-amber-300">
                        <ShieldAlert className="size-4" />
                        MANUAL PRACTICAL REVIEW
                    </div>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                        {assessment.title}
                    </h1>
                    <p className="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground">
                        Answer without copying the source. This gate combines
                        explanations and practical evidence, so submission never
                        auto-unlocks the next stage. A reviewer must apply the
                        Appendix Y framework; the threshold is{' '}
                        {assessment.passingScore}%.
                    </p>
                </header>

                {latestAttempt && (
                    <div className="mt-6 rounded-xl border bg-muted/40 p-4 text-sm">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck className="size-4" />
                            <strong className="capitalize">
                                {latestAttempt.status.replace('_', ' ')}
                            </strong>
                            {latestAttempt.score !== null && (
                                <Badge variant="outline">
                                    {latestAttempt.score}%
                                </Badge>
                            )}
                        </div>
                        {latestAttempt.summary.feedback && (
                            <p className="mt-2 text-muted-foreground">
                                {latestAttempt.summary.feedback}
                            </p>
                        )}
                    </div>
                )}

                <Form
                    action={`/course/${unit.slug}/gate`}
                    method="post"
                    className="mt-8 space-y-5"
                >
                    {assessment.questions.map((question) => (
                        <label
                            key={question.id}
                            className="block rounded-xl border bg-card p-5"
                        >
                            <span className="text-xs font-medium text-muted-foreground">
                                QUESTION {question.position}
                            </span>
                            <span className="mt-2 block leading-6 font-medium">
                                {question.prompt}
                            </span>
                            <textarea
                                name={`answers[${question.id}]`}
                                required
                                rows={4}
                                maxLength={10000}
                                className="mt-4 w-full rounded-md border bg-background p-3 text-sm"
                                placeholder="Explain and cite your practical evidence…"
                            />
                        </label>
                    ))}
                    <Button type="submit" size="lg">
                        Submit gate for review
                    </Button>
                </Form>
            </main>
        </>
    );
}
