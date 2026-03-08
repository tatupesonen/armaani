<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('server-log.{serverId}', function ($user) {
    return $user !== null;
});

Broadcast::channel('game-install.{gameInstallId}', function ($user) {
    return $user !== null;
});

Broadcast::channel('mod-download.{modId}', function ($user) {
    return $user !== null;
});

Broadcast::channel('servers', function ($user) {
    return $user !== null;
});
