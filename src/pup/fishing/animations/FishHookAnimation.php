<?php

declare(strict_types=1);

namespace pup\fishing\animations;

use pocketmine\entity\animation\Animation;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pup\fishing\entities\FishingHook;

final readonly class FishHookAnimation implements Animation
{
    public function __construct(
        private FishingHook $entity,
        private FishHookAnimationType $type = FishHookAnimationType::POSITION
    ) {
    }

    public function encode(): array
    {
        return [
            ActorEventPacket::create(
                $this->entity->getId(),
                $this->type->value,
                1,
                $this->entity->getPosition()
            )
        ];
    }
}