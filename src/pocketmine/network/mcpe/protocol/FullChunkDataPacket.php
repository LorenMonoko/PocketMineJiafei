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

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>


use pocketmine\network\mcpe\NetworkSession;

class FullChunkDataPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::FULL_CHUNK_DATA_PACKET;

	/** @var int */
	public $chunkX;
	/** @var int */
	public $chunkZ;
	/** @var string */
	public $data;

	protected function decodePayload(){
		$this->chunkX = $this->getVarInt();
		$this->chunkZ = $this->getVarInt();
		$this->data = $this->getString();
	}

	protected function encodePayload(){
		$this->putVarInt($this->chunkX);
		$this->putVarInt($this->chunkZ);

		$data = $this->data;
		if($this->protocol >= 354){
			$newData = "";
			$offset = 0;
			$len = strlen($data);
			// 16 is the standard number of subchunks for Bedrock 1.9
			for($i = 0; $i < 16; ++$i){
				if($offset >= $len) break;
				$version = ord($data{$offset});
				if($version === 0){ // SubChunk version 0
					if($offset + 1 + 4096 + 2048 > $len) break;
					$ids = substr($data, $offset + 1, 4096);
					$translatedIds = "";
					for($j = 0; $j < 4096; ++$j){
						$id19 = ord($ids{$j});
						// Map legacy ID to new Runtime ID
						// Since we can only fit 8 bits, we mask it.
						// This is imperfect but better than nothing.
						$translatedIds .= chr(\pocketmine\network\mcpe\protocol\types\RuntimeBlockMapping::from19To111Legacy($id19) & 0xff);
					}
					$newData .= "\x00" . $translatedIds . substr($data, $offset + 1 + 4096, 2048);
					$offset += 1 + 4096 + 2048;
				}else{
					break; // Stop if we hit something unexpected
				}
			}
			if($offset < $len){
				$newData .= substr($data, $offset);
			}
			$data = $newData;
		}

		$this->putString($data);
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleFullChunkData($this);
	}
}
