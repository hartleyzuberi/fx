import { Form, Head } from '@inertiajs/react';
import { BrainCircuit, Clock3, ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = { defaults: { timezone: string; ai_tutor_enabled: boolean } };

const selectClass =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20';

export default function Onboarding({ defaults }: Props) {
    return (
        <>
            <Head title="Set up your learning path" />
            <div className="mx-auto w-full max-w-5xl p-4 md:p-8">
                <div className="mb-8 max-w-2xl">
                    <div className="mb-3 inline-flex items-center gap-2 rounded-full bg-emerald-500/10 px-3 py-1 text-sm font-medium text-emerald-700 dark:text-emerald-300">
                        <ShieldCheck className="size-4" /> Foundations stay
                        mandatory
                    </div>
                    <h1 className="text-3xl font-semibold tracking-tight md:text-4xl">
                        Build a study path you can sustain.
                    </h1>
                    <p className="mt-3 text-muted-foreground">
                        Your answers tune pace and explanation depth. They never
                        bypass safety-critical foundations or mastery gates.
                    </p>
                </div>
                <Form action="/onboarding" method="post" disableWhileProcessing>
                    {({ processing, errors }) => (
                        <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Your learning profile</CardTitle>
                                    <CardDescription>
                                        About two minutes. You can change
                                        reminders and tutor consent later.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5 md:grid-cols-2">
                                    <Field
                                        label="Current forex knowledge"
                                        error={errors.knowledge_level}
                                    >
                                        <select
                                            name="knowledge_level"
                                            defaultValue="new"
                                            className={selectClass}
                                        >
                                            <option value="new">
                                                Completely new
                                            </option>
                                            <option value="beginner">
                                                Know a few basics
                                            </option>
                                            <option value="intermediate">
                                                Can analyze and size trades
                                            </option>
                                            <option value="advanced">
                                                Experienced, seeking structure
                                            </option>
                                        </select>
                                    </Field>
                                    <Field
                                        label="Trading experience"
                                        error={errors.trading_experience}
                                    >
                                        <select
                                            name="trading_experience"
                                            defaultValue="none"
                                            className={selectClass}
                                        >
                                            <option value="none">
                                                None yet
                                            </option>
                                            <option value="demo">
                                                Demo only
                                            </option>
                                            <option value="live">
                                                Live only
                                            </option>
                                            <option value="both">
                                                Demo and live
                                            </option>
                                        </select>
                                    </Field>
                                    <Field
                                        label="Study hours each week"
                                        error={errors.study_hours_per_week}
                                    >
                                        <Input
                                            name="study_hours_per_week"
                                            type="number"
                                            min={1}
                                            max={40}
                                            defaultValue={4}
                                        />
                                    </Field>
                                    <Field
                                        label="Preferred pace"
                                        error={errors.preferred_pace}
                                    >
                                        <select
                                            name="preferred_pace"
                                            defaultValue="steady"
                                            className={selectClass}
                                        >
                                            <option value="gentle">
                                                Gentle
                                            </option>
                                            <option value="steady">
                                                Steady
                                            </option>
                                            <option value="intensive">
                                                Intensive
                                            </option>
                                        </select>
                                    </Field>
                                    <Field
                                        label="Timezone"
                                        error={errors.timezone}
                                    >
                                        <Input
                                            name="timezone"
                                            defaultValue={defaults.timezone}
                                            autoComplete="off"
                                        />
                                    </Field>
                                    <Field
                                        label="Study reminders"
                                        error={errors.reminder_preference}
                                    >
                                        <select
                                            name="reminder_preference"
                                            defaultValue="none"
                                            className={selectClass}
                                        >
                                            <option value="none">
                                                No reminders
                                            </option>
                                            <option value="daily">Daily</option>
                                            <option value="weekdays">
                                                Weekdays
                                            </option>
                                            <option value="weekly">
                                                Weekly review
                                            </option>
                                        </select>
                                    </Field>
                                    <div className="md:col-span-2">
                                        <Field
                                            label="What do you want this course to help you achieve?"
                                            error={errors.learning_objective}
                                        >
                                            <textarea
                                                name="learning_objective"
                                                required
                                                minLength={10}
                                                rows={4}
                                                className={`${selectClass} h-auto py-3`}
                                                placeholder="For example: build enough market and risk literacy to test one strategy responsibly."
                                            />
                                        </Field>
                                    </div>
                                    <div className="rounded-xl border bg-muted/40 p-4 md:col-span-2">
                                        <input
                                            type="hidden"
                                            name="ai_tutor_enabled"
                                            value="0"
                                        />
                                        <div className="flex items-start gap-3">
                                            <Checkbox
                                                id="ai_tutor_enabled"
                                                name="ai_tutor_enabled"
                                                value="1"
                                                defaultChecked={
                                                    defaults.ai_tutor_enabled
                                                }
                                            />
                                            <div>
                                                <Label
                                                    htmlFor="ai_tutor_enabled"
                                                    className="font-medium"
                                                >
                                                    Enable the optional AI tutor
                                                </Label>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    The tutor can explain cited
                                                    course material and assess
                                                    free-text practice. It never
                                                    unlocks units or supplies
                                                    active quiz answers.
                                                </p>
                                            </div>
                                        </div>
                                        <InputError
                                            message={errors.ai_tutor_enabled}
                                            className="mt-2"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        size="lg"
                                        className="md:col-span-2"
                                    >
                                        {processing && <Spinner />}Create my
                                        learning path
                                    </Button>
                                </CardContent>
                            </Card>
                            <div className="space-y-4">
                                <Info
                                    icon={Clock3}
                                    title="Mastery, not seat time"
                                >
                                    Progress follows demonstrated understanding,
                                    exercises and gates—not scrolling.
                                </Info>
                                <Info
                                    icon={BrainCircuit}
                                    title="Adaptive, not abbreviated"
                                >
                                    Explanations can adapt, but the complete
                                    source curriculum remains mapped and
                                    accessible.
                                </Info>
                                <div className="rounded-xl border border-amber-300/60 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100">
                                    <strong>Education only.</strong> This
                                    platform does not provide signals,
                                    individualized financial advice or promises
                                    of profit.
                                </div>
                            </div>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function Info({
    icon: Icon,
    title,
    children,
}: {
    icon: typeof Clock3;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div className="rounded-xl border bg-card p-5">
            <Icon className="mb-3 size-5 text-emerald-600" />
            <h2 className="font-semibold">{title}</h2>
            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                {children}
            </p>
        </div>
    );
}
