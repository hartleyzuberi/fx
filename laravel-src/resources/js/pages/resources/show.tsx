import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpen } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

type Block = {
    id: string;
    type: string;
    title: string | null;
    body: string | null;
    sources: Array<{ document: string; page: number }>;
};

export default function ReferenceShow({
    unit,
    blocks,
}: {
    unit: {
        title: string;
        metadata: { canonical_pages?: { start: number; end: number } };
    };
    blocks: Block[];
}) {
    return (
        <>
            <Head title={unit.title} />
            <main className="mx-auto w-full max-w-4xl p-4 md:p-8">
                <Link
                    href="/resources"
                    className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                    Resource library
                </Link>
                <header className="mt-7 border-b pb-7">
                    <div className="flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-300">
                        <BookOpen className="size-4" />
                        CANONICAL COURSE APPENDIX
                    </div>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                        {unit.title}
                    </h1>
                    {unit.metadata.canonical_pages && (
                        <p className="mt-2 text-sm text-muted-foreground">
                            Physical source pages{' '}
                            {unit.metadata.canonical_pages.start}–
                            {unit.metadata.canonical_pages.end}
                        </p>
                    )}
                </header>
                <section className="space-y-4 py-8">
                    {blocks.map((block) => (
                        <article
                            key={block.id}
                            className="rounded-xl border bg-card p-5 md:p-7"
                        >
                            <Badge variant="outline" className="capitalize">
                                {block.type.replace('_', ' ')}
                            </Badge>
                            {block.title && (
                                <h2 className="mt-4 text-lg font-semibold">
                                    {block.title}
                                </h2>
                            )}
                            <p className="mt-3 text-sm leading-7 whitespace-pre-line text-muted-foreground">
                                {block.body}
                            </p>
                            <div className="mt-4 text-xs text-muted-foreground">
                                {block.sources
                                    .map(
                                        (source) =>
                                            `${source.document}, p. ${source.page}`,
                                    )
                                    .join(' · ')}
                            </div>
                        </article>
                    ))}
                </section>
            </main>
        </>
    );
}
