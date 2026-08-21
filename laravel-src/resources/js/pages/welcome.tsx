import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpenCheck,
    Calculator,
    ChartNoAxesCombined,
    CheckCircle2,
    ShieldCheck,
} from 'lucide-react';
import { dashboard, login, register } from '@/routes';

const proof = [
    ['90', 'guided chapters'],
    ['540', 'mastery questions'],
    ['379', 'practical exercises'],
    ['7', 'evidence gates'],
];

const features = [
    {
        icon: BookOpenCheck,
        title: 'Mastery before motion',
        copy: 'Learn in sequence, explain concepts in your own words, and unlock the next session with evidence.',
    },
    {
        icon: Calculator,
        title: 'Risk first',
        copy: 'Use position-size, pip, R, expectancy, drawdown, margin, and open-risk calculators built into the course.',
    },
    {
        icon: ChartNoAxesCombined,
        title: 'Practice that compounds',
        copy: 'Move from chart drills to frozen strategies, honest backtests, robustness checks, and serious demo trading.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Forex mastery, taught as a discipline" />
            <main className="min-h-screen bg-[#07110f] text-stone-100">
                <div className="absolute inset-x-0 top-0 h-[620px] overflow-hidden">
                    <div className="absolute -top-48 left-1/2 h-[480px] w-[760px] -translate-x-1/2 rounded-full bg-emerald-400/15 blur-[120px]" />
                    <div className="absolute top-20 -right-40 h-96 w-96 rounded-full bg-amber-300/10 blur-[100px]" />
                </div>

                <div className="relative mx-auto max-w-7xl px-5 md:px-8">
                    <header className="flex h-20 items-center justify-between border-b border-white/10">
                        <Link href="/" className="flex items-center gap-3">
                            <span className="grid size-9 place-items-center rounded-xl bg-emerald-400 text-sm font-black text-emerald-950">
                                FX
                            </span>
                            <span className="font-semibold tracking-tight">
                                FX Mastery
                            </span>
                        </Link>
                        <nav className="flex items-center gap-2 text-sm">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="rounded-full bg-emerald-400 px-5 py-2.5 font-semibold text-emerald-950 transition hover:bg-emerald-300"
                                >
                                    Open dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="rounded-full px-4 py-2.5 text-stone-300 transition hover:text-white"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="rounded-full bg-emerald-400 px-5 py-2.5 font-semibold text-emerald-950 transition hover:bg-emerald-300"
                                    >
                                        Start learning
                                    </Link>
                                </>
                            )}
                        </nav>
                    </header>

                    <section className="grid min-h-[660px] items-center gap-12 py-16 lg:grid-cols-[1.1fr_0.9fr] lg:py-20">
                        <div className="max-w-3xl">
                            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-300/10 px-3 py-1.5 text-xs font-medium text-emerald-200">
                                <ShieldCheck className="size-3.5" />
                                Evidence-led. Kenya-aware. Risk-first.
                            </div>
                            <h1 className="text-5xl leading-[0.98] font-semibold tracking-[-0.045em] text-balance sm:text-6xl lg:text-7xl">
                                Learn forex without pretending mastery.
                            </h1>
                            <p className="mt-7 max-w-2xl text-lg leading-8 text-stone-300">
                                A complete, tutor-led learning system that turns
                                two source courses into guided sessions,
                                notebooks, assessments, practical evidence, and
                                disciplined progression.
                            </p>
                            <div className="mt-9 flex flex-wrap items-center gap-3">
                                <Link
                                    href={auth.user ? dashboard() : register()}
                                    className="inline-flex items-center gap-2 rounded-full bg-emerald-400 px-6 py-3.5 font-semibold text-emerald-950 transition hover:bg-emerald-300"
                                >
                                    {auth.user
                                        ? 'Continue your course'
                                        : 'Begin with first principles'}
                                    <ArrowRight className="size-4" />
                                </Link>
                                {!auth.user && (
                                    <Link
                                        href={login()}
                                        className="rounded-full border border-white/15 px-6 py-3.5 font-medium text-stone-200 transition hover:border-white/30 hover:bg-white/5"
                                    >
                                        I already have an account
                                    </Link>
                                )}
                            </div>
                            <p className="mt-5 flex items-center gap-2 text-xs text-stone-400">
                                <CheckCircle2 className="size-3.5 text-emerald-400" />
                                Education and decision-process training—not
                                investment advice or profit promises.
                            </p>
                        </div>

                        <div className="relative mx-auto w-full max-w-lg">
                            <div className="rounded-[2rem] border border-white/10 bg-white/[0.06] p-3 shadow-2xl shadow-black/30 backdrop-blur">
                                <div className="rounded-[1.4rem] border border-white/10 bg-[#0d1b17] p-6">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-xs font-medium tracking-widest text-emerald-300 uppercase">
                                                Your next session
                                            </p>
                                            <h2 className="mt-2 text-xl font-semibold">
                                                What Forex Actually Is
                                            </h2>
                                        </div>
                                        <span className="rounded-lg bg-amber-300/10 px-2.5 py-1 text-xs text-amber-200">
                                            Week 1
                                        </span>
                                    </div>
                                    <div className="mt-7 space-y-3">
                                        {[
                                            'Relative exchange rates',
                                            'OTC market structure',
                                            'Hedging vs. speculation',
                                        ].map((item, index) => (
                                            <div
                                                key={item}
                                                className="flex items-center gap-3 rounded-xl border border-white/8 bg-white/[0.035] p-3.5"
                                            >
                                                <span className="grid size-7 place-items-center rounded-full bg-emerald-400/15 text-xs font-semibold text-emerald-300">
                                                    {index + 1}
                                                </span>
                                                <span className="text-sm text-stone-200">
                                                    {item}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="mt-6 rounded-xl bg-emerald-400 p-4 text-emerald-950">
                                        <p className="text-xs font-bold uppercase">
                                            Mastery rule
                                        </p>
                                        <p className="mt-1 text-sm leading-5">
                                            Score at least 85% and save an
                                            own-words notebook entry before the
                                            next session unlocks.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="border-y border-white/10 py-8">
                        <div className="grid grid-cols-2 gap-6 md:grid-cols-4">
                            {proof.map(([value, label]) => (
                                <div key={label}>
                                    <div className="text-3xl font-semibold text-emerald-300">
                                        {value}
                                    </div>
                                    <div className="mt-1 text-sm text-stone-400">
                                        {label}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="py-20">
                        <div className="max-w-2xl">
                            <p className="text-sm font-semibold text-emerald-300">
                                BUILT FOR REAL LEARNING
                            </p>
                            <h2 className="mt-3 text-3xl font-semibold tracking-tight md:text-4xl">
                                The course keeps the evidence. You keep the
                                judgment.
                            </h2>
                        </div>
                        <div className="mt-10 grid gap-4 md:grid-cols-3">
                            {features.map(({ icon: Icon, title, copy }) => (
                                <article
                                    key={title}
                                    className="rounded-2xl border border-white/10 bg-white/[0.035] p-6"
                                >
                                    <Icon className="size-6 text-emerald-300" />
                                    <h3 className="mt-5 text-lg font-semibold">
                                        {title}
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-stone-400">
                                        {copy}
                                    </p>
                                </article>
                            ))}
                        </div>
                    </section>

                    <footer className="flex flex-col gap-3 border-t border-white/10 py-8 text-xs text-stone-500 sm:flex-row sm:items-center sm:justify-between">
                        <span>
                            FX Mastery · Structured learning for serious
                            beginners
                        </span>
                        <span>
                            Risk limits and current official sources come first.
                        </span>
                    </footer>
                </div>
            </main>
        </>
    );
}
