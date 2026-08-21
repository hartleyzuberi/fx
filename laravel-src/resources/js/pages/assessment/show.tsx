import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    CircleAlert,
    RotateCcw,
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
import { Spinner } from '@/components/ui/spinner';

type Option = { id: string; text: string };
type ChoicePayload =
    | Option[]
    | {
          left?: Option[];
          right?: Option[];
          items?: Option[];
          categories?: Option[];
      }
    | null;

type Question = {
    id: string;
    position: number;
    type: string;
    prompt: string;
    points: number;
    choices: ChoicePayload;
    requiresWorking: boolean;
    structuredFallback: boolean;
};
type Result = {
    prompt: string;
    answer: string;
    status: string;
    feedback: string;
    explanation: string;
};
type Attempt = {
    score: number;
    passed: boolean;
    summary: {
        advanced?: boolean;
        reasons?: string[];
        next_unit_slug?: string | null;
    };
    results: Result[];
};
type Props = {
    unit: { slug: string; title: string };
    assessment: {
        id: string;
        title: string;
        passingScore: number;
        questions: Question[];
    };
    latestAttempt: Attempt | null;
};

export default function AssessmentShow({
    unit,
    assessment,
    latestAttempt,
}: Props) {
    const usingStructuredFallback = assessment.questions.some(
        (question) => question.structuredFallback,
    );

    return (
        <>
            <Head title={assessment.title} />
            <main className="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-8">
                <header>
                    <Link
                        href={`/course/${unit.slug}`}
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        ← Back to session
                    </Link>
                    <div className="mt-5 flex flex-wrap items-center gap-3">
                        <Badge variant="outline">Closed book</Badge>
                        <Badge variant="outline">
                            Pass mark {assessment.passingScore}%
                        </Badge>
                        {usingStructuredFallback && (
                            <Badge variant="secondary">
                                Deterministic mastery mode
                            </Badge>
                        )}
                    </div>
                    <h1 className="mt-3 text-3xl font-semibold tracking-tight">
                        {assessment.title}
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Answer from memory first. Protected answer keys stay on
                        the server during the active attempt.
                    </p>
                    {usingStructuredFallback && (
                        <p className="mt-2 text-sm text-muted-foreground">
                            AI semantic grading is not being used for these
                            questions. The same course objectives are being
                            assessed through deterministic structured checks.
                        </p>
                    )}
                </header>
                {latestAttempt && <AttemptSummary attempt={latestAttempt} />}
                <Form
                    action={`/course/${unit.slug}/quiz`}
                    method="post"
                    disableWhileProcessing
                >
                    {({ processing, errors }) => (
                        <div className="space-y-4">
                            {assessment.questions.map((question) => (
                                <Card key={question.id}>
                                    <CardHeader>
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="text-xs font-medium text-muted-foreground">
                                                QUESTION {question.position} OF{' '}
                                                {assessment.questions.length}
                                            </div>
                                            <Badge
                                                variant="outline"
                                                className="capitalize"
                                            >
                                                {question.type.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Badge>
                                        </div>
                                        <CardTitle className="text-lg leading-7">
                                            {question.prompt}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <QuestionInput question={question} />
                                        <p className="mt-2 text-xs text-destructive">
                                            {errors[`answers.${question.id}`]}
                                        </p>
                                    </CardContent>
                                </Card>
                            ))}
                            <Button type="submit" size="lg" className="w-full">
                                {processing && <Spinner />}Submit all answers
                                for grading
                            </Button>
                            <p className="text-center text-xs text-muted-foreground">
                                Submitting creates an immutable attempt. Retakes
                                add new evidence; they do not overwrite this
                                answer history.
                            </p>
                        </div>
                    )}
                </Form>
            </main>
        </>
    );
}

function QuestionInput({ question }: { question: Question }) {
    const field = `answers[${question.id}]`;

    if (question.type === 'free_response') {
        return (
            <textarea
                name={field}
                required
                rows={4}
                maxLength={5000}
                className="w-full rounded-lg border bg-background p-3 text-sm leading-6 outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                placeholder="Explain in your own words…"
            />
        );
    }

    if (question.type === 'fill_blank') {
        return (
            <input
                name={field}
                required
                maxLength={500}
                className="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                placeholder="Enter your answer"
            />
        );
    }

    if (question.type === 'numeric' || question.type === 'calculation') {
        return (
            <div className="space-y-3">
                <input
                    type="number"
                    step="any"
                    name={field}
                    required
                    inputMode="decimal"
                    className="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                    placeholder="Enter the numerical result"
                />
                {question.requiresWorking && (
                    <p className="text-xs text-muted-foreground">
                        Keep your working in your notebook. The authoritative
                        result is calculated by the backend.
                    </p>
                )}
            </div>
        );
    }

    const options = arrayChoices(question.choices);
    if (
        [
            'single_choice',
            'scenario_choice',
            'misconception_choice',
            'true_false',
        ].includes(question.type)
    ) {
        const renderedOptions =
            question.type === 'true_false' && options.length === 0
                ? [
                      { id: 'true', text: 'True' },
                      { id: 'false', text: 'False' },
                  ]
                : options;

        return (
            <fieldset className="space-y-2">
                <legend className="sr-only">Choose one answer</legend>
                {renderedOptions.map((option) => (
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
                        <span className="text-sm leading-6">
                            {option.text}
                        </span>
                    </label>
                ))}
            </fieldset>
        );
    }

    if (question.type === 'multiple_select') {
        return (
            <fieldset className="space-y-2">
                <legend className="mb-2 text-xs text-muted-foreground">
                    Select every option that applies.
                </legend>
                {options.map((option) => (
                    <label
                        key={option.id}
                        className="flex cursor-pointer items-start gap-3 rounded-lg border p-3 hover:bg-muted/40"
                    >
                        <input
                            type="checkbox"
                            name={`${field}[]`}
                            value={option.id}
                            className="mt-1 size-4"
                        />
                        <span className="text-sm leading-6">
                            {option.text}
                        </span>
                    </label>
                ))}
            </fieldset>
        );
    }

    if (question.type === 'matching') {
        const payload = objectChoices(question.choices);
        const left = payload.left ?? [];
        const right = payload.right ?? [];

        return (
            <div className="space-y-3">
                {left.map((item) => (
                    <label
                        key={item.id}
                        className="grid gap-2 rounded-lg border p-3 md:grid-cols-[1fr_1fr] md:items-center"
                    >
                        <span className="text-sm">{item.text}</span>
                        <select
                            name={`${field}[${item.id}]`}
                            required
                            defaultValue=""
                            className="rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <option value="" disabled>
                                Select match
                            </option>
                            {right.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.text}
                                </option>
                            ))}
                        </select>
                    </label>
                ))}
            </div>
        );
    }

    if (question.type === 'classification') {
        const payload = objectChoices(question.choices);
        const items = payload.items ?? [];
        const categories = payload.categories ?? [];

        return (
            <div className="space-y-3">
                {items.map((item) => (
                    <label
                        key={item.id}
                        className="grid gap-2 rounded-lg border p-3 md:grid-cols-[1fr_1fr] md:items-center"
                    >
                        <span className="text-sm">{item.text}</span>
                        <select
                            name={`${field}[${item.id}]`}
                            required
                            defaultValue=""
                            className="rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <option value="" disabled>
                                Select category
                            </option>
                            {categories.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.text}
                                </option>
                            ))}
                        </select>
                    </label>
                ))}
            </div>
        );
    }

    if (question.type === 'ordering') {
        return (
            <div className="space-y-3">
                <p className="text-xs text-muted-foreground">
                    Assign each item a unique position from 1 to {options.length}.
                </p>
                {options.map((item) => (
                    <label
                        key={item.id}
                        className="grid gap-2 rounded-lg border p-3 md:grid-cols-[1fr_8rem] md:items-center"
                    >
                        <span className="text-sm">{item.text}</span>
                        <select
                            name={`${field}[${item.id}]`}
                            required
                            defaultValue=""
                            className="rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <option value="" disabled>
                                Position
                            </option>
                            {options.map((_, index) => (
                                <option key={index + 1} value={index + 1}>
                                    {index + 1}
                                </option>
                            ))}
                        </select>
                    </label>
                ))}
            </div>
        );
    }

    return (
        <div className="rounded-lg border border-destructive/50 bg-destructive/5 p-3 text-sm text-destructive">
            This question type is not supported by the current learner UI.
        </div>
    );
}

