<?php
declare(strict_types=1);

/**
 * Prismatch - Room Inactivity Cleanup Script
 * Automatically deletes rooms that have had no active players for 60 minutes.
 * Can be run via CLI, cron job, or scheduled task.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db.php';

use Prismatch\Repositories\RoomRepository;

$inactiveMinutes = isset($argv[1]) ? (int)$argv[1] : 60;
if ($inactiveMinutes <= 0) {
    $inactiveMinutes = 60;
}

echo "[" . date('Y-m-d H:i:s') . "] Cleaning up rooms with no players for {$inactiveMinutes} minutes...\n";

$repo = new RoomRepository();
$deleted = $repo->cleanupInactiveRooms($inactiveMinutes);

echo "[" . date('Y-m-d H:i:s') . "] Done. {$deleted} inactive room(s) deleted.\n";
