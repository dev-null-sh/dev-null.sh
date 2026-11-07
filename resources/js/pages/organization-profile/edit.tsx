import { Form, Head, router } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import OrganizationLaneController from '@/actions/App/Http/Controllers/OrganizationLaneController';
import OrganizationPeopleVisibilityController from '@/actions/App/Http/Controllers/OrganizationPeopleVisibilityController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { LaneOptions } from '@/components/lane-picker';
import { OrganizationAvatar } from '@/components/organization-avatar';
import { SegmentedControl } from '@/components/segmented-control';
import { Button } from '@/components/ui/button';
import { usePicked } from '@/hooks/use-picked';
import AppLayout from '@/layouts/app-layout';
import OrganizationSettingsLayout from '@/layouts/organization-settings/layout';
import { organizationLaneColor, withViewTransition } from '@/lib/utils';
import type { Lane, Organization } from '@/types';

export default function Edit({
    organization,
    showsPeople,
}: {
    organization: Organization;
    showsPeople: boolean;
}) {
    const { lane } = organization;
    const [selected, setSelected] = useState<Lane | null>(lane);
    const { picked, pick } = usePicked<boolean>();
    const peopleShown = picked ?? showsPeople;

    const selectPeopleShown = (value: boolean): void => {
        if (value === peopleShown) {
            return;
        }

        let finish = (): void => {};

        void withViewTransition(() => {
            finish = pick(value);
        }).then(() =>
            router.put(
                OrganizationPeopleVisibilityController.update.url(
                    organization.id,
                ),
                { shows_people: value },
                {
                    preserveScroll: true,
                    onFinish: () => finish(),
                    onSuccess: () => router.flushAll(),
                },
            ),
        );
    };

    return (
        <>
            <Head title={`Profile of @${organization.handle}`} />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="Color"
                    description={`@${organization.handle}'s lane: the ring around its avatar, the dot before its handle, and its link preview. /dev/null's blue until you choose another.`}
                />

                <div
                    aria-hidden
                    className="flex items-center gap-4 rounded-lg border p-4"
                    style={
                        {
                            '--lane': organizationLaneColor(selected),
                        } as CSSProperties
                    }
                    data-test="organization-lane-preview"
                >
                    <OrganizationAvatar
                        organization={organization}
                        className="size-10 shrink-0 ring-2 ring-(--lane) ring-offset-2 ring-offset-background"
                    />
                    <div className="min-w-0">
                        <p className="truncate font-semibold">
                            {organization.name}
                        </p>
                        <p className="flex items-center gap-1.5 font-mono text-sm text-muted-foreground">
                            <span className="size-2 shrink-0 rounded-full bg-(--lane) transition-colors duration-200" />
                            <span className="truncate">
                                @{organization.handle}
                            </span>
                        </p>
                    </div>
                </div>

                <Form
                    {...OrganizationLaneController.update.form(organization.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => router.flushAll()}
                    className="mt-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <LaneOptions
                                selected={selected}
                                automaticLane={null}
                                onSelect={(choice) => {
                                    // Dragging through the custom color picker shouldn't animate every step...
                                    if (
                                        typeof choice === 'string' &&
                                        typeof selected === 'string'
                                    ) {
                                        setSelected(choice);

                                        return;
                                    }

                                    void withViewTransition(() =>
                                        setSelected(choice),
                                    );
                                }}
                            />

                            <InputError
                                message={errors.lane ?? errors.color}
                                className="mt-2"
                            />

                            <Button
                                type="submit"
                                className="mt-5"
                                disabled={processing || selected === lane}
                                data-test="save-organization-lane-button"
                            >
                                Save color
                            </Button>
                        </>
                    )}
                </Form>
            </section>

            <section className="border-t px-4 py-6 sm:px-6">
                <Heading
                    title="People"
                    description={`A People tab on @${organization.handle}'s page, listing its members to everyone. Hidden until you show it: only members see who the members are.`}
                />
                <SegmentedControl
                    label="People"
                    name="shows-people"
                    options={[
                        { value: true, icon: Eye, label: 'Show' },
                        { value: false, icon: EyeOff, label: 'Hide' },
                    ]}
                    value={peopleShown}
                    onChange={selectPeopleShown}
                />
            </section>
        </>
    );
}

Edit.layout = [AppLayout, OrganizationSettingsLayout];
