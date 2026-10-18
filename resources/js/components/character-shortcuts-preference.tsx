import { useId } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    setCharacterShortcutsEnabled,
    useCharacterShortcuts,
} from '@/hooks/use-character-shortcuts';

export function CharacterShortcutsPreference() {
    const id = useId();
    const enabled = useCharacterShortcuts();

    return (
        <div className="flex items-start gap-3">
            <Checkbox
                id={id}
                checked={enabled}
                onCheckedChange={(checked) =>
                    setCharacterShortcutsEnabled(checked === true)
                }
                aria-describedby={`${id}-description`}
                className="mt-0.5"
                data-test="character-shortcuts-checkbox"
            />
            <div className="space-y-2">
                <Label htmlFor={id}>Enable single-character shortcuts</Label>
                <p
                    id={`${id}-description`}
                    className="text-sm text-muted-foreground"
                >
                    Use keys such as C, J, K and /, including actions on focused
                    commits. Modifier shortcuts stay available. Remembered in
                    this browser.
                </p>
            </div>
        </div>
    );
}
