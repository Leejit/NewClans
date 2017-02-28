<?php

namespace FactionsPro;

use onebone\economyapi\EconomyAPI;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\level\level;
use pocketmine\level\Position;

class FactionCommands {

    public $plugin;

    public function __construct(FactionMain $pg) {
        $this->plugin = $pg;
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        if ($sender instanceof Player) {
            $player = $sender->getPlayer()->getName();
			$create = $this->plugin->prefs->get("CreateCost");
			$claim = $this->plugin->prefs->get("ClaimCost");
			$oclaim = $this->plugin->prefs->get("OverClaimCost");
			$allyr = $this->plugin->prefs->get("AllyCost");
			$allya = $this->plugin->prefs->get("AllyPrice");
			$home = $this->plugin->prefs->get("SetHomeCost");
            if (strtolower($command->getName('c'))) {
                if (empty($args)) {
                    $sender->sendMessage($this->plugin->formatMessage("§bPlease use §e/c help §bfor a list of commands§r"));
                    return true;
                }
                if (count($args == 2)) {

                    ///////////////////////////////// WAR /////////////////////////////////

                    if ($args[0] == "war") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c war <clan name:tp>§r"));
                            return true;
                        }
                        if (strtolower($args[1]) == "tp") {
                            foreach ($this->plugin->wars as $r => $f) {
                                $fac = $this->plugin->getPlayerFaction($player);
                                if ($r == $fac) {
                                    $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                                    $tper = $this->plugin->war_players[$f][$x];
                                    $sender->teleport($this->plugin->getServer()->getPlayerByName($tper));
                                    return;
                                }
                                if ($f == $fac) {
                                    $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                                    $tper = $this->plugin->war_players[$r][$x];
                                    $sender->teleport($this->plugin->getServer()->getPlayer($tper));
                                    return;
                                }
                            }
                            $sender->sendMessage("§cYou must be in a war to do that§r");
                            return true;
                        }
                        if (!(ctype_alnum($args[1]))) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou may only use letters and numbers§r"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cClan does not exist§r"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cOnly your clan leader may start wars§r"));
                            return true;
                        }
                        if (!$this->plugin->areEnemies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan is not an enemy of§a $args[1]§r"));
                            return true;
                        } else {
                            $factionName = $args[1];
                            $sFaction = $this->plugin->getPlayerFaction($player);
                            foreach ($this->plugin->war_req as $r => $f) {
                                if ($r == $args[1] && $f == $sFaction) {
                                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                        $task = new FactionWar($this->plugin, $r);
                                        $handler = $this->plugin->getServer()->getScheduler()->scheduleDelayedTask($task, 20 * 60 * 2);
                                        $task->setHandler($handler);
                                        $p->sendMessage("§bThe war against§a $factionName §band§a $sFaction §bhas started!§r");
                                        if ($this->plugin->getPlayerFaction($p->getName()) == $sFaction) {
                                            $this->plugin->war_players[$sFaction][] = $p->getName();
                                        }
                                        if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                            $this->plugin->war_players[$factionName][] = $p->getName();
                                        }
                                    }
                                    $this->plugin->wars[$factionName] = $sFaction;
                                    unset($this->plugin->war_req[strtolower($args[1])]);
                                    return true;
                                }
                            }
                            $this->plugin->war_req[$sFaction] = $factionName;
                            foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                    if ($this->plugin->getLeader($factionName) == $p->getName()) {
                                        $p->sendMessage("§a$sFaction §bwants to start a war,§e '/c war§a $sFaction§e' §bto start!§r");
                                        $sender->sendMessage("§aClan war requested§r");
                                        return true;
                                    }
                                }
                            }
                            $sender->sendMessage("§cClan leader is not online.§r");
                            return true;
                        }
                    }

                    /////////////////////////////// CREATE ///////////////////////////////

                    if ($args[0] == "create") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c create <clan name>§r"));
                            return true;
                        }
                        if (!(ctype_alnum($args[1]))) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou may only use letters and numbers§r"));
                            return true;
                        }
                        if ($this->plugin->isNameBanned($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThis name is not allowed§r"));
                            return true;
                        }
                        if ($this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe Clan already exists§r"));
                            return true;
                        }
                        if (strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThat name is too long, please try again§r"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must leave the clan you're in first§r"));
                            return true;
						              } elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $create)) {
                            $factionName = $args[1];
                            $rank = "Leader";
                            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                            $stmt->bindValue(":player", $player);
                            $stmt->bindValue(":faction", $factionName);
                            $stmt->bindValue(":rank", $rank);
                            $result = $stmt->execute();
                            $this->plugin->updateAllies($factionName);
                            $this->plugin->setFactionPower($factionName, $this->plugin->prefs->get("TheDefaultPowerEveryFactionStartsWith"));
                            $this->plugin->updateTag($sender->getName());
                            $sender->sendMessage($this->plugin->formatMessage("§aClan successfully created§r", true));
                            return true;
                        }
						else {

						switch($r){
							case EconomyAPI::RET_INVALID:

								$sender->sendMessage($this->plugin->formatMessage("§cYou do not have enough Money to create a Clan! Need §6$$create.§r"));
								break;
							case EconomyAPI::RET_CANCELLED:

								$sender->sendMessage($this->plugin->formatMessage("§c-ERROR!§r"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("§c-ERROR!§r"));
								break;
						}
					}
        }

                    /////////////////////////////// INVITE ///////////////////////////////

                    if ($args[0] == "invite") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cUsage: §e/c invite <player>§r"));
                            return true;
                        }
                        if ($this->plugin->isFactionFull($this->plugin->getPlayerFaction($player))) {
                            $sender->sendMessage($this->plugin->formatMessage("§cClan is full, please kick players to make room§r"));
                            return true;
                        }
                        $invited = $this->plugin->getServer()->getPlayerExact($args[1]);
                        if (!($invited instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer not online§r"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($invited) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is currently in a clan§r"));
                            return true;
                        }
                        if ($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite")) {
                            if (!($this->plugin->isOfficer($player) || $this->plugin->isLeader($player))) {
                                $sender->sendMessage($this->plugin->formatMessage("§cOnly your clan leader/officers can invite§r"));
                                return true;
                            }
                        }
                        if ($invited->getName() == $player) {

                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't invite yourself to your own clan§r"));
                            return true;
                        }

                        $factionName = $this->plugin->getPlayerFaction($player);
                        $invitedName = $invited->getName();
                        $rank = "Member";

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
                        $stmt->bindValue(":player", $invitedName);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":invitedby", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("§b$invitedName §ahas been invited§r", true));
                        $invited->sendMessage($this->plugin->formatMessage("§bYou have been invited to§a $factionName.§b Type§e '/c yes' §bor§e '/c no'§b into chat to accept or deny!§r", true));
                      }
					          }

                    /////////////////////////////// LEADER ///////////////////////////////

                    if ($args[0] == "giveto") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cUsage:§e /c giveto <player>§r"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this§r"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cAdd player to clan first§r"));
                            return true;
                        }
                        if (!($this->plugin->getServer()->getPlayerExact($args[1]) instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer not online§r"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {

                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't transfer the leadership to yourself§r"));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($player);

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $player);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Member");
                        $result = $stmt->execute();

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Leader");
                        $result = $stmt->execute();


                        $sender->sendMessage($this->plugin->formatMessage("§aYou are no longer leader§r", true));
                        $this->plugin->getServer()->getPlayerExact($args[1])->sendMessage($this->plugin->formatMessage("§bYou are now leader \n§bof§a $factionName!§r", true));
                        $this->plugin->updateTag($sender->getName());
                        $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                    }

                    /////////////////////////////// PROMOTE ///////////////////////////////

                    if ($args[0] == "promote") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage: /c promote <player>§r"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this§r"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is not in this clan§r"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't promote yourself§r"));
                            return true;
                        }

                        if ($this->plugin->isOfficer($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is already Officer§r"));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($player);
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Officer");
                        $result = $stmt->execute();
                        $player = $this->plugin->getServer()->getPlayerExact($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§a$args[1] §bhas been promoted to Officer§r", true));

                        if ($player instanceof Player) {
                            $player->sendMessage($this->plugin->formatMessage("§bYou were promoted to officer of §a$factionName!§r", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
                    }

                    /////////////////////////////// DEMOTE ///////////////////////////////

                    if ($args[0] == "demote") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c demote <player>§r"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this§r"));
                            return true;
                        }
                        if ($this->plugin->isLeader($player) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this§r"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is not in this clan§r"));
                            return true;
                        }

                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't demote yourself§r"));
                            return true;
                        }
                        if (!$this->plugin->isOfficer($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is already Member§r"));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($player);
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Member");
                        $result = $stmt->execute();
                        $player = $this->plugin->getServer()->getPlayerExact($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§a$args[1] §bhas been demoted to Member§r", true));
                        if ($player instanceof Player) {
                            $player->sendMessage($this->plugin->formatMessage("§cYou were demoted to member of§a $factionName!§r", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
                    }

                    /////////////////////////////// KICK ///////////////////////////////

                    if ($args[0] == "kick") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c kick <player>§r"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this§r"));
                            return true;
                        }
                        if ($this->plugin->isLeader($player) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this§r"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is not in this clan§r"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't kick yourself§r"));
                            return true;
                        }
                        $kicked = $this->plugin->getServer()->getPlayerExact($args[1]);
                        $factionName = $this->plugin->getPlayerFaction($player);
                        $this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
                        $sender->sendMessage($this->plugin->formatMessage("§aYou successfully kicked §b$args[1]§r", true));
                        $this->plugin->subtractFactionPower($factionName, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));

                        if ($kicked instanceof Player) {
                            $kicked->sendMessage($this->plugin->formatMessage("§cYou have been kicked from \n §a$factionName §r", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
                    }

                    /////////////////////////////// INFO ///////////////////////////////

                    if (strtolower($args[0]) == 'info') {
                        if (isset($args[1])) {
                            if (!(ctype_alnum($args[1])) | !($this->plugin->factionExists($args[1]))) {
                                $sender->sendMessage($this->plugin->formatMessage("§cClan does not exist§r"));
                                $sender->sendMessage($this->plugin->formatMessage("§bMake sure the name of the selected clan is ABSOLUTELY EXACT.§r"));
                                return true;
                            }
                            $faction = $args[1];
                            $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                            $array = $result->fetchArray(SQLITE3_ASSOC);
                            $power = $this->plugin->getFactionPower($faction);
                            $message = $array["message"];
							              $leader = $this->plugin->getLeader($faction);
							              $numPlayers = $this->plugin->getNumberOfPlayers($faction);
              							$sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
							              $sender->sendMessage(TextFormat::BOLD . TextFormat::GOLD . "»§b $faction §6«");
							              $sender->sendMessage("§6Leader:§3 $leader");
							              $sender->sendMessage("§6Players:§f $numPlayers");
							              $sender->sendMessage("§6Power:§f $power");
							              $sender->sendMessage("§6MOTD:§e $message");
							              $sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
						              } else {
                            if (!$this->plugin->isInFaction($player)) {
                                $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this!§r"));
                                return true;
                            }
                            $faction = $this->plugin->getPlayerFaction(($sender->getName()));
                            $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                            $array = $result->fetchArray(SQLITE3_ASSOC);
                            $power = $this->plugin->getFactionPower($faction);
                            $message = $array["message"];
							              $leader = $this->plugin->getLeader($faction);
							              $numPlayers = $this->plugin->getNumberOfPlayers($faction);
							              $sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
							              $sender->sendMessage(TextFormat::BOLD . TextFormat::GOLD . "»§b $faction §6«");
							              $sender->sendMessage("§6Leader:§3 $leader");
							              $sender->sendMessage("§6Players:§f $numPlayers");
							              $sender->sendMessage("§6Power:§f $power");
							              $sender->sendMessage("§6MOTD:§e $message");
							              $sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
						            }
                    }
                    if (strtolower($args[0]) == "help") {
                        if (!isset($args[1]) || $args[1] == 1) {
                            $sender->sendMessage("§f====§bClans Help (1/5)§f====" . TextFormat::WHITE . "\n§e/c about\n§e/c yes §f[Accept a Clan Invite]\n§e/c overclaim §f[Overclaim this clans land for §6$$oclaim]\n§e/c claim §f[Clam your clan land for §6$$claim]\n§e/c create <name> §f[Make a clan for §6$$create]\n§e/c del §f[Delete your clan]\n§e/c demote <player>\n§e/c no §f[Deny a Clan Invite]");
                            return true;
                        }
                        if ($args[1] == 2) {
                            $sender->sendMessage("§f====§bClans Help (2/5)§f====" . TextFormat::WHITE . "\n§e/c home\n§e/c help <page>\n§e/c info\n§e/c info <clan>\n§e/c invite <player>\n§e/c kick <player>\n§e/c gievto <player>\n§e/c leave");
                            return true;
                        }
                        if ($args[1] == 3) {
                            $sender->sendMessage("§f====§bClans Help (3/5)§f====" . TextFormat::WHITE . "\n§e/c sethome §f[Set home for your Clan for §6$$home]\n§e/c unclaim\n§e/c unsethome\n§e/c members §f[Members + Statuses]\n§e/c officers §f[Officers + Statuses]\n§e/c leader §f[Leader + Status]\n§e/c allies §f[The allies of your clan]");
                            return true;
                        }
                        if ($args[1] == 4) {
                            $sender->sendMessage("§f====§bClans Help (4/5)§f====" . TextFormat::WHITE . "\n§e/c motd\n§e/c promote <player>\n§e/c ally <clan> §f[Request for an ally for §6$$allyr]\n§e/c unally <clan>\n§e\n§e/c allyok §f[Accept a request for alliance and earn §6$$allya]\n§e/c allyno §f[Deny a request for alliance]\n§e/c allies <clan> §f[See allies of a clan]");
                            return true;
                        }
                        if ($args[1] == 5) {
                            $sender->sendMessage("§f====§bClans Help (5/5)§f====" . TextFormat::WHITE . "\n§e/c membersof <clan>\n§e/c officersof <clan>\n§e/c leaderof <clan>\n§e/c c <private chat your clan members>\n§e/c pc <player>\n§e/c tops");
                            return true;
                        } else {
                            $sender->sendMessage("§f====§bClans Help (OP)§f====" . TextFormat::WHITE . "\n§e/c forceunclaim <clan> §f[Unclaim a clan plot by force - OP]\n§e\n§e/c forcedelete <clan> §f[Delete a clan by force - OP]\n§e/c addpowerto <clan> <power> §f[Add positive/negative power to a clan - OP]");
                            return true;
                        }
                    }
                }
                if (count($args == 1)) {

                    /////////////////////////////// CLAIM ///////////////////////////////

                    if (strtolower($args[0]) == 'claim') {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan.§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this.§r"));
                            return true;
                        }
                        if (!in_array($sender->getPlayer()->getLevel()->getName(), $this->plugin->prefs->get("ClanWorlds"))) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou can only do that in: " . implode(" ", $this->plugin->prefs->get("ClanWorlds"))));
                            return true;
                        }

                        if ($this->plugin->inOwnPlot($sender)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan has already claimed this area.§r"));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
                        if ($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")) {

                            $needed_players = $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                                    $this->plugin->getNumberOfPlayers($faction);
                            $sender->sendMessage($this->plugin->formatMessage("§cYou need §a$needed_players §cmore players in your clan to claim a clan land§r"));
                            return true;
                        }
                        if ($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")) {
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan doesn't have enough power to claim a land.§r"));
                            $sender->sendMessage($this->plugin->formatMessage("§e$needed_power §bpower is required but your clan has only §e$faction_power §bpower.§r"));
                            return true;
                        }

                        $x = floor($sender->getX());
                        $y = floor($sender->getY());
                        $z = floor($sender->getZ());
                        if ($this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {

                            return true;
                        }

                        $sender->sendMessage($this->plugin->formatMessage("§bGetting your coordinates...§r", true));
                        $plot_size = $this->plugin->prefs->get("PlotSize");
                        $faction_power = $this->plugin->getFactionPower($faction);
                        $sender->sendMessage($this->plugin->formatMessage("§bYour land has been claimed.§r", true));
                    }
                    if (strtolower($args[0]) == 'pos') {
                        $x = floor($sender->getX());
                        $y = floor($sender->getY());
                        $z = floor($sender->getZ());
                        if (!$this->plugin->isInPlot($sender)) {
                            $sender->sendMessage($this->plugin->formatMessage("§bThis plot is not claimed by anyone. You can claim it by typing§e /c claim§r", true));
                            return true;
                        }

                        $fac = $this->plugin->factionFromPoint($x, $z);
                        $power = $this->plugin->getFactionPower($fac);
                        $sender->sendMessage($this->plugin->formatMessage("§bThis plot is claimed by§a $fac §bwith§e $power §bpower§r"));
                    }
                    if(strtolower($args[0]) == 'top') {
						$result = $this->plugin->db->query("SELECT * FROM strength ORDER BY power DESC LIMIT 8;");
						 $i = 1;

						while($row = $result->fetchArray(SQLITE3_ASSOC)){
							if($this->plugin->factionExists($row['faction'])) {
						    $fac = $row['faction'];
							$res = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$fac';");
							$arr = $res->fetchArray(SQLITE3_ASSOC);
							$motd = $arr["message"];
							$lead = $this->plugin->getLeader($row['faction']);
							$num = $this->plugin->getNumberOfPlayers($row['faction']);
							$pow = $this->plugin->getFactionPower($row['faction']);
							$sender->sendMessage(TextFormat::BOLD . $i . ". §6$fac\n§4Leader:§f $lead §ePlayers:§f $num §bPower:§f $pow §5MOTD: §o§f$motd\n");
						    $i++;
						}
						}
					}
                    if (strtolower($args[0]) == 'forcedelete') {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c forcedelete <clan>§r"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist.§r"));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be OP to do this.§r"));
                            return true;
                        }
                        $this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
                        $this->plugin->db->query("DELETE FROM strength WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
                        $sender->sendMessage($this->plugin->formatMessage("§bUnwanted clan was successfully deleted and any claimed land was unclaimed!§r", true));
                    }
                    if (strtolower($args[0]) == 'addpowerto') {
                        if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c addpowerto <clan> <power>§r"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist.§r"));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be OP to do this.§r"));
                            return true;
                        }
                        $this->plugin->addFactionPower($args[1], $args[2]);
                        $sender->sendMessage($this->plugin->formatMessage("§bSuccessfully added §a$args[2] §bpower to §a$args[1]§r", true));
                    }
                    if (strtolower($args[0]) == 'pc') {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c pc <player>§r"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe selected player is not in a clan or doesn't exist.§r"));
                            $sender->sendMessage($this->plugin->formatMessage("§bMake sure the name of the selected player is ABSOLUTELY EXACT.§r"));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§a-$args[1]§c is in§a $faction-§r", true));
                    }

                    if (strtolower($args[0]) == 'overclaim') {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan.§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this.§r"));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($player);
                        if ($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")) {

                            $needed_players = $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                                    $this->plugin->getNumberOfPlayers($faction);
                            $sender->sendMessage($this->plugin->formatMessage("§bYou need §a$needed_players §bmore players in your clan to overclaim a clan plot§r"));
                            return true;
                        }
                        if ($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")) {
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan doesn't have enough power to claim a land.§r"));
                            $sender->sendMessage($this->plugin->formatMessage("§a$needed_power §bpower is required but your clan has only §a$faction_power §bpower.§r"));
                            return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("§bGetting your coordinates...§r", true));
                        $x = floor($sender->getX());
                        $y = floor($sender->getY());
                        $z = floor($sender->getZ());
                        if ($this->plugin->prefs->get("EnableOverClaim")) {
                            if ($this->plugin->isInPlot($sender)) {
                                $faction_victim = $this->plugin->factionFromPoint($x, $z);
                                $faction_victim_power = $this->plugin->getFactionPower($faction_victim);
                                $faction_ours = $this->plugin->getPlayerFaction($player);
                                $faction_ours_power = $this->plugin->getFactionPower($faction_ours);
                                if ($this->plugin->inOwnPlot($sender)) {
                                    $sender->sendMessage($this->plugin->formatMessage("§cYou can't overclaim your own plot.§r"));
                                    return true;
                                } else {
                                    if ($faction_ours_power < $faction_victim_power) {
                                        $sender->sendMessage($this->plugin->formatMessage("§cYou can't overclaim the plot of§a $faction_victim §cbecause your power is lower than theirs.§r"));
                                        return true;
                                    } elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $oclaim))
									   {
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_ours';");
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_victim';");
                                        $arm = (($this->plugin->prefs->get("PlotSize")) - 1) / 2;
                                        $this->plugin->newPlot($faction_ours, $x + $arm, $z + $arm, $x - $arm, $z - $arm);
                                        $sender->sendMessage($this->plugin->formatMessage("§bThe land of §a$faction_victim §bhas been claimed. It is now yours.§r", true));
                                        return true;
                                    }
									else {
						// $r is an error code
						    switch($r){
							case EconomyAPI::RET_INVALID:
								# Invalid $amount
								$sender->sendMessage($this->plugin->formatMessage("§cYou do not have enough Money to Overclaim! Need §6$oclaim§r"));
								break;
							case EconomyAPI::RET_CANCELLED:
								# Transaction was cancelled for some reason :/
								$sender->sendMessage($this->plugin->formatMessage("§c-ERROR!§r"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("§c-ERROR!§r"));
								break;
						}
					}

                                }
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage("§cYou are not in claimed land"));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cInsufficient permissions"));
                            return true;
                        }

					}


                    /////////////////////////////// UNCLAIM ///////////////////////////////

                    if (strtolower($args[0]) == "unclaim") {
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this§r"));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($sender->getName());
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                        $sender->sendMessage($this->plugin->formatMessage("§bYour land has been unclaimed§r", true));
                    }

                    /////////////////////////////// DESCRIPTION ///////////////////////////////

                    if (strtolower($args[0]) == "motd") {
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this!§r"));
                            return true;
                        }
                        if ($this->plugin->isLeader($player) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this§r"));
                            return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("§bType your message in chat. It will not be visible to other players§r", true));
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
                        $stmt->bindValue(":player", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                    }

                    /////////////////////////////// ACCEPT ///////////////////////////////

                    if (strtolower($args[0]) == "yes") {
                        $player = $sender->getName();
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou have not been invited to any clan§rs"));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                            $faction = $array["faction"];
                            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                            $stmt->bindValue(":player", ($player));
                            $stmt->bindValue(":faction", $faction);
                            $stmt->bindValue(":rank", "Member");
                            $result = $stmt->execute();
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("§bYou successfully joined §a$faction", true));
                            $this->plugin->addFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                            $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("§a$player §bjoined the clan§r", true));
                            $this->plugin->updateTag($sender->getName());
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cInvite has timed out§r"));
                            $this->plugin->db->query("DELETE * FROM confirm WHERE player='$player';");
                        }
                    }

                    /////////////////////////////// DENY ///////////////////////////////

                    if (strtolower($args[0]) == "no") {
                        $player = $sender->getName();
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou have not been invited to any clans§r"));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("§cInvite declined§r", true));
                            $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player declined the invitation"));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cInvite has timed out§r"));
                            $this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
                        }
                    }

                    /////////////////////////////// DELETE ///////////////////////////////

                    if (strtolower($args[0]) == "del") {
                        if ($this->plugin->isInFaction($player) == true) {
                            if ($this->plugin->isLeader($player)) {
                                $faction = $this->plugin->getPlayerFaction($player);
                                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM allies WHERE faction1='$faction';");
                                $this->plugin->db->query("DELETE FROM allies WHERE faction2='$faction';");
                                $this->plugin->db->query("DELETE FROM strength WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM motd WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM home WHERE faction='$faction';");
                                $sender->sendMessage($this->plugin->formatMessage("§aclan successfully disbanded and any clan plots were unclaimed§r", true));
                                $this->plugin->updateTag($sender->getName());
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage("§cYou are not leader!§r"));
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou are not in a clan!§r"));
                        }
                    }

                    /////////////////////////////// LEAVE ///////////////////////////////

                    if (strtolower($args[0] == "leave")) {
                        if ($this->plugin->isLeader($player) == false) {
                            $remove = $sender->getPlayer()->getNameTag();
                            $faction = $this->plugin->getPlayerFaction($player);
                            $name = $sender->getName();
                            $this->plugin->db->query("DELETE FROM master WHERE player='$name';");
                            $sender->sendMessage($this->plugin->formatMessage("§bYou successfully left§a $faction§r", true));

                            $this->plugin->subtractFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                            $this->plugin->updateTag($sender->getName());
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must delete the clan or give leadership to someone else§r"));
                        }
                    }

                    /////////////////////////////// SETHOME ///////////////////////////////

                    if (strtolower($args[0] == "sethome")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to set home§r"));
                            return true;
                        }
                        if (!in_array($sender->getPlayer()->getLevel()->getName(), $this->plugin->prefs->get("ClanWorlds"))) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou can only do that in: " . implode(" ", $this->plugin->prefs->get("ClanWorlds"))));
                            return true;
                        }
						            elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $home)){
                        $factionName = $this->plugin->getPlayerFaction($sender->getName());
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z) VALUES (:faction, :x, :y, :z);");
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":x", $sender->getX());
                        $stmt->bindValue(":y", $sender->getY());
                        $stmt->bindValue(":z", $sender->getZ());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("§aClan home set§r", true));}
						else {
						// $r is an error code
						    switch($r){
							case EconomyAPI::RET_INVALID:
								# Invalid $amount
								$sender->sendMessage($this->plugin->formatMessage("§cYou do not have enough Money to set Clan Home!§r\n§bYou will need§6 $$home§r"));
								break;
							case EconomyAPI::RET_CANCELLED:
								# Transaction was cancelled for some reason :/
								$sender->sendMessage($this->plugin->formatMessage("§c-ERROR!§r"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("§c-ERROR!§r"));
								break;
						}
					}
					}

                    /////////////////////////////// UNSETHOME ///////////////////////////////

                    if (strtolower($args[0] == "unsethome")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to unset home§r"));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($sender->getName());
                        $this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
                        $sender->sendMessage($this->plugin->formatMessage("§bHome unset§r", true));
                    }

                    /////////////////////////////// HOME ///////////////////////////////

                    if (strtolower($args[0] == "home")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($sender->getName());
                        $result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (!empty($array)) {
                            $sender->getPlayer()->teleport(new Position($array['x'], $array['y'], $array['z'], $this->plugin->getServer()->getLevelByName("Factions")));
                            $sender->sendMessage($this->plugin->formatMessage("Teleported home", true));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cHome is not set§r"));
                        }
                    }

                    /////////////////////////////// POWER ///////////////////////////////
                    if(strtolower($args[0] == "power")) {
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                            return true;
						}
                        $faction_power = $this->plugin->getFactionPower($this->plugin->getPlayerFaction($sender->getName()));

                        $sender->sendMessage($this->plugin->formatMessage("§bYour clan has§e $faction_power §bpower§r",true));
                    }
                    if(strtolower($args[0] == "seepower")) {
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c seepower <clan>§r"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cClan does not exist§r"));
                            return true;
						}
                        $faction_power = $this->plugin->getFactionPower($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§a$args[1] §bhas §e$faction_power §bpower.§r",true));
                    }

                    /////////////////////////////// MEMBERS/OFFICERS/LEADER AND THEIR STATUSES ///////////////////////////////
                    if (strtolower($args[0] == "members")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Member");
                    }
                    if (strtolower($args[0] == "membersof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c membersof <clan>§r"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist§r"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Member");
                    }
                    if (strtolower($args[0] == "officers")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Officer");
                    }
                    if (strtolower($args[0] == "officersof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c officersof <clan>§r"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist§r"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Officer");
                    }
                    if (strtolower($args[0] == "leader")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Leader");
                    }
                    if (strtolower($args[0] == "leaderof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c leaderof <clan>§r"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist§r"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Leader");
                    }
                    if (strtolower($args[0] == "c")) {
                        if (true) {
                            $sender->sendMessage($this->plugin->formatMessage("§c/c c is disabled§r"));
                            return true;
                        }
                        if (!($this->plugin->isInFaction($player))) {

                            $sender->sendMessage($this->plugin->formatMessage("§cYou don't have a clan to send messages to§r"));
                            return true;
                        }
                        $r = count($args);
                        $row = array();
                        $rank = "";
                        $f = $this->plugin->getPlayerFaction($player);

                        if ($this->plugin->isOfficer($player)) {
                            $rank = "*";
                        } else if ($this->plugin->isLeader($player)) {
                            $rank = "**";
                        }
                        $message = "; ";
                        for ($i = 0; $i < $r - 1; $i = $i + 1) {
                            $message = $message . $args[$i + 1] . " ";
                        }
                        $result = $this->plugin->db->query("SELECT * FROM master WHERE faction='$f';");
                        for ($i = 0; $resultArr = $result->fetchArray(SQLITE3_ASSOC); $i = $i + 1) {
                            $row[$i]['player'] = $resultArr['player'];
                            $p = $this->plugin->getServer()->getPlayerExact($row[$i]['player']);
                            if ($p instanceof Player) {
                                $p->sendMessage("§f[§bClanChat§f]§a$rank$player  §o§e$message§r");
                            }
                        }
                    }


                    ////////////////////////////// ALLY SYSTEM ////////////////////////////////
                    if (strtolower($args[0] == "enemywith")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c enemywith <clan>§r"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be the leader to do this§r"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist§r"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan can not enemy with itsel§r"));
                            return true;
                        }
                        if ($this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan is already enemied with§a $args[1]§r"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));

                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe leader of the requested clan is offline§r"));
                            return true;
                        }
                        $this->plugin->setEnemies($fac, $args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§bYou are now enemies with §a$args[1]!", true));
                        $leader->sendMessage($this->plugin->formatMessage("§cThe leader of§a $fac §chas declared your clan as an enemy§r", true));
                    }
                    if (strtolower($args[0] == "ally")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c ally <clan>§r"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be the leader to do this§r"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist§r"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan can not ally with itself§r"));
                            return true;
                        }
                        if ($this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan is already allied with§a $args[1]§r"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->updateAllies($fac);
                        $this->plugin->updateAllies($args[1]);

                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe leader of the requested clan is offline§r"));
                            return true;
                        }
                        if ($this->plugin->getAlliesCount($args[1]) >= $this->plugin->getAlliesLimit()) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan has the maximum amount of allies§r", false));
                            return true;
                        }
                        if ($this->plugin->getAlliesCount($fac) >= $this->plugin->getAlliesLimit()) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan has the maximum amount of allies§r", false));
                            return true;
                        }
                        elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $allyr)){
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
                        $stmt->bindValue(":player", $leader->getName());
                        $stmt->bindValue(":faction", $args[1]);
                        $stmt->bindValue(":requestedby", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("§bYou requested to ally with §a$args[1]!§r\n§bWait for the leader's response...§r", true));
                        $leader->sendMessage($this->plugin->formatMessage("§bThe leader of §a$fac §brequested an alliance.§r\n§bType §e/c allyok§b to accept or§e /c allyno§b to deny.§r", true));
                    }
							else {
						// $r is an error code
						    switch($r){
							case EconomyAPI::RET_INVALID:
								# Invalid $amount
								$sender->sendMessage($this->plugin->formatMessage("§cYou do not have enough Money to send a Ally Request! Need §6$$allyr"));
								break;
							case EconomyAPI::RET_CANCELLED:
								# Transaction was cancelled for some reason :/
								$sender->sendMessage($this->plugin->formatMessage("§c-ERROR!"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("§c-ERROR!"));
								break;
						}
					}
        }
                    if (strtolower($args[0] == "unally")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c unally <clan>§r"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be the leader to do this§r"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist§r"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan can not unally with itself§r"));
                            return true;
                        }
                        if (!$this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan is not allied with §a$args[1]"));
                            return true;
                        }

                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->deleteAllies($fac, $args[1]);
                        $this->plugin->deleteAllies($args[1], $fac);
                        $this->plugin->subtractFactionPower($fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->subtractFactionPower($args[1], $this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->updateAllies($fac);
                        $this->plugin->updateAllies($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§bYour clan§a $fac §bis no longer allied with§a $args[1]§r", true));
                        if ($leader instanceof Player) {
                            $leader->sendMessage($this->plugin->formatMessage("§bThe leader of§a $fac §bbroke the alliance with your clan §a$args[1]§r", false));
                        }
                    }
                    if (strtolower($args[0] == "forceunclaim")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c forceunclaim <clan>§r"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist§r"));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be OP to do this§r"));
                            return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("§aSuccessfully unclaimed the unwanted plot of§b $args[1]§r"));
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                    }

                    if (strtolower($args[0] == "allies")) {
                        if (!isset($args[1])) {
                            if (!$this->plugin->isInFaction($player)) {
                                $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                                return true;
                            }

                            $this->plugin->updateAllies($this->plugin->getPlayerFaction($player));
                            $this->plugin->getAllAllies($sender, $this->plugin->getPlayerFaction($player));
                        } else {
                            if (!$this->plugin->factionExists($args[1])) {
                                $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist§r"));
                                return true;
                            }
                            $this->plugin->updateAllies($args[1]);
                            $this->plugin->getAllAllies($sender, $args[1]);
                        }
                    }
                    if (strtolower($args[0] == "allyok")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be a leader to do this§r"));
                            return true;
                        }
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan has not been requested to ally with any clans§r"));
                            return true;
                        }
                        $allyTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $allyTime) <= 60) { //This should be configurable
                            if($r = EconomyAPI::getInstance()->addMoney($player, $allya)){
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
                            $this->plugin->setAllies($requested_fac, $sender_fac);
                            $this->plugin->setAllies($sender_fac, $requested_fac);
                            $this->plugin->addFactionPower($sender_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                            $this->plugin->addFactionPower($requested_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                            $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $this->plugin->updateAllies($requested_fac);
                            $this->plugin->updateAllies($sender_fac);
                            $sender->sendMessage($this->plugin->formatMessage("§aYour clan has successfully allied with§b $requested_fac §r", true));
                            $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("§a$player §bfrom§a $sender_fac §bhas accepted the alliance!§r", true));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cRequest has timed out§r"));
                            $this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
                        }
                    }
                    if (strtolower($args[0]) == "allyno") {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this§r"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be a leader to do this§r"));
                            return true;
                        }
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan has not been requested to ally with any clans§r"));
                            return true;
                        }
                        $allyTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $allyTime) <= 60) { //This should be configurable
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
                            $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("§aYour clan has successfully declined the alliance request.§r", true));
                            $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player from $sender_fac has declined the alliance!"));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cRequest has timed out§r"));
                            $this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
                        }
                    }


                    /////////////////////////////// ABOUT ///////////////////////////////

                    if (strtolower($args[0] == 'about')) {
                        $sender->sendMessage(TextFormat::GREEN . "FactionsPro v1.3.2 by " . TextFormat::BOLD . "toomanypeople");
                    }
                    ////////////////////////////// CHAT ////////////////////////////////
                    if (strtolower($args[0]) == "chat" or strtolower($args[0]) == "c") {

                        $sender->sendMessage($this->plugin->formatMessage("§cClan chat disabled§r", false));
                        return true;

                        if ($this->plugin->isInFaction($player)) {
                            if (isset($this->plugin->factionChatActive[$player])) {
                                unset($this->plugin->factionChatActive[$player]);
                                $sender->sendMessage($this->plugin->formatMessage("§cclan chat disabled§r", false));
                                return true;
                            } else {
                                $this->plugin->factionChatActive[$player] = 1;
                                $sender->sendMessage($this->plugin->formatMessage("§aclan chat enabled§r", false));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou are not in a clan§r"));
                            return true;
                        }
                    }
                    if (strtolower($args[0]) == "allychat" or strtolower($args[0]) == "ac") {

                        $sender->sendMessage($this->plugin->formatMessage("§cClan chat disabled§r", false));
                        return true;

                        if ($this->plugin->isInFaction($player)) {
                            if (isset($this->plugin->allyChatActive[$player])) {
                                unset($this->plugin->allyChatActive[$player]);
                                $sender->sendMessage($this->plugin->formatMessage("§cAlly chat disabled§r", false));
                                return true;
                            } else {
                                $this->plugin->allyChatActive[$player] = 1;
                                $sender->sendMessage($this->plugin->formatMessage("§aAlly chat enabled§r", false));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou are not in a clan§r"));
                            return true;
                        }
                    }
                }
            }
        } else {
            $this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage("§cPlease run command in game§r"));
        }
    }

}
