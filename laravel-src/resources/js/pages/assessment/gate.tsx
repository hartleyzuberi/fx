import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    ClipboardCheck,
    ShieldAlert,
    ShieldCheck,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Option = { id: string; text: string };
type Question = {
    id: string;
    position: number;
    prompt: string;
    type: string;
    choices: Option[] | null;
};

export default function GateAssessment({
    unit,
    assessment,
    latestAttempt,
}: {
    unit: { slug: string; title: string };
    assessment: {
        title: string;
        passingScore: number;
        mode: 'deterministic' | 'manual_review';
        questions: Question[];
    };
    latestAttempt: {
        status: string;
        score: number | null;
        passed: boolean | null;
        summary: {
            feedback?: string;
            advanced?: boolean;
            reasons?: string[];
            course_completed?: boolean;
        };
    } | null;
}) {
    const deterministic = assessment.mode === 'deterministic';

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
                    <div
                        className={
                            deterministic
                                ? 'flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-300'
                                : 'flex items-center gap-2 text-sm font-medium text-amber-700 dark:text-amber-300'
                        }
                    >
                        {deterministic ? (
                            <ShieldCheck className="size-4" />
                        ) : (
                            <ShieldAlert className="size-4" />
                        )}
                        {deterministic
                            ? 'DETERMINISTIC SELF-STUDY GATE'
                            : 'MANUAL PRACTICAL REVIEW'}
                    </div>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                        {assessment.title}
                    </h1>
                    <p className="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground">
                        {deterministic
                            ? `This gate is graded automatically by Laravel from protected, source-grounded answer keys. No AI or human reviewer is required. The pass threshold is ${assessment.passingScore}%.`
                            : `Answer without copying the source. This legacy gate still requires manual review because no approved deterministic equivalent has been built yet. The pass threshold is ${assessment.passingScore}%.`}
                    </p>
                </header>

                {latestAttempt && (
                    <div className="mt-6 rounded-xl border bg-muted/40 p-4 text-sm">
                        <div className="flex flex-wrap items-center gap-2">
                            {latestAttempt.passed ? (
                                <CheckCircle2 className="size-4 text-emerald-600" />
                            ) : (
                                <ClipboardCheck className="size-4" />
                            )}
                            <strong className="capitalize">
                                {latestAttempt.status.replaceAll('_', ' ')}
                            </strong>
                            {latestAttempt.score !== null && (
                                <Badge variant="outline">
                                    {latestAttempt.score}%
                                </Badge>
                            )}
                            {latestAttempt.passed !== null && (
                                <Badge variant="outline">
                                    {latestAttempt.passed
                                        ? 'Passed'
                                        : 'Not passed'}
                                </Badge>
                            )}
                        </div>
                        {latestAttempt.summary.feedback && (
                            <p className="mt-2 text-muted-foreground">
                                {latestAttempt.summary.feedback}
                            </p>
                        )}
                        {latestAttempt.summary.reasons?.map((reason) => (
                            <p
                                key={reason}
                                className="mt-2 text-muted-foreground"
                            >
                                {reason}
                            </p>
                        ))}
                        {latestAttempt.summary.course_completed && (
                            <p className="mt-2 font-medium text-emerald-700 dark:text-emerald-300">
                                All final assessment requirements for this
                                course path are complete.
                            </p>
                        )}
                    </div>
                )}

                <Form
                    action={`/course/${unit.slug}/gate`}
                    method="post"
                    className="mt-8 space-y-5"
                >
                    {({ errors, processing }) => (
                        <>
                            {assessment.questions.map((question, index) => (
                                <section
                                    key={question.id}
                                    className="block rounded-xl border bg-card p-5"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="text-xs font-medium text-muted-foreground">
                                            QUESTION {index + 1} OF{' '}
                                            {assessment.questions.length}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className="capitalize"
                                        >
                                            {question.type.replaceAll('_', ' ')}
                                        </Badge>
                                    </div>
                                    <h2 className="mt-2 block leading-6 font-medium">
                                        {question.prompt}
                                    </h2>

                                    {deterministic ? (
                                        <GateStructuredInput
                                            question={question}
                                        />
                                    ) : (
                                        <textarea
                                            name={`answers[${question.id}]`}
                                            required
                                            rows={4}
                                            maxLength={10000}
                                            className="mt-4 w-full rounded-md border bg-background p-3 text-sm"
                                            placeholder="Explain and cite your practical evidence…"
                                        />
                                    )}
                                    <p className="mt-2 text-xs text-destructive">
                                        {errors[`answers.${question.id}`]}
                                    </p>
                                </section>
                            ))}
                            <Button
                                type="submit"
                                size="lg"
                                disabled={processing}
                            >
                                {deterministic
                                    ? 'Submit gate for automatic grading'
                                    : 'Submit gate for review'}
                            </Button>
                        </>
                    )}
                </Form>
            </main>
        </>
    );
}

function GateStructuredInput({ question }: { question: Question }) {
    const field = `answers[${question.id}]`;
    const options = Array.isArray(question.choices) ? question.choices : [];

    if (
        [
            'single_choice',
            'scenario_choice',
            'misconception_choice',
            'true_false',
        ].includes(question.type)
    ) {
        const rendered =
            question.type === 'true_false' && options.length === 0
                ? [
                      { id: 'true', text: 'True' },
                      { id: 'false', text: 'False' },
                  ]
                : options;

        return (
            <fieldset className="mt-4 space-y-2">
                <legend className="sr-only">Choose one answer</legend>
                {rendered.map((option) => (
                    <label
                        key={option.id}
                        className="flex cursor-pointer items-start gap-3 rounded-lg border p-3 hover:bg-muted/40"
                    >
                        <input
                            type="radio"
                            name={field}
                            value={option.id}
                            required
                            className="mt-1 size-4"
                        />
                        <span className="text-sm leading-6">{option.text}</span>
                    </label>
                ))}
            </fieldset>
        );
    }

    if (question.type === 'numeric' || question.type === 'calculation') {
        return (
            <input
                type="number"
                step="any"
                inputMode="decimal"
                name={field}
                required
                className="mt-4 w-full rounded-md border bg-background px-3 py-2 text-sm"
                placeholder="Enter the numerical answer"
            />
        );
    }

    return (
        <div className="mt-4 rounded-lg border border-destructive/50 bg-destructive/5 p-3 text-sm text-destructive">
            This structured gate question type is not supported by the current
            gate renderer.
        </div>
    );
}
