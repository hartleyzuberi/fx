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

type Question = {
    id: string;
    position: number;
    type: string;
    prompt: string;
    points: number;
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
                    </div>
                    <h1 className="mt-3 text-3xl font-semibold tracking-tight">
                        {assessment.title}
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Answer from memory first. The tutor will not reveal
                        protected answers during an active attempt.
                    </p>
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
                                        <div className="text-xs font-medium text-muted-foreground">
                                            QUESTION {question.position} OF{' '}
                                            {assessment.questions.length}
                                        </div>
                                        <CardTitle className="text-lg leading-7">
                                            {question.prompt}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <textarea
                                            name={`answers[${question.id}]`}
                                            required
                                            rows={4}
                                            className="w-full rounded-lg border bg-background p-3 text-sm leading-6 outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                                            placeholder="Explain in your own words…"
                                        />
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
                                <p className="mt-2 text-sm text-emerald-800 dark:text-emerald-200">
                                    <strong>Model explanation:</strong>{' '}
                                    {result.explanation}
                                </p>
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