function arrayChoices(choices: ChoicePayload): Option[] {
    return Array.isArray(choices) ? choices : [];
}

function objectChoices(
    choices: ChoicePayload,
): Exclude<ChoicePayload, Option[] | null> {
    return !Array.isArray(choices) && choices ? choices : {};
}

function AttemptSummary({ attempt }: { attempt: Attempt }) {
    const advanced = attempt.summary.advanced;

    return (
        <Card
            className={
                advanced
                    ? 'border-emerald-300 bg-emerald-50/60 dark:border-emerald-900 dark:bg-emerald-950/20'
                    : 'border-amber-300 bg-amber-50/60 dark:border-amber-900 dark:bg-amber-950/20'
            }
        >
            <CardHeader>
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            {advanced ? (
                                <CheckCircle2 className="size-5 text-emerald-600" />
                            ) : (
                                <CircleAlert className="size-5 text-amber-600" />
                            )}
                            Latest attempt: {attempt.score}%
                        </CardTitle>
                        <CardDescription className="mt-2">
                            {advanced
                                ? 'The backend verified every required predicate and unlocked the next session.'
                                : attempt.passed
                                  ? 'Quiz threshold reached; complete the remaining evidence below before progression.'
                                  : 'Review the targeted feedback, then submit a fresh attempt.'}
                        </CardDescription>
                    </div>
                    <Badge variant="outline">
                        {attempt.passed ? 'Quiz passed' : 'Remediation needed'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {attempt.summary.reasons?.map((reason) => (
                    <div key={reason} className="flex gap-2 text-sm">
                        <RotateCcw className="mt-0.5 size-4 shrink-0" />
                        {reason}
                    </div>
                ))}
                <details className="rounded-lg border bg-background/70 p-4">
                    <summary className="cursor-pointer font-medium">
                        Review question-level feedback
                    </summary>
                    <div className="mt-4 space-y-5">
                        {attempt.results.map((result, index) => (
                            <div
                                key={index}
                                className="border-t pt-4 first:border-0 first:pt-0"
                            >
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        className="capitalize"
                                    >
                                        {result.status.replace('_', ' ')}
                                    </Badge>
                                    <span className="text-sm font-medium">
                                        {result.prompt}
                                    </span>
                                </div>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Your answer: {result.answer}
                                </p>
                                <p className="mt-2 text-sm">
                                    {result.feedback}
                                </p>
                                {result.explanation && (
                                    <p className="mt-2 text-sm text-emerald-800 dark:text-emerald-200">
                                        <strong>Course explanation:</strong>{' '}
                                        {result.explanation}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                </details>
                {advanced && attempt.summary.next_unit_slug && (
                    <Button asChild>
                        <Link
                            href={`/course/${attempt.summary.next_unit_slug}`}
                        >
                            Open next session <ArrowRight />
                        </Link>
                    </Button>
                )}
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <ShieldCheck className="size-4" />
                    Progression was decided by stored Laravel rules, not an AI
                    message.
                </div>
            </CardContent>
        </Card>
    );
}
