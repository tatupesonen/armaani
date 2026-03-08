import { Head, Link, usePoll } from '@inertiajs/react';
import {
    HardDrive,
    Map,
    Package,
    Server as ServerIcon,
    Shapes,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import {
    formatBytes,
    gameTypeLabel,
    serverStatusVariant,
    usageBarColor,
} from '@/lib/utils';
import { dashboard, steamSettings } from '@/routes';
import { index as gameInstallsIndex } from '@/routes/game-installs';
import { index as missionsIndex } from '@/routes/missions';
import { index as modsIndex } from '@/routes/mods';
import { index as presetsIndex } from '@/routes/presets';
import { index as serversIndex } from '@/routes/servers';
import type {
    BreadcrumbItem,
    CpuInfo,
    DashboardGameInstallStats,
    DashboardModStats,
    DashboardQueueStats,
    DashboardServerStats,
    DiskUsage,
    MemoryUsage,
    Server,
} from '@/types';

type DashboardProps = {
    serverStats: DashboardServerStats;
    gameInstallStats: DashboardGameInstallStats;
    modStats: DashboardModStats;
    presetCount: number;
    missionCount: number;
    queueStats: DashboardQueueStats;
    steamConfigured: boolean;
    servers: Server[];
    diskUsage: DiskUsage;
    memoryUsage: MemoryUsage;
    cpuInfo: CpuInfo;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

function StatCard({
    title,
    value,
    subtitle,
    icon: Icon,
    href,
}: {
    title: string;
    value: string | number;
    subtitle?: string;
    icon: React.ComponentType<{ className?: string }>;
    href: string;
}) {
    return (
        <Link href={href} className="group">
            <Card className="transition-colors group-hover:border-foreground/20">
                <CardHeader className="flex flex-row items-center justify-between pb-2">
                    <CardTitle className="text-sm font-medium text-muted-foreground">
                        {title}
                    </CardTitle>
                    <Icon className="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{value}</div>
                    {subtitle && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            {subtitle}
                        </p>
                    )}
                </CardContent>
            </Card>
        </Link>
    );
}

function UsageCard({
    title,
    percent,
    used,
    total,
}: {
    title: string;
    percent: number;
    used: number;
    total: number;
}) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="flex items-baseline justify-between">
                    <span className="text-2xl font-bold">{percent}%</span>
                    <span className="text-xs text-muted-foreground">
                        {formatBytes(used)} / {formatBytes(total)}
                    </span>
                </div>
                <div className="mt-3 h-2 overflow-hidden rounded-full bg-secondary">
                    <div
                        className={`h-full rounded-full transition-all ${usageBarColor(percent)}`}
                        style={{ width: `${Math.min(percent, 100)}%` }}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    serverStats,
    gameInstallStats,
    modStats,
    presetCount,
    missionCount,
    queueStats,
    steamConfigured,
    servers,
    diskUsage,
    memoryUsage,
    cpuInfo,
}: DashboardProps) {
    usePoll(30000);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4">
                {/* Stat Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Servers"
                        value={serverStats.total}
                        subtitle={`${serverStats.running} running, ${serverStats.stopped} stopped`}
                        icon={ServerIcon}
                        href={serversIndex.url()}
                    />
                    <StatCard
                        title="Game Installs"
                        value={gameInstallStats.total}
                        subtitle={`${gameInstallStats.installed} installed, ${formatBytes(gameInstallStats.disk_size)}`}
                        icon={HardDrive}
                        href={gameInstallsIndex.url()}
                    />
                    <StatCard
                        title="Workshop Mods"
                        value={modStats.total}
                        subtitle={`${modStats.installed} installed, ${formatBytes(modStats.total_size)}`}
                        icon={Package}
                        href={modsIndex.url()}
                    />
                    <StatCard
                        title="Missions"
                        value={missionCount}
                        subtitle="PBO files"
                        icon={Map}
                        href={missionsIndex.url()}
                    />
                </div>

                {/* System Resources */}
                <div>
                    <h3 className="mb-3 text-sm font-medium text-muted-foreground">
                        System Resources
                    </h3>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <UsageCard
                            title="Disk Usage"
                            percent={diskUsage.percent}
                            used={diskUsage.used}
                            total={diskUsage.total}
                        />
                        <UsageCard
                            title="Memory"
                            percent={memoryUsage.percent}
                            used={memoryUsage.used}
                            total={memoryUsage.total}
                        />
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">
                                    CPU Load
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-baseline justify-between">
                                    <span className="text-2xl font-bold">
                                        {cpuInfo.percent}%
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {cpuInfo.cores} cores
                                    </span>
                                </div>
                                <div className="mt-3 h-2 overflow-hidden rounded-full bg-secondary">
                                    <div
                                        className={`h-full rounded-full transition-all ${usageBarColor(cpuInfo.percent)}`}
                                        style={{
                                            width: `${Math.min(cpuInfo.percent, 100)}%`,
                                        }}
                                    />
                                </div>
                                <div className="mt-2 flex justify-between text-xs text-muted-foreground">
                                    <span>1m: {cpuInfo.load_1}</span>
                                    <span>5m: {cpuInfo.load_5}</span>
                                    <span>15m: {cpuInfo.load_15}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Server Status + Quick Info */}
                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Server Status Table */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Server Status</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {servers.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No servers configured yet.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-muted-foreground">
                                                <th className="pb-2 font-medium">
                                                    Status
                                                </th>
                                                <th className="pb-2 font-medium">
                                                    Name
                                                </th>
                                                <th className="pb-2 font-medium">
                                                    Game
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Port
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {servers.map((server) => (
                                                <tr
                                                    key={server.id}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="py-2">
                                                        <Badge
                                                            variant={serverStatusVariant(
                                                                server.status,
                                                            )}
                                                        >
                                                            {server.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-2 font-medium">
                                                        {server.name}
                                                    </td>
                                                    <td className="py-2 text-muted-foreground">
                                                        {server.game_install
                                                            ? server
                                                                  .game_install
                                                                  .name
                                                            : gameTypeLabel(
                                                                  server.game_type,
                                                              )}
                                                    </td>
                                                    <td className="py-2 text-right tabular-nums">
                                                        {server.port}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Quick Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Quick Info</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Link
                                href={presetsIndex()}
                                className="flex items-center justify-between text-sm hover:underline"
                            >
                                <span className="flex items-center gap-2 text-muted-foreground">
                                    <Shapes className="size-4" />
                                    Mod Presets
                                </span>
                                <span className="font-medium">
                                    {presetCount}
                                </span>
                            </Link>

                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Queue Jobs
                                </span>
                                <span className="font-medium">
                                    {queueStats.pending}
                                </span>
                            </div>

                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Failed Jobs
                                </span>
                                <span
                                    className={`font-medium ${queueStats.failed > 0 ? 'text-red-500' : ''}`}
                                >
                                    {queueStats.failed}
                                </span>
                            </div>

                            <div className="border-t pt-4">
                                <Link
                                    href={steamSettings()}
                                    className="flex items-center justify-between text-sm hover:underline"
                                >
                                    <span className="text-muted-foreground">
                                        Steam Account
                                    </span>
                                    <Badge
                                        variant={
                                            steamConfigured
                                                ? 'success'
                                                : 'warning'
                                        }
                                    >
                                        {steamConfigured
                                            ? 'Configured'
                                            : 'Not Set'}
                                    </Badge>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
