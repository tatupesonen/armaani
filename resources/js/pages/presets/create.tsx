import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { index as presetsIndex, store } from '@/routes/presets';
import type {
    BreadcrumbItem,
    ModSection,
    RegisteredMod,
    WorkshopMod,
} from '@/types';

type GameTypeOption = {
    value: string;
    label: string;
    supportsWorkshopMods: boolean;
    modSections: ModSection[];
};

type Props = {
    gameTypes: GameTypeOption[];
    workshopMods: WorkshopMod[];
    registeredMods: Record<string, RegisteredMod[]>;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mod Presets', href: presetsIndex() },
    { title: 'Create', href: '#' },
];

export default function PresetCreate({
    gameTypes,
    workshopMods,
    registeredMods,
}: Props) {
    const firstGameType = gameTypes[0]?.value ?? '';

    // Build initial form data including registered mod fields from all game types
    const initialData: Record<string, string | number[]> = {
        game_type: firstGameType,
        name: '',
        mod_ids: [],
    };
    for (const gt of gameTypes) {
        for (const section of gt.modSections) {
            if (section.type === 'registered') {
                initialData[section.formField] = [];
            }
        }
    }

    const form = useForm(initialData);

    const currentGameType = gameTypes.find(
        (gt) => gt.value === form.data.game_type,
    );
    const sections = currentGameType?.modSections ?? [];

    // Workshop mods filtered to current game type
    const availableWorkshopMods = workshopMods.filter(
        (m) => m.game_type === form.data.game_type,
    );

    function toggleMod(fieldName: string, modId: number) {
        const current = (form.data[fieldName] as number[]) ?? [];
        const updated = current.includes(modId)
            ? current.filter((id) => id !== modId)
            : [...current, modId];
        form.setData(fieldName, updated);
    }

    function handleGameTypeChange(value: string) {
        const resetData: Record<string, string | number[]> = {
            game_type: value,
            name: form.data.name as string,
            mod_ids: [],
        };
        // Reset all registered mod ID fields
        for (const gt of gameTypes) {
            for (const section of gt.modSections) {
                if (section.type === 'registered') {
                    resetData[section.formField] = [];
                }
            }
        }
        form.setData(resetData);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(store.url());
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Preset" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Create Mod Preset"
                    description="Select mods to include in this preset."
                />

                <form onSubmit={submit} className="max-w-2xl space-y-6">
                    <div className="space-y-2">
                        <Label>Game Type</Label>
                        <Select
                            value={form.data.game_type as string}
                            onValueChange={handleGameTypeChange}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {gameTypes.map((gt) => (
                                    <SelectItem key={gt.value} value={gt.value}>
                                        {gt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>Preset Name</Label>
                        <Input
                            value={form.data.name as string}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                            placeholder="My Preset"
                        />
                        {form.errors.name && (
                            <p className="text-sm text-destructive">
                                {form.errors.name}
                            </p>
                        )}
                    </div>

                    {/* Dynamic mod sections from handler */}
                    {sections.map((section) => {
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
                                    {availableWorkshopMods.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No workshop mods available for this
                                            game type.
                                        </p>
                                    ) : (
                                        <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border p-2">
                                            {availableWorkshopMods.map(
                                                (mod) => (
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
                                                ),
                                            )}
                                        </div>
                                    )}
                                </div>
                            );
                        }

                        if (section.type === 'registered') {
                            const selectedIds =
                                (form.data[section.formField] as number[]) ??
                                [];
                            const gameType = form.data.game_type as string;
                            const availableMods =
                                registeredMods[gameType] ?? [];

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
                            Create Preset
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
