<?php

declare(strict_types=1);

namespace pup\fishing;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\world\World;
use pup\fishing\entities\FishingHook;
use pup\fishing\items\fish\FishManager;
use pup\fishing\items\loottable\LootTableManager;
use pup\fishing\items\rods\RodManager;
use pup\fishing\session\SessionManager;
use RuntimeException;

final class Main extends PluginBase
{
    private static Main $instance;

    private SessionManager $sessionManager;
    private FishManager $fishManager;
    private LootTableManager $lootTableManager;
    private RodManager $rodManager;

    public function onLoad(): void
    {
        self::$instance = $this;
        $this->sessionManager = new SessionManager();

        $this->saveResource("fish.yml");
        $this->saveResource("loot-table.yml");
        $this->saveResource("rods.yml");
    }

    public function onEnable(): void
    {
        try {
            $logger = $this->getLogger();

            $this->fishManager = new FishManager();
            $this->fishManager->load($this->yaml("fish.yml"), $logger);

            $this->lootTableManager = new LootTableManager($this->fishManager);
            $this->lootTableManager->load($this->yaml("loot-table.yml"), $logger);

            $this->rodManager = new RodManager($this->lootTableManager);
            $this->rodManager->load($this->yaml("rods.yml"), $logger);
            $this->lootTableManager->linkRods($this->rodManager, $logger);

        } catch (RuntimeException $e) {
            $this->getLogger()->critical("Config error: " . $e->getMessage());
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
        EntityFactory::getInstance()->register(FishingHook::class, function (World $world, CompoundTag $nbt): FishingHook {
            return new FishingHook(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
        }, ["FishingHook"]);
    }

    private function yaml(string $file): Config
    {
        return new Config($this->getDataFolder() . $file, Config::YAML);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command->getName() !== "fishrod") {
            return false;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage("Run this in-game.");
            return true;
        }

        $rod = isset($args[0]) ? $this->rodManager->get($args[0]) : null;
        if ($rod === null) {
            $sender->sendMessage("Usage: /fishrod <" . implode("|", $this->rodManager->getIds()) . ">");
            return true;
        }

        $sender->getInventory()->addItem($this->rodManager->createItem($rod));
        $sender->sendMessage("Gave you: " . $rod->getDisplayName());
        return true;
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function getSessionManager(): SessionManager
    {
        return $this->sessionManager;
    }

    public function getFishManager(): FishManager
    {
        return $this->fishManager;
    }

    public function getLootTableManager(): LootTableManager
    {
        return $this->lootTableManager;
    }

    public function getRodManager(): RodManager
    {
        return $this->rodManager;
    }
}