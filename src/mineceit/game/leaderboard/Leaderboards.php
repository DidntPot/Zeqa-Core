<?php

declare(strict_types=1);

namespace mineceit\game\leaderboard;

use mineceit\game\leaderboard\holograms\EloHologram;
use mineceit\game\leaderboard\holograms\PatchNoteHologram;
use mineceit\game\leaderboard\holograms\Rank2Hologram;
use mineceit\game\leaderboard\holograms\RankHologram;
use mineceit\game\leaderboard\holograms\RuleHologram;
use mineceit\game\leaderboard\holograms\StatsHologram;
use mineceit\game\leaderboard\tasks\EloLeaderboardsTask;
use mineceit\game\leaderboard\tasks\StatsLeaderboardsTask;
use mineceit\kits\DefaultKit;
use mineceit\MineceitCore;
use mineceit\player\MineceitPlayer;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\utils\Config;

class Leaderboards{

	/* @var array */
	private $eloLeaderboards;
	/** @var array */
	private $statsLeaderboards;

	/* @var Server */
	private $server;
	/* @var string */
	private $dataFolder;

	/* @var EloHologram|null */
	private $eloLeaderboardHologram;
	/** @var StatsHologram|null */
	private $statsLeaderboardHologram;
	/* @var RuleHologram|null */
	private $ruleHologram;
	/* @var PatchNoteHologram|null */
	private $patchNoteHologram;
	/** @var RankHologram|null */
	private $rankHologram;
	/** @var RankHologram|null */
	private $rank2Hologram;

	/* @var Config */
	private $leaderboardConfig;

	/**
	 * Leaderboards constructor.
	 *
	 * @param MineceitCore $core
	 */
	public function __construct(MineceitCore $core){
		$this->eloLeaderboards = [];
		$this->statsLeaderboards = [];
		$this->server = $core->getServer();
		$this->dataFolder = $core->getDataFolder();
		$this->eloLeaderboardHologram = null;
		$this->statsLeaderboardHologram = null;
		$this->initConfig();
	}

	private function initConfig() : void{

		$keys = ['elo', 'stats', 'rule', 'rank', 'patchNote', 'runningEvent'];

		$arr = [];

		foreach($keys as $key){

			$arr[strval($key)] = [
				'x' => null,
				'y' => null,
				'z' => null,
				'level' => null
			];
		}

		$this->leaderboardConfig = new Config($this->dataFolder . '/leaderboard-hologram.yml', Config::YAML);

		if(!$this->leaderboardConfig->exists('data')){

			$this->leaderboardConfig->set('data', $arr);
			$this->leaderboardConfig->save();
		}else{

			$data = $this->leaderboardConfig->get('data');

			$loaded = $this->loadData($data);

			if($loaded !== null){

				/** @var Level $level */
				$level = $loaded['level'];

				$this->eloLeaderboardHologram = new EloHologram(
					new Vector3($loaded['x'], $loaded['y'], $loaded['z']),
					$level,
					false,
					$this
				);
			}else{

				if(isset($data['stats'])){

					$statsLoaded = $this->loadData($data['stats']);

					if($statsLoaded !== null){

						$this->statsLeaderboardHologram = new StatsHologram(
							new Vector3($statsLoaded['x'], $statsLoaded['y'], $statsLoaded['z']),
							$statsLoaded['level'],
							false,
							$this
						);
					}
				}

				if(isset($data['elo'])){

					$eloLoaded = $this->loadData($data['elo']);

					if($eloLoaded !== null){

						$this->eloLeaderboardHologram = new EloHologram(
							new Vector3($eloLoaded['x'], $eloLoaded['y'], $eloLoaded['z']),
							$eloLoaded['level'],
							false,
							$this
						);
					}
				}

				if(isset($data['rule'])){

					$ruleLoaded = $this->loadData($data['rule']);

					if($ruleLoaded !== null){

						$this->ruleHologram = new RuleHologram(
							new Vector3($ruleLoaded['x'], $ruleLoaded['y'], $ruleLoaded['z']),
							$ruleLoaded['level'],
							false,
							$this
						);
					}
				}

				if(isset($data['rank'])){

					$rankLoaded = $this->loadData($data['rank']);

					if($rankLoaded !== null){

						$this->rankHologram = new RankHologram(
							new Vector3($rankLoaded['x'], $rankLoaded['y'], $rankLoaded['z']),
							$rankLoaded['level'],
							false,
							$this
						);
					}
				}

				if(isset($data['rank2'])){

					$rankLoaded = $this->loadData($data['rank2']);

					if($rankLoaded !== null){

						$this->rank2Hologram = new Rank2Hologram(
							new Vector3($rankLoaded['x'], $rankLoaded['y'], $rankLoaded['z']),
							$rankLoaded['level'],
							false,
							$this
						);
					}
				}

				if(isset($data['patchNote'])){

					$patchNoteLoaded = $this->loadData($data['patchNote']);

					if($patchNoteLoaded !== null){

						$this->patchNoteHologram = new PatchNoteHologram(
							new Vector3($patchNoteLoaded['x'], $patchNoteLoaded['y'], $patchNoteLoaded['z']),
							$patchNoteLoaded['level'],
							false,
							$this
						);
					}
				}
			}
		}
	}

