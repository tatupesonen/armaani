<?php

namespace App\Attributes;

use Attribute;

/**
 * Marks a game handler as work-in-progress.
 *
 * Handlers with this attribute are only loaded in the local
 * environment and are excluded from production.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Beta {}
