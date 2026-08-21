import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Dumbbell } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Assignment = {
    id: string;
    title: string;
    instructions: string;
    requirements: { source_page?: number };
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
                        Responses are append-only revisions. Attach evidence
                        where the task involves a chart, worksheet, or external
                        platform.
                    </p>
                </header>
                {assignments.length === 0 ? (
                    <div className="mt-8 rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        This chapter has no separate source exercise set.
                    </div>
                ) : (
                    <div className="mt-8 space-y-5">
                        {assignments.map((assignment, index) => (
                            <article
                                key={assignment.id}
                                className="rounded-xl border bg-card p-5 md:p-6"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div className="text-xs font-medium text-muted-foreground">
                                            EXERCISE {index + 1}
                                            {assignment.requirements.source_page
                                                ? ` · SOURCE PAGE ${assignment.requirements.source_page}`
                                                : ''}
                                        </div>
                                        <h2 className="mt-2 leading-6 font-semibold">
                                            {assignment.instructions}
                                        </h2>
                                    </div>
                                    {assignment.latestSubmission && (
                                        <Badge className="gap-1">
                                            <CheckCircle2 className="size-3" />
                                            Revision{' '}
                                            {
                                                assignment.latestSubmission
                                                    .revision
                                            }
                                        </Badge>
                                    )}
                                </div>
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
                                            assignment.latestSubmission
                                                ?.response.text || ''
                                        }
                                        className="w-full rounded-md border bg-background p-3 text-sm"
                                        placeholder="Write your response or describe the evidence you completed…"
                                    />
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <input
                                            type="file"
                                            name="attachment"
                                            accept="image/jpeg,image/png,image/webp,application/pdf,text/csv"
                                            className="text-xs"
                                        />
                                        <Button type="submit" size="sm">
                                            Save new revision
                                        </Button>
                                    </div>
                                </Form>
                            </article>
                        ))}
                    </div>
                )}
            </main>
        </>
    );
}