	/**
	 * @param $data
	 *
	 * @return array|null
	 */
	private function loadData($data) : ?array{

		$result = null;

		if(isset($data['x'], $data['y'], $data['z'], $data['level'])){

			$x = $data['x'];
			$y = $data['y'];
			$z = $data['z'];
			$levelName = $data['level'];

			if(is_int($x) && is_int($y) && is_int($z) && is_string($levelName) && ($theLevel = $this->server->getLevelByName($levelName)) !== null && $theLevel instanceof Level){

				$result = [
					'x' => $x,
					'y' => $y,
					'z' => $z,
					'level' => $theLevel
				];
			}
		}
		return $result;
	}


	public function reloadEloLeaderboards() : void{
		$dir = $this->dataFolder . 'player';
		$kitslocal = [];
		$kits = MineceitCore::getKits()->getKits();
		foreach($kits as $kit){
			if($kit->getMiscKitInfo()->isDuelsEnabled())
				$kitslocal[] = $kit->getLocalizedName();
		}
		$task = new EloLeaderboardsTask($dir, array_values($kitslocal));
		$this->server->getAsyncPool()->submitTask($task);
	}

	public function reloadStatsLeaderboards() : void{
		$dir = $this->dataFolder . 'player';
		$task = new StatsLeaderboardsTask($dir, ['kills', 'deaths']);
		$this->server->getAsyncPool()->submitTask($task);
	}

	/**
	 * Updates the holograms.
	 */
	public function updateHolograms() : void{
		if($this->eloLeaderboardHologram instanceof EloHologram){
			$this->eloLeaderboardHologram->updateHologram();
		}

		if($this->statsLeaderboardHologram instanceof StatsHologram){
			$this->statsLeaderboardHologram->updateHologram();
		}

		if($this->ruleHologram instanceof RuleHologram){
			$this->ruleHologram->updateHologram();
		}

		if($this->rankHologram instanceof RankHologram){
			$this->rankHologram->updateHologram();
		}

		if($this->rank2Hologram instanceof Rank2Hologram){
			$this->rank2Hologram->updateHologram();
		}

		if($this->patchNoteHologram instanceof PatchNoteHologram){
			$this->patchNoteHologram->updateHologram();
		}
	}

