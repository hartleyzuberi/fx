import { Head, Link } from '@inertiajs/react';
import { BookOpen, ExternalLink, FileText, Library, Link2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Resource = {
    id: string;
    resource_type: 'book' | 'academic_paper' | 'external_url';
    title: string;
    url: string | null;
    review_status: string;
    verified_on: string | null;
    source_page: number | null;
};
type Appendix = {
    slug: string;
    title: string;
    pages: { start: number; end: number } | null;
};

const filters = [
    ['all', 'All resources'],
    ['book', 'Books'],
    ['academic_paper', 'Research'],
    ['external_url', 'Official & external'],
] as const;

export default function ResourceLibrary({
    resources,
    appendices,
}: {
    resources: Resource[];
    appendices: Appendix[];
}) {
    const [filter, setFilter] = useState<(typeof filters)[number][0]>('all');
    const visible = useMemo(
        () =>
            filter === 'all'
                ? resources
                : resources.filter((item) => item.resource_type === filter),
        [filter, resources],
    );

    return (
        <>
            <Head title="Resource library" />
            <main className="mx-auto w-full max-w-7xl space-y-8 p-4 md:p-8">
                <header className="max-w-3xl">
                    <p className="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                        SOURCE-BOUND REFERENCE LIBRARY
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                        Resources and appendices
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Books remain references, not reproduced content. Links
                        carry their source verification date and should be
                        rechecked before a legal, regulatory, tax, or broker
                        decision.
                    </p>
                </header>

                <section>
                    <div className="mb-4 flex items-center gap-2">
                        <Library className="size-5 text-emerald-600" />
                        <h2 className="text-xl font-semibold">26 appendices</h2>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {appendices.map((appendix) => (
                            <Link
                                key={appendix.slug}
                                href={`/references/${appendix.slug}`}
                                className="rounded-xl border bg-card p-4"
                            >
                                <div className="text-sm font-medium">
                                    {appendix.title}
                                </div>
                                {appendix.pages && (
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        Canonical PDF pages{' '}
                                        {appendix.pages.start}–
                                        {appendix.pages.end}
                                    </div>
                                )}
                            </Link>
                        ))}
                    </div>
                </section>

                <section>
                    <div className="mb-4 flex flex-wrap gap-2">
                        {filters.map(([key, label]) => (
                            <Button
                                key={key}
                                size="sm"
                                variant={filter === key ? 'default' : 'outline'}
                                onClick={() => setFilter(key)}
                            >
                                {label}
                            </Button>
                        ))}
                    </div>
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {visible.map((resource) => (
                            <ResourceCard
                                key={resource.id}
                                resource={resource}
                            />
                        ))}
                    </div>
                </section>
            </main>
        </>
    );
}

function ResourceCard({ resource }: { resource: Resource }) {
    const Icon =
        resource.resource_type === 'book'
            ? BookOpen
            : resource.resource_type === 'academic_paper'
              ? FileText
              : Link2;

    return (
        <Card className="gap-3">
            <CardHeader className="pb-0">
                <div className="flex items-start justify-between gap-3">
                    <Icon className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                    <Badge variant="outline" className="capitalize">
                        {resource.resource_type.replace('_', ' ')}
                    </Badge>
                </div>
                <CardTitle className="pt-2 text-base leading-6">
                    {resource.title}
                </CardTitle>
            </CardHeader>
            <CardContent className="mt-auto">
                <div className="text-xs text-muted-foreground">
                    Source page {resource.source_page ?? '—'}
                    {resource.verified_on
                        ? ` · listed ${resource.verified_on}`
                        : ''}
                </div>
                {resource.url && (
                    <a
                        href={resource.url}
                        target="_blank"
                        rel="noreferrer"
                        className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700 hover:underline dark:text-emerald-300"
                    >
                        Open original source
                        <ExternalLink className="size-3.5" />
                    </a>
                )}
            </CardContent>
        </Card>
    );
}
