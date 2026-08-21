import { Form, Head, router } from '@inertiajs/react';
import {
    Bookmark,
    Bot,
    ChevronLeft,
    ChevronRight,
    NotebookPen,
    Quote,
    Send,
    ShieldAlert,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Source = { document: string; page: number; relationship: string };
type Block = {
    id: string;
    type: string;
    title: string | null;
    body: string;
    required: boolean;
    bookmarkId: string | null;
    sources: Source[];
};
type Props = {
    unit: {
        id: string;
        slug: string;
        title: string;
        schedule: string | null;
        estimatedMinutes: number | null;
    };
    blocks: Block[];
    resumeBlockId: string | null;
    status: string;
    gate: { title: string; passingScore: number } | null;
    exerciseCount: number;
};

export default function SessionShow({
    unit,
    blocks,
    resumeBlockId,
    gate,
    exerciseCount,
}: Props) {
    const initial = Math.max(
        0,
        blocks.findIndex((block) => block.id === resumeBlockId),
    );
    const [index, setIndex] = useState(initial);
    const block = blocks[index];
    const progress = blocks.length
        ? Math.round(((index + 1) / blocks.length) * 100)
        : 0;
    const sourceLabel = useMemo(
        () =>
            block?.sources
                .map((source) => `${source.document}, p. ${source.page}`)
                .join(' · '),
        [block],
    );
    function move(next: number) {
        const bounded = Math.max(0, Math.min(blocks.length - 1, next));
        setIndex(bounded);
        const target = blocks[bounded];

        if (target) {
            router.put(
                `/course/${unit.slug}/position`,
                { content_block_id: target.id, block_offset: 0 },
                { preserveScroll: true, preserveState: true },
            );
        }
    }

    if (!block) {
        return <div className="p-8">This session has no published blocks.</div>;
    }

    return (
        <>
            <Head title={unit.title} />
            <main className="mx-auto grid w-full max-w-7xl gap-6 p-4 md:p-8 lg:grid-cols-[240px_minmax(0,720px)_280px]">
                <aside className="hidden lg:block">
                    <div className="sticky top-6 rounded-xl border bg-card p-4">
                        <div className="text-xs font-medium text-muted-foreground">
                            {unit.schedule}
                        </div>
                        <h1 className="mt-2 text-sm leading-5 font-semibold">
                            {unit.title}
                        </h1>
                        <div className="mt-5 h-1.5 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full bg-emerald-500"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        <div className="mt-2 text-xs text-muted-foreground">
                            Concept {index + 1} of {blocks.length}
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="mt-4 w-full justify-start"
                            onClick={() => router.visit('/course')}
                        >
                            <ChevronLeft />
                            Course map
                        </Button>
                    </div>
                </aside>
                <section className="min-w-0">
                    <div className="mb-4 flex items-center justify-between lg:hidden">
                        <Badge variant="outline">
                            {index + 1} / {blocks.length}
                        </Badge>
                        <span className="text-xs text-muted-foreground">
                            {progress}% through session
                        </span>
                    </div>
                    <LearningBlock block={block} />
                    <div className="mt-5 flex items-center justify-between">
                        <Button
                            variant="outline"
                            onClick={() => move(index - 1)}
                            disabled={index === 0}
                        >
                            <ChevronLeft />
                            Previous concept
                        </Button>
                        {index === blocks.length - 1 ? (
                            <div className="flex flex-wrap justify-end gap-2">
                                {exerciseCount > 0 && (
                                    <Button variant="outline" asChild>
                                        <a
                                            href={`/course/${unit.slug}/exercises`}
                                        >
                                            {exerciseCount} exercises
                                        </a>
                                    </Button>
                                )}
                                {gate && (
                                    <Button variant="outline" asChild>
                                        <a href={`/course/${unit.slug}/gate`}>
                                            {gate.title}
                                        </a>
                                    </Button>
                                )}
                                <Button asChild>
                                    <a href={`/course/${unit.slug}/quiz`}>
                                        Mastery check
                                        <ChevronRight />
                                    </a>
                                </Button>
                            </div>
                        ) : (
                            <Button onClick={() => move(index + 1)}>
                                Continue
                                <ChevronRight />
                            </Button>
                        )}
                    </div>
                    <div className="mt-4 text-center text-xs text-muted-foreground">
                        Source: {sourceLabel}
                    </div>
                </section>
                <aside>
                    <div className="sticky top-6 space-y-4">
                        <Card className="gap-3">
                            <CardHeader className="pb-0">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <NotebookPen className="size-4 text-emerald-600" />
                                    Concept Notebook
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action="/notebooks"
                                    method="post"
                                    className="space-y-3"
                                >
                                    <input
                                        type="hidden"
                                        name="learning_unit_id"
                                        value={unit.id}
                                    />
                                    <input
                                        type="hidden"
                                        name="content_block_id"
                                        value={block.id}
                                    />
                                    <input
                                        type="hidden"
                                        name="notebook_type"
                                        value="concept"
                                    />
                                    <input
                                        type="hidden"
                                        name="entry_type"
                                        value="own_words"
                                    />
                                    <textarea
                                        name="body"
                                        required
                                        rows={5}
                                        className="w-full resize-none rounded-md border bg-background p-3 text-sm outline-none focus:border-ring"
                                        placeholder="Explain this idea in your own words…"
                                    />
                                    <Button
                                        size="sm"
                                        type="submit"
                                        className="w-full"
                                    >
                                        Save note
                                    </Button>
                                </Form>
                            </CardContent>
                        </Card>
                        <TutorBox unitSlug={unit.slug} />
                        <Form
                            action={
                                block.bookmarkId
                                    ? `/bookmarks/${block.bookmarkId}`
                                    : '/bookmarks'
                            }
                            method={block.bookmarkId ? 'delete' : 'post'}
                        >
                            <input
                                type="hidden"
                                name="content_block_id"
                                value={block.id}
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                className="w-full"
                                type="submit"
                            >
                                <Bookmark />
                                {block.bookmarkId
                                    ? 'Remove bookmark'
                                    : 'Bookmark concept'}
                            </Button>
                        </Form>
                    </div>
                </aside>
            </main>
        </>
    );
}

function TutorBox({ unitSlug }: { unitSlug: string }) {
    const [prompt, setPrompt] = useState('');
    const [reply, setReply] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    async function ask() {
        if (!prompt.trim()) {
            return;
        }

        setLoading(true);
        const token =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content || '';

        try {
            const response = await fetch(`/course/${unitSlug}/tutor`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    prompt,
                    request_id: crypto.randomUUID(),
                }),
            });
            const data = await response.json();
            setReply(data.message || data.error || 'The tutor is unavailable.');
        } catch {
            setReply(
                'The tutor is unavailable. Your core lesson remains available.',
            );
        } finally {
            setLoading(false);
        }
    }

    return (
        <Card className="gap-3">
            <CardHeader className="pb-0">
                <CardTitle className="flex items-center gap-2 text-base">
                    <Bot className="size-4 text-emerald-600" />
                    Ask the Course
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                <p className="text-xs leading-5 text-muted-foreground">
                    Course search always works. When an allowed tutor provider
                    is available, you can also receive a generated explanation.
                </p>
                <textarea
                    value={prompt}
                    onChange={(event) => setPrompt(event.target.value)}
                    disabled={loading}
                    rows={3}
                    className="w-full resize-none rounded-md border bg-background p-3 text-sm"
                    placeholder="Ask about this concept…"
                />
                {reply && (
                    <div className="rounded-lg bg-muted p-3 text-xs leading-5">
                        {reply}
                    </div>
                )}
                <Button
                    size="sm"
                    variant="outline"
                    className="w-full"
                    onClick={ask}
                    disabled={loading || !prompt.trim()}
                >
                    <Send />
                    {loading ? 'Searching…' : 'Search / explain with tutor'}
                </Button>
            </CardContent>
        </Card>
    );
}

function LearningBlock({ block }: { block: Block }) {
    const style =
        block.type === 'note_this'
            ? 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30'
            : block.type === 'warning'
              ? 'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/20'
              : 'bg-card';

    return (
        <article
            className={`min-h-[430px] rounded-2xl border p-6 shadow-sm md:p-10 ${style}`}
        >
            <div className="mb-8 flex items-center justify-between">
                <Badge variant="outline" className="capitalize">
                    {block.type.replace('_', ' ')}
                </Badge>
                {block.required && (
                    <span className="text-xs text-muted-foreground">
                        Required
                    </span>
                )}
            </div>
            {block.type === 'note_this' && (
                <div className="mb-4 flex items-center gap-2 text-sm font-semibold text-amber-800 dark:text-amber-200">
                    <Quote className="size-4" />
                    NOTE THIS
                </div>
            )}
            {block.type === 'warning' && (
                <ShieldAlert className="mb-4 size-6 text-red-600" />
            )}
            {block.title && (
                <h2 className="mb-5 text-2xl leading-tight font-semibold">
                    {block.title}
                </h2>
            )}
            <div className="text-base leading-8 whitespace-pre-line text-foreground/90">
                {block.body}
            </div>
        </article>
    );
}
