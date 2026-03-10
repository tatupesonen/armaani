<?php

namespace App\Attributes;

use App\Enums\GameType;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class HandlesGame
{
    public function __construct(public GameType $gameType) {}
}
