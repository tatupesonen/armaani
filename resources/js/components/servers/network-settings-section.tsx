import { ChevronDown, RotateCcw, Zap } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type NetworkData = {
    max_msg_send: number;
    max_size_guaranteed: number;
    max_size_nonguaranteed: number;
    min_bandwidth: string;
    max_bandwidth: string;
    min_error_to_send: string;
    min_error_to_send_near: string;
    max_packet_size: number;
    max_custom_file_size: number;
    terrain_grid: string;
    view_distance: number;
};

type NetworkSettingsSectionProps = {
    data: NetworkData;
    onChange: <K extends keyof NetworkData>(
        key: K,
        value: NetworkData[K],
    ) => void;
    onBatchChange: (values: Partial<NetworkData>) => void;
    errors?: Partial<Record<keyof NetworkData, string>>;
};

const DEFAULT_PRESET: NetworkData = {
    max_msg_send: 128,
    max_size_guaranteed: 512,
    max_size_nonguaranteed: 256,
    min_bandwidth: '131072',
    max_bandwidth: '10000000000',
    min_error_to_send: '0.0010',
    min_error_to_send_near: '0.0100',
    max_packet_size: 1400,
    max_custom_file_size: 0,
    terrain_grid: '25.0000',
    view_distance: 0,
};

const HIGH_PERFORMANCE_PRESET: NetworkData = {
    max_msg_send: 2048,
    max_size_guaranteed: 512,
    max_size_nonguaranteed: 256,
    min_bandwidth: '5120000',
    max_bandwidth: '104857600',
    min_error_to_send: '0.0010',
    min_error_to_send_near: '0.0100',
    max_packet_size: 1400,
    max_custom_file_size: 0,
    terrain_grid: '3.1250',
    view_distance: 0,
};

function NetworkField({
    label,
    description,
    children,
    error,
}: {
    label: string;
    description: string;
    children: React.ReactNode;
    error?: string;
}) {
    return (
        <div className="space-y-1.5">
            <Label className="text-xs">{label}</Label>
            {children}
            <p className="text-xs text-muted-foreground">{description}</p>
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

export default function NetworkSettingsSection({
    data,
    onChange,
    onBatchChange,
    errors = {},
}: NetworkSettingsSectionProps) {
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
                        Network Settings
                    </span>
                    <span className="block text-xs text-muted-foreground">
                        Bandwidth, packet sizes, terrain detail, and view
                        distance tuning for server_basic.cfg.
                    </span>
                </div>
                <ChevronDown
                    className={`size-4 text-muted-foreground transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
                />
            </button>

            {open && (
                <div className="space-y-4 border-t px-4 py-4">
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => onBatchChange(DEFAULT_PRESET)}
                        >
                            <RotateCcw className="mr-2 size-3" />
                            Reset to Default
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            onClick={() =>
                                onBatchChange(HIGH_PERFORMANCE_PRESET)
                            }
                        >
                            <Zap className="mr-2 size-3" />
                            Apply High Performance
                        </Button>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <NetworkField
                            label="MaxMsgSend"
                            description="Max packets per simulation cycle. Default: 128, high-perf: 2048."
                            error={errors.max_msg_send}
                        >
                            <Input
                                type="number"
                                min="1"
                                max="10000"
                                value={data.max_msg_send}
                                onChange={(e) =>
                                    onChange(
                                        'max_msg_send',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </NetworkField>
                        <NetworkField
                            label="MaxSizeGuaranteed"
                            description="Max guaranteed packet payload (bytes). Used for non-repetitive events. Default: 512."
                            error={errors.max_size_guaranteed}
                        >
                            <Input
                                type="number"
                                min="1"
                                max="4096"
                                value={data.max_size_guaranteed}
                                onChange={(e) =>
                                    onChange(
                                        'max_size_guaranteed',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </NetworkField>
                        <NetworkField
                            label="MaxSizeNonguaranteed"
                            description="Max non-guaranteed packet payload (bytes). Used for position updates. Default: 256."
                            error={errors.max_size_nonguaranteed}
                        >
                            <Input
                                type="number"
                                min="1"
                                max="4096"
                                value={data.max_size_nonguaranteed}
                                onChange={(e) =>
                                    onChange(
                                        'max_size_nonguaranteed',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </NetworkField>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <NetworkField
                            label="MinBandwidth"
                            description="Guaranteed bandwidth (bps). Default: 131072, high-perf: 5120000."
                            error={errors.min_bandwidth}
                        >
                            <Input
                                type="number"
                                min="0"
                                value={data.min_bandwidth}
                                onChange={(e) =>
                                    onChange('min_bandwidth', e.target.value)
                                }
                            />
                        </NetworkField>
                        <NetworkField
                            label="MaxBandwidth"
                            description="Max bandwidth cap (bps). High-perf: 104857600 (100 Mbps)."
                            error={errors.max_bandwidth}
                        >
                            <Input
                                type="number"
                                min="0"
                                value={data.max_bandwidth}
                                onChange={(e) =>
                                    onChange('max_bandwidth', e.target.value)
                                }
                            />
                        </NetworkField>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <NetworkField
                            label="MinErrorToSend"
                            description="Min error for distant unit updates. Smaller = smoother optics. Default: 0.001."
                            error={errors.min_error_to_send}
                        >
                            <Input
                                inputMode="decimal"
                                value={data.min_error_to_send}
                                onChange={(e) =>
                                    onChange(
                                        'min_error_to_send',
                                        e.target.value,
                                    )
                                }
                            />
                        </NetworkField>
                        <NetworkField
                            label="MinErrorToSendNear"
                            description="Min error for near unit updates. Too large causes warping. Default: 0.01."
                            error={errors.min_error_to_send_near}
                        >
                            <Input
                                inputMode="decimal"
                                value={data.min_error_to_send_near}
                                onChange={(e) =>
                                    onChange(
                                        'min_error_to_send_near',
                                        e.target.value,
                                    )
                                }
                            />
                        </NetworkField>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <NetworkField
                            label="MaxPacketSize"
                            description="Max network packet size. Only change if router enforces lower. Default: 1400."
                            error={errors.max_packet_size}
                        >
                            <Input
                                type="number"
                                min="256"
                                max="1500"
                                value={data.max_packet_size}
                                onChange={(e) =>
                                    onChange(
                                        'max_packet_size',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </NetworkField>
                        <NetworkField
                            label="MaxCustomFileSize"
                            description="Users with custom face/sound larger than this (bytes) are kicked. 0 = no limit."
                            error={errors.max_custom_file_size}
                        >
                            <Input
                                type="number"
                                min="0"
                                value={data.max_custom_file_size}
                                onChange={(e) =>
                                    onChange(
                                        'max_custom_file_size',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </NetworkField>
                        <NetworkField
                            label="View Distance"
                            description="Server-side view distance override (meters). 0 = mission default."
                            error={errors.view_distance}
                        >
                            <Input
                                type="number"
                                min="0"
                                value={data.view_distance}
                                onChange={(e) =>
                                    onChange(
                                        'view_distance',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </NetworkField>
                    </div>

                    <NetworkField
                        label="Terrain Grid"
                        description="Server-side terrain resolution. 25 = low detail, 3.125 = high detail. Default: 25."
                        error={errors.terrain_grid}
                    >
                        <Input
                            inputMode="decimal"
                            value={data.terrain_grid}
                            onChange={(e) =>
                                onChange('terrain_grid', e.target.value)
                            }
                        />
                    </NetworkField>
                </div>
            )}
        </div>
    );
}
