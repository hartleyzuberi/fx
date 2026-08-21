import { Form, Head, router } from '@inertiajs/react';
import {
    Bookmark,
    BookOpen,
    Bot,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock3,
    Lightbulb,
    ListChecks,
    MessageCircleQuestion,
    NotebookPen,
    Quote,
    Send,
    ShieldAlert,
    Sparkles,
    Target,
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
    status,
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
            <main className="mx-auto w-full max-w-[1480px] p-3 sm:p-4 md:p-6 lg:p-8">
                <LessonHeader
                    unit={unit}
                    status={status}
                    progress={progress}
                    current={index + 1}
                    total={blocks.length}
                />

                <div className="mt-5 grid gap-5 xl:grid-cols-[260px_minmax(0,760px)_310px]">
                    <aside className="hidden xl:block">
                        <div className="sticky top-6 space-y-4">
                            <Card className="gap-3 overflow-hidden">
                                <CardHeader className="pb-0">
                                    <CardTitle className="flex items-center gap-2 text-sm">
                                        <ListChecks className="size-4 text-emerald-600" />
                                        Lesson path
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-1.5">
                                    {blocks.map((item, itemIndex) => (
                                        <button
                                            key={item.id}
                                            type="button"
                                            onClick={() => move(itemIndex)}
                                            className={`flex w-full items-start gap-2 rounded-lg px-2.5 py-2 text-left text-xs transition ${
                                                itemIndex === index
                                                    ? 'bg-emerald-500/10 text-emerald-800 dark:text-emerald-200'
                                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                            }`}
                                        >
                                            {itemIndex < index ? (
                                                <CheckCircle2 className="mt-0.5 size-3.5 shrink-0 text-emerald-600" />
                                            ) : (
                                                <span
                                                    className={`mt-0.5 flex size-3.5 shrink-0 items-center justify-center rounded-full border text-[9px] ${
                                                        itemIndex === index
                                                            ? 'border-emerald-500 bg-emerald-500 text-white'
                                                            : ''
                                                    }`}
                                                >
                                                    {itemIndex + 1}
                                                </span>
                                            )}
                                            <span className="line-clamp-2 leading-5">
                                                {item.title || blockTypeLabel(item.type)}
                                            </span>
                                        </button>
                                    ))}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="mt-3 w-full justify-start"
                                        onClick={() => router.visit('/course')}
                                    >
                                        <ChevronLeft />
                                        Complete course map
                                    </Button>
                                </CardContent>
                            </Card>

                            <Card className="gap-2 bg-muted/30">
                                <CardContent className="pt-5 text-xs leading-5 text-muted-foreground">
                                    <div className="flex items-start gap-2">
                                        <Target className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                        <p>
                                            Read for understanding, then close the
                                            loop with recall, notebook work and the
                                            mastery check.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </aside>

                    <section className="min-w-0">
                        <div className="mb-3 flex items-center justify-between xl:hidden">
                            <Badge variant="outline">
                                Concept {index + 1} of {blocks.length}
                            </Badge>
                            <span className="text-xs text-muted-foreground">
                                {progress}% through lesson
                            </span>
                        </div>

                        <LearningBlock block={block} />

                        <div className="mt-4 rounded-xl border bg-muted/20 px-4 py-3">
                            <div className="flex items-start gap-2">
                                <Lightbulb className="mt-0.5 size-4 shrink-0 text-amber-600" />
                                <div>
                                    <p className="text-sm font-medium">
                                        Before you continue
                                    </p>
                                    <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                        Say the main idea back in your own words.
                                        If you cannot explain it simply yet, use
                                        the notebook or ask the course for another
                                        explanation before moving on.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="mt-5 flex items-center justify-between gap-3">
                            <Button
                                variant="outline"
                                onClick={() => move(index - 1)}
                                disabled={index === 0}
                            >
                                <ChevronLeft />
                                Previous
                            </Button>
                            {index === blocks.length - 1 ? (
                                <div className="flex flex-wrap justify-end gap-2">
                                    {exerciseCount > 0 && (
                                        <Button variant="outline" asChild>
                                            <a href={`/course/${unit.slug}/exercises`}>
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

                        {sourceLabel && (
                            <details className="mt-5 rounded-lg border bg-card px-4 py-3 text-xs text-muted-foreground">
                                <summary className="cursor-pointer font-medium text-foreground">
                                    View source provenance
                                </summary>
                                <p className="mt-2 leading-5">{sourceLabel}</p>
                            </details>
                        )}
                    </section>

                    <aside>
                        <div className="sticky top-6 space-y-4">
                            <Card className="gap-3 border-emerald-200 dark:border-emerald-900">
                                <CardHeader className="pb-0">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <NotebookPen className="size-4 text-emerald-600" />
                                        Concept Notebook
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="mb-3 text-xs leading-5 text-muted-foreground">
                                        Capture the idea in language you would use
                                        to teach someone else. This is part of the
                                        learning evidence, not just a scratch pad.
                                    </p>
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
                                            className="w-full resize-none rounded-lg border bg-background p-3 text-sm leading-6 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10"
                                            placeholder="What does this mean in your own words?"
                                        />
                                        <Button
                                            size="sm"
                                            type="submit"
                                            className="w-full"
                                        >
                                            Save to notebook
                                        </Button>
                                    </Form>
                                </CardContent>
                            </Card>

                            <TutorBox
                                unitSlug={unit.slug}
                                conceptTitle={
                                    block.title || blockTypeLabel(block.type)
                                }
                            />

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
                                        : 'Bookmark this concept'}
                                </Button>
                            </Form>
                        </div>
                    </aside>
                </div>
            </main>
        </>
    );
}

function LessonHeader({
    unit,
    status,
    progress,
    current,
    total,
}: {
    unit: Props['unit'];
    status: string;
    progress: number;
    current: number;
    total: number;
}) {
    return (
        <header className="overflow-hidden rounded-2xl border bg-card">
            <div className="grid gap-5 p-5 md:grid-cols-[1fr_auto] md:items-center md:p-6">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge className="bg-emerald-600 text-white hover:bg-emerald-600">
                            {unit.schedule || 'Guided lesson'}
                        </Badge>
                        <Badge variant="outline" className="capitalize">
                            {status.replace('_', ' ')}
                        </Badge>
                    </div>
                    <h1 className="mt-3 max-w-4xl text-2xl leading-tight font-semibold tracking-tight md:text-3xl">
                        {unit.title}
                    </h1>
                    <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted-foreground">
                        {unit.estimatedMinutes && (
                            <span className="flex items-center gap-1.5">
                                <Clock3 className="size-4" />
                                About {unit.estimatedMinutes} min
                            </span>
                        )}
                        <span className="flex items-center gap-1.5">
                            <BookOpen className="size-4" />
                            {total} guided concepts
                        </span>
                        <span className="flex items-center gap-1.5">
                            <Target className="size-4" />
                            Active recall required
                        </span>
                    </div>
                </div>
                <div className="w-full md:w-56">
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>
                            {current} of {total}
                        </span>
                        <span>{progress}%</span>
                    </div>
                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-emerald-500 transition-all"
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                </div>
            </div>
        </header>
    );
}

function TutorBox({
    unitSlug,
    conceptTitle,
}: {
    unitSlug: string;
    conceptTitle: string;
}) {
    const [prompt, setPrompt] = useState('');
    const [reply, setReply] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const quickPrompts = [
        'Explain this simply',
        'Give me a real example',
        'What mistakes should I avoid?',
        'Quiz me on this concept',
    ];

    async function ask(value?: string) {
        const effectivePrompt = (value ?? prompt).trim();

        if (!effectivePrompt) {
            return;
        }

        if (value) {
            setPrompt(value);
        }

        setLoading(true);
        setReply(null);
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
                    prompt: `${effectivePrompt}\n\nCurrent lesson concept: ${conceptTitle}`,
                    request_id: crypto.randomUUID(),
                }),
            });
            const data = await response.json();
            setReply(data.message || data.error || 'The tutor is unavailable.');
        } catch {
            setReply(
                'The tutor is unavailable. Your lesson, course search and deterministic assessments are still available.',
            );
        } finally {
            setLoading(false);
        }
    }

    return (
        <Card className="gap-3 overflow-hidden">
            <CardHeader className="pb-0">
                <div className="mb-1 flex items-center justify-between gap-3">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <Bot className="size-4 text-emerald-600" />
                        Ask the Course
                    </CardTitle>
                    <Badge variant="outline" className="text-[10px]">
                        Course-grounded
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                <p className="text-xs leading-5 text-muted-foreground">
                    Use a quick prompt or ask naturally. Search remains useful
                    even when generated tutoring is unavailable.
                </p>
                <div className="flex flex-wrap gap-1.5">
                    {quickPrompts.map((item) => (
                        <button
                            key={item}
                            type="button"
                            disabled={loading}
                            onClick={() => void ask(item)}
                            className="rounded-full border bg-background px-2.5 py-1.5 text-[11px] leading-4 transition hover:border-emerald-400 hover:bg-emerald-500/5 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {item}
                        </button>
                    ))}
                </div>
                <div className="relative">
                    <MessageCircleQuestion className="absolute top-3 left-3 size-4 text-muted-foreground" />
                    <textarea
                        value={prompt}
                        onChange={(event) => setPrompt(event.target.value)}
                        disabled={loading}
                        rows={3}
                        className="w-full resize-none rounded-lg border bg-background py-2.5 pr-3 pl-9 text-sm leading-6 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10"
                        placeholder="Ask about this concept…"
                    />
                </div>
                {reply && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-500/5 p-3 text-xs leading-5 dark:border-emerald-900">
                        <div className="mb-1.5 flex items-center gap-1.5 font-medium text-emerald-800 dark:text-emerald-200">
                            <Sparkles className="size-3.5" />
                            Course response
                        </div>
                        <div className="whitespace-pre-line">{reply}</div>
                    </div>
                )}
                <Button
                    size="sm"
                    variant="outline"
                    className="w-full"
                    onClick={() => void ask()}
                    disabled={loading || !prompt.trim()}
                >
                    <Send />
                    {loading ? 'Working…' : 'Ask the course'}
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

    const icon =
        block.type === 'note_this' ? (
            <Quote className="size-5 text-amber-700 dark:text-amber-300" />
        ) : block.type === 'warning' ? (
            <ShieldAlert className="size-5 text-red-600" />
        ) : (
            <BookOpen className="size-5 text-emerald-600" />
        );

    return (
        <article
            className={`min-h-[470px] overflow-hidden rounded-2xl border shadow-sm ${style}`}
        >
            <div className="border-b bg-background/40 px-6 py-4 md:px-9">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        {icon}
                        <Badge variant="outline" className="capitalize">
                            {blockTypeLabel(block.type)}
                        </Badge>
                    </div>
                    {block.required && (
                        <span className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <Target className="size-3.5" />
                            Required concept
                        </span>
                    )}
                </div>
            </div>
            <div className="px-6 py-7 md:px-9 md:py-9">
                {block.type === 'note_this' && (
                    <div className="mb-4 text-sm font-semibold text-amber-800 dark:text-amber-200">
                        NOTE THIS
                    </div>
                )}
                {block.title && (
                    <h2 className="mb-6 max-w-3xl text-2xl leading-tight font-semibold tracking-tight md:text-[1.8rem]">
                        {block.title}
                    </h2>
                )}
                <div className="max-w-3xl text-[15.5px] leading-8 whitespace-pre-line text-foreground/90 md:text-base">
                    {block.body}
                </div>
            </div>
        </article>
    );
}

function blockTypeLabel(type: string): string {
    return type.replaceAll('_', ' ');
}
