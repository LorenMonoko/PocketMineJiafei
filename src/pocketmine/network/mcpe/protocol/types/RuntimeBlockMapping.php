<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pocketmine\block\BlockIds;
use function file_get_contents;
use function getmypid;
use function json_decode;
use function mt_rand;
use function mt_srand;
use function shuffle;

/**
 * @internal
 */
final class RuntimeBlockMapping{

	/** @var int[] */
	private static $legacyToRuntimeMap = [];
	/** @var int[] */
	private static $runtimeToLegacyMap = [];
	/** @var mixed[] */
	private static $bedrockKnownStates;

	/** @var string|null */
	private static $runtimeIdTable111 = null;
	/** @var int[] */
	private static $translateTo111 = [];

	private function __construct(){
		//NOOP
	}

	public static function init() : void{
		$legacyIdMap = json_decode(file_get_contents(\pocketmine\RESOURCE_PATH . "legacy_id_map.json"), true);

		self::$bedrockKnownStates = self::randomizeTable(json_decode(file_get_contents(\pocketmine\RESOURCE_PATH . "runtimeid_table.json"), true));

		foreach(self::$bedrockKnownStates as $k => $obj){
			//this has to use the json offset to make sure the mapping is consistent with what we send over network, even though we aren't using all the entries
			if(!isset($legacyIdMap[$obj["name"]])){
				continue;
			}
			self::registerMapping($k, $legacyIdMap[$obj["name"]], $obj["data"]);
		}

		self::init111($legacyIdMap);
	}

	private static function init111(array $legacyIdMap) : void{
		$path = \pocketmine\RESOURCE_PATH . "vanilla/required_block_states_1.11.4.json";
		if(!file_exists($path)) return;

		$compressedTable = json_decode(file_get_contents($path), true);
		$decompressed = [];
		foreach($compressedTable as $prefix => $entries){
			foreach($entries as $shortStringId => $states){
				foreach($states as $state){
					$decompressed[] = [
						"name" => "$prefix:$shortStringId",
						"data" => $state
					];
				}
			}
		}

		$stream = new \pocketmine\network\mcpe\NetworkBinaryStream();
		$stream->putUnsignedVarInt(count($decompressed));
		foreach($decompressed as $k => $obj){
			$stream->putString($obj["name"]);
			$stream->putLShort($obj["data"]);

			// Create mapping: 1.9.1 RuntimeID -> 1.11.4 RuntimeID
			// We find the 1.9.1 ID for this name+data
			if(isset($legacyIdMap[$obj["name"]])){
				$legacyId = $legacyIdMap[$obj["name"]];
				$meta = $obj["data"];
				$runtime19 = self::$legacyToRuntimeMap[($legacyId << 4) | $meta] ?? null;
				if($runtime19 !== null){
					self::$translateTo111[$runtime19] = $k;
				}
			}
		}
		self::$runtimeIdTable111 = $stream->buffer;
	}

	public static function getRuntimeIdTable(int $protocol) : ?string{
		if($protocol >= 354){
			return self::$runtimeIdTable111;
		}
		return null;
	}

	public static function from19To111(int $runtimeId19) : int{
		return self::$translateTo111[$runtimeId19] ?? $runtimeId19;
	}

	public static function from19To111Legacy(int $legacyId19) : int{
		// This is a rough mapping for Version 0 chunks.
		// We map Legacy ID -> 1.11.4 Runtime ID.
		// Since Version 0 chunk IDs only go up to 255, we can only map the first 256 IDs.
		$runtime19 = self::$legacyToRuntimeMap[$legacyId19 << 4] ?? 0;
		return self::$translateTo111[$runtime19] ?? $legacyId19;
	}

	/**
	 * Randomizes the order of the runtimeID table to prevent plugins relying on them.
	 * Plugins shouldn't use this stuff anyway, but plugin devs have an irritating habit of ignoring what they
	 * aren't supposed to do, so we have to deliberately break it to make them stop.
	 *
	 * @param array $table
	 *
	 * @return array
	 */
	private static function randomizeTable(array $table) : array{
		$postSeed = mt_rand(); //save a seed to set afterwards, to avoid poor quality randoms
		mt_srand(getmypid() ?: 0); //Use a seed which is the same on all threads. This isn't a secure seed, but we don't care.
		shuffle($table);
		mt_srand($postSeed); //restore a good quality seed that isn't dependent on PID
		return $table;
	}

	/**
	 * @param int $id
	 * @param int $meta
	 *
	 * @return int
	 */
	public static function toStaticRuntimeId(int $id, int $meta = 0) : int{
		/*
		 * try id+meta first
		 * if not found, try id+0 (strip meta)
		 * if still not found, return update! block
		 */
		return self::$legacyToRuntimeMap[($id << 4) | $meta] ?? self::$legacyToRuntimeMap[$id << 4] ?? self::$legacyToRuntimeMap[BlockIds::INFO_UPDATE << 4];
	}

	/**
	 * @param int $runtimeId
	 *
	 * @return int[] [id, meta]
	 */
	public static function fromStaticRuntimeId(int $runtimeId) : array{
		$v = self::$runtimeToLegacyMap[$runtimeId];
		return [$v >> 4, $v & 0xf];
	}

	private static function registerMapping(int $staticRuntimeId, int $legacyId, int $legacyMeta) : void{
		self::$legacyToRuntimeMap[($legacyId << 4) | $legacyMeta] = $staticRuntimeId;
		self::$runtimeToLegacyMap[$staticRuntimeId] = ($legacyId << 4) | $legacyMeta;
	}

	/**
	 * @return array
	 */
	public static function getBedrockKnownStates() : array{
		return self::$bedrockKnownStates;
	}
}
RuntimeBlockMapping::init();
