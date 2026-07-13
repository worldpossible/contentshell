<?php
/**
 * sda-recover.php — Attempt to recover the internal SSD (SDA)
 *
 * Called via AJAX from the panic banner on the homepage when the device
 * is running on eMMC fallback storage (SDA failed to mount).
 *
 * Steps:
 *   1. Run e2fsck -y on /dev/sda1 to repair filesystem
 *   2. If successful, attempt to mount SDA at /.data
 *   3. If mounted, remove the .emmc-fallback flag and report success
 *
 * Returns JSON with status and log output.
 */

header('Content-Type: application/json');

// Only run if we're actually in fallback mode
if (!file_exists('/.data/.emmc-fallback')) {
    echo json_encode(['status' => 'ok', 'message' => 'SDA is already mounted normally.']);
    exit;
}

// Check if SDA exists at all
if (!file_exists('/dev/sda')) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal hard drive not detected. The drive may have physically failed.',
        'log' => ''
    ]);
    exit;
}

$log = [];
$overall_status = 'error';
$message = '';

// Step 1: Check for partition
if (!file_exists('/dev/sda1')) {
    $log[] = 'No partition found on /dev/sda — drive may need to be reformatted.';
    echo json_encode([
        'status' => 'error',
        'message' => 'No partition found on the internal hard drive. The partition table may be damaged.',
        'log' => implode("\n", $log)
    ]);
    exit;
}

// Step 2: Run filesystem check
$log[] = '=== Running filesystem check on /dev/sda1 ===';
$log[] = date('Y-m-d H:i:s');
$fsck_output = [];
$fsck_rc = 0;
exec('e2fsck -y /dev/sda1 2>&1', $fsck_output, $fsck_rc);
$log = array_merge($log, $fsck_output);
$log[] = "e2fsck exit code: $fsck_rc";

if ($fsck_rc > 2) {
    // Unrecoverable — offer to reformat
    $log[] = 'Filesystem errors are too severe to repair automatically.';
    echo json_encode([
        'status' => 'unrecoverable',
        'message' => 'The internal hard drive has severe filesystem corruption that cannot be automatically repaired. Content data may be lost. A full reformat is needed.',
        'log' => implode("\n", $log)
    ]);
    exit;
}

$log[] = '';
$log[] = '=== Filesystem check complete — attempting to mount ===';

// Step 3: Unmount eMMC fallback, mount SDA
exec('umount /.data 2>&1', $umount_out, $umount_rc);
$log[] = "Unmount eMMC fallback: rc=$umount_rc";

exec('mount -o noatime,nodiratime /dev/sda1 /.data 2>&1', $mount_out, $mount_rc);
$log = array_merge($log, $mount_out);

if ($mount_rc === 0) {
    // Success — remove fallback flag, create bind mount
    if (file_exists('/.data/.emmc-fallback')) {
        unlink('/.data/.emmc-fallback');
    }
    exec('mkdir -p /media/uploaded && mount -o bind /.data/uploaded/ /media/uploaded 2>/dev/null');
    if (!is_link('/media/RACHEL')) {
        symlink('/.data/RACHEL', '/media/RACHEL');
    }

    $log[] = '';
    $log[] = '=== SDA recovered and mounted successfully! ===';
    $log[] = 'Full content library is now available.';

    // Restart services that depend on /.data
    exec('systemctl restart nginx php8.3-fpm 2>/dev/null');

    echo json_encode([
        'status' => 'recovered',
        'message' => 'Internal hard drive recovered successfully! The full content library is now available. This page will reload.',
        'log' => implode("\n", $log)
    ]);
    exit;
}

// Mount failed even after fsck
$log[] = "Mount failed (rc=$mount_rc)";
$log[] = '';
$log[] = 'Re-mounting eMMC fallback...';
exec('mount -o noatime,nodiratime $(blkid -L emmc-data) /.data 2>&1');
touch('/.data/.emmc-fallback');

echo json_encode([
    'status' => 'error',
    'message' => 'Filesystem was repaired but the drive could not be mounted. The drive may have a hardware fault.',
    'log' => implode("\n", $log)
]);
