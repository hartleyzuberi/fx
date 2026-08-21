import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    ClipboardList,
    Dumbbell,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Assignment = {
    id: string;
    title: string;
    instructions: string;
    requirements: {
        source_page?: number;
        evidence_key?: string;
        attachment_required?: boolean;
    };
    evidenceMode: boolean;
    requiredObservations: number | null;
    observationCount: number;
    latestSubmission: {
        revision: number;
        status: string;
        response: { text?: string };
    } | null;
};

export default function Exercises({
    unit,
    assignments,
}: {
    unit: { slug: string; title: string };
    assignments: Assignment[];
}) {
    return (
        <>
            <Head title={`${unit.title} exercises`} />
            <main className="mx-auto w-full max-w-4xl p-4 md:p-8">
                <Link
                    href={`/course/${unit.slug}`}
                    className="inline-flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <ArrowLeft className="size-4" />
                    Return to session
                </Link>
                <header className="mt-7">
                    <div className="flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-300">
                        <Dumbbell className="size-4" />
                        SOURCE-DERIVED PRACTICE
                    </div>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                        {unit.title} exercises
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Ordinary responses are append-only revisions. Gate
                        evidence is recorded as separate observations so a
                        required sample such as 100 charts cannot be replaced
                        by a single text claim.
                    </p>
                </header>
                {assignments.length === 0 ? (
                    <div className="mt-8 rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        This chapter has no separate source exercise set.
                    </div>
                ) : (
                    <div className="mt-8 space-y-5">
                        {assignments.map((assignment, index) => {
                            const required = assignment.requiredObservations ?? 0;
                            const complete =
                                assignment.evidenceMode &&
                                required > 0 &&
                                assignment.observationCount >= required;

                            return (
                                <article
                                    key={assignment.id}
                                    className="rounded-xl border bg-card p-5 md:p-6"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="max-w-2xl">
                                            <div className="text-xs font-medium text-muted-foreground">
                                                EXERCISE {index + 1}
                                                {assignment.requirements.source_page
                                                    ? ` · SOURCE PAGE ${assignment.requirements.source_page}`
                                                    : ''}
                                            </div>
                                            <h2 className="mt-2 leading-6 font-semibold">
                                                {assignment.title}
                                            </h2>
                                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                                {assignment.instructions}
                                            </p>
                                        </div>
                                        {assignment.evidenceMode ? (
                                            <Badge
                                                variant={
                                                    complete
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                className="gap-1"
                                            >
                                                {complete ? (
                                                    <CheckCircle2 className="size-3" />
                                                ) : (
                                                    <ClipboardList className="size-3" />
                                                )}
                                                {assignment.observationCount}/
                                                {required} observations
                                            </Badge>
                                        ) : (
                                            assignment.latestSubmission && (
                                                <Badge className="gap-1">
                                                    <CheckCircle2 className="size-3" />
                                                    Revision{' '}
                                                    {
                                                        assignment
                                                            .latestSubmission
                                                            .revision
                                                    }
                                                </Badge>
                                            )
                                        )}
                                    </div>

                                    {assignment.evidenceMode && required > 0 && (
                                        <div className="mt-4" aria-label="Evidence progress">
                                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full bg-foreground transition-[width]"
                                                    style={{
                                                        width: `${Math.min(
                                                            100,
                                                            (assignment.observationCount /
                                                                required) *
                                                                100,
                                                        )}%`,
                                                    }}
                                                />
                                            </div>
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                {complete
                                                    ? 'Minimum evidence count reached. Keep records accurate; later gate checks still test the underlying concepts and other required predicates.'
                                                    : `${required - assignment.observationCount} more observation${required - assignment.observationCount === 1 ? '' : 's'} required for this evidence predicate.`}
                                            </p>
                                        </div>
                                    )}

                                    <Form
                                        action={`/course/${unit.slug}/exercises/${assignment.id}`}
                                        method="post"
                                        encType="multipart/form-data"
                                        resetOnSuccess
                                        className="mt-5 space-y-3"
                                    >
                                        <textarea
                                            name="response"
                                            required
                                            rows={4}
                                            maxLength={30000}
                                            defaultValue={
                                                assignment.evidenceMode
                                                    ? ''
                                                    : assignment.latestSubmission
                                                          ?.response.text || ''
                                            }
                                            className="w-full rounded-md border bg-background p-3 text-sm"
                                            placeholder={
                                                assignment.evidenceMode
                                                    ? 'Record this observation and the evidence you actually completed…'
                                                    : 'Write your response or describe the evidence you completed…'
                                            }
                                        />
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div>
                                                <input
                                                    type="file"
                                                    name="attachment"
                                                    required={
                                                        assignment.requirements
                                                            .attachment_required
                                                    }
                                                    accept="image/jpeg,image/png,image/webp,application/pdf,text/csv"
                                                    className="text-xs"
                                                />
                                                {assignment.requirements
                                                    .attachment_required && (
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        An attachment is required
                                                        for each observation in
                                                        this evidence set.
                                                    </p>
                                                )}
                                            </div>
                                            <Button type="submit" size="sm">
                                                {assignment.evidenceMode
                                                    ? 'Add evidence observation'
                                                    : 'Save new revision'}
                                            </Button>
                                        </div>
                                    </Form>
                                </article>
                            );
                        })}
                    </div>
                )}
            </main>
        </>
    );
}
