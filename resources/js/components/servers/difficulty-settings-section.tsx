import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

type DifficultyData = {
    reduced_damage: boolean;
    group_indicators: number;
    friendly_tags: number;
    enemy_tags: number;
    detected_mines: number;
    commands: number;
    waypoints: number;
    tactical_ping: number;
    weapon_info: number;
    stance_indicator: number;
    stamina_bar: boolean;
    weapon_crosshair: boolean;
    vision_aid: boolean;
    third_person_view: number;
    camera_shake: boolean;
    score_table: boolean;
    death_messages: boolean;
    von_id: boolean;
    map_content: boolean;
    auto_report: boolean;
    ai_level_preset: number;
    skill_ai: string;
    precision_ai: string;
};

type DifficultySettingsSectionProps = {
    data: DifficultyData;
    onChange: <K extends keyof DifficultyData>(
        key: K,
        value: DifficultyData[K],
    ) => void;
};

function SegmentedField({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: number;
    onChange: (value: number) => void;
    options: { value: string; label: string }[];
}) {
    return (
        <div className="space-y-1.5">
            <Label className="text-xs">{label}</Label>
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                value={String(value)}
                onValueChange={(v) => v && onChange(Number(v))}
                className="w-full"
            >
                {options.map((opt) => (
                    <ToggleGroupItem
                        key={opt.value}
                        value={opt.value}
                        className="flex-1 text-xs"
                    >
                        {opt.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
        </div>
    );
}

const NEVER_LIMITED_ALWAYS = [
    { value: '0', label: 'Never' },
    { value: '1', label: 'Limited' },
    { value: '2', label: 'Always' },
];

const NEVER_FADE_ALWAYS = [
    { value: '0', label: 'Never' },
    { value: '1', label: 'Fade' },
    { value: '2', label: 'Always' },
];

export default function DifficultySettingsSection({
    data,
    onChange,
}: DifficultySettingsSectionProps) {
    const [open, setOpen] = useState(false);

    return (
        <div className="rounded-lg border">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="flex w-full items-center gap-3 px-4 py-3 text-left"
            >
                <div className="flex-1">
                    <span className="text-base font-semibold">
                        Difficulty Settings
                    </span>
                    <span className="block text-xs text-muted-foreground">
                        HUD elements, third-person view, AI behavior, and
                        gameplay options.
                    </span>
                </div>
                <ChevronDown
                    className={`size-4 text-muted-foreground transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
                />
            </button>

            {open && (
                <div className="space-y-6 border-t px-4 py-4">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        {/* Column 1: Boolean toggles */}
                        <div className="space-y-3">
                            {(
                                [
                                    ['reduced_damage', 'Reduced damage'],
                                    ['stamina_bar', 'Stamina bar'],
                                    ['weapon_crosshair', 'Weapon crosshair'],
                                    ['vision_aid', 'Vision aid'],
                                    ['camera_shake', 'Camera shake'],
                                    ['score_table', 'Score table'],
                                    ['death_messages', 'Killed by'],
                                    ['von_id', 'VON ID'],
                                    ['map_content', 'Extended map content'],
                                    ['auto_report', 'Auto report'],
                                ] as const
                            ).map(([key, label]) => (
                                <div
                                    key={key}
                                    className="flex items-center gap-2"
                                >
                                    <Switch
                                        checked={data[key] as boolean}
                                        onCheckedChange={(v) =>
                                            onChange(key, v)
                                        }
                                    />
                                    <Label>{label}</Label>
                                </div>
                            ))}
                        </div>

                        {/* Column 2: Situational awareness + AI */}
                        <div className="space-y-4">
                            <SegmentedField
                                label="Group indicators"
                                value={data.group_indicators}
                                onChange={(v) =>
                                    onChange('group_indicators', v)
                                }
                                options={NEVER_LIMITED_ALWAYS}
                            />
                            <SegmentedField
                                label="Friendly tags"
                                value={data.friendly_tags}
                                onChange={(v) => onChange('friendly_tags', v)}
                                options={NEVER_LIMITED_ALWAYS}
                            />
                            <SegmentedField
                                label="Enemy tags"
                                value={data.enemy_tags}
                                onChange={(v) => onChange('enemy_tags', v)}
                                options={NEVER_LIMITED_ALWAYS}
                            />
                            <SegmentedField
                                label="Detected mines"
                                value={data.detected_mines}
                                onChange={(v) => onChange('detected_mines', v)}
                                options={NEVER_LIMITED_ALWAYS}
                            />

                            <Separator />

                            <SegmentedField
                                label="AI level preset"
                                value={data.ai_level_preset}
                                onChange={(v) => onChange('ai_level_preset', v)}
                                options={[
                                    { value: '0', label: 'Low' },
                                    { value: '1', label: 'Normal' },
                                    { value: '2', label: 'High' },
                                    { value: '3', label: 'Custom' },
                                ]}
                            />
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label className="text-xs">AI Skill</Label>
                                    <Input
                                        type="number"
                                        min="0"
                                        max="1"
                                        step="0.05"
                                        value={data.skill_ai}
                                        onChange={(e) =>
                                            onChange('skill_ai', e.target.value)
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs">
                                        AI Precision
                                    </Label>
                                    <Input
                                        type="number"
                                        min="0"
                                        max="1"
                                        step="0.05"
                                        value={data.precision_ai}
                                        onChange={(e) =>
                                            onChange(
                                                'precision_ai',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Column 3: HUD & view settings */}
                        <div className="space-y-4">
                            <SegmentedField
                                label="Commands"
                                value={data.commands}
                                onChange={(v) => onChange('commands', v)}
                                options={NEVER_FADE_ALWAYS}
                            />
                            <SegmentedField
                                label="Waypoints"
                                value={data.waypoints}
                                onChange={(v) => onChange('waypoints', v)}
                                options={NEVER_FADE_ALWAYS}
                            />
                            <SegmentedField
                                label="Weapon info"
                                value={data.weapon_info}
                                onChange={(v) => onChange('weapon_info', v)}
                                options={NEVER_FADE_ALWAYS}
                            />
                            <SegmentedField
                                label="Stance indicator"
                                value={data.stance_indicator}
                                onChange={(v) =>
                                    onChange('stance_indicator', v)
                                }
                                options={NEVER_FADE_ALWAYS}
                            />
                            <SegmentedField
                                label="Third person view"
                                value={data.third_person_view}
                                onChange={(v) =>
                                    onChange('third_person_view', v)
                                }
                                options={[
                                    { value: '0', label: 'Disabled' },
                                    { value: '1', label: 'Enabled' },
                                    { value: '2', label: 'Vehicles' },
                                ]}
                            />
                            <SegmentedField
                                label="Tactical ping"
                                value={data.tactical_ping}
                                onChange={(v) => onChange('tactical_ping', v)}
                                options={[
                                    { value: '0', label: 'Off' },
                                    { value: '1', label: '3D' },
                                    { value: '2', label: 'Map' },
                                    { value: '3', label: 'Both' },
                                ]}
                            />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
