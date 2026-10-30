import { Form, router } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import OrganizationLaneController from '@/actions/App/Http/Controllers/OrganizationLaneController';
import InputError from '@/components/input-error';
import { LaneOptions } from '@/components/lane-picker';
import { OrganizationAvatar } from '@/components/organization-avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { organizationLaneColor, withViewTransition } from '@/lib/utils';
import type { Lane, Organization } from '@/types';

export function OrganizationLaneDialog({
    organization,
    lane,
    open,
    onOpenChange,
}: {
    organization: Organization & { id: string };
    lane: Lane | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [selected, setSelected] = useState<Lane | null>(lane);

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => {
                onOpenChange(isOpen);

                if (!isOpen) {
                    setSelected(lane);
                }
            }}
        >
            <DialogContent>
                <DialogTitle>Color</DialogTitle>
                <DialogDescription>
                    @{organization.handle}'s lane: the ring around its avatar
                    and the dot before its handle.
                </DialogDescription>

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
                    onSuccess={() => {
                        onOpenChange(false);
                        router.flushAll();
                    }}
                >
                    {({ processing, errors }) => (
                        <>
                            <LaneOptions
                                selected={selected}
                                automaticLane={null}
                                onSelect={(choice) => {
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

                            <DialogFooter className="mt-5">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancel</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={processing || selected === lane}
                                    data-test="save-organization-lane-button"
                                >
                                    Save color
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
