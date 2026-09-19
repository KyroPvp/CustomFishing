<?php

declare(strict_types=1);

namespace pup\fishing;

use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;
use pup\fishing\entities\FishingHook;
use pup\fishing\session\SessionManager;

final class Main extends PluginBase
{
    private static Main $instance;

    private SessionManager $sessionManager;

    public function onLoad(): void
    {
        self::$instance = $this;
        $this->sessionManager = new SessionManager();
    }

    public function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
        EntityFactory::getInstance()->register(FishingHook::class, function (World $world, CompoundTag $nbt): FishingHook {
            return new FishingHook(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
        }, ["FishingHook"]);
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function getSessionManager(): SessionManager
    {
        return $this->sessionManager;
    }
}