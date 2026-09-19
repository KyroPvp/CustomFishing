<?php

declare(strict_types=1);

namespace pup\fishing\animations;

use pocketmine\network\mcpe\protocol\types\ActorEvent;

enum FishHookAnimationType: int
{
    case BUBBLE = ActorEvent::FISH_HOOK_BUBBLE;
    case POSITION = ActorEvent::FISH_HOOK_POSITION;
    case HOOK = ActorEvent::FISH_HOOK_HOOK;
    case TEASE = ActorEvent::FISH_HOOK_TEASE;
}