import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Props = {
    preferences: { enabled: boolean; allowCloud: boolean; allowLocal: boolean };
    runtimeMode: string;
};

export default function AiPreferences({ preferences, runtimeMode }: Props) {
    return (
        <>
            <Head title="AI preferences" />
            <h1 className="sr-only">AI preferences</h1>
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="AI preferences"
                    description="Choose whether optional tutor generation may use local or cloud providers."
                />
                <Card>
                    <CardContent className="space-y-3 pt-6 text-sm">
                        <p>
                            <strong>Current learning mode:</strong>{' '}
                            {runtimeMode}
                        </p>
                        <p className="text-muted-foreground">
                            Search Course, lessons, quizzes, calculators,
                            notebooks and progression continue when AI is off.
                            The tutor can occasionally be wrong; course rules
                            and progression are enforced separately.
                        </p>
                    </CardContent>
                </Card>
                <Form
                    action="/settings/ai"
                    method="patch"
                    className="space-y-5"
                >
                    <Preference
                        name="ai_tutor_enabled"
                        label="AI Tutor"
                        description="Allow optional generated explanations and semantic feedback."
                        enabled={preferences.enabled}
                    />
                    <Preference
                        name="allow_local_ai"
                        label="Allow local AI"
                        description="Permit a self-hosted Ollama provider when the administrator has configured one."
                        enabled={preferences.allowLocal}
                    />
                    <Preference
                        name="allow_cloud_ai"
                        label="Allow cloud AI"
                        description="Permit configured hosted providers. Private journal content is not routed to cloud providers by default."
                        enabled={preferences.allowCloud}
                    />
                    <Button>Save preferences</Button>
                </Form>
            </div>
        </>
    );
}

function Preference({
    name,
    label,
    description,
    enabled,
}: {
    name: string;
    label: string;
    description: string;
    enabled: boolean;
}) {
    return (
        <label className="flex items-start justify-between gap-4 rounded-lg border p-4">
            <span>
                <span className="font-medium">{label}</span>
                <span className="mt-1 block text-sm text-muted-foreground">
                    {description}
                </span>
            </span>
            <select
                name={name}
                defaultValue={enabled ? '1' : '0'}
                className="h-9 rounded-md border bg-background px-2 text-sm"
            >
                <option value="1">On</option>
                <option value="0">Off</option>
            </select>
        </label>
    );
}

AiPreferences.layout = {
    breadcrumbs: [{ title: 'AI preferences', href: '/settings/ai' }],
};
