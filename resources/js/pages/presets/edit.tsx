import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { gameTypeLabel } from '@/lib/utils';
import { index as presetsIndex, update } from '@/routes/presets';
import type {
    BreadcrumbItem,
    ModPreset,
    ReforgerMod,
    WorkshopMod,
} from '@/types';

type Props = {
    preset: ModPreset;
    workshopMods: WorkshopMod[];
    reforgerMods: ReforgerMod[];
};

export default function PresetEdit({
    preset,
    workshopMods,
    reforgerMods,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Mod Presets', href: presetsIndex() },
        { title: preset.name, href: '#' },
    ];

    const isReforger = preset.game_type === 'reforger';

    const form = useForm({
        name: preset.name,
        mod_ids: preset.mods?.map((m) => m.id) ?? [],
        reforger_mod_ids: preset.reforger_mods?.map((m) => m.id) ?? [],
    });

    function toggleMod(modId: number) {
        const ids = form.data.mod_ids.includes(modId)
            ? form.data.mod_ids.filter((id) => id !== modId)
            : [...form.data.mod_ids, modId];
        form.setData('mod_ids', ids);
    }

    function toggleReforgerMod(modId: number) {
        const ids = form.data.reforger_mod_ids.includes(modId)
            ? form.data.reforger_mod_ids.filter((id) => id !== modId)
            : [...form.data.reforger_mod_ids, modId];
        form.setData('reforger_mod_ids', ids);
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
                    description={`Game: ${gameTypeLabel(preset.game_type)}`}
                />

                <form onSubmit={submit} className="max-w-2xl space-y-6">
                    <div className="space-y-2">
                        <Label>Preset Name</Label>
                        <Input
                            value={form.data.name}
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

                    {!isReforger && (
                        <div className="space-y-2">
                            <Label>
                                Workshop Mods ({form.data.mod_ids.length}{' '}
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
                                                checked={form.data.mod_ids.includes(
                                                    mod.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleMod(mod.id)
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
                    )}

                    {isReforger && (
                        <div className="space-y-2">
                            <Label>
                                Reforger Mods (
                                {form.data.reforger_mod_ids.length} selected)
                            </Label>
                            {reforgerMods.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No Reforger mods available.
                                </p>
                            ) : (
                                <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border p-2">
                                    {reforgerMods.map((mod) => (
                                        <label
                                            key={mod.id}
                                            className="flex cursor-pointer items-center gap-2 rounded p-1.5 hover:bg-muted"
                                        >
                                            <Checkbox
                                                checked={form.data.reforger_mod_ids.includes(
                                                    mod.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleReforgerMod(mod.id)
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
                    )}

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
