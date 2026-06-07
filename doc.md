# PocketMineJiafei - NetEase Protocol Patches

This document outlines the specific modifications made to the standard PocketMine-MP 3.9.8 codebase to ensure compatibility with the Chinese (NetEase) Minecraft: Bedrock Edition v1.12.0 client.

## 1. Network Protocol Modifications

The NetEase client utilizes a heavily modified transport layer compared to the global Bedrock version. The following patches were applied to bridge this gap:

### A. RakNet Protocol Downgrade
* **File modified:** `src/pocketmine/network/mcpe/RakLibInterface.php`
* **Change:** Downgraded `MCPE_RAKNET_PROTOCOL_VERSION` from `9` to `8`.
* **Reason:** Despite running on Minecraft Bedrock 1.12.0, the NetEase client continues to use RakNet protocol version 8. Without this change, the server refuses the connection with an "incompatible RakNet protocol version" error.

### B. Zlib Compression Removal & Custom Batching
* **Files modified:** 
  * `src/pocketmine/network/mcpe/protocol/BatchPacket.php`
  * `src/pocketmine/Server.php`
  * `src/pocketmine/network/CompressBatchedTask.php`
* **Change:** 
  * Completely removed `zlib_encode` and `zlib_decode` from the `BatchPacket` payload handling.
  * Removed the standard VarInt length prefixes used for sub-packets within a batch.
  * Modified the `Server::batchPackets()` method to send every packet individually, wrapped in its own `0xfe` (BatchPacket) ID, rather than genuinely batching them.
* **Reason:** The NetEase client completely strips standard Zlib compression from its payload. Sending standard compressed batches causes client/server disconnects, and standard Wireshark dissectors fail to parse the traffic. The protocol enforces a strict "uncompressed, single packet per `0xfe` wrapper" structure.

## 2. Resource & Language Restorations

* **Files added/restored:**
  * `src/pocketmine/resources/vanilla/*.json` (`creativeitems.json`, `item_id_map.json`, `recipes.json`, `block_id_map.json`, etc.)
  * `src/pocketmine/lang/locale/eng.ini`
* **Change:** Extracted missing Vanilla resources and the English language file from the official PocketMine-MP 3.9.8 Phar and injected them into the source tree.
* **Reason:** The server crashes with `file_get_contents` errors on startup if the `vanilla` JSON data maps are missing, as they are required to boot the Minecraft engine. The `eng.ini` file was required to prevent the setup wizard from getting stuck in an infinite loop.

## 3. Phar Build & Autoloader Fixes

* **File modified:** `build_phar.php`
* **Change:** Rewrote the Phar compilation script to explicitly include the `vendor/` directory and preserve the `src/` path prefix for all internal files.
* **Reason:** Composer's PSR-4 autoloader expects PocketMine classes to be located within a `src/` directory. The previous build script stripped this prefix, causing fatal "Composer autoloader not found" errors upon execution.

## 4. Rebranding & Cleanup

* **Files modified:** `composer.json`, `src/pocketmine/VersionInfo.php`, `.gitignore`, `README.md`
* **Change:** 
  * Global search-and-replace to rename `PocketMine-MP` to `PocketMineJiafei`.
  * Removed administrative boilerplate (`CONTRIBUTING.md`, `LICENSE`).
  * Updated `.gitignore` to prevent tracking of temporary packets, crashes, and build artifacts, while explicitly keeping startup scripts and configuration files.

## 5. Windows Distribution Packaging

* **Artifacts generated:** `PocketMineJiafei_Complete_Windows.zip`
* **Change:** Bundled the newly compiled `PocketMineJiafei.phar` with a legacy PHP 7.3.33 (x64) binary containing the `pthreads` extension.
* **Reason:** PocketMine-MP 3.x relies on the deprecated `pthreads` extension, which is only supported up to PHP 7.3. Modern PHP 8.x binaries (using `parallel`) will cause fatal startup errors. The complete package provides a one-click, self-contained Windows environment.
