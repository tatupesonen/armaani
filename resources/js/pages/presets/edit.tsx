import { Head, useForm, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { index as presetsIndex, update } from '@/routes/presets';
import type {
    BreadcrumbItem,
    ModPreset,
    ModSection,
    RegisteredMod,
    WorkshopMod,
} from '@/types';

type Props = {
    preset: ModPreset;
    modSections: ModSection[];
    workshopMods: WorkshopMod[];
    registeredMods: Record<string, RegisteredMod[]>;
};

function toSnakeCase(str: string): string {
    return str.replace(/([A-Z])/g, '_$1').toLowerCase();
}

export default function PresetEdit({
    preset,
    modSections,
    workshopMods,
    registeredMods,
}: Props) {
    const { gameTypeLabels } = usePage().props;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Mod Presets', href: presetsIndex() },
        { title: preset.name, href: '#' },
    ];

    // Build initial form data from preset's loaded relationships
    const initialData: Record<string, string | number[]> = {
        name: preset.name,
        mod_ids: preset.mods?.map((m) => m.id) ?? [],
    };
    for (const section of modSections) {
        if (section.type === 'registered') {
            const jsonKey = toSnakeCase(section.relationship);
            const presetMods = (preset as Record<string, unknown>)[jsonKey] as
                | RegisteredMod[]
                | undefined;
            initialData[section.formField] = presetMods?.map((m) => m.id) ?? [];
        }
    }

    const form = useForm(initialData);

    function toggleMod(fieldName: string, modId: number) {
        const current = (form.data[fieldName] as number[]) ?? [];
        const updated = current.includes(modId)
            ? current.filter((id) => id !== modId)
            : [...current, modId];
        form.setData(fieldName, updated);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.put(update.url(preset.id));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${preset.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title={`Edit Preset: ${preset.name}`}
                    description={`Game: ${gameTypeLabels[preset.game_type] ?? preset.game_type}`}
                />

                <form onSubmit={submit} className="max-w-2xl space-y-6">
                    <div className="space-y-2">
                        <Label>Preset Name</Label>
                        <Input
                            value={form.data.name as string}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                        />
                        {form.errors.name && (
                            <p className="text-sm text-destructive">
                                {form.errors.name}
                            </p>
                        )}
                    </div>

                    {modSections.map((section) => {
                        if (section.type === 'workshop') {
                            const selectedIds =
                                (form.data.mod_ids as number[]) ?? [];
                            return (
                                <div
                                    key={section.relationship}
                                    className="space-y-2"
                                >
                                    <Label>
                                        {section.label} ({selectedIds.length}{' '}
                                        selected)
                                    </Label>
                                    {workshopMods.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No workshop mods available.
                                        </p>
                                    ) : (
                                        <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border p-2">
                                            {workshopMods.map((mod) => (
                                                <label
                                                    key={mod.id}
                                                    className="flex cursor-pointer items-center gap-2 rounded p-1.5 hover:bg-muted"
                                                >
                                                    <Checkbox
                                                        checked={selectedIds.includes(
                                                            mod.id,
                                                        )}
                                                        onCheckedChange={() =>
                                                            toggleMod(
                                                                'mod_ids',
                                                                mod.id,
                                                            )
                                                        }
                                                    />
                                                    <span className="text-sm">
                                                        {mod.name ||
                                                            `Mod #${mod.workshop_id}`}
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            );
                        }

                        if (section.type === 'registered') {
                            const selectedIds =
                                (form.data[section.formField] as number[]) ??
                                [];
                            const availableMods =
                                registeredMods[preset.game_type] ?? [];

                            return (
                                <div
                                    key={section.relationship}
                                    className="space-y-2"
                                >
                                    <Label>
                                        {section.label} ({selectedIds.length}{' '}
                                        selected)
                                    </Label>
                                    {availableMods.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No {section.label.toLowerCase()}{' '}
                                            available.
                                        </p>
                                    ) : (
                                        <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border p-2">
                                            {availableMods.map((mod) => (
                                                <label
                                                    key={mod.id}
                                                    className="flex cursor-pointer items-center gap-2 rounded p-1.5 hover:bg-muted"
                                                >
                                                    <Checkbox
                                                        checked={selectedIds.includes(
                                                            mod.id,
                                                        )}
                                                        onCheckedChange={() =>
                                                            toggleMod(
                                                                section.formField,
                                                                mod.id,
                                                            )
                                                        }
                                                    />
                                                    <span className="text-sm">
                                                        {mod.name}
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            );
                        }

                        return null;
                    })}

                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            Save Changes
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
