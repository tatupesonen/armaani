import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/servers';
import type { GameInstall, ModPreset } from '@/types';

type GameTypeOption = {
    value: string;
    label: string;
    defaultPort: number;
    defaultQueryPort: number;
    supportsHeadlessClients: boolean;
    supportsWorkshopMods: boolean;
    supportsMissionUpload: boolean;
};

type CreateServerDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    gameTypes: GameTypeOption[];
    gameInstalls: GameInstall[];
    presets: ModPreset[];
};

export default function CreateServerDialog({
    open,
    onOpenChange,
    gameTypes,
    gameInstalls,
    presets,
}: CreateServerDialogProps) {
    const form = useForm({
        game_type: 'arma3',
        name: '',
        port: 2302,
        query_port: 2303,
        max_players: 32,
        password: '',
        admin_password: '',
        description: '',
        active_preset_id: '' as string,
        game_install_id: '' as string,
        additional_params: '',
        verify_signatures: true,
        allowed_file_patching: false,
        battle_eye: true,
        persistent: false,
        von_enabled: true,
        additional_server_options: '',
    });

    const isArma3 = form.data.game_type === 'arma3';
    const filteredInstalls = gameInstalls.filter(
        (gi) => gi.game_type === form.data.game_type,
    );
    const filteredPresets = presets.filter(
        (p) => p.game_type === form.data.game_type,
    );

    function onGameTypeChange(value: string) {
        const gt = gameTypes.find((g) => g.value === value);
        const defaultInstall = gameInstalls.find(
            (gi) => gi.game_type === value,
        );
        form.setData((prev) => ({
            ...prev,
            game_type: value,
            port: gt?.defaultPort ?? prev.port,
            query_port: gt?.defaultQueryPort ?? prev.query_port,
            game_install_id: defaultInstall?.id?.toString() ?? '',
            active_preset_id: '',
        }));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            active_preset_id: data.active_preset_id || null,
            game_install_id: Number(data.game_install_id),
        }));
        form.post(store.url(), {
            onSuccess: () => onOpenChange(false),
        });
    }

    function handleOpenChange(isOpen: boolean) {
        if (isOpen) {
            const gt = gameTypes[0];
            const defaultInstall = gameInstalls.find(
                (gi) => gi.game_type === gt.value,
            );
            form.setData({
                game_type: gt.value,
                name: '',
                port: gt.defaultPort,
                query_port: gt.defaultQueryPort,
                max_players: 32,
                password: '',
                admin_password: '',
                description: '',
                active_preset_id: '',
                game_install_id: defaultInstall?.id?.toString() ?? '',
                additional_params: '',
                verify_signatures: true,
                allowed_file_patching: false,
                battle_eye: true,
                persistent: false,
                von_enabled: true,
                additional_server_options: '',
            });
            form.clearErrors();
        }
        onOpenChange(isOpen);
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>New Server</DialogTitle>
                    <DialogDescription>
                        Configure and create a new game server instance.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Game</Label>
                        <Select
                            value={form.data.game_type}
                            onValueChange={onGameTypeChange}
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
                        <Label>Name</Label>
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

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>Game Port</Label>
                            <Input
                                type="number"
                                value={form.data.port}
                                onChange={(e) => {
                                    const port = Number(e.target.value);
                                    form.setData((prev) => ({
                                        ...prev,
                                        port,
                                        query_port: port + 1,
                                    }));
                                }}
                                required
                            />
                            {form.errors.port && (
                                <p className="text-sm text-destructive">
                                    {form.errors.port}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label>Query Port</Label>
                            <Input
                                type="number"
                                value={form.data.query_port}
                                onChange={(e) =>
                                    form.setData(
                                        'query_port',
                                        Number(e.target.value),
                                    )
                                }
                                required
                            />
                            {form.errors.query_port && (
                                <p className="text-sm text-destructive">
                                    {form.errors.query_port}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label>Max Players</Label>
                        <Input
                            type="number"
                            value={form.data.max_players}
                            onChange={(e) =>
                                form.setData(
                                    'max_players',
                                    Number(e.target.value),
                                )
                            }
                            required
                        />
                    </div>

                    <div className="space-y-2">
                        <Label>Game Install</Label>
                        <Select
                            value={form.data.game_install_id}
                            onValueChange={(v) =>
                                form.setData('game_install_id', v)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select install..." />
                            </SelectTrigger>
                            <SelectContent>
                                {filteredInstalls.map((gi) => (
                                    <SelectItem
                                        key={gi.id}
                                        value={gi.id.toString()}
                                    >
                                        {gi.name} ({gi.branch})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.game_install_id && (
                            <p className="text-sm text-destructive">
                                {form.errors.game_install_id}
                            </p>
                        )}
                    </div>

                    {filteredPresets.length > 0 && (
                        <div className="space-y-2">
                            <Label>Mod Preset (optional)</Label>
                            <Select
                                value={form.data.active_preset_id}
                                onValueChange={(v) =>
                                    form.setData('active_preset_id', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="None" />
                                </SelectTrigger>
                                <SelectContent>
                                    {filteredPresets.map((p) => (
                                        <SelectItem
                                            key={p.id}
                                            value={p.id.toString()}
                                        >
                                            {p.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>Password (optional)</Label>
                            <Input
                                value={form.data.password}
                                onChange={(e) =>
                                    form.setData('password', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Admin Password (optional)</Label>
                            <Input
                                value={form.data.admin_password}
                                onChange={(e) =>
                                    form.setData(
                                        'admin_password',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    {isArma3 && (
                        <div className="space-y-3 rounded-lg border p-3">
                            <p className="text-sm font-medium">
                                Arma 3 Options
                            </p>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={form.data.verify_signatures}
                                        onCheckedChange={(v) =>
                                            form.setData('verify_signatures', v)
                                        }
                                    />
                                    <Label>Verify Signatures</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={form.data.battle_eye}
                                        onCheckedChange={(v) =>
                                            form.setData('battle_eye', v)
                                        }
                                    />
                                    <Label>BattlEye</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={form.data.von_enabled}
                                        onCheckedChange={(v) =>
                                            form.setData('von_enabled', v)
                                        }
                                    />
                                    <Label>VON</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={form.data.persistent}
                                        onCheckedChange={(v) =>
                                            form.setData('persistent', v)
                                        }
                                    />
                                    <Label>Persistent</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={
                                            form.data.allowed_file_patching
                                        }
                                        onCheckedChange={(v) =>
                                            form.setData(
                                                'allowed_file_patching',
                                                v,
                                            )
                                        }
                                    />
                                    <Label>File Patching</Label>
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label>Additional Parameters (optional)</Label>
                        <Input
                            value={form.data.additional_params}
                            onChange={(e) =>
                                form.setData(
                                    'additional_params',
                                    e.target.value,
                                )
                            }
                            placeholder="-noSplash -hugepages"
                        />
                    </div>

                    {isArma3 && (
                        <div className="space-y-2">
                            <Label>
                                Additional server.cfg Options (optional)
                            </Label>
                            <Textarea
                                value={form.data.additional_server_options}
                                onChange={(e) =>
                                    form.setData(
                                        'additional_server_options',
                                        e.target.value,
                                    )
                                }
                                rows={3}
                                placeholder="Raw config directives appended to server.cfg"
                            />
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Create Server
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