	/**
	 * @param MineceitPlayer $player
	 * @param string         $board
	 */
	public function setLeaderboardHologram(MineceitPlayer $player, string $board) : void{
		$vec3 = $player->asVector3();
		$level = $player->getLevel();

		if($board === 'elo'){
			$key = 'elo';
			if($this->eloLeaderboardHologram !== null){
				$this->eloLeaderboardHologram->moveHologram($vec3, $level);
			}else{
				$this->eloLeaderboardHologram = new EloHologram($vec3, $level, true, $this);
			}
		}elseif($board === 'stats'){
			$key = 'stats';
			if($this->statsLeaderboardHologram !== null){
				$this->statsLeaderboardHologram->moveHologram($vec3, $level);
			}else{
				$this->statsLeaderboardHologram = new StatsHologram($vec3, $level, true, $this);
			}
		}elseif($board === 'rule'){
			$key = 'rule';
			if($this->ruleHologram !== null){
				$this->ruleHologram->moveHologram($vec3, $level);
			}else{
				$this->ruleHologram = new RuleHologram($vec3, $level, true, $this);
			}
		}elseif($board === 'rank'){
			$key = 'rank';
			if($this->rankHologram !== null){
				$this->rankHologram->moveHologram($vec3, $level);
			}else{
				$this->rankHologram = new RankHologram($vec3, $level, true, $this);
			}
		}elseif($board === 'rank2'){
			$key = 'rank2';
			if($this->rank2Hologram !== null){
				$this->rank2Hologram->moveHologram($vec3, $level);
			}else{
				$this->rank2Hologram = new Rank2Hologram($vec3, $level, true, $this);
			}
		}elseif($board === 'patchNote'){
			$key = 'patchNote';
			if($this->patchNoteHologram !== null){
				$this->patchNoteHologram->moveHologram($vec3, $level);
			}else{
				$this->patchNoteHologram = new PatchNoteHologram($vec3, $level, true, $this);
			}
		}

		$data = $this->leaderboardConfig->get('data');

		if(isset($data['x'], $data['y'], $data['z'], $data['level'])){
			unset($data['x'], $data['y'], $data['z'], $data['level']);
		}

		$data[$key] = [
			'x' => (int) $vec3->x,
			'y' => (int) $vec3->y,
			'z' => (int) $vec3->z,
			'level' => $level->getName()
		];

		$this->leaderboardConfig->setAll(['data' => $data]);
		$this->leaderboardConfig->save();
	}

	/**
	 * @param DefaultKit|string $queue
	 *
	 * @return array|int[]
	 */
	public function getEloLeaderboardOf($queue = 'global') : array{
		$result = [];
		$queue = $queue instanceof DefaultKit ? $queue->getLocalizedName() : $queue;

		if(isset($this->eloLeaderboards[$queue])){
			$result = $this->eloLeaderboards[$queue];
		}
		return $result;
	}

	/**
	 * @param string $key
	 *
	 * @return array
	 */
	public function getStatsLeaderboardOf(string $key) : array{

		$result = [];

		if(isset($this->statsLeaderboards[$key])){
			$result = $this->statsLeaderboards[$key];
		}

		return $result;
	}

	/**
	 *
	 * @param bool $elo
	 *
	 * @return array|string[]
	 */
	public function getLeaderboardKeys(bool $elo = true) : array{

		$result = ['kills', 'deaths', 'kdr'];

		if($elo){
			$duelkits = [];
			$kits = MineceitCore::getKits()->getKits();
			foreach($kits as $kit){
				if($kit->getMiscKitInfo()->isDuelsEnabled())
					$duelkits[] = $kit->getLocalizedName();
			}
			$result = array_values($duelkits);
			$result[] = 'global';
		}

		return $result;
	}

	/**
	 * @return array
	 */
	public function getEloLeaderboards() : array{
		return $this->eloLeaderboards;
	}

	/**
	 * @param array $eloLeaderboards
	 */
	public function setEloLeaderboards(array $eloLeaderboards) : void{
		$this->eloLeaderboards = $eloLeaderboards;
	}

	/**
	 * @return array
	 */
	public function getStatsLeaderboards() : array{
		return $this->statsLeaderboards;
	}

	/**
	 * @param array $statsLeaderboards
	 */
	public function setStatsLeaderboards(array $statsLeaderboards) : void{
		$this->statsLeaderboards = $statsLeaderboards;
	}

	/**
	 * @param string $player
	 * @param string $key
	 * @param bool   $elo
	 *
	 * @return null|int
	 */
	public function getRankingOf(string $player, string $key, bool $elo = true) : ?int{
		$list = $this->eloLeaderboards;
		if(!$elo){
			$list = $this->statsLeaderboards;
		}
		if(isset($list[$key][$player])){
			$leaderboardSet = $list[$key];
			$searched = array_keys($leaderboardSet);
			$result = array_search($player, $searched);
			if(is_int($result)){
				return $result + 1;
			}
		}
		return null;
	}
}
