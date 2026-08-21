import { Form, Head } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    CalendarRange,
    CandlestickChart,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Entry = {
    id: string;
    notebook_type: string;
    entry_type: string;
    title: string | null;
    body: string | null;
    physical_task_completed: boolean;
    created_at: string;
    unit_title: string | null;
};
const books = [
    {
        key: 'concept',
        title: 'Concept Notebook',
        description:
            'Definitions, mechanisms, formulas and explanations in your own words.',
        icon: BookOpen,
    },
    {
        key: 'chart',
        title: 'Chart Notebook',
        description:
            'Screenshots, annotations, structure and replay observations.',
        icon: CandlestickChart,
    },
    {
        key: 'macro',
        title: 'Macro Notebook',
        description:
            'Events, central banks, expectations and currency observations.',
        icon: CalendarRange,
    },
    {
        key: 'trading_journal',
        title: 'Trading Journal',
        description:
            'Execution evidence; used when replay and demo stages require it.',
        icon: BarChart3,
    },
];

export default function Notebooks({ entries }: { entries: Entry[] }) {
    return (
        <>
            <Head title="Notebooks" />
            <main className="mx-auto w-full max-w-6xl p-4 md:p-8">
                <header className="mb-8 max-w-3xl">
                    <p className="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                        YOUR EVIDENCE, NOT DECORATION
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                        Four-notebook system
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Capture only what helps you reconstruct an idea, inspect
                        a decision or preserve evidence.
                    </p>
                </header>
                <section className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {books.map((book) => {
                        const count = entries.filter(
                            (entry) => entry.notebook_type === book.key,
                        ).length;
                        const Icon = book.icon;

                        return (
                            <Card key={book.key} className="gap-3">
                                <CardHeader className="pb-0">
                                    <div className="flex items-center justify-between">
                                        <Icon className="size-5 text-emerald-600" />
                                        <Badge variant="outline">{count}</Badge>
                                    </div>
                                    <CardTitle className="pt-3 text-base">
                                        {book.title}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="text-sm leading-6 text-muted-foreground">
                                    {book.description}
                                </CardContent>
                            </Card>
                        );
                    })}
                </section>
                <section className="mt-8 rounded-xl border bg-card p-5 md:p-6">
                    <h2 className="text-xl font-semibold">Add an entry</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Record a digital note, or mark work completed in a
                        physical notebook and optionally keep a private photo.
                    </p>
                    <Form
                        action="/notebooks"
                        method="post"
                        encType="multipart/form-data"
                        resetOnSuccess
                        className="mt-5 grid gap-4 md:grid-cols-2"
                    >
                        <label className="space-y-1.5 text-sm">
                            <span className="font-medium">Notebook</span>
                            <select
                                name="notebook_type"
                                className="w-full rounded-md border bg-background p-2.5"
                                defaultValue="concept"
                            >
                                {books.map((book) => (
                                    <option key={book.key} value={book.key}>
                                        {book.title}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="space-y-1.5 text-sm">
                            <span className="font-medium">Title</span>
                            <input
                                name="title"
                                maxLength={255}
                                className="w-full rounded-md border bg-background p-2.5"
                                placeholder="Optional short title"
                            />
                        </label>
                        <input type="hidden" name="entry_type" value="note" />
                        <label className="space-y-1.5 text-sm md:col-span-2">
                            <span className="font-medium">Your note</span>
                            <textarea
                                name="body"
                                rows={5}
                                className="w-full rounded-md border bg-background p-3"
                                placeholder="Explain, observe, or reflect in your own words."
                            />
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                name="physical_task_completed"
                                value="1"
                                className="size-4"
                            />
                            I completed this in a physical notebook
                        </label>
                        <label className="space-y-1.5 text-sm">
                            <span className="font-medium">
                                Private photo or PDF (optional)
                            </span>
                            <input
                                type="file"
                                name="attachment"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                className="block w-full text-xs"
                            />
                        </label>
                        <div className="md:col-span-2">
                            <Button type="submit">Save notebook entry</Button>
                        </div>
                    </Form>
                </section>
                <section className="mt-8">
                    <h2 className="mb-4 text-xl font-semibold">
                        Recent entries
                    </h2>
                    {entries.length === 0 ? (
                        <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                            Your first entry will appear when you explain a
                            lesson concept in your own words.
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {entries.map((entry) => (
                                <article
                                    key={entry.id}
                                    className="rounded-xl border bg-card p-5"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge
                                            variant="outline"
                                            className="capitalize"
                                        >
                                            {entry.notebook_type.replace(
                                                '_',
                                                ' ',
                                            )}
                                        </Badge>
                                        <Badge
                                            variant="secondary"
                                            className="capitalize"
                                        >
                                            {entry.entry_type.replace('_', ' ')}
                                        </Badge>
                                        <span className="ml-auto text-xs text-muted-foreground">
                                            {new Date(
                                                entry.created_at,
                                            ).toLocaleDateString()}
                                        </span>
                                    </div>
                                    <h3 className="mt-3 font-medium">
                                        {entry.title ||
                                            entry.unit_title ||
                                            'Notebook entry'}
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 whitespace-pre-line text-muted-foreground">
                                        {entry.body}
                                    </p>
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </main>
        </>
    );
}
