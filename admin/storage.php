<?php

require_once("common.php");

$page_title  = $lang['storage'];
$page_script = "";
$page_nav    = "storage";

if (!authorized()) {
    exit();
}

# Define the modules directory
define('MODULES_DIR', '/.data/RACHEL/rachel/modules');

# Get system version info for the World Possible tab
# OS detection
foreach (glob("/etc/*-release") as $filename) {
    $filecont = file_get_contents($filename);
    if (preg_match("/PRETTY_NAME=\"(.+?)\"/", $filecont, $matches)) {
        $os = $matches[1];
        break;
    }
}
if (!isset($os)) {
    foreach (glob("/etc/*-release") as $filename) {
        $os = file_get_contents($filename);
        break;
    }
}
if (!isset($os)) { $os = exec("uname -srmp"); }

# Hardware detection
$hardware = "";
unset($output, $matches);
exec("dmesg 2>&1 | grep 'Machine model'", $output);
if (isset($output[0]) && preg_match("/Machine model: (.+)/", $output[0], $matches)) {
    $hardware = $matches[1];
} else {
    $plusmodel = exec("uname -n");
    if ($plusmodel == "WRTD-303N-Server") {
        $hardware = "Intel CAP 1.0";
    } else if ($plusmodel == "WAPD-235N-Server") {
        $hardware = "Intel CAP 2.0";
    }
    if (!$hardware) {
        $hardware = exec("head -1 /etc/issue | cut -b1-7");
    }
    exec("arch", $output);
    if ($output) {
        if ($hardware) {
            $hardware .= " ($output[0])";
        } else {
            $hardware = $output[0];
        }
    }
}

# RACHEL version
$rachel_version = "?";
if (file_exists("/etc/rachelinstaller-version")) {
    $rachel_version = file_get_contents("/etc/rachelinstaller-version");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['listDrives'])){
        listDrives();
        return;
    }
    
    if(isset($_POST['ejectPath'])){
        ejectDrive();
        return;
    }
    
    if(isset($_POST['scanDrive'])){
        scanDriveForContent();
        return;
    }
    
    if(isset($_POST['installZip'])){
        installZipModule();
        return;
    }
    
    if(isset($_POST['installFolder'])){
        installFolderAsModule();
        return;
    }
    
    if(isset($_POST['buildModule'])){
        buildCustomModule();
        return;
    }
    
    if(isset($_POST['exportModule'])){
        exportModuleToUSB();
        return;
    }
    
    if(isset($_POST['getExportableModules'])){
        getExportableModules();
        return;
    }
    
    if(isset($_POST['getModuleSize'])){
        getModuleSize();
        return;
    }
}

# Handle browser file upload
if (isset($_FILES['uploadZip'])) {
    handleBrowserUpload();
    exit();
}

# Handle quiz media upload
if (isset($_FILES['quizMedia'])) {
    handleQuizMediaUpload();
    exit();
}

# Handle browser-based ZIP upload
function handleBrowserUpload() {
    header('Content-Type: application/json; charset=UTF-8');
    
    if (!isset($_FILES['uploadZip']) || $_FILES['uploadZip']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds maximum upload size',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        $errorCode = isset($_FILES['uploadZip']) ? $_FILES['uploadZip']['error'] : UPLOAD_ERR_NO_FILE;
        $message = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : 'Unknown upload error';
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => $message]);
        return;
    }
    
    $uploadedFile = $_FILES['uploadZip']['tmp_name'];
    $originalName = $_FILES['uploadZip']['name'];
    $moduleName = isset($_POST['moduleName']) ? $_POST['moduleName'] : '';
    $moduleTitle = isset($_POST['moduleTitle']) ? $_POST['moduleTitle'] : '';
    $moduleDesc = isset($_POST['moduleDesc']) ? $_POST['moduleDesc'] : '';
    $includeSubfolders = isset($_POST['includeSubfolders']) && $_POST['includeSubfolders'] === '1';
    
    # Determine module name from filename if not provided
    if (empty($moduleName)) {
        $moduleName = pathinfo($originalName, PATHINFO_FILENAME);
    }
    
    # Sanitize module name
    $moduleName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $moduleName);
    
    if (empty($moduleTitle)) {
        $moduleTitle = str_replace('_', ' ', $moduleName);
        $moduleTitle = ucwords($moduleTitle);
    }
    
    if (empty($moduleDesc)) {
        $moduleDesc = 'Content uploaded via browser';
    }
    
    $targetDir = MODULES_DIR . '/' . $moduleName;
    
    # Check if module already exists
    if (is_dir($targetDir)) {
        header('HTTP/1.1 409 Conflict');
        echo json_encode(['error' => 'Module already exists: ' . $moduleName]);
        return;
    }
    
    # Create target directory
    if (!mkdir($targetDir, 0755, true)) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to create module directory']);
        return;
    }
    
    # Check if it's a ZIP file
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $uploadedFile);
    finfo_close($finfo);
    
    $isZip = in_array($mimeType, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream']) 
             && strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) === 'zip';
    
    if ($isZip) {
        # Extract the zip
        exec("unzip -o " . escapeshellarg($uploadedFile) . " -d " . escapeshellarg($targetDir) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            # Try with PHP ZipArchive as fallback
            $zip = new ZipArchive;
            if ($zip->open($uploadedFile) === TRUE) {
                $zip->extractTo($targetDir);
                $zip->close();
            } else {
                exec("rm -rf " . escapeshellarg($targetDir));
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(['error' => 'Failed to extract zip file']);
                return;
            }
        }
        
        # Check if zip extracted into a single subfolder (common pattern)
        $contents = glob($targetDir . '/*');
        if (count($contents) === 1 && is_dir($contents[0])) {
            # Move contents up one level
            $subdir = $contents[0];
            exec("mv " . escapeshellarg($subdir) . "/* " . escapeshellarg($targetDir) . "/ 2>/dev/null");
            exec("mv " . escapeshellarg($subdir) . "/.* " . escapeshellarg($targetDir) . "/ 2>/dev/null");
            @rmdir($subdir);
        }
    } else {
        # Not a zip - just move the file
        move_uploaded_file($uploadedFile, $targetDir . '/' . $originalName);
    }
    
    # Check for existing index file
    $hasIndex = false;
    $possibleIndexes = ['index.html', 'index.htm', 'index.php', 'home.html', 'home.htm', 'default.html', 'default.htm'];
    foreach ($possibleIndexes as $idx) {
        if (file_exists($targetDir . '/' . $idx)) {
            $hasIndex = true;
            break;
        }
    }
    
    # Get subfolders if requested and no index
    $subfolders = array();
    if ($includeSubfolders && !$hasIndex) {
        $items = glob($targetDir . '/*', GLOB_ONLYDIR);
        foreach ($items as $item) {
            $name = basename($item);
            if (strpos($name, '.') === 0) continue;
            if (in_array($name, ['System Volume Information', 'RECYCLER', '$RECYCLE.BIN', 'lost+found', '__MACOSX'])) continue;
            $subfolders[] = $name;
        }
        sort($subfolders);
    }
    
    # Create rachel-index.php
    createRachelIndex($targetDir, $moduleTitle, $moduleDesc, $subfolders);
    
    header("HTTP/1.1 200 OK");
    echo json_encode(['success' => true, 'module' => $moduleName]);
}

function ejectDrive(){
    $mountPath = $_POST['ejectPath'];

    # CMAL100 mounted in /media/usb, CMAL150 mounts in /media/root
    if (strpos($mountPath, '/media/usb') === 0
        || strpos($mountPath, '/media/root') === 0
    ) {

        exec("umount " . escapeshellarg($mountPath) . " 2>&1", $output, $returnCode);

        if ($returnCode === 0) {
            header("HTTP/1.1 200 OK");
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true]);
            die();
        } else {
            header('HTTP/1.1 500 Internal Server');
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => 'Unmount failed']);
            die();
        }
    } else {
        header('HTTP/1.1 500 Internal Server');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => false, 'message' => 'Invalid mount path']);
        die();
    }
}

# Handle quiz media upload (images, videos, audio, pdfs for quiz questions)
# World Possible Attribution Required
function handleQuizMediaUpload() {
    header('Content-Type: application/json; charset=UTF-8');
    
    // Attribution verification
    if (!wp_feature_enabled('quiz_media')) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'World Possible attribution required for this feature']);
        return;
    }
    
    if (!isset($_FILES['quizMedia']) || $_FILES['quizMedia']['error'] !== UPLOAD_ERR_OK) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Upload failed']);
        return;
    }
    
    $uploadDir = MODULES_DIR . '/quiz-media';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        
        // Create a simple rachel-index.php for the quiz-media module
        $indexContent = '<?php
$mod = array(
    "title"         => "Quiz Media",
    "category"      => "system",
    "description"   => "Media files for quizzes",
    "dir"           => __DIR__,
    "moddir"        => basename(__DIR__),
    "fragment"      => ""  // hidden module
);
?>';
        file_put_contents($uploadDir . '/rachel-index.php', $indexContent);
    }
    
    $originalName = $_FILES['quizMedia']['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    // Allowed extensions
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mp3', 'wav', 'ogg', 'pdf', 'doc', 'docx', 'ppt', 'pptx'];
    if (!in_array($extension, $allowedExts)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'File type not allowed: ' . $extension]);
        return;
    }
    
    // Generate unique filename
    $filename = date('Y-m-d_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
    $targetPath = $uploadDir . '/' . $filename;
    
    if (move_uploaded_file($_FILES['quizMedia']['tmp_name'], $targetPath)) {
        $url = '/modules/quiz-media/' . $filename;
        echo json_encode([
            'success' => true,
            'url' => $url,
            'filename' => $filename
        ]);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to save file']);
    }
}

# Get list of modules that can be exported to USB
# World Possible Attribution Required
function getExportableModules() {
    header('Content-Type: application/json; charset=UTF-8');
    
    // Attribution verification
    if (!wp_feature_enabled('usb_export')) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'World Possible attribution required for this feature']);
        return;
    }
    
    $modules = array();
    $fsmods = getmods_fs();
    
    if ($fsmods) {
        foreach ($fsmods as $moddir => $mod) {
            if (!$mod['fragment']) continue;
            
            // Skip size calculation for speed - will be calculated on demand
            $modules[] = array(
                'moddir' => $moddir,
                'title' => isset($mod['title']) ? $mod['title'] : $moddir
            );
        }
    }
    
    // Sort by title
    usort($modules, function($a, $b) {
        return strcasecmp($a['title'], $b['title']);
    });
    
    echo json_encode(['modules' => $modules]);
}

# Get size of a specific module (called on-demand)
function getModuleSize() {
    header('Content-Type: application/json; charset=UTF-8');
    
    $moddir = isset($_POST['moddir']) ? $_POST['moddir'] : '';
    
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $moddir) || strpos($moddir, '..') !== false) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Invalid module name']);
        return;
    }
    
    $modPath = MODULES_DIR . '/' . $moddir;
    if (!is_dir($modPath)) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Module not found']);
        return;
    }
    
    $size = 0;
    $output = array();
    exec("du -sb " . escapeshellarg($modPath) . " 2>/dev/null", $output);
    if (!empty($output)) {
        $parts = explode("\t", $output[0]);
        $size = intval($parts[0]);
    }
    
    echo json_encode([
        'moddir' => $moddir,
        'size' => $size,
        'sizeFormatted' => formatFileSize($size)
    ]);
}

# Export a module to USB drive
# World Possible Attribution Required
function exportModuleToUSB() {
    header('Content-Type: application/json; charset=UTF-8');
    
    // Attribution verification
    if (!wp_feature_enabled('usb_export')) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'World Possible attribution required for this feature']);
        return;
    }
    
    $moddir = isset($_POST['moddir']) ? $_POST['moddir'] : '';
    $usbPath = isset($_POST['usbPath']) ? $_POST['usbPath'] : '';
    
    // Validate module name
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $moddir) || strpos($moddir, '..') !== false) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Invalid module name']);
        return;
    }
    
    // Validate USB path
    if (strpos($usbPath, '/media/usb') !== 0 && strpos($usbPath, '/media/root') !== 0) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Invalid USB path']);
        return;
    }
    
    $sourcePath = MODULES_DIR . '/' . $moddir;
    if (!is_dir($sourcePath)) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Module not found']);
        return;
    }
    
    // Try to remount USB as read-write if it's mounted read-only
    exec("mount | grep " . escapeshellarg($usbPath), $mountOutput);
    if (!empty($mountOutput) && strpos($mountOutput[0], 'ro,') !== false) {
        // USB is mounted read-only, try to remount as read-write
        exec("sudo mount -o remount,rw " . escapeshellarg($usbPath) . " 2>&1", $remountOutput, $remountCode);
        if ($remountCode !== 0) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'USB drive is read-only and could not be remounted. Please check the drive or try a different USB drive.']);
            return;
        }
    }
    
    // Check USB has enough space and won't exceed 95% capacity after export
    $moduleSize = 0;
    $output = array();
    exec("du -sb " . escapeshellarg($sourcePath) . " 2>/dev/null", $output);
    if (!empty($output)) {
        $parts = explode("\t", $output[0]);
        $moduleSize = intval($parts[0]);
    }
    
    $freeSpace = disk_free_space($usbPath);
    $totalSpace = disk_total_space($usbPath);
    
    if ($freeSpace === false || $freeSpace < $moduleSize) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode([
            'error' => 'Not enough space on USB drive',
            'required' => formatFileSize($moduleSize),
            'available' => formatFileSize($freeSpace ?: 0)
        ]);
        return;
    }
    
    // Check if export would push drive over 95% capacity
    if ($totalSpace !== false && $totalSpace > 0) {
        $usedSpaceAfterExport = ($totalSpace - $freeSpace) + $moduleSize;
        $percentAfterExport = ($usedSpaceAfterExport / $totalSpace) * 100;
        
        if ($percentAfterExport > 95) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode([
                'error' => 'Export would fill USB drive beyond 95% capacity',
                'currentUsage' => round((($totalSpace - $freeSpace) / $totalSpace) * 100, 1) . '%',
                'afterExport' => round($percentAfterExport, 1) . '%',
                'moduleSize' => formatFileSize($moduleSize),
                'freeSpace' => formatFileSize($freeSpace)
            ]);
            return;
        }
    }
    
    // Create export directory using shell command (more reliable on USB)
    $exportDir = $usbPath . '/RACHEL-Export';
    exec("sudo mkdir -p " . escapeshellarg($exportDir) . " 2>&1", $mkdirOutput, $mkdirCode);
    if ($mkdirCode !== 0 || !is_dir($exportDir)) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to create export directory on USB: ' . implode(' ', $mkdirOutput)]);
        return;
    }
    
    $destPath = $exportDir . '/' . $moddir;
    
    // Remove existing if present (including any partial/failed exports)
    if (is_dir($destPath)) {
        exec("sudo rm -rf " . escapeshellarg($destPath));
    }
    
    // Create destination directory first
    exec("sudo mkdir -p " . escapeshellarg($destPath) . " 2>&1", $mkdirOutput2, $mkdirCode2);
    if ($mkdirCode2 !== 0) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to create module directory: ' . implode(' ', $mkdirOutput2)]);
        return;
    }
    
    // Copy module using rsync for better handling of large files
    $cmd = "sudo rsync -a " . escapeshellarg($sourcePath . '/') . " " . escapeshellarg($destPath . '/') . " 2>&1";
    $output = array();
    $returnCode = 0;
    exec($cmd, $output, $returnCode);
    
    if ($returnCode !== 0) {
        // Fallback to cp
        $cmd = "sudo cp -r " . escapeshellarg($sourcePath . '/.') . " " . escapeshellarg($destPath . '/') . " 2>&1";
        $output2 = array();
        exec($cmd, $output2, $returnCode);
        
        if ($returnCode !== 0) {
            // Clean up partial export on failure
            exec("sudo rm -rf " . escapeshellarg($destPath));
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode([
                'error' => 'Failed to copy module (cleaned up partial export): ' . implode("\n", array_merge($output, $output2)),
                'hint' => 'The USB drive may be full or have filesystem errors. Try a different drive.'
            ]);
            return;
        }
    }
    
    // Verify the export completed successfully by checking destination exists and has content
    $destSize = 0;
    $destOutput = array();
    exec("du -sb " . escapeshellarg($destPath) . " 2>/dev/null", $destOutput);
    if (!empty($destOutput)) {
        $parts = explode("\t", $destOutput[0]);
        $destSize = intval($parts[0]);
    }
    
    // If destination is much smaller than source, something went wrong
    if ($destSize < ($moduleSize * 0.9)) {
        // Clean up partial export
        exec("sudo rm -rf " . escapeshellarg($destPath));
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode([
            'error' => 'Export appears incomplete (cleaned up partial export)',
            'expected' => formatFileSize($moduleSize),
            'actual' => formatFileSize($destSize),
            'hint' => 'The USB drive may have run out of space during transfer. Check drive capacity.'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Module exported successfully',
        'destination' => $destPath,
        'size' => formatFileSize($moduleSize)
    ]);
}

function listDrives() {
    $drives = array();

    exec("df -h", $output);

    # this is to handle volume names with spaces
    exec("df --output=target", $volNames);
    $i = 0;

    foreach ($output as $line) {
        $info = explode(" ", preg_replace('/\s+/', ' ', $line));

        if (count($info) >= 6) {

            $mountPath = $volNames[$i];
            ++$i;

            # CMAL100 mounted in /media/usb, CMAL150 mounts in /media/root
            if (strpos($mountPath, '/media/usb') === 0
                || strpos($mountPath, '/media/root') === 0
            ) {
                $driveInfo = array(
                    'mountPath' => $mountPath,
                    'label'     => preg_replace("/\/media\/(usb|root)\//", "", $mountPath),
                    'size'      => $info[1],
                    'used'      => $info[4],
                );
                
                array_push($drives, $driveInfo);
            }
        }
    }

    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($drives);
    die();
}

# Scan a USB drive for installable content (zips and folders)
function scanDriveForContent() {
    $mountPath = $_POST['scanDrive'];
    
    # Validate the mount path
    if (strpos($mountPath, '/media/usb') !== 0 && strpos($mountPath, '/media/root') !== 0) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Invalid mount path']);
        die();
    }
    
    if (!is_dir($mountPath)) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Drive not found']);
        die();
    }
    
    $content = array(
        'zips' => array(),
        'folders' => array()
    );
    
    # Scan for .zip files (top level and one level deep)
    $zipFiles = glob($mountPath . '/*.zip');
    $zipFiles = array_merge($zipFiles, glob($mountPath . '/*/*.zip'));
    
    foreach ($zipFiles as $zip) {
        $size = filesize($zip);
        $sizeFormatted = formatFileSize($size);
        $content['zips'][] = array(
            'path' => $zip,
            'name' => basename($zip),
            'size' => $sizeFormatted,
            'sizeBytes' => $size
        );
    }
    
    # Scan for folders that could be modules (top level only)
    $folders = glob($mountPath . '/*', GLOB_ONLYDIR);
    
    foreach ($folders as $folder) {
        $folderName = basename($folder);
        
        # Skip hidden folders and system folders
        if (strpos($folderName, '.') === 0) continue;
        if (in_array($folderName, ['System Volume Information', 'RECYCLER', '$RECYCLE.BIN', 'lost+found'])) continue;
        
        # Check if it's already a RACHEL module (has rachel-index.php)
        $isModule = file_exists($folder . '/rachel-index.php');
        
        # Count files in folder (limit depth to avoid hanging on huge folders)
        $fileCount = 0;
        $dirHandle = opendir($folder);
        if ($dirHandle) {
            while (($file = readdir($dirHandle)) !== false) {
                if ($file !== '.' && $file !== '..') $fileCount++;
            }
            closedir($dirHandle);
        }
        
        $content['folders'][] = array(
            'path' => $folder,
            'name' => $folderName,
            'isModule' => $isModule,
            'fileCount' => $fileCount
        );
    }
    
    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($content);
    die();
}

# Install a zip file as a module
function installZipModule() {
    $zipPath = $_POST['installZip'];
    $moduleName = isset($_POST['moduleName']) ? $_POST['moduleName'] : '';
    
    # Validate zip path is on a USB drive
    if (strpos($zipPath, '/media/usb') !== 0 && strpos($zipPath, '/media/root') !== 0) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Invalid zip path']);
        die();
    }
    
    if (!file_exists($zipPath)) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Zip file not found']);
        die();
    }
    
    # Determine module name from zip filename if not provided
    if (empty($moduleName)) {
        $moduleName = pathinfo($zipPath, PATHINFO_FILENAME);
    }
    
    # Sanitize module name
    $moduleName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $moduleName);
    
    $targetDir = MODULES_DIR . '/' . $moduleName;
    
    # Check if module already exists
    if (is_dir($targetDir)) {
        header('HTTP/1.1 409 Conflict');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Module already exists: ' . $moduleName]);
        die();
    }
    
    # Create target directory
    if (!mkdir($targetDir, 0755, true)) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Failed to create module directory']);
        die();
    }
    
    # Extract the zip using unzip command (more reliable for large files)
    exec("unzip -o " . escapeshellarg($zipPath) . " -d " . escapeshellarg($targetDir) . " 2>&1", $output, $returnCode);
    
    if ($returnCode !== 0) {
        # Try with PHP ZipArchive as fallback
        $zip = new ZipArchive;
        if ($zip->open($zipPath) === TRUE) {
            $zip->extractTo($targetDir);
            $zip->close();
        } else {
            exec("rm -rf " . escapeshellarg($targetDir));
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'Failed to extract zip file']);
            die();
        }
    }
    
    # Check if zip extracted into a single subfolder (common pattern)
    $contents = glob($targetDir . '/*');
    if (count($contents) === 1 && is_dir($contents[0])) {
        # Move contents up one level
        $subdir = $contents[0];
        exec("mv " . escapeshellarg($subdir) . "/* " . escapeshellarg($targetDir) . "/ 2>/dev/null");
        exec("mv " . escapeshellarg($subdir) . "/.* " . escapeshellarg($targetDir) . "/ 2>/dev/null");
        @rmdir($subdir);
    }
    
    # If no rachel-index.php exists, create one
    if (!file_exists($targetDir . '/rachel-index.php')) {
        createRachelIndex($targetDir, $moduleName);
    }
    
    # Auto-assign 'local' category to newly installed module
    $db = getdb();
    if ($db) {
        try {
            $stmt = $db->prepare("INSERT OR REPLACE INTO module_categories (moddir, categories) VALUES (:moddir, :categories)");
            $stmt->bindValue(':moddir', $moduleName, SQLITE3_TEXT);
            $stmt->bindValue(':categories', json_encode(['local']), SQLITE3_TEXT);
            $stmt->execute();
        } catch (Exception $e) {
            // Silently fail - category assignment is non-critical
        }
    }
    
    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => true, 'module' => $moduleName]);
    die();
}

# Install a folder as a module
function installFolderAsModule() {
    $folderPath = $_POST['installFolder'];
    $moduleName = isset($_POST['moduleName']) ? $_POST['moduleName'] : '';
    $moduleTitle = isset($_POST['moduleTitle']) ? $_POST['moduleTitle'] : '';
    $moduleDesc = isset($_POST['moduleDesc']) ? $_POST['moduleDesc'] : '';
    $includeSubfolders = isset($_POST['includeSubfolders']) && $_POST['includeSubfolders'] === '1';
    
    # Validate folder path is on a USB drive
    if (strpos($folderPath, '/media/usb') !== 0 && strpos($folderPath, '/media/root') !== 0) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Invalid folder path']);
        die();
    }
    
    if (!is_dir($folderPath)) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Folder not found']);
        die();
    }
    
    # Determine module name from folder name if not provided
    if (empty($moduleName)) {
        $moduleName = basename($folderPath);
    }
    
    # Sanitize module name
    $moduleName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $moduleName);
    
    if (empty($moduleTitle)) {
        $moduleTitle = str_replace('_', ' ', $moduleName);
        $moduleTitle = ucwords($moduleTitle);
    }
    
    if (empty($moduleDesc)) {
        $moduleDesc = 'Content installed from USB';
    }
    
    $targetDir = MODULES_DIR . '/' . $moduleName;
    
    # Check if module already exists
    if (is_dir($targetDir)) {
        header('HTTP/1.1 409 Conflict');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Module already exists: ' . $moduleName]);
        die();
    }
    
    # Copy the folder using cp command (handles large folders better)
    exec("cp -r " . escapeshellarg($folderPath) . " " . escapeshellarg($targetDir) . " 2>&1", $output, $returnCode);
    
    if ($returnCode !== 0) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Failed to copy folder: ' . implode("\n", $output)]);
        die();
    }
    
    # Check if folder has an existing index file
    $hasIndex = false;
    $possibleIndexes = ['index.html', 'index.htm', 'index.php', 'home.html', 'home.htm', 'default.html', 'default.htm'];
    foreach ($possibleIndexes as $idx) {
        if (file_exists($targetDir . '/' . $idx)) {
            $hasIndex = true;
            break;
        }
    }
    
    # Get subfolders if requested
    $subfolders = array();
    if ($includeSubfolders && !$hasIndex) {
        $items = glob($targetDir . '/*', GLOB_ONLYDIR);
        foreach ($items as $item) {
            $name = basename($item);
            # Skip hidden and system folders
            if (strpos($name, '.') === 0) continue;
            if (in_array($name, ['System Volume Information', 'RECYCLER', '$RECYCLE.BIN', 'lost+found'])) continue;
            $subfolders[] = $name;
        }
        sort($subfolders);
    }
    
    # Create or overwrite rachel-index.php
    createRachelIndex($targetDir, $moduleTitle, $moduleDesc, $subfolders);
    
    # Auto-assign 'local' category to newly installed module
    $db = getdb();
    if ($db) {
        try {
            $stmt = $db->prepare("INSERT OR REPLACE INTO module_categories (moddir, categories) VALUES (:moddir, :categories)");
            $stmt->bindValue(':moddir', $moduleName, SQLITE3_TEXT);
            $stmt->bindValue(':categories', json_encode(['local']), SQLITE3_TEXT);
            $stmt->execute();
        } catch (Exception $e) {
            // Silently fail - category assignment is non-critical
        }
    }
    
    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => true, 'module' => $moduleName]);
    die();
}

# Build a custom module from the module builder
function buildCustomModule() {
    header('Content-Type: application/json; charset=UTF-8');
    
    $moduleName = isset($_POST['moduleName']) ? $_POST['moduleName'] : '';
    $moduleTitle = isset($_POST['moduleTitle']) ? $_POST['moduleTitle'] : '';
    $moduleDesc = isset($_POST['moduleDesc']) ? $_POST['moduleDesc'] : '';
    $pagesJson = isset($_POST['pages']) ? $_POST['pages'] : '[]';
    
    if (empty($moduleName)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Module name is required']);
        return;
    }
    
    # Sanitize module name
    $moduleName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $moduleName);
    
    if (empty($moduleTitle)) {
        $moduleTitle = str_replace('_', ' ', $moduleName);
        $moduleTitle = ucwords($moduleTitle);
    }
    
    $targetDir = MODULES_DIR . '/' . $moduleName;
    
    # Check if module already exists
    if (is_dir($targetDir)) {
        header('HTTP/1.1 409 Conflict');
        echo json_encode(['error' => 'Module already exists: ' . $moduleName]);
        return;
    }
    
    # Create target directory
    if (!mkdir($targetDir, 0755, true)) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to create module directory']);
        return;
    }
    
    # Handle logo upload
    $logoFile = '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logoExt = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $logoFile = 'logo.' . strtolower($logoExt);
        move_uploaded_file($_FILES['logo']['tmp_name'], $targetDir . '/' . $logoFile);
    }
    
    # Parse pages
    $pages = json_decode($pagesJson, true);
    if (!is_array($pages) || empty($pages)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'At least one page is required']);
        return;
    }
    
    # Process each page and create HTML files
    $pageFiles = [];
    foreach ($pages as $idx => $page) {
        $pageNum = $idx + 1;
        $pageTitle = isset($page['title']) ? $page['title'] : 'Page ' . $pageNum;
        $pageSlug = 'page' . $pageNum;
        $pageFile = $pageSlug . '.html';
        
        # Build page HTML content
        $pageContent = '';
        
        if (isset($page['blocks']) && is_array($page['blocks'])) {
            foreach ($page['blocks'] as $block) {
                $type = isset($block['type']) ? $block['type'] : 'text';
                
                if ($type === 'text') {
                    $text = isset($block['content']) ? htmlspecialchars($block['content']) : '';
                    $text = nl2br($text);
                    $pageContent .= "<div class=\"text-block\">\n<p>{$text}</p>\n</div>\n\n";
                } else {
                    # Media block
                    $fileKey = isset($block['fileKey']) ? $block['fileKey'] : '';
                    $caption = isset($block['caption']) ? htmlspecialchars($block['caption']) : '';
                    
                    if ($fileKey && isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                        $origName = $_FILES[$fileKey]['name'];
                        $ext = pathinfo($origName, PATHINFO_EXTENSION);
                        $safeFileName = $pageSlug . '_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME)) . '.' . $ext;
                        
                        move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetDir . '/' . $safeFileName);
                        
                        if ($type === 'video') {
                            $pageContent .= "<div class=\"media-block video-block\">\n";
                            $pageContent .= "<video controls width=\"100%\">\n<source src=\"{$safeFileName}\" type=\"video/mp4\">\nYour browser does not support video.\n</video>\n";
                            if ($caption) {
                                $pageContent .= "<p class=\"caption\">{$caption}</p>\n";
                            }
                            $pageContent .= "</div>\n\n";
                        } elseif ($type === 'pdf') {
                            $pageContent .= "<div class=\"media-block pdf-block\">\n";
                            $pageContent .= "<a href=\"{$safeFileName}\" class=\"pdf-link\" target=\"_blank\">📄 {$caption}</a>\n";
                            $pageContent .= "<iframe src=\"{$safeFileName}\" width=\"100%\" height=\"600px\"></iframe>\n";
                            $pageContent .= "</div>\n\n";
                        } elseif ($type === 'image') {
                            $pageContent .= "<div class=\"media-block image-block\">\n";
                            $pageContent .= "<img src=\"{$safeFileName}\" alt=\"{$caption}\">\n";
                            if ($caption) {
                                $pageContent .= "<p class=\"caption\">{$caption}</p>\n";
                            }
                            $pageContent .= "</div>\n\n";
                        }
                    }
                }
            }
        }
        
        # Create page HTML
        $pageHtml = "<!DOCTYPE html>\n<html>\n<head>\n";
        $pageHtml .= "<meta charset=\"UTF-8\">\n";
        $pageHtml .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $pageHtml .= "<title>{$pageTitle}</title>\n";
        $pageHtml .= "<style>\n";
        $pageHtml .= "* { box-sizing: border-box; }\n";
        $pageHtml .= "body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; background: #f5f5f5; color: #1e293b; }\n";
        $pageHtml .= ".header { display: flex; align-items: center; justify-content: space-between; padding: 15px 0; border-bottom: 2px solid #3b82f6; margin-bottom: 20px; }\n";
        $pageHtml .= ".header h1 { margin: 0; color: #1e3a5f; font-size: 1.5em; }\n";
        $pageHtml .= ".nav { display: flex; gap: 10px; }\n";
        $pageHtml .= ".nav a { padding: 8px 16px; background: #3b82f6; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9em; }\n";
        $pageHtml .= ".nav a:hover { background: #2563eb; }\n";
        $pageHtml .= ".nav a.disabled { background: #94a3b8; pointer-events: none; }\n";
        $pageHtml .= ".text-block { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e5e7eb; }\n";
        $pageHtml .= ".text-block p { margin: 0; line-height: 1.7; }\n";
        $pageHtml .= ".media-block { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e5e7eb; text-align: center; }\n";
        $pageHtml .= ".media-block img { max-width: 100%; height: auto; border-radius: 4px; }\n";
        $pageHtml .= ".media-block video { max-width: 100%; border-radius: 4px; }\n";
        $pageHtml .= ".media-block iframe { border: 1px solid #e5e7eb; border-radius: 4px; }\n";
        $pageHtml .= ".media-block .caption { color: #64748b; font-size: 0.9em; margin-top: 10px; }\n";
        $pageHtml .= ".pdf-link { display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 4px; margin-bottom: 15px; }\n";
        $pageHtml .= "</style>\n</head>\n<body>\n\n";
        
        # Navigation
        $prevLink = $pageNum > 1 ? 'page' . ($pageNum - 1) . '.html' : '';
        $nextLink = $pageNum < count($pages) ? 'page' . ($pageNum + 1) . '.html' : '';
        
        $pageHtml .= "<div class=\"header\">\n";
        $pageHtml .= "<h1>{$pageTitle}</h1>\n";
        $pageHtml .= "<div class=\"nav\">\n";
        $pageHtml .= "<a href=\"index.html\">🏠 Home</a>\n";
        if ($prevLink) {
            $pageHtml .= "<a href=\"{$prevLink}\">← Previous</a>\n";
        }
        if ($nextLink) {
            $pageHtml .= "<a href=\"{$nextLink}\">Next →</a>\n";
        }
        $pageHtml .= "</div>\n</div>\n\n";
        
        $pageHtml .= $pageContent;
        $pageHtml .= "\n</body>\n</html>";
        
        file_put_contents($targetDir . '/' . $pageFile, $pageHtml);
        
        $pageFiles[] = [
            'file' => $pageFile,
            'title' => $pageTitle
        ];
    }
    
    # Create index.html with links to all pages
    $indexHtml = "<!DOCTYPE html>\n<html>\n<head>\n";
    $indexHtml .= "<meta charset=\"UTF-8\">\n";
    $indexHtml .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    $indexHtml .= "<title>{$moduleTitle}</title>\n";
    $indexHtml .= "<style>\n";
    $indexHtml .= "* { box-sizing: border-box; }\n";
    $indexHtml .= "body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; background: #f5f5f5; color: #1e293b; }\n";
    $indexHtml .= ".header { display: flex; align-items: center; gap: 20px; padding-bottom: 15px; border-bottom: 3px solid #3b82f6; margin-bottom: 25px; }\n";
    $indexHtml .= ".header img { max-width: 100px; max-height: 100px; border-radius: 8px; }\n";
    $indexHtml .= ".header h1 { margin: 0 0 8px 0; color: #1e3a5f; }\n";
    $indexHtml .= ".header p { margin: 0; color: #64748b; }\n";
    $indexHtml .= ".pages-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; }\n";
    $indexHtml .= ".page-card { display: block; padding: 20px; background: white; border: 1px solid #e5e7eb; border-radius: 8px; text-decoration: none; color: #1e40af; font-weight: 500; transition: all 0.2s; }\n";
    $indexHtml .= ".page-card:hover { background: #eff6ff; border-color: #3b82f6; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15); }\n";
    $indexHtml .= ".page-card .num { display: inline-block; background: #3b82f6; color: white; width: 28px; height: 28px; border-radius: 50%; text-align: center; line-height: 28px; margin-right: 10px; font-size: 0.85em; }\n";
    $indexHtml .= "</style>\n</head>\n<body>\n\n";
    
    $indexHtml .= "<div class=\"header\">\n";
    if ($logoFile) {
        $indexHtml .= "<img src=\"{$logoFile}\" alt=\"\">\n";
    }
    $indexHtml .= "<div>\n<h1>{$moduleTitle}</h1>\n";
    if ($moduleDesc) {
        $indexHtml .= "<p>{$moduleDesc}</p>\n";
    }
    $indexHtml .= "</div>\n</div>\n\n";
    
    $indexHtml .= "<div class=\"pages-grid\">\n";
    foreach ($pageFiles as $idx => $pf) {
        $num = $idx + 1;
        $indexHtml .= "<a href=\"{$pf['file']}\" class=\"page-card\"><span class=\"num\">{$num}</span>{$pf['title']}</a>\n";
    }
    $indexHtml .= "</div>\n\n</body>\n</html>";
    
    file_put_contents($targetDir . '/index.html', $indexHtml);
    
    # Create rachel-index.php fragment for homepage
    $subfolders = []; # No subfolders for built modules
    createRachelIndex($targetDir, $moduleTitle, $moduleDesc, $subfolders);
    
    # Auto-assign 'local' category to newly created module
    $db = getdb();
    if ($db) {
        try {
            $stmt = $db->prepare("INSERT OR REPLACE INTO module_categories (moddir, categories) VALUES (:moddir, :categories)");
            $stmt->bindValue(':moddir', $moduleName, SQLITE3_TEXT);
            $stmt->bindValue(':categories', json_encode(['local']), SQLITE3_TEXT);
            $stmt->execute();
        } catch (Exception $e) {
            // Silently fail - category assignment is non-critical
        }
    }
    
    header("HTTP/1.1 200 OK");
    echo json_encode(['success' => true, 'module' => $moduleName]);
}

# Create a rachel-index.php file for a module
# This creates a FRAGMENT that gets included on the RACHEL homepage inside #content
# It should output a <div class="indexmodule"> with proper structure
function createRachelIndex($targetDir, $title, $description = 'Content installed from USB', $subfolders = array()) {
    $moduleName = basename($targetDir);
    
    # Find an index file if one exists
    $indexFile = '';
    $possibleIndexes = ['index.html', 'index.htm', 'index.php', 'home.html', 'home.htm', 'default.html', 'default.htm'];
    foreach ($possibleIndexes as $idx) {
        if (file_exists($targetDir . '/' . $idx)) {
            $indexFile = $idx;
            break;
        }
    }
    
    # Check for a logo file in the module root
    $logoFile = '';
    $possibleLogos = ['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.gif', 'icon.png', 'icon.jpg', 'icon.jpeg', 'icon.gif', 'thumb.png', 'thumb.jpg'];
    foreach ($possibleLogos as $logo) {
        if (file_exists($targetDir . '/' . $logo)) {
            $logoFile = $logo;
            break;
        }
    }
    
    $titleEscaped = htmlspecialchars($title, ENT_QUOTES);
    $descEscaped = htmlspecialchars($description, ENT_QUOTES);
    
    # Determine the main link target
    $mainLink = $indexFile ? $indexFile : '';
    if (empty($mainLink) && !empty($subfolders)) {
        $mainLink = $subfolders[0] . '/';
    }
    
    # Build the homepage fragment
    $rachelIndex = "<?php\n";
    $rachelIndex .= "\$rachel_title = \"{$titleEscaped}\";\n";
    $rachelIndex .= "\$rachel_description = \"{$descEscaped}\";\n";
    $rachelIndex .= "?>\n";
    $rachelIndex .= "<!-- RACHEL Module Fragment - displays on homepage -->\n";
    $rachelIndex .= "<div class=\"indexmodule\">\n\n";
    
    # Logo and title link
    if (!empty($logoFile)) {
        if (!empty($mainLink)) {
            $rachelIndex .= "<a href=\"<?php echo \$dir ?>/{$mainLink}\">\n";
            $rachelIndex .= "<img src=\"<?php echo \$dir ?>/{$logoFile}\" alt=\"\">\n";
            $rachelIndex .= "</a>\n\n";
        } else {
            $rachelIndex .= "<img src=\"<?php echo \$dir ?>/{$logoFile}\" alt=\"\">\n\n";
        }
    }
    
    # Title
    if (!empty($mainLink)) {
        $rachelIndex .= "<h2><a href=\"<?php echo \$dir ?>/{$mainLink}\">{$titleEscaped}</a></h2>\n\n";
    } else {
        $rachelIndex .= "<h2>{$titleEscaped}</h2>\n\n";
    }
    
    # Description
    $rachelIndex .= "<p>{$descEscaped}</p>\n\n";
    
    # Subfolder links or file list
    if (!empty($subfolders)) {
        # Determine column class based on count
        $count = count($subfolders);
        $colClass = '';
        if ($count >= 8) {
            $colClass = ' class="quad"';
        } elseif ($count >= 6) {
            $colClass = ' class="triple"';
        } elseif ($count >= 4) {
            $colClass = ' class="double"';
        }
        
        $rachelIndex .= "<ul{$colClass}>\n";
        foreach ($subfolders as $folder) {
            $displayName = str_replace(['_', '-'], ' ', $folder);
            $displayName = ucwords($displayName);
            $rachelIndex .= "<li><a href=\"<?php echo \$dir ?>/{$folder}/\">{$displayName}</a></li>\n";
        }
        $rachelIndex .= "</ul>\n\n";
    } elseif (empty($indexFile)) {
        # No index and no subfolders - list files
        $files = scandir($targetDir);
        $fileList = array();
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === 'rachel-index.php') continue;
            if (strpos($file, '.') === 0) continue; # skip hidden files
            $fileList[] = $file;
        }
        
        if (!empty($fileList)) {
            $count = count($fileList);
            $colClass = '';
            if ($count >= 8) {
                $colClass = ' class="double"';
            }
            
            $rachelIndex .= "<ul{$colClass}>\n";
            foreach ($fileList as $file) {
                $rachelIndex .= "<li><a href=\"<?php echo \$dir ?>/{$file}\">{$file}</a></li>\n";
            }
            $rachelIndex .= "</ul>\n\n";
        }
    }
    
    $rachelIndex .= "</div>";

    file_put_contents($targetDir . '/rachel-index.php', $rachelIndex);
}

# Format file size to human readable
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 1) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

include "head.php";

?>
<style>
th { text-align: left; }
td { text-align: left; padding-right:20px; }
h2 { border-bottom: 1px solid #ccc; margin-top: 20px; }
.info-box { background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 6px; padding: 15px; margin-bottom: 20px; }
.info-box h3 { margin-top: 0; color: #0369a1; }
.warning-box { background: #fff7ed; border: 1px solid #f97316; border-radius: 6px; padding: 15px; margin-top: 15px; }
.warning-box h4 { margin-top: 0; color: #c2410c; }

#driveTable { width: 100%; border-collapse: collapse; }
#driveTable th, #driveTable td { padding: 10px; border-bottom: 1px solid #e5e7eb; }
#driveTable tr:hover { background: #f9fafb; }

.content-section { margin-top: 20px; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; }
.content-section h3 { margin-top: 0; color: #334155; }

.content-item { 
    display: flex; 
    align-items: center; 
    justify-content: space-between;
    padding: 12px 15px; 
    margin: 8px 0; 
    background: white; 
    border: 1px solid #e5e7eb; 
    border-radius: 6px;
}
.content-item:hover { border-color: #3b82f6; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.content-item .info { flex: 1; }
.content-item .name { font-weight: 600; color: #1e293b; }
.content-item .meta { font-size: 0.85em; color: #64748b; margin-top: 4px; }
.content-item .actions { display: flex; align-items: center; }
.content-item button { 
    padding: 8px 16px; 
    border: none; 
    border-radius: 4px; 
    cursor: pointer; 
    font-weight: 500;
    margin-left: 8px;
}
.content-item input[type="text"] {
    width: 180px;
    padding: 8px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 14px;
}
.btn-install { background: #3b82f6 !important; color: white !important; border-color: #3b82f6 !important; }
.btn-install:hover { background: #2563eb !important; }
.btn-install:disabled { background: #94a3b8 !important; cursor: not-allowed; }
.btn-eject { background: #f97316 !important; color: white !important; border-color: #f97316 !important; }
.btn-eject:hover { background: #ea580c !important; color: white !important; }
.btn-scan { background: #10b981 !important; color: white !important; border-color: #10b981 !important; }
.btn-scan:hover { background: #059669 !important; }

.badge { 
    display: inline-block; 
    padding: 2px 8px; 
    border-radius: 12px; 
    font-size: 0.75em; 
    font-weight: 600;
    margin-left: 8px;
}
.badge-module { background: #dbeafe; color: #1e40af; }
.badge-folder { background: #fef3c7; color: #92400e; }
.badge-zip { background: #e0e7ff; color: #4338ca; }

.spinner { 
    display: inline-block; 
    width: 16px; 
    height: 16px; 
    border: 2px solid #f3f3f3; 
    border-top: 2px solid #3b82f6; 
    border-radius: 50%; 
    animation: spin 1s linear infinite;
    margin-right: 8px;
    vertical-align: middle;
}
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

.no-content { color: #64748b; font-style: italic; padding: 20px; text-align: center; }

#statusMessage { 
    position: fixed; 
    bottom: 20px; 
    right: 20px; 
    padding: 15px 25px; 
    border-radius: 8px; 
    color: white; 
    font-weight: 500;
    z-index: 1000;
    display: none;
    max-width: 400px;
}
#statusMessage.success { background: #10b981; }
#statusMessage.error { background: #ef4444; }

/* Upload drop zone */
.upload-zone {
    border: 3px dashed #cbd5e1;
    border-radius: 12px;
    padding: 40px 20px;
    text-align: center;
    background: #f8fafc;
    transition: all 0.2s;
    cursor: pointer;
    margin-bottom: 20px;
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: #3b82f6;
    background: #eff6ff;
}
.upload-zone.dragover {
    transform: scale(1.01);
}
.upload-zone h3 {
    margin: 0 0 10px 0;
    color: #334155;
}
.upload-zone p {
    margin: 0;
    color: #64748b;
}
.upload-zone input[type="file"] {
    display: none;
}

.upload-form {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-top: 15px;
    display: none;
}
.upload-form.active {
    display: block;
}
.upload-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}
.upload-form label {
    display: block;
    font-size: 0.85em;
    color: #64748b;
    margin-bottom: 5px;
}
.upload-form input[type="text"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 14px;
}
.upload-form .form-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e5e7eb;
}
.upload-form .file-info {
    font-size: 0.9em;
    color: #334155;
}
.upload-form .file-info strong {
    color: #1e40af;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 10px;
    display: none;
}
.progress-bar .progress {
    height: 100%;
    background: #3b82f6;
    width: 0%;
    transition: width 0.3s;
}

.section-tabs {
    display: flex;
    border-bottom: 2px solid #e5e7eb;
    margin-bottom: 20px;
}
.section-tabs button {
    padding: 12px 24px;
    border: none;
    background: none;
    font-size: 1em;
    font-weight: 500;
    color: #64748b;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
}
.section-tabs button:hover {
    color: #334155;
}
.section-tabs button.active {
    color: #2563eb;
    border-bottom-color: #2563eb;
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}

/* Rearrange Tab Styles */
#sortableModules {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}
.sortable-module {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    border-bottom: 1px solid #e5e7eb;
    cursor: move;
    background: white;
    transition: background 0.2s;
}
.sortable-module:last-child {
    border-bottom: none;
}
.sortable-module:hover {
    background: #f8fafc;
}
.sortable-module.ui-sortable-helper {
    background: #eff6ff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.sortable-module .drag-handle {
    font-size: 1.2em;
    color: #94a3b8;
    margin-right: 15px;
    cursor: grab;
}
.sortable-module .module-info {
    flex: 1;
}
.sortable-module .module-info strong {
    color: #1e293b;
}

/* Visibility Tab Styles */
.visibility-item {
    border-bottom: 1px solid #e5e7eb;
}
.visibility-item:last-child {
    border-bottom: none;
}

/* Module Builder Styles */
.builder-setup label {
    display: block;
    font-size: 0.85em;
    color: #64748b;
    margin-bottom: 5px;
}
.builder-setup input[type="text"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 14px;
}

.page-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 15px;
    overflow: hidden;
}
.page-card-header {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    background: #f8fafc;
    border-bottom: 1px solid #e5e7eb;
    cursor: move;
}
.page-card-header .page-num {
    background: #3b82f6;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.85em;
    margin-right: 12px;
}
.page-card-header input {
    flex: 1;
    border: 1px solid transparent;
    background: transparent;
    font-size: 1em;
    font-weight: 500;
    padding: 5px 10px;
    border-radius: 4px;
}
.page-card-header input:focus {
    border-color: #3b82f6;
    background: white;
    outline: none;
}
.page-card-header .page-actions button {
    background: none;
    border: none;
    padding: 5px 8px;
    cursor: pointer;
    font-size: 1.1em;
    opacity: 0.6;
}
.page-card-header .page-actions button:hover {
    opacity: 1;
}
.page-card-body {
    padding: 15px;
}
.content-block {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 10px;
    position: relative;
}
.content-block .block-type {
    font-size: 0.75em;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}
.content-block textarea {
    width: 100%;
    min-height: 80px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    padding: 10px;
    font-size: 14px;
    resize: vertical;
}
.content-block .media-preview {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: white;
    border-radius: 4px;
}
.content-block .media-preview img {
    max-width: 100px;
    max-height: 60px;
    border-radius: 4px;
}
.content-block .remove-block {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #fee2e2;
    border: none;
    color: #dc2626;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 14px;
}
.add-content-btns {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}
.add-content-btns button {
    padding: 8px 16px;
    border: 1px dashed #cbd5e1;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9em;
    color: #64748b;
    transition: all 0.2s;
}
.add-content-btns button:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: #eff6ff;
}
</style>

<div class="info-box">
    <h3>Content Management</h3>
    <p>Create, add, update, rearrange, or remove educational content on your RACHEL device.</p>
</div>

<div class="section-tabs">
    <button class="active" onclick="showTab('upload')">Upload</button>
    <button onclick="showTab('usb')">USB</button>
    <button onclick="showTab('builder')">Build</button>
    <button onclick="showTab('worldpossible')">World Possible</button>
    <button onclick="showTab('delete')">Delete</button>
    <button onclick="showTab('rearrange')">Rearrange</button>
    <button onclick="showTab('hide')">Visibility</button>
    <button onclick="showTab('categories')">Categories</button>
    <button onclick="showTab('quizzes')">Quizzes</button>
    <button onclick="showTab('results')">Results</button>
</div>

<!-- Browser Upload Tab -->
<div id="tab-upload" class="tab-content active">
    <div class="warning-box" style="margin-bottom:20px;">
        <h4>💡 Tips for Browser Upload</h4>
        <ul style="margin:0; padding-left:20px;">
            <li><strong>ZIP files:</strong> Package your content folder as a .zip before uploading</li>
            <li><strong>Include a logo:</strong> Add <code>logo.png</code> or <code>icon.png</code> in the root folder for the module icon</li>
            <li><strong>Large files:</strong> For very large content (over 500MB), use the USB install option instead</li>
            <li><strong>Index file:</strong> If your content has an <code>index.html</code>, users will be directed there automatically</li>
        </ul>
    </div>

    <div class="upload-zone" id="dropZone" onclick="document.getElementById('fileInput').click();">
        <h3>📦 Drop ZIP file here or click to browse</h3>
        <p>Upload a .zip file containing educational content to create a new RACHEL module</p>
        <input type="file" id="fileInput" accept=".zip">
    </div>
    
    <div class="upload-form" id="uploadForm">
        <div class="file-info" id="fileInfo"></div>
        <div class="form-row" style="grid-template-columns: 1fr 1fr;">
            <div>
                <label>Module Name</label>
                <input type="text" id="uploadModuleName" placeholder="my_module">
                <small style="color:#64748b;">Used as folder name and display title</small>
            </div>
            <div>
                <label>Description</label>
                <input type="text" id="uploadModuleDesc" placeholder="Brief description...">
            </div>
        </div>
        <div>
            <label><input type="checkbox" id="uploadSubfolders" checked> Create navigation links to subfolders</label>
        </div>
        <div class="progress-bar" id="uploadProgress">
            <div class="progress" id="progressFill"></div>
        </div>
        <div class="form-actions">
            <button class="btn-eject" onclick="cancelUpload()">Cancel</button>
            <button class="btn-install" id="uploadBtn" onclick="startUpload()">📤 Upload & Create Module</button>
        </div>
    </div>
</div>

<!-- USB Install Tab -->
<div id="tab-usb" class="tab-content">
    <div class="warning-box" style="margin-bottom:20px;">
        <h4>💡 Tips for USB Operations</h4>
        <ul style="margin:0; padding-left:20px;">
            <li><strong>Install from USB:</strong> Copy ZIP files or folders from a USB drive to this RACHEL</li>
            <li><strong>Export to USB:</strong> Copy modules from this RACHEL to a USB drive to transfer to another device</li>
            <li><strong>ZIP files:</strong> Should contain content (HTML, videos, documents, etc.). A browsable index will be created automatically.</li>
            <li><strong>Logo:</strong> Include a file named <code>logo.png</code>, <code>icon.png</code>, or <code>thumb.png</code> in the root of your folder to use it as the module logo.</li>
        </ul>
    </div>
    
    <div style="display:flex; gap:10px; margin-bottom:20px;">
        <button class="btn-install" id="usbImportBtn" onclick="showUSBMode('import');" style="flex:1;">📥 Import from USB</button>
        <button class="btn-scan" id="usbExportBtn" onclick="showUSBMode('export');" style="flex:1;">📤 Export to USB</button>
    </div>
    
    <div id="usbImportSection">
        <h2>Connected USB Drives</h2>
        <div id="drivesContainer">
            <p><span class="spinner"></span> Scanning for USB drives...</p>
        </div>
        
        <div id="contentContainer"></div>
    </div>
    
    <div id="usbExportSection" style="display:none;">
        <h2>Export Modules to USB</h2>
        <div id="exportDrivesContainer">
            <p><span class="spinner"></span> Scanning for USB drives...</p>
        </div>
        
        <div id="exportModulesContainer" style="display:none;">
            <h3 style="margin-top:20px;">Select Modules to Export</h3>
            <div style="margin-bottom:15px;">
                <input type="text" id="exportModuleSearch" placeholder="Search modules..." onkeyup="filterExportModules();" style="padding:8px 12px; border:1px solid #cbd5e1; border-radius:4px; width:100%; box-sizing:border-box;">
            </div>
            <div id="exportModulesList" style="max-height:400px; overflow-y:auto;"></div>
        </div>
    </div>
</div>

<!-- Module Builder Tab -->
<div id="tab-builder" class="tab-content">
    <div class="warning-box" style="margin-bottom:20px;">
        <h4>💡 Builder Tips</h4>
        <ul style="margin:0; padding-left:20px;">
            <li><strong>Pages:</strong> Each page becomes a section in your module with its own title and content</li>
            <li><strong>Text blocks:</strong> Add formatted text to explain concepts</li>
            <li><strong>Media:</strong> Upload videos (MP4), PDFs, or images to embed in pages</li>
            <li><strong>Order:</strong> Drag pages to reorder them, or use the arrows</li>
            <li><strong>Preview:</strong> After saving, view your module on the homepage to see how it looks</li>
        </ul>
    </div>
    
    <div class="builder-setup" id="builderSetup">
        <h3>Step 1: Module Information</h3>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:20px;">
            <div>
                <label>Module Name</label>
                <input type="text" id="builderModuleName" placeholder="my_custom_module">
                <small style="color:#64748b;">Used as folder name and display title</small>
            </div>
            <div>
                <label>Module Description</label>
                <input type="text" id="builderModuleDesc" placeholder="A brief description of this educational content">
            </div>
        </div>
        <div style="margin-bottom:20px;">
            <label>Module Logo (optional)</label>
            <input type="file" id="builderLogo" accept="image/*">
            <small style="color:#64748b; display:block; margin-top:5px;">Upload an image to use as the module icon on the homepage</small>
        </div>
        <button class="btn-install" onclick="startBuilder()">Start Building →</button>
    </div>
    
    <div class="builder-workspace" id="builderWorkspace" style="display:none;">
        <div class="builder-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding:15px; background:#f1f5f9; border-radius:8px;">
            <div>
                <strong id="builderTitle">Module Title</strong>
                <span style="color:#64748b; margin-left:10px;" id="builderPageCount">0 pages</span>
            </div>
            <div>
                <button class="btn-scan" onclick="addPage()">➕ Add Page</button>
                <button class="btn-install" onclick="saveModule()">💾 Save Module</button>
            </div>
        </div>
        
        <div id="pagesContainer"></div>
        </div>
    </div>
</div>

<!-- World Possible Tab -->
<div id="tab-worldpossible" class="tab-content">
    <div class="info-box">
        <h3>🌐 World Possible Content Server</h3>
        <p>Download or update educational modules from World Possible's content server. Requires internet connection.</p>
    </div>
    
    <h3 style="margin-top:0;">System Information</h3>
    <table class="version-table" style="width:100%; border-collapse:collapse; margin-bottom:25px;">
        <tr style="background:#f1f5f9;"><th colspan="2" style="padding:10px; text-align:left;">System Software</th></tr>
        <tr><td style="padding:8px 10px; border-bottom:1px solid #e5e7eb; width:200px;">Hardware</td><td style="padding:8px 10px; border-bottom:1px solid #e5e7eb;"><?php echo $hardware ?? 'Unknown'; ?></td></tr>
        <tr><td style="padding:8px 10px; border-bottom:1px solid #e5e7eb;">OS</td><td style="padding:8px 10px; border-bottom:1px solid #e5e7eb;"><?php echo $os ?? 'Unknown'; ?></td></tr>
        <tr><td style="padding:8px 10px; border-bottom:1px solid #e5e7eb;">RACHEL Version</td><td style="padding:8px 10px; border-bottom:1px solid #e5e7eb;"><?php echo $rachel_version ?? '?'; ?></td></tr>
        <tr><td style="padding:8px 10px; border-bottom:1px solid #e5e7eb;">Content Shell</td><td style="padding:8px 10px; border-bottom:1px solid #e5e7eb;">
            <span id="cur_contentshell">v5.0.0</span>
            <span style="float:right;">
                <img src="../art/spinner.gif" id="shellSpinner" style="display:none; width:16px; vertical-align:middle;">
                <button id="shellUpdateBtn" class="btn-scan" onclick="checkShellUpdate();" style="margin-left:10px;">Check for Updates</button>
            </span>
        </td></tr>
    </table>
    
    <!-- Update Installed Modules Section -->
    <div style="background:#f0fdf4; border:2px solid #22c55e; border-radius:8px; padding:20px; margin-bottom:25px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <h3 style="margin:0; color:#166534;">🔄 Update Installed Modules</h3>
            <span>
                <img src="../art/spinner.gif" id="updateAllSpinner" style="display:none; width:16px; vertical-align:middle;">
                <button id="updateAllBtn" class="btn-install" onclick="updateAllMods();" style="display:none;">📥 Update All</button>
                <button class="btn-scan" onclick="checkForUpdates();">🔄 Check for Updates</button>
            </span>
        </div>
        <div class="warning-box" style="background:#fefce8; border-color:#eab308; margin-bottom:15px;">
            <p style="margin:0; font-size:0.9em;"><strong>⚠️ Note:</strong> Updates are detected by comparing version strings. A different version doesn't always mean "newer" — some modules may have been customized locally. <strong>Compare versions carefully</strong> before updating to avoid overwriting local changes.</p>
        </div>
        <div id="installedModulesUpdate" style="max-height:250px; overflow-y:auto; background:white; border:1px solid #e5e7eb; border-radius:6px; padding:10px;">
            <p style="color:#64748b; text-align:center;">Click "Check for Updates" to scan installed modules</p>
        </div>
    </div>
    
    <!-- Download New Modules Section -->
    <div style="background:#eff6ff; border:2px solid #3b82f6; border-radius:8px; padding:20px;">
        <h3 style="margin:0 0 10px 0; color:#1e40af;">Download New Modules</h3>
        <?php
        // Get free disk space
        $freeSpaceBytes = 0;
        $freeSpaceDisplay = 'Unknown';
        exec("df -B1 " . escapeshellarg(getAbsModPath()) . " 2>/dev/null | tail -1", $dfOutput);
        if (!empty($dfOutput[0])) {
            $parts = preg_split("/\s+/", $dfOutput[0]);
            if (isset($parts[3])) {
                $freeSpaceBytes = intval($parts[3]);
                if ($freeSpaceBytes >= 1073741824) {
                    $freeSpaceDisplay = round($freeSpaceBytes / 1073741824, 1) . ' GB';
                } else {
                    $freeSpaceDisplay = round($freeSpaceBytes / 1048576) . ' MB';
                }
            }
        }
        ?>
        <div id="storageInfo" style="background:#dbeafe; padding:10px 15px; border-radius:6px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
            <span><strong>Free Storage:</strong> <?php echo $freeSpaceDisplay; ?></span>
            <span style="color:#64748b; font-size:0.9em;">Modules sized in MB will show storage impact</span>
        </div>
        <script>var freeSpaceMB = <?php echo round($freeSpaceBytes / 1048576); ?>;</script>
    <div style="display:flex; gap:15px; margin-bottom:15px;">
        <input type="text" id="moduleSearch" placeholder="Search available modules..." style="flex:1; padding:10px; border:1px solid #cbd5e1; border-radius:4px; font-size:14px;" onkeyup="filterRemoteModules()">
        <button class="btn-scan" onclick="loadRemoteModules();">Refresh List</button>
    </div>
        <div id="remoteModulesContainer" style="max-height:350px; overflow-y:auto; background:white; border:1px solid #e5e7eb; border-radius:6px; padding:10px;">
            <p style="color:#64748b; text-align:center;"><span class="spinner"></span> Loading available modules...</p>
        </div>
    </div>
    
    <details style="margin-top:25px;">
        <summary style="cursor:pointer; font-weight:600; color:#334155; padding:10px; background:#f8fafc; border-radius:8px;">⚙️ Advanced Options</summary>
        <div style="padding:15px; background:#f8fafc; border-radius:0 0 8px 8px; border:1px solid #e5e7eb; border-top:none;">
            <div style="margin-bottom:20px;">
                <label style="font-weight:500; display:block; margin-bottom:5px;">Custom Server URL</label>
                <input type="text" id="customServer" placeholder="rsync://your.server.com/rachel" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:4px;">
                <small style="color:#64748b;">Use a different server to download modules (for offline deployments)</small>
            </div>
            <div>
                <label style="font-weight:500; display:block; margin-bottom:5px;">Upload .modules File</label>
                <input type="file" id="modulesFile" accept=".modules">
                <button class="btn-install" onclick="uploadModulesFile();" style="margin-left:10px;">📤 Install from File</button>
                <small style="color:#64748b; display:block; margin-top:5px;">Upload a .modules file to batch install multiple modules</small>
            </div>
        </div>
    </details>
</div>

<!-- Delete Modules Tab -->
<div id="tab-delete" class="tab-content">
    <div class="warning-box" style="background:#fef2f2; border-color:#ef4444; margin-bottom:20px;">
        <h4 style="margin-top:0; color:#dc2626;">Warning</h4>
        <p style="margin-bottom:0;">Deleting a module permanently removes all its content from this device. This action cannot be undone. Make sure you have a backup if the content is important.</p>
    </div>
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h3 style="margin:0;">Installed Modules</h3>
        <input type="text" id="deleteModuleSearch" placeholder="Search modules..." onkeyup="filterDeleteModules();" style="padding:8px 12px; border:1px solid #cbd5e1; border-radius:4px; width:250px;">
    </div>
    <div id="deleteModulesContainer" style="max-height:500px; overflow-y:auto;">
        <p style="color:#64748b; text-align:center;"><span class="spinner"></span> Loading modules...</p>
    </div>
</div>

<!-- Rearrange Modules Tab -->
<div id="tab-rearrange" class="tab-content">
    <div class="info-box" style="margin-bottom:20px;">
        <h4 style="margin-top:0;">💡 About Module Order</h4>
        <ul style="margin:0; padding-left:20px;">
            <li><strong>Drag to reorder:</strong> Click and drag modules to change their order on the homepage</li>
            <li><strong>Save:</strong> Click "Save Order" to apply changes</li>
            <li><strong>Reset to Default:</strong> Restores alphabetical order or order from a .modules file if present</li>
            <li><strong>Save as Default:</strong> Saves current order as the new default for future resets</li>
        </ul>
    </div>
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; padding:10px; background:#f8fafc; border-radius:8px;">
        <span id="rearrangeStatus" style="color:#64748b;"></span>
        <div>
            <button class="btn-scan" id="resetDefaultBtn" onclick="resetToDefaultOrder();" style="margin-right:8px;">🔄 Reset to Default</button>
            <button class="btn-install" id="saveAsDefaultBtn" onclick="saveAsDefaultOrder();" style="margin-right:8px; background:#64748b;">💾 Save as Default</button>
            <button class="btn-install" id="saveOrderBtn" onclick="saveModuleOrder();" disabled>💾 Save Order</button>
        </div>
    </div>
    
    <ul id="sortableModules" style="list-style:none; padding:0; margin:0;">
        <?php
        $fsmods = getmods_fs();
        if ($fsmods) {
            $db = getdb();
            if ($db) {
                $dbmods = getmods_db();
                foreach (array_keys($dbmods) as $moddir) {
                    if (isset($fsmods[$moddir])) {
                        $fsmods[$moddir]['position'] = $dbmods[$moddir]['position'];
                        $fsmods[$moddir]['hidden'] = $dbmods[$moddir]['hidden'];
                    }
                }
            }
            uasort($fsmods, 'bypos');
            
            foreach ($fsmods as $moddir => $mod) {
                if (!$mod['fragment']) continue;
                $title = isset($mod['title']) ? htmlspecialchars($mod['title']) : $moddir;
                $hidden = isset($mod['hidden']) && $mod['hidden'] ? 'hidden' : '';
                echo "<li class=\"sortable-module\" data-moddir=\"{$moddir}\" data-hidden=\"{$hidden}\">";
                echo "<span class=\"drag-handle\">☰</span>";
                echo "<span class=\"module-info\">";
                echo "<strong>{$moddir}</strong>";
                if ($title != $moddir) echo " <span style=\"color:#64748b;\">— {$title}</span>";
                if ($hidden) echo " <span class=\"badge\" style=\"background:#fee2e2; color:#dc2626;\">Hidden</span>";
                echo "</span>";
                echo "</li>\n";
            }
        }
        ?>
    </ul>
</div>

<!-- Hide/Show Modules Tab -->
<div id="tab-hide" class="tab-content">
    <div class="info-box" style="margin-bottom:20px;">
        <h4 style="margin-top:0;">Module Visibility</h4>
        <ul style="margin:0; padding-left:20px;">
            <li><strong>Visible:</strong> Module appears on homepage and can be accessed directly by URL</li>
            <li><strong>Hidden from Homepage:</strong> Module won't appear on homepage, but can still be accessed if someone knows the URL</li>
            <li><strong>Completely Hidden:</strong> Module is blocked entirely - won't appear on homepage AND cannot be accessed by URL</li>
        </ul>
    </div>
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; padding:10px; background:#f8fafc; border-radius:8px;">
        <div>
            <button class="btn-scan" onclick="setAllVisibility('visible');" style="margin-right:8px;">Show All</button>
            <button class="btn-eject" onclick="setAllVisibility('homepage');">Hide All from Homepage</button>
        </div>
        <button class="btn-install" id="saveVisibilityBtn" onclick="saveModuleVisibility();" disabled>Save Visibility</button>
    </div>
    
    <div style="margin-bottom:15px;">
        <input type="text" id="visibilityModuleSearch" placeholder="Search modules..." onkeyup="filterVisibilityModules();" style="padding:8px 12px; border:1px solid #cbd5e1; border-radius:4px; width:100%; box-sizing:border-box;">
    </div>
    
    <div id="visibilityContainer" style="max-height:500px; overflow-y:auto;">
        <?php
        $fsmods = getmods_fs();
        if ($fsmods) {
            $db = getdb();
            if ($db) {
                $dbmods = getmods_db();
                foreach (array_keys($dbmods) as $moddir) {
                    if (isset($fsmods[$moddir])) {
                        $fsmods[$moddir]['hidden'] = $dbmods[$moddir]['hidden'];
                        $fsmods[$moddir]['blocked'] = isset($dbmods[$moddir]['blocked']) ? $dbmods[$moddir]['blocked'] : 0;
                    }
                }
            }
            ksort($fsmods);
            
            foreach ($fsmods as $moddir => $mod) {
                if (!$mod['fragment']) continue;
                $title = isset($mod['title']) ? htmlspecialchars($mod['title']) : $moddir;
                $hidden = isset($mod['hidden']) && $mod['hidden'] ? 1 : 0;
                $blocked = isset($mod['blocked']) && $mod['blocked'] ? 1 : 0;
                
                // Determine current state
                $state = 'visible';
                if ($blocked) $state = 'blocked';
                else if ($hidden) $state = 'homepage';
                
                echo "<div class=\"content-item visibility-item\" data-moddir=\"{$moddir}\">";
                echo "<div class=\"info\"><div class=\"name\">{$moddir}</div>";
                if ($title != $moddir) echo "<div class=\"meta\">{$title}</div>";
                echo "</div>";
                echo "<div class=\"actions\" style=\"display:flex; gap:8px;\">";
                echo "<select class=\"visibility-select\" onchange=\"markVisibilityChanged();\" style=\"padding:8px 12px; border:1px solid #cbd5e1; border-radius:4px; font-size:14px;\">";
                echo "<option value=\"visible\"" . ($state == 'visible' ? ' selected' : '') . ">👁️ Visible</option>";
                echo "<option value=\"homepage\"" . ($state == 'homepage' ? ' selected' : '') . ">🏠 Hidden from Homepage</option>";
                echo "<option value=\"blocked\"" . ($state == 'blocked' ? ' selected' : '') . ">🚫 Completely Hidden</option>";
                echo "</select>";
                echo "</div></div>\n";
            }
        }
        ?>
    </div>
</div>

<!-- Categories Tab -->
<div id="tab-categories" class="tab-content">
    <div class="info-box" style="margin-bottom:20px;">
        <h4 style="margin-top:0;">Module Categories</h4>
        <p style="margin-bottom:0;">Assign categories to modules for easier browsing on the homepage. You can create custom categories or use the pre-defined ones.</p>
    </div>
    
    <!-- Custom Category Creator -->
    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:15px; margin-bottom:20px;">
        <h4 style="margin:0 0 12px 0; color:#166534;">➕ Create Custom Category</h4>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label style="display:block; font-size:12px; color:#64748b; margin-bottom:4px;">Emoji</label>
                <div style="position:relative;">
                    <button type="button" id="emojiPickerBtn" onclick="toggleEmojiPicker();" style="width:50px; height:40px; font-size:20px; border:1px solid #cbd5e1; border-radius:4px; background:white; cursor:pointer;">📁</button>
                    <div id="emojiPickerDropdown" style="display:none; position:absolute; top:100%; left:0; z-index:100; background:white; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15); padding:10px; width:280px; max-height:250px; overflow-y:auto;">
                        <div style="display:grid; grid-template-columns:repeat(8, 1fr); gap:4px;" id="emojiGrid"></div>
                    </div>
                </div>
                <input type="hidden" id="newCategoryIcon" value="📁">
            </div>
            <div style="flex:1; min-width:150px;">
                <label style="display:block; font-size:12px; color:#64748b; margin-bottom:4px;">Category ID (lowercase, no spaces)</label>
                <input type="text" id="newCategoryId" placeholder="e.g., history" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:4px;" oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');">
            </div>
            <div style="flex:1; min-width:150px;">
                <label style="display:block; font-size:12px; color:#64748b; margin-bottom:4px;">Display Label</label>
                <input type="text" id="newCategoryLabel" placeholder="e.g., History & Social Studies" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:4px;">
            </div>
            <div>
                <label style="display:block; font-size:12px; color:#64748b; margin-bottom:4px;">Color</label>
                <input type="color" id="newCategoryColor" value="#6366f1" style="width:50px; height:40px; border:1px solid #cbd5e1; border-radius:4px; cursor:pointer;">
            </div>
            <button class="btn-install" onclick="createCustomCategory();" style="height:40px;">Create Category</button>
        </div>
        <div id="createCategoryMessage" style="margin-top:10px; display:none;"></div>
    </div>
    
    <!-- Existing Categories List -->
    <div style="background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:15px; margin-bottom:20px;">
        <h4 style="margin:0 0 12px 0; color:#1e293b;">📋 Available Categories</h4>
        <div id="existingCategoriesList" style="display:flex; flex-wrap:wrap; gap:8px;">
            <span style="color:#64748b;">Loading...</span>
        </div>
    </div>
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; padding:10px; background:#f8fafc; border-radius:8px;">
        <div>
            <span id="categoryStats" style="color:#64748b;"></span>
        </div>
        <button class="btn-install" id="saveCategoriesBtn" onclick="saveModuleCategories();" disabled>Save Categories</button>
    </div>
    
    <div style="margin-bottom:15px;">
        <input type="text" id="categoryModuleSearch" placeholder="Search modules..." onkeyup="filterCategoryModules();" style="padding:8px 12px; border:1px solid #cbd5e1; border-radius:4px; width:100%; box-sizing:border-box;">
    </div>
    
    <div id="categoriesContainer" style="max-height:600px; overflow-y:auto;">
        <div style="text-align:center; padding:40px; color:#64748b;">
            <i class="fa fa-spinner fa-spin"></i> Loading categories...
        </div>
    </div>
</div>

<!-- Quizzes Tab -->
<div id="tab-quizzes" class="tab-content">
    <div class="info-box" style="margin-bottom:20px;">
        <h4 style="margin-top:0;">Quiz Builder</h4>
        <p style="margin-bottom:0;">Create quizzes with multiple choice, short answer, and file upload questions. Students can access quizzes at <strong>/quiz.php</strong></p>
    </div>
    
    <div id="quizListView">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0;">Your Quizzes</h3>
            <button class="btn-install" onclick="createNewQuiz();">+ New Quiz</button>
        </div>
        <div id="quizList"></div>
    </div>
    
    <div id="quizEditorView" style="display:none;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <button class="btn-scan" onclick="backToQuizList();">Back to Quizzes</button>
            <button class="btn-install" onclick="saveCurrentQuiz();">Save Quiz</button>
        </div>
        
        <div class="form-section" style="background:white; border:1px solid #e5e7eb; border-radius:8px; padding:20px; margin-bottom:20px;">
            <h3 style="margin-top:0;">Quiz Settings</h3>
            <input type="hidden" id="quizId">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:500;">Quiz Title</label>
                    <input type="text" id="quizTitle" placeholder="Enter quiz title" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:4px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:500;">Time Limit (minutes, 0 = unlimited)</label>
                    <input type="number" id="quizTimeLimit" value="0" min="0" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:4px;">
                </div>
            </div>
            <div style="margin-top:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:500;">Description</label>
                <textarea id="quizDescription" placeholder="Optional description" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:4px; min-height:60px;"></textarea>
            </div>
            <div style="margin-top:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:500;">Quiz Password (optional)</label>
                <input type="text" id="quizPassword" placeholder="Leave blank for no password" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:4px;">
                <p style="color:#64748b; font-size:0.85em; margin:5px 0 0 0;">If set, students must enter this password to access the quiz.</p>
            </div>
            <div style="margin-top:15px; display:flex; gap:20px;">
                <label><input type="checkbox" id="quizActive" checked> Active (students can take this quiz)</label>
                <label><input type="checkbox" id="quizShowResults" checked> Show results to students after submission</label>
            </div>
            <div style="margin-top:15px; padding:15px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px;">
                <p style="margin:0 0 10px 0; font-weight:500; color:#166534;">💡 Tips for Student Identity Verification</p>
                <ul style="margin:0; padding-left:20px; color:#166534; font-size:0.9em;">
                    <li>Consider verbally assigning a unique <strong>secret key</strong> to each student (e.g., their birthday, a code word)</li>
                    <li>Students will enter their name and secret key when taking the quiz</li>
                    <li>Use the <strong>quiz password</strong> above to prevent unauthorized access to the quiz itself</li>
                    <li>Review submissions in the Results tab to verify student identities</li>
                </ul>
            </div>
        </div>
        
        <div class="form-section" style="background:white; border:1px solid #e5e7eb; border-radius:8px; padding:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 style="margin:0;">Questions</h3>
                <div>
                    <button class="btn-scan" onclick="addQuestion('multiple_choice');" style="margin-right:5px;">+ Multiple Choice</button>
                    <button class="btn-scan" onclick="addQuestion('short_answer');" style="margin-right:5px;">+ Short Answer</button>
                    <button class="btn-scan" onclick="addQuestion('file_upload');">+ File Upload</button>
                </div>
            </div>
            <div id="questionsContainer"></div>
        </div>
    </div>
</div>

<!-- Results Tab -->
<div id="tab-results" class="tab-content">
    <div class="info-box" style="margin-bottom:20px;">
        <h4 style="margin-top:0;">Quiz Results</h4>
        <p style="margin-bottom:0;">View and grade student quiz submissions.</p>
    </div>
    
    <div id="resultsQuizSelect" style="margin-bottom:20px;">
        <label style="font-weight:500; margin-right:10px;">Select Quiz:</label>
        <select id="resultsQuizDropdown" onchange="loadSubmissions();" style="padding:8px 12px; border:1px solid #cbd5e1; border-radius:4px; min-width:200px;">
            <option value="">-- Select a quiz --</option>
        </select>
    </div>
    
    <div id="submissionsListView">
        <div id="submissionsList"></div>
    </div>
    
    <div id="submissionDetailView" style="display:none;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <button class="btn-scan" onclick="backToSubmissions();">Back to Submissions</button>
            <button class="btn-install" onclick="saveGrades();">Save Grades</button>
        </div>
        
        <div id="submissionDetails"></div>
    </div>
</div>

<div id="statusMessage"></div>

<script>
var selectedFile = null;
var remoteModules = [];
var installedModuleVersions = {};
var modulesCache = {};

$(function() {
    loadDrives();
    setupDropZone();
    
    // Handle direct tab linking via URL hash
    var hash = window.location.hash.replace('#', '');
    if (hash) {
        showTab(hash);
    }
});

// Listen for hash changes (back/forward navigation)
window.addEventListener('hashchange', function() {
    var hash = window.location.hash.replace('#', '');
    if (hash) {
        showTab(hash);
    }
});

function showTab(tab) {
    $('.section-tabs button').removeClass('active');
    $('.tab-content').removeClass('active');
    var tabs = ['upload', 'usb', 'builder', 'worldpossible', 'delete', 'rearrange', 'hide', 'categories', 'quizzes', 'results'];
    var tabIndex = tabs.indexOf(tab);
    if (tabIndex === -1) tabIndex = 0;
    tab = tabs[tabIndex]; // Normalize to valid tab
    $('.section-tabs button').eq(tabIndex).addClass('active');
    $('#tab-' + tab).addClass('active');
    
    // Update URL hash without triggering hashchange
    if (window.location.hash !== '#' + tab) {
        history.replaceState(null, null, '#' + tab);
    }
    
    if (tab === 'usb') {
        loadDrives();
    } else if (tab === 'worldpossible') {
        // Start polling when viewing this tab
        pollTasks();
    } else if (tab === 'delete') {
        loadInstalledModules();
    } else if (tab === 'rearrange') {
        initSortable();
    } else if (tab === 'categories') {
        loadCategories();
    } else if (tab === 'quizzes') {
        loadQuizzes();
    } else if (tab === 'results') {
        loadQuizzesForResults();
    }
}

function setupDropZone() {
    var dropZone = document.getElementById('dropZone');
    var fileInput = document.getElementById('fileInput');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
        dropZone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
    });
    
    ['dragenter', 'dragover'].forEach(function(eventName) {
        dropZone.addEventListener(eventName, function() {
            dropZone.classList.add('dragover');
        });
    });
    
    ['dragleave', 'drop'].forEach(function(eventName) {
        dropZone.addEventListener(eventName, function() {
            dropZone.classList.remove('dragover');
        });
    });
    
    dropZone.addEventListener('drop', function(e) {
        var files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelect(files[0]);
        }
    });
    
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            handleFileSelect(this.files[0]);
        }
    });
}

function handleFileSelect(file) {
    if (!file.name.toLowerCase().endsWith('.zip')) {
        showStatus('Please select a .zip file', true);
        return;
    }
    
    selectedFile = file;
    
    var sizeMB = (file.size / 1024 / 1024).toFixed(1);
    $('#fileInfo').html('<strong>Selected:</strong> ' + file.name + ' (' + sizeMB + ' MB)');
    
    // Suggest module name from filename (used as both folder and title)
    var suggestedName = file.name.replace('.zip', '').replace(/[^a-zA-Z0-9_\-]/g, '_');
    
    $('#uploadModuleName').val(suggestedName);
    
    $('#uploadForm').addClass('active');
    $('#dropZone').hide();
}

function cancelUpload() {
    selectedFile = null;
    $('#uploadForm').removeClass('active');
    $('#dropZone').show();
    $('#fileInput').val('');
    $('#uploadProgress').hide();
    $('#progressFill').css('width', '0%');
}

function startUpload() {
    if (!selectedFile) {
        showStatus('No file selected', true);
        return;
    }
    
    var moduleName = $('#uploadModuleName').val().trim();
    if (!moduleName) {
        showStatus('Please enter a module name', true);
        $('#uploadModuleName').focus();
        return;
    }
    
    // Use module name for both folder and display title
    var moduleTitle = moduleName.replace(/[_\-]/g, ' ');
    moduleTitle = moduleTitle.replace(/\b\w/g, function(l) { return l.toUpperCase(); });
    
    var formData = new FormData();
    formData.append('uploadZip', selectedFile);
    formData.append('moduleName', moduleName);
    formData.append('moduleTitle', moduleTitle);
    formData.append('moduleDesc', $('#uploadModuleDesc').val());
    formData.append('includeSubfolders', $('#uploadSubfolders').is(':checked') ? '1' : '0');
    
    $('#uploadBtn').prop('disabled', true).html('<span class="spinner"></span> Uploading...');
    $('#uploadProgress').show();
    
    $.ajax({
        type: 'POST',
        url: 'storage.php',
        data: formData,
        processData: false,
        contentType: false,
        xhr: function() {
            var xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    var percent = Math.round((e.loaded / e.total) * 100);
                    $('#progressFill').css('width', percent + '%');
                }
            });
            return xhr;
        },
        success: function(result) {
            showStatus('Module "' + result.module + '" created successfully! Go to Modules tab to configure.', false);
            cancelUpload();
            $('#uploadBtn').prop('disabled', false).html('📤 Upload & Create Module');
        },
        error: function(xhr) {
            var err = xhr.responseJSON ? xhr.responseJSON.error : 'Upload failed';
            showStatus(err, true);
            $('#uploadBtn').prop('disabled', false).html('📤 Upload & Create Module');
            $('#uploadProgress').hide();
        }
    });
}

function showStatus(message, isError) {
    var $status = $('#statusMessage');
    $status.removeClass('success error').addClass(isError ? 'error' : 'success');
    $status.text(message).fadeIn();
    setTimeout(function() { $status.fadeOut(); }, 5000);
}

function loadDrives() {
    $.ajax({
        type: 'POST',
        url: 'storage.php',
        data: { listDrives: 'listDrives' },
        dataType: 'json',
        success: function(drives) {
            renderDrives(drives);
        },
        error: function() {
            $('#drivesContainer').html('<p class="no-content">Failed to scan for USB drives</p>');
        }
    });
}

function renderDrives(drives) {
    if (drives.length === 0) {
        $('#drivesContainer').html('<p class="no-content">No USB drives connected. Insert a USB drive and refresh this page.</p>');
        $('#contentContainer').html('');
        return;
    }
    
    var html = '<table id="driveTable"><tr><th>Drive</th><th>Size</th><th>Used</th><th>Actions</th></tr>';
    for (var i = 0; i < drives.length; i++) {
        var d = drives[i];
        html += '<tr>';
        html += '<td><strong>💾 ' + d.label + '</strong><br><small style="color:#64748b;">' + d.mountPath + '</small></td>';
        html += '<td>' + d.size + '</td>';
        html += '<td>' + d.used + '</td>';
        html += '<td>';
        html += '<button class="btn-scan" onclick="scanDrive(\'' + d.mountPath + '\', \'' + d.label + '\')">🔍 Scan for Content</button> ';
        html += '<button class="btn-eject" onclick="ejectDrive(\'' + d.mountPath + '\')">⏏ Eject</button>';
        html += '</td></tr>';
    }
    html += '</table>';
    
    $('#drivesContainer').html(html);
}

function ejectDrive(mountPath) {
    if (!confirm('Eject this drive? Make sure no operations are in progress.')) return;
    
    $.ajax({
        type: 'POST',
        url: 'storage.php',
        data: { ejectPath: mountPath },
        dataType: 'json',
        success: function() {
            showStatus('Drive ejected successfully. Safe to remove.', false);
            loadDrives();
            $('#contentContainer').html('');
        },
        error: function() {
            showStatus('Failed to eject drive. It may be in use.', true);
        }
    });
}

function scanDrive(mountPath, label) {
    $('#contentContainer').html('<div class="content-section"><p><span class="spinner"></span> Scanning "' + label + '" for installable content...</p></div>');
    
    $.ajax({
        type: 'POST',
        url: 'storage.php',
        data: { scanDrive: mountPath },
        dataType: 'json',
        success: function(content) {
            renderContent(content, label, mountPath);
        },
        error: function() {
            $('#contentContainer').html('<div class="content-section"><p class="no-content">Failed to scan drive</p></div>');
        }
    });
}

function renderContent(content, label, mountPath) {
    var html = '<div class="content-section">';
    html += '<h3>📦 Content Found on "' + label + '"</h3>';
    
    // ZIP files
    html += '<h4 style="margin-top:20px;">ZIP Module Packages <span class="badge badge-zip">ZIP</span></h4>';
    if (content.zips.length === 0) {
        html += '<p class="no-content">No .zip files found in root or first-level folders</p>';
    } else {
        for (var i = 0; i < content.zips.length; i++) {
            var z = content.zips[i];
            var suggestedName = z.name.replace('.zip', '').replace(/[^a-zA-Z0-9_\-]/g, '_');
            html += '<div class="content-item">';
            html += '<div class="info"><div class="name">📦 ' + z.name + '</div>';
            html += '<div class="meta">Size: ' + z.size + '</div></div>';
            html += '<div class="actions">';
            html += '<input type="text" id="zipname-' + i + '" value="' + suggestedName + '" placeholder="Module name">';
            html += '<button class="btn-install" onclick="installZip(\'' + z.path.replace(/'/g, "\\'") + '\', ' + i + ')">📥 Install</button>';
            html += '</div></div>';
        }
    }
    
    // Folders
    html += '<h4 style="margin-top:20px;">Folders <span class="badge badge-folder">FOLDER</span></h4>';
    if (content.folders.length === 0) {
        html += '<p class="no-content">No folders found</p>';
    } else {
        for (var i = 0; i < content.folders.length; i++) {
            var f = content.folders[i];
            var suggestedName = f.name.replace(/[^a-zA-Z0-9_\-]/g, '_');
            var suggestedTitle = f.name.replace(/[_\-]/g, ' ');
            
            html += '<div class="content-item folder-item" style="flex-wrap:wrap;">';
            html += '<div class="info" style="width:100%; margin-bottom:10px;"><div class="name">';
            if (f.isModule) {
                html += '✅ ' + f.name + ' <span class="badge badge-module">RACHEL Module</span>';
            } else {
                html += '📁 ' + f.name;
            }
            html += '</div>';
            html += '<div class="meta">' + f.fileCount + ' items</div></div>';
            
            // Show extra options for non-module folders
            if (!f.isModule) {
                html += '<div class="folder-options" style="width:100%; display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px; margin-bottom:10px;">';
                html += '<div><label style="font-size:0.85em; color:#64748b;">Module Folder Name</label>';
                html += '<input type="text" id="foldername-' + i + '" value="' + suggestedName + '" placeholder="Module folder name" style="width:100%;"></div>';
                html += '<div><label style="font-size:0.85em; color:#64748b;">Display Title</label>';
                html += '<input type="text" id="foldertitle-' + i + '" value="' + suggestedTitle + '" placeholder="Title shown to users" style="width:100%;"></div>';
                html += '<div><label style="font-size:0.85em; color:#64748b;">Description</label>';
                html += '<input type="text" id="folderdesc-' + i + '" value="" placeholder="Optional description" style="width:100%;"></div>';
                html += '</div>';
                html += '<div style="width:100%; display:flex; align-items:center; justify-content:space-between;">';
                html += '<label style="font-size:0.9em;"><input type="checkbox" id="foldersubdirs-' + i + '" checked> Add navigation links to subfolders</label>';
                html += '<button class="btn-install" onclick="installFolder(\'' + f.path.replace(/'/g, "\\'") + '\', ' + i + ')">📥 Create Module</button>';
                html += '</div>';
            } else {
                // For existing modules, simpler interface
                html += '<div class="actions" style="width:100%; display:flex; justify-content:flex-end; align-items:center;">';
                html += '<input type="text" id="foldername-' + i + '" value="' + suggestedName + '" placeholder="Module name" style="width:180px;">';
                html += '<button class="btn-install" onclick="installFolder(\'' + f.path.replace(/'/g, "\\'") + '\', ' + i + ')">📥 Install</button>';
                html += '</div>';
            }
            html += '</div>';
        }
    }
    
    html += '</div>';
    
    $('#contentContainer').html(html);
}

function installZip(zipPath, index) {
    var moduleName = $('#zipname-' + index).val().trim();
    if (!moduleName) {
        showStatus('Please enter a module name', true);
        $('#zipname-' + index).focus();
        return;
    }
    
    var $btn = $('#zipname-' + index).siblings('button');
    var originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner"></span> Installing...');
    
    $.ajax({
        type: 'POST',
        url: 'storage.php',
        data: { installZip: zipPath, moduleName: moduleName },
        dataType: 'json',
        success: function(result) {
            $btn.html('✓ Installed').css('background', '#10b981');
            showStatus('Module "' + result.module + '" installed successfully! Go to Modules to configure.', false);
        },
        error: function(xhr) {
            var err = xhr.responseJSON ? xhr.responseJSON.error : 'Installation failed';
            $btn.prop('disabled', false).html(originalText);
            showStatus(err, true);
        }
    });
}

function installFolder(folderPath, index) {
    var moduleName = $('#foldername-' + index).val().trim();
    if (!moduleName) {
        showStatus('Please enter a module name', true);
        $('#foldername-' + index).focus();
        return;
    }
    
    // Get optional title and description
    var moduleTitle = $('#foldertitle-' + index).val() || '';
    var moduleDesc = $('#folderdesc-' + index).val() || '';
    var includeSubfolders = $('#foldersubdirs-' + index).is(':checked') ? '1' : '0';
    
    var $btn = $('#foldername-' + index).closest('.content-item').find('button.btn-install');
    var originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner"></span> Copying...');
    
    $.ajax({
        type: 'POST',
        url: 'storage.php',
        data: { 
            installFolder: folderPath, 
            moduleName: moduleName,
            moduleTitle: moduleTitle,
            moduleDesc: moduleDesc,
            includeSubfolders: includeSubfolders
        },
        dataType: 'json',
        success: function(result) {
            $btn.html('✓ Installed').css('background', '#10b981');
            showStatus('Module "' + result.module + '" created successfully! Go to Modules to configure.', false);
        },
        error: function(xhr) {
            var err = xhr.responseJSON ? xhr.responseJSON.error : 'Installation failed';
            $btn.prop('disabled', false).html(originalText);
            showStatus(err, true);
        }
    });
}

// ==================== USB EXPORT ====================

var currentExportUSB = null;
var exportableModules = [];

function showUSBMode(mode) {
    if (mode === 'import') {
        $('#usbImportBtn').removeClass('btn-scan').addClass('btn-install');
        $('#usbExportBtn').removeClass('btn-install').addClass('btn-scan');
        $('#usbImportSection').show();
        $('#usbExportSection').hide();
        loadDrives();
    } else {
        $('#usbExportBtn').removeClass('btn-scan').addClass('btn-install');
        $('#usbImportBtn').removeClass('btn-install').addClass('btn-scan');
        $('#usbImportSection').hide();
        $('#usbExportSection').show();
        loadExportDrives();
    }
}

function loadExportDrives() {
    $('#exportDrivesContainer').html('<p><span class="spinner"></span> Scanning for USB drives...</p>');
    
    $.ajax({
        type: 'POST',
        url: 'storage.php',
        data: { listDrives: 'listDrives' },
        dataType: 'json',
        success: function(drives) {
            renderExportDrives(drives);
        },
        error: function() {
            $('#exportDrivesContainer').html('<p class="no-content">Failed to scan for USB drives</p>');
        }
    });
}

function renderExportDrives(drives) {
    if (drives.length === 0) {
        $('#exportDrivesContainer').html('<p class="no-content">No USB drives connected. Insert a USB drive and refresh this page.</p>');
        $('#exportModulesContainer').hide();
        return;
    }
    
    var html = '<table id="exportDriveTable"><tr><th>Drive</th><th>Size</th><th>Free Space</th><th>Actions</th></tr>';
    for (var i = 0; i < drives.length; i++) {
        var d = drives[i];
        // Calculate free space from size and used percentage
        var usedPercent = parseInt(d.used.replace('%', '')) || 0;
        var freePercent = 100 - usedPercent;
        
        html += '<tr>';
        html += '<td><strong>💾 ' + d.label + '</strong><br><small style="color:#64748b;">' + d.mountPath + '</small></td>';
        html += '<td>' + d.size + '</td>';
        html += '<td>' + freePercent + '% free</td>';
        html += '<td>';
        html += '<button class="btn-install" onclick="selectExportDrive(\'' + d.mountPath + '\', \'' + d.label + '\')">📤 Select for Export</button> ';
        html += '<button class="btn-eject" onclick="ejectDrive(\'' + d.mountPath + '\'); loadExportDrives();">⏏ Eject</button>';
        html += '</td></tr>';
    }
    html += '</table>';
    
    $('#exportDrivesContainer').html(html);
}

function selectExportDrive(mountPath, label) {
    currentExportUSB = { path: mountPath, label: label };
    
    $('#exportDrivesContainer').html('<div style="padding:15px; background:#dbeafe; border-radius:8px; margin-bottom:15px;">' +
        '<strong>📤 Exporting to: ' + label + '</strong> (' + mountPath + ')' +
        '<button class="btn-scan" onclick="loadExportDrives();" style="margin-left:15px;">Change Drive</button>' +
        '</div>');
    
    loadExportableModules();
}

function loadExportableModules() {
    $('#exportModulesContainer').show();
    $('#exportModulesList').html('<p><span class="spinner"></span> Loading modules...</p>');
    
    $.ajax({
        type: 'POST',
        url: 'storage.php',
        data: { getExportableModules: '1' },
        dataType: 'json',
        success: function(data) {
            exportableModules = data.modules || [];
            renderExportableModules();
        },
        error: function() {
            $('#exportModulesList').html('<p class="no-content">Failed to load modules</p>');
        }
    });
}

function renderExportableModules() {
    if (exportableModules.length === 0) {
        $('#exportModulesList').html('<p class="no-content">No modules found to export</p>');
        return;
    }
    
    var html = '';
    for (var i = 0; i < exportableModules.length; i++) {
        var m = exportableModules[i];
        var safeModdir = $('<div>').text(m.moddir).html(); // HTML escape
        html += '<div class="content-item export-module-item" data-moddir="' + safeModdir + '" data-index="' + i + '">';
        html += '<div class="info">';
        html += '<div class="name">' + $('<div>').text(m.title).html() + '</div>';
        html += '<div class="meta">' + safeModdir + ' • <span class="module-size" id="size-' + i + '">Size: calculating...</span></div>';
        html += '</div>';
        html += '<div class="actions">';
        html += '<button class="btn-scan check-size-btn" data-index="' + i + '" style="display:none;">📏 Check Size</button>';
        html += '<button class="btn-install export-btn" data-index="' + i + '" style="display:none;">📤 Export</button>';
        html += '</div>';
        html += '</div>';
    }
    
    $('#exportModulesList').html(html);
    
    // Auto-calculate sizes for all modules (in batches to avoid overwhelming)
    calculateAllModuleSizes();
    
    // Bind click handlers after rendering
    $('.check-size-btn').on('click', function() {
        var $item = $(this).closest('.export-module-item');
        var moddir = $item.data('moddir');
        var index = $(this).data('index');
        checkModuleSize(moddir, index);
    });
    
    $('.export-btn').on('click', function() {
        var $item = $(this).closest('.export-module-item');
        var moddir = $item.data('moddir');
        var index = $(this).data('index');
        var size = $item.find('.module-size').text();
        if (confirm('Export "' + moddir + '" (' + size + ') to USB drive?')) {
            exportModule(moddir, index);
        }
    });
}

function calculateAllModuleSizes() {
    var items = $('.export-module-item');
    var index = 0;
    
    function calcNext() {
        if (index >= items.length) return;
        
        var $item = $(items[index]);
        var moddir = $item.data('moddir');
        var i = $item.data('index');
        var $sizeSpan = $('#size-' + i);
        var $exportBtn = $item.find('.export-btn');
        
        $.ajax({
            type: 'POST',
            url: 'storage.php',
            data: { getModuleSize: '1', moddir: moddir },
            dataType: 'json',
            success: function(sizeData) {
                $sizeSpan.text(sizeData.sizeFormatted);
                $exportBtn.show();
            },
            error: function() {
                $sizeSpan.text('Unknown size');
                $exportBtn.show();
            },
            complete: function() {
                index++;
                // Small delay to avoid hammering the server
                setTimeout(calcNext, 50);
            }
        });
    }
    
    calcNext();
}

function checkModuleSize(moddir, index) {
    var $sizeSpan = $('#size-' + index);
    var $checkBtn = $('.check-size-btn[data-index="' + index + '"]');
    var $exportBtn = $('.export-btn[data-index="' + index + '"]');
    
    $checkBtn.prop('disabled', true).html('<span class="spinner"></span> Checking...');
    
    $.ajax({
        type: 'POST',
        url: 'storage.php',
        data: { getModuleSize: '1', moddir: moddir },
        dataType: 'json',
        success: function(sizeData) {
            $sizeSpan.text(sizeData.sizeFormatted);
            $checkBtn.hide();
            $exportBtn.show();
        },
        error: function(xhr) {
            var err = xhr.responseJSON ? xhr.responseJSON.error : 'Failed to calculate size';
            $checkBtn.prop('disabled', false).html('📏 Check Size');
            showStatus(err, true);
        }
    });
}

function exportModule(moddir, index) {
    if (!currentExportUSB) {
        showStatus('Please select a USB drive first', true);
        return;
    }
    
    var $btn = $('.export-btn[data-index="' + index + '"]');
    $btn.prop('disabled', true).html('<span class="spinner"></span> Copying...');
    
    // Do the export
    $.ajax({
        type: 'POST',
        url: 'storage.php',
        data: { 
            exportModule: '1', 
            moddir: moddir,
            usbPath: currentExportUSB.path
        },
        dataType: 'json',
        success: function(result) {
            $btn.html('✓ Exported').css('background', '#10b981');
            showStatus('Module exported to ' + currentExportUSB.label + '/RACHEL-Export/' + moddir, false);
        },
        error: function(xhr) {
            var err = xhr.responseJSON ? xhr.responseJSON.error : 'Export failed';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.afterExport) {
                    // 95% capacity error
                    err += ' (Current: ' + xhr.responseJSON.currentUsage + ', After export: ' + xhr.responseJSON.afterExport + ')';
                } else if (xhr.responseJSON.required) {
                    // Not enough space error
                    err += ' (Need: ' + xhr.responseJSON.required + ', Have: ' + xhr.responseJSON.available + ')';
                }
            }
            $btn.prop('disabled', false).html('📤 Export');
            showStatus(err, true);
        }
    });
}

function filterExportModules() {
    var search = $('#exportModuleSearch').val().toLowerCase();
    $('.export-module-item').each(function() {
        var text = $(this).text().toLowerCase();
        if (!search || text.indexOf(search) !== -1) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
}

// ==================== MODULE BUILDER ====================

var builderData = {
    moduleName: '',
    moduleTitle: '',
    moduleDesc: '',
    logoFile: null,
    pages: []
};

var pageCounter = 0;
var blockCounter = 0;

function startBuilder() {
    var moduleName = $('#builderModuleName').val().trim();
    
    if (!moduleName) {
        showStatus('Please enter a module name', true);
        $('#builderModuleName').focus();
        return;
    }
    
    // Generate display title from module name
    var moduleTitle = moduleName.replace(/[_\-]/g, ' ');
    moduleTitle = moduleTitle.replace(/\b\w/g, function(l) { return l.toUpperCase(); });
    
    builderData.moduleName = moduleName.replace(/[^a-zA-Z0-9_\-]/g, '_');
    builderData.moduleTitle = moduleTitle;
    builderData.moduleDesc = $('#builderModuleDesc').val() || '';
    
    var logoInput = document.getElementById('builderLogo');
    if (logoInput.files.length > 0) {
        builderData.logoFile = logoInput.files[0];
    }
    
    $('#builderSetup').hide();
    $('#builderWorkspace').show();
    $('#builderTitle').text(moduleTitle);
    
    // Add first page automatically
    addPage();
}

function addPage() {
    pageCounter++;
    var pageId = 'page-' + pageCounter;
    
    var pageHtml = '<div class="page-card" id="' + pageId + '" data-page-id="' + pageCounter + '">';
    pageHtml += '<div class="page-card-header">';
    pageHtml += '<span class="page-num">' + pageCounter + '</span>';
    pageHtml += '<input type="text" class="page-title" placeholder="Page Title (e.g., Introduction, Chapter 1...)" value="">';
    pageHtml += '<div class="page-actions">';
    pageHtml += '<button onclick="movePage(\'' + pageId + '\', -1)" title="Move Up">⬆️</button>';
    pageHtml += '<button onclick="movePage(\'' + pageId + '\', 1)" title="Move Down">⬇️</button>';
    pageHtml += '<button onclick="deletePage(\'' + pageId + '\')" title="Delete Page">🗑️</button>';
    pageHtml += '</div></div>';
    pageHtml += '<div class="page-card-body">';
    pageHtml += '<div class="blocks-container" id="blocks-' + pageId + '"></div>';
    pageHtml += '<div class="add-content-btns">';
    pageHtml += '<button onclick="addTextBlock(\'' + pageId + '\')">📝 Add Text</button>';
    pageHtml += '<button onclick="addMediaBlock(\'' + pageId + '\', \'video\')">🎬 Add Video</button>';
    pageHtml += '<button onclick="addMediaBlock(\'' + pageId + '\', \'pdf\')">📄 Add PDF</button>';
    pageHtml += '<button onclick="addMediaBlock(\'' + pageId + '\', \'image\')">🖼️ Add Image</button>';
    pageHtml += '</div></div></div>';
    
    $('#pagesContainer').append(pageHtml);
    updatePageCount();
    
    // Focus on the title
    $('#' + pageId + ' .page-title').focus();
}

function addTextBlock(pageId) {
    blockCounter++;
    var blockId = 'block-' + blockCounter;
    
    var blockHtml = '<div class="content-block" id="' + blockId + '" data-type="text">';
    blockHtml += '<div class="block-type">📝 Text Block</div>';
    blockHtml += '<button class="remove-block" onclick="removeBlock(\'' + blockId + '\')">×</button>';
    blockHtml += '<textarea placeholder="Enter your text content here. You can explain concepts, provide instructions, or add any educational content..."></textarea>';
    blockHtml += '</div>';
    
    $('#blocks-' + pageId).append(blockHtml);
}

function addMediaBlock(pageId, mediaType) {
    blockCounter++;
    var blockId = 'block-' + blockCounter;
    
    var accept = '';
    var label = '';
    var icon = '';
    
    switch(mediaType) {
        case 'video':
            accept = 'video/mp4,video/webm,video/ogg';
            label = 'Video';
            icon = '🎬';
            break;
        case 'pdf':
            accept = 'application/pdf';
            label = 'PDF Document';
            icon = '📄';
            break;
        case 'image':
            accept = 'image/*';
            label = 'Image';
            icon = '🖼️';
            break;
    }
    
    var blockHtml = '<div class="content-block" id="' + blockId + '" data-type="' + mediaType + '">';
    blockHtml += '<div class="block-type">' + icon + ' ' + label + '</div>';
    blockHtml += '<button class="remove-block" onclick="removeBlock(\'' + blockId + '\')">×</button>';
    blockHtml += '<div class="media-preview" id="preview-' + blockId + '">';
    blockHtml += '<input type="file" accept="' + accept + '" onchange="previewMedia(\'' + blockId + '\', this)">';
    blockHtml += '</div>';
    blockHtml += '<input type="text" class="media-caption" placeholder="Caption (optional)" style="width:100%; margin-top:10px; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">';
    blockHtml += '</div>';
    
    $('#blocks-' + pageId).append(blockHtml);
}

function previewMedia(blockId, input) {
    var preview = $('#preview-' + blockId);
    var file = input.files[0];
    
    if (!file) return;
    
    var type = $('#' + blockId).data('type');
    var html = '<input type="file" accept="' + input.accept + '" onchange="previewMedia(\'' + blockId + '\', this)">';
    
    if (type === 'image') {
        var reader = new FileReader();
        reader.onload = function(e) {
            html = '<img src="' + e.target.result + '"><span>' + file.name + '</span>' + html;
            preview.html(html);
        };
        reader.readAsDataURL(file);
    } else {
        var icon = type === 'video' ? '🎬' : '📄';
        html = '<span style="font-size:2em;">' + icon + '</span><span>' + file.name + ' (' + (file.size / 1024 / 1024).toFixed(1) + ' MB)</span>' + html;
        preview.html(html);
    }
}

function removeBlock(blockId) {
    $('#' + blockId).remove();
}

function deletePage(pageId) {
    if (!confirm('Delete this page and all its content?')) return;
    $('#' + pageId).remove();
    updatePageCount();
    renumberPages();
}

function movePage(pageId, direction) {
    var $page = $('#' + pageId);
    if (direction === -1) {
        $page.prev('.page-card').before($page);
    } else {
        $page.next('.page-card').after($page);
    }
    renumberPages();
}

function renumberPages() {
    $('.page-card').each(function(index) {
        $(this).find('.page-num').text(index + 1);
    });
}

function updatePageCount() {
    var count = $('.page-card').length;
    $('#builderPageCount').text(count + ' page' + (count !== 1 ? 's' : ''));
}

function saveModule() {
    // Collect all the data
    var pages = [];
    
    $('.page-card').each(function() {
        var $page = $(this);
        var pageData = {
            title: $page.find('.page-title').val() || 'Untitled Page',
            blocks: []
        };
        
        $page.find('.content-block').each(function() {
            var $block = $(this);
            var type = $block.data('type');
            var blockData = { type: type };
            
            if (type === 'text') {
                blockData.content = $block.find('textarea').val();
            } else {
                var fileInput = $block.find('input[type="file"]')[0];
                if (fileInput && fileInput.files.length > 0) {
                    blockData.file = fileInput.files[0];
                }
                blockData.caption = $block.find('.media-caption').val() || '';
            }
            
            pageData.blocks.push(blockData);
        });
        
        pages.push(pageData);
    });
    
    if (pages.length === 0) {
        showStatus('Please add at least one page', true);
        return;
    }
    
    // Build FormData
    var formData = new FormData();
    formData.append('buildModule', '1');
    formData.append('moduleName', builderData.moduleName);
    formData.append('moduleTitle', builderData.moduleTitle);
    formData.append('moduleDesc', builderData.moduleDesc);
    
    if (builderData.logoFile) {
        formData.append('logo', builderData.logoFile);
    }
    
    // Add pages data as JSON
    var pagesJson = [];
    var fileIndex = 0;
    
    pages.forEach(function(page, pageIdx) {
        var pageJson = {
            title: page.title,
            blocks: []
        };
        
        page.blocks.forEach(function(block) {
            var blockJson = { type: block.type };
            
            if (block.type === 'text') {
                blockJson.content = block.content;
            } else {
                if (block.file) {
                    blockJson.fileKey = 'media_' + fileIndex;
                    formData.append('media_' + fileIndex, block.file);
                    fileIndex++;
                }
                blockJson.caption = block.caption;
            }
            
            pageJson.blocks.push(blockJson);
        });
        
        pagesJson.push(pageJson);
    });
    
    formData.append('pages', JSON.stringify(pagesJson));
    
    // Show saving state
    $('button').prop('disabled', true);
    showStatus('Saving module...', false);
    
    $.ajax({
        type: 'POST',
        url: 'storage.php',
        data: formData,
        processData: false,
        contentType: false,
        success: function(result) {
            showStatus('Module "' + result.module + '" created successfully! Go to Modules tab to view.', false);
            // Reset builder
            resetBuilder();
        },
        error: function(xhr) {
            var err = xhr.responseJSON ? xhr.responseJSON.error : 'Failed to save module';
            showStatus(err, true);
            $('button').prop('disabled', false);
        }
    });
}

function resetBuilder() {
    builderData = { moduleName: '', moduleTitle: '', moduleDesc: '', logoFile: null, pages: [] };
    pageCounter = 0;
    blockCounter = 0;
    $('#pagesContainer').empty();
    $('#builderWorkspace').hide();
    $('#builderSetup').show();
    $('#builderModuleName, #builderModuleTitle, #builderModuleDesc').val('');
    $('#builderLogo').val('');
    $('button').prop('disabled', false);
}

// ==================== WORLD POSSIBLE TAB ====================

// Load remote modules list on tab view
$(function() {
    loadRemoteModules();
});

// Check for content shell updates
function checkShellUpdate() {
    var btn = $('#shellUpdateBtn');
    btn.prop('disabled', true);
    $('#shellSpinner').show();
    
    $.ajax({
        url: 'background.php?selfUpdate=1&check=1',
        success: function(results) {
            modulesCache = results;
            var curShell = $('#cur_contentshell').text();
            var cshell4 = results['contentshell4'];
            
            if (!cshell4) {
                btn.css('color', '#dc2626').html('✗ Cannot check');
                return;
            }
            
            var liveShell = cshell4['version'];
            if (liveShell != curShell) {
                btn.removeClass('btn-scan').addClass('btn-install').html('📥 Install ' + liveShell);
                btn.off('click').on('click', function() { applyShellUpdate(); });
            } else {
                btn.css('background', '#10b981').html('✓ Up to date');
            }
        },
        error: function() {
            btn.css('color', '#dc2626').html('✗ Cannot connect');
        },
        complete: function() {
            $('#shellSpinner').hide();
            btn.prop('disabled', false);
        }
    });
}

function applyShellUpdate() {
    var btn = $('#shellUpdateBtn');
    btn.prop('disabled', true);
    $('#shellSpinner').show();
    
    $.ajax({
        url: 'background.php?selfUpdate=1',
        success: function(results) {
            btn.css('background', '#10b981').html('✓ Updated');
            $('#cur_contentshell').text(results.version);
            showStatus('Content Shell updated successfully!', false);
        },
        error: function() {
            btn.css('color', '#dc2626').html('✗ Update failed');
            showStatus('Failed to update Content Shell', true);
        },
        complete: function() {
            $('#shellSpinner').hide();
        }
    });
}

// Check for module updates
function checkForUpdates() {
    $('#installedModulesUpdate').html('<p style="text-align:center;"><span class="spinner"></span> Checking for updates...</p>');
    
    $.ajax({
        url: 'background.php?selfUpdate=1&check=1',
        success: function(results) {
            modulesCache = results;
            renderInstalledModulesUpdate(results);
        },
        error: function() {
            $('#installedModulesUpdate').html('<p style="color:#dc2626; text-align:center;">Failed to check for updates. Check your internet connection.</p>');
        }
    });
}

function renderInstalledModulesUpdate(remoteVersions) {
    var html = '';
    var updatesAvailable = 0;
    
    <?php
    foreach (getmods_fs() as $mod) {
        echo "installedModuleVersions['{$mod['moddir']}'] = '{$mod['version']}';\n";
    }
    ?>
    
    // Only show modules that have different versions (potential updates)
    for (var moddir in installedModuleVersions) {
        var curVersion = installedModuleVersions[moddir];
        var remoteVersion = remoteVersions[moddir] ? remoteVersions[moddir].version : null;
        var hasUpdate = remoteVersion && curVersion && remoteVersion != curVersion;
        
        // Skip modules that are up-to-date or local-only
        if (!hasUpdate) continue;
        
        updatesAvailable++;
        
        html += '<div class="content-item">';
        html += '<div class="info">';
        html += '<div class="name">' + moddir + '</div>';
        html += '<div class="meta">Local: <strong>' + (curVersion || 'unknown') + '</strong> → Server: <strong>' + remoteVersion + '</strong></div>';
        html += '</div>';
        html += '<div class="actions">';
        html += '<button class="btn-install" id="upd_' + moddir + '" onclick="updateModule(\'' + moddir + '\')">📥 Update</button>';
        html += '<div class="progbar" id="updprog_' + moddir + '" style="display:none; width:150px; height:20px; background:#e5e7eb; border-radius:4px; margin-left:10px; overflow:hidden;">';
        html += '<div class="progbarin" id="updprogin_' + moddir + '" style="height:100%; background:#3b82f6; width:0; font-size:12px; color:white; text-align:center; line-height:20px;"></div></div>';
        html += '</div></div>';
    }
    
    if (updatesAvailable === 0) {
        html = '<p style="color:#10b981; text-align:center;">✓ All modules are up to date with the server</p>';
    }
    
    $('#installedModulesUpdate').html(html);
    
    if (updatesAvailable > 0) {
        $('#updateAllBtn').show().html('📥 Update All (' + updatesAvailable + ')');
    } else {
        $('#updateAllBtn').hide();
    }
}

function updateModule(moddir) {
    var btn = $('#upd_' + moddir);
    var prog = $('#updprog_' + moddir);
    var progin = $('#updprogin_' + moddir);
    
    btn.hide();
    prog.show();
    progin.html('Starting...');
    
    $.ajax({
        url: 'background.php?modUpdate=' + moddir,
        success: function() {
            progin.html('Downloading...');
            pollTasks();
        },
        error: function() {
            prog.hide();
            btn.show().css('color', '#dc2626').html('✗ Failed');
        }
    });
}

function updateAllMods() {
    $('#updateAllBtn').prop('disabled', true);
    $('#updateAllSpinner').show();
    
    var moddirs = [];
    $('button[id^="upd_"]:visible').each(function() {
        var moddir = this.id.substring(4);
        moddirs.push(moddir);
        $('#upd_' + moddir).hide();
        $('#updprog_' + moddir).show();
        $('#updprogin_' + moddir).html('Queued...');
    });
    
    if (moddirs.length === 0) {
        $('#updateAllSpinner').hide();
        $('#updateAllBtn').prop('disabled', false);
        return;
    }
    
    $.ajax({
        url: 'background.php?addModules=' + moddirs.join(','),
        success: function() {
            pollTasks();
            $('#updateAllSpinner').hide();
            $('#updateAllBtn').css('background', '#10b981').html('✓ Updates started');
        },
        error: function() {
            $('#updateAllSpinner').hide();
            $('#updateAllBtn').prop('disabled', false).css('color', '#dc2626').html('✗ Failed');
        }
    });
}

// Poll for download progress
var polling = false;
function pollTasks() {
    if (polling) return;
    
    $.ajax({
        url: 'background.php?getTasks=1&includeVersion=1',
        success: function(results) {
            var inProgress = false;
            
            for (var i = 0; i < results.length; i++) {
                var task = results[i];
                var moddir = task.moddir;
                
                // Update progress bar
                var prog = $('#updprog_' + moddir);
                var progin = $('#updprogin_' + moddir);
                var btn = $('#upd_' + moddir);
                
                // Also check remote module download progress
                var rmProg = $('#rmprog_' + moddir);
                var rmProgin = $('#rmprogin_' + moddir);
                var rmBtn = $('#rmbtn_' + moddir);
                
                if (task.retval > 0) {
                    // Error
                    prog.hide(); btn.show().css('color', '#dc2626').html('✗ Error');
                    rmProg.hide(); rmBtn.show().css('color', '#dc2626').html('✗ Error');
                } else if (task.completed && task.retval == 0) {
                    // Done
                    prog.hide(); btn.show().css('background', '#10b981').html('✓ Updated');
                    rmProg.hide(); rmBtn.show().css('background', '#10b981').html('✓ Installed');
                    installedModuleVersions[moddir] = task.version;
                } else if (task.started) {
                    // In progress
                    prog.show(); btn.hide();
                    rmProg.show(); rmBtn.hide();
                    
                    if (modulesCache[moddir]) {
                        var total = modulesCache[moddir].ksize || 1;
                        var done = Math.round(task.data_done / 1024);
                        var perc = Math.min(100, Math.round((done / total) * 100));
                        progin.css('width', perc + '%').html(perc + '%');
                        rmProgin.css('width', perc + '%').html(perc + '%');
                    } else {
                        progin.html('Downloading...');
                        rmProgin.html('Downloading...');
                    }
                    inProgress = true;
                } else {
                    progin.html('Waiting...');
                    rmProgin.html('Waiting...');
                    inProgress = true;
                }
            }
            
            setTimeout(function() { polling = false; pollTasks(); }, 2000);
            polling = true;
        },
        error: function() {
            polling = false;
        }
    });
}

// Load remote modules for download
function loadRemoteModules() {
    $('#remoteModulesContainer').html('<p style="text-align:center;"><span class="spinner"></span> Loading available modules...</p>');
    
    $.ajax({
        url: 'background.php?getRemoteModuleList=1',
        success: function(results) {
            remoteModules = [];
            for (var mod in results) {
                if (results.hasOwnProperty(mod) && mod !== 'contentshell' && mod !== 'contentshell4') {
                    remoteModules.push({
                        moddir: mod,
                        title: results[mod].title || mod,
                        description: results[mod].description || '',
                        version: results[mod].version || '',
                        ksize: results[mod].ksize || 0,
                        category: results[mod].category || ''
                    });
                }
            }
            modulesCache = results;
            renderRemoteModules();
        },
        error: function() {
            $('#remoteModulesContainer').html('<p style="color:#dc2626; text-align:center;">Failed to load module list. Check your internet connection.</p>');
        }
    });
}

function renderRemoteModules() {
    var search = $('#moduleSearch').val().toLowerCase();
    var html = '';
    var count = 0;
    
    remoteModules.sort(function(a, b) { return a.title.localeCompare(b.title); });
    
    for (var i = 0; i < remoteModules.length; i++) {
        var mod = remoteModules[i];
        
        // Filter by search
        if (search && mod.title.toLowerCase().indexOf(search) === -1 && mod.moddir.toLowerCase().indexOf(search) === -1) {
            continue;
        }
        
        var isInstalled = installedModuleVersions.hasOwnProperty(mod.moddir);
        
        // ksize is in MB, convert properly
        var sizeStr = '';
        var sizeMB = mod.ksize || 0;
        var storageImpact = '';
        
        if (sizeMB > 0) {
            if (sizeMB >= 1024) {
                sizeStr = (sizeMB / 1024).toFixed(1) + ' GB';
            } else {
                sizeStr = sizeMB + ' MB';
            }
            
            // Calculate percentage of free space this would use
            if (typeof freeSpaceMB !== 'undefined' && freeSpaceMB > 0) {
                var pct = (sizeMB / freeSpaceMB * 100).toFixed(1);
                if (pct > 100) {
                    storageImpact = ' <span style="color:#dc2626; font-weight:500;">(NOT ENOUGH SPACE!)</span>';
                } else if (pct > 50) {
                    storageImpact = ' <span style="color:#f59e0b;">(' + pct + '% of free space)</span>';
                } else {
                    storageImpact = ' <span style="color:#64748b;">(' + pct + '% of free space)</span>';
                }
            }
        }
        
        html += '<div class="content-item">';
        html += '<div class="info">';
        html += '<div class="name">' + mod.title;
        if (isInstalled) {
            html += ' <span class="badge badge-module">Installed</span>';
        }
        html += '</div>';
        html += '<div class="meta">' + mod.moddir;
        if (sizeStr) html += ' | <strong>' + sizeStr + '</strong>' + storageImpact;
        if (mod.category) html += ' | ' + mod.category;
        html += '</div>';
        html += '</div>';
        html += '<div class="actions">';
        
        if (isInstalled) {
            html += '<button class="btn-install" id="rmbtn_' + mod.moddir + '" onclick="downloadModule(\'' + mod.moddir + '\')" style="background:#64748b;">🔄 Reinstall</button>';
        } else {
            html += '<button class="btn-install" id="rmbtn_' + mod.moddir + '" onclick="downloadModule(\'' + mod.moddir + '\')">📥 Download</button>';
        }
        
        html += '<div class="progbar" id="rmprog_' + mod.moddir + '" style="display:none; width:150px; height:28px; background:#e5e7eb; border-radius:4px; margin-left:10px; overflow:hidden;">';
        html += '<div class="progbarin" id="rmprogin_' + mod.moddir + '" style="height:100%; background:#3b82f6; width:0; font-size:12px; color:white; text-align:center; line-height:28px;"></div></div>';
        html += '</div></div>';
        count++;
    }
    
    if (!html) {
        html = '<p style="color:#64748b; text-align:center;">No modules found matching your search</p>';
    }
    
    $('#remoteModulesContainer').html(html);
}

function filterRemoteModules() {
    renderRemoteModules();
}

function downloadModule(moddir) {
    var btn = $('#rmbtn_' + moddir);
    var prog = $('#rmprog_' + moddir);
    var progin = $('#rmprogin_' + moddir);
    
    var customServer = $('#customServer').val();
    var url = 'background.php?addModules=' + moddir;
    if (customServer) {
        url += '&server=' + encodeURIComponent(customServer);
    }
    
    btn.hide();
    prog.show();
    progin.html('Starting...');
    
    $.ajax({
        url: url,
        success: function() {
            progin.html('Downloading...');
            pollTasks();
        },
        error: function() {
            prog.hide();
            btn.show().css('color', '#dc2626').html('✗ Failed');
        }
    });
}

function uploadModulesFile() {
    var fileInput = document.getElementById('modulesFile');
    if (!fileInput.files.length) {
        showStatus('Please select a .modules file', true);
        return;
    }
    
    var formData = new FormData();
    formData.append('modulesFile', fileInput.files[0]);
    
    $.ajax({
        type: 'POST',
        url: 'background.php?uploadModulesFile=1',
        data: formData,
        processData: false,
        contentType: false,
        success: function(results) {
            showStatus('Modules file uploaded. Downloading ' + results.count + ' modules...', false);
            pollTasks();
        },
        error: function(xhr) {
            var err = xhr.responseJSON ? xhr.responseJSON.error : 'Failed to upload file';
            showStatus(err, true);
        }
    });
}

// ==================== DELETE MODULES TAB ====================

function loadInstalledModules() {
    $('#deleteModulesContainer').html('<p style="text-align:center;"><span class="spinner"></span> Loading modules...</p>');
    
    // Get from the PHP-rendered variable
    var html = '';
    
    <?php
    // Get modules and sort by position (same as Rearrange tab)
    $fsmods = getmods_fs();
    if ($fsmods) {
        $db = getdb();
        if ($db) {
            $dbmods = getmods_db();
            foreach (array_keys($dbmods) as $moddir) {
                if (isset($fsmods[$moddir])) {
                    $fsmods[$moddir]['position'] = $dbmods[$moddir]['position'];
                }
            }
        }
        uasort($fsmods, 'bypos');
        
        foreach ($fsmods as $mod) {
            if (!$mod['fragment']) continue;
            $moddir = $mod['moddir'];
            $title = isset($mod['title']) ? addslashes($mod['title']) : $moddir;
            echo "html += renderDeleteModuleItem('{$moddir}', '{$title}');\n";
        }
    }
    ?>
    
    if (!html) {
        html = '<p style="color:#64748b; text-align:center;">No modules installed</p>';
    }
    
    $('#deleteModulesContainer').html(html);
}

function renderDeleteModuleItem(moddir, title) {
    var html = '<div class="content-item delete-module-item" id="delitem_' + moddir + '" data-moddir="' + moddir.toLowerCase() + '" data-title="' + title.toLowerCase() + '">';
    html += '<div class="info">';
    html += '<div class="name">' + title + '</div>';
    html += '<div class="meta">' + moddir + '</div>';
    html += '</div>';
    html += '<div class="actions">';
    html += '<button class="btn-eject" onclick="deleteModule(\'' + moddir + '\', \'' + title.replace(/'/g, "\\'") + '\')">Delete</button>';
    html += '</div></div>';
    return html;
}

function filterDeleteModules() {
    var search = $('#deleteModuleSearch').val().toLowerCase();
    $('.delete-module-item').each(function() {
        var moddir = $(this).data('moddir');
        var title = $(this).data('title');
        if (!search || moddir.indexOf(search) !== -1 || title.indexOf(search) !== -1) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
}

function filterVisibilityModules() {
    var search = $('#visibilityModuleSearch').val().toLowerCase();
    $('.visibility-item').each(function() {
        var moddir = $(this).data('moddir').toLowerCase();
        var text = $(this).text().toLowerCase();
        if (!search || moddir.indexOf(search) !== -1 || text.indexOf(search) !== -1) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
}

// ==================== CATEGORIES TAB ====================

var masterCategories = {};
var moduleCategories = {};
var categoriesChanged = false;
var originalCategories = {};

// Emoji picker data - commonly used emojis for categories
var emojiLibrary = [
    // Education & Learning
    '📚', '📖', '📕', '📗', '📘', '📙', '📓', '📔', '📒', '📃', '📜', '📄', '📰', '🗞️', '📑',
    '🎓', '🏫', '✏️', '📝', '✒️', '🖊️', '🖋️', '📐', '📏', '🔬', '🔭', '🧪', '🧫', '🧬', '🔍',
    // Technology
    '💻', '🖥️', '⌨️', '🖱️', '📱', '📲', '☎️', '📞', '📟', '📠', '🔌', '🔋', '💾', '💿', '📀',
    // Arts & Media
    '🎨', '🖼️', '🎭', '🎬', '🎤', '🎧', '🎵', '🎶', '🎹', '🎸', '🎺', '🎻', '🥁', '📷', '📹',
    // Nature & Science
    '🌍', '🌎', '🌏', '🌐', '🗺️', '🧭', '⛰️', '🏔️', '🌋', '🏕️', '🏖️', '🌊', '🌲', '🌳', '🌴',
    '🌱', '🌿', '☘️', '🍀', '🌸', '🌺', '🌻', '🌼', '🌷', '🦋', '🐝', '🐛', '🦎', '🐢', '🐠',
    // Health & Wellness  
    '🏥', '💊', '💉', '🩺', '🩹', '🧬', '🦠', '🧘', '🏃', '🚴', '🏊', '⚽', '🏀', '🎾', '🏐',
    // People & Community
    '👨‍🏫', '👩‍🏫', '👨‍🎓', '👩‍🎓', '👨‍💻', '👩‍💻', '👨‍🔬', '👩‍🔬', '👶', '🧒', '👦', '👧', '🧑', '👨', '👩',
    // Work & Vocational
    '🔧', '🔨', '⚒️', '🛠️', '⚙️', '🔩', '🪛', '🪚', '🔑', '🗝️', '🏠', '🏗️', '👷', '👨‍🍳', '👩‍🌾',
    // Food & Agriculture
    '🌾', '🌽', '🥕', '🥬', '🍎', '🍊', '🍋', '🍇', '🍓', '🥛', '🍞', '🧀', '🍳', '🥗', '🍲',
    // Objects & Symbols
    '📁', '📂', '🗂️', '📋', '📌', '📍', '🏷️', '🔖', '❤️', '⭐', '🌟', '✨', '💡', '🔔', '📢',
    '🎯', '🏆', '🥇', '🎖️', '🏅', '📊', '📈', '📉', '🗃️', '🗄️', '💼', '📦', '🎁', '🎀', '🎈',
    // Flags & Symbols
    '🚩', '🏁', '🔴', '🟠', '🟡', '🟢', '🔵', '🟣', '⚫', '⚪', '🟤', '✅', '❌', '❓', '❗'
];

var selectedEmoji = '📁';

function initEmojiPicker() {
    var grid = document.getElementById('emojiGrid');
    if (!grid) return;
    
    var html = '';
    emojiLibrary.forEach(function(emoji) {
        html += '<button type="button" class="emoji-pick-btn" onclick="selectEmoji(\'' + emoji + '\');" style="width:30px; height:30px; font-size:18px; border:1px solid transparent; border-radius:4px; background:none; cursor:pointer; transition:all 0.15s;">' + emoji + '</button>';
    });
    grid.innerHTML = html;
}

function toggleEmojiPicker() {
    var dropdown = document.getElementById('emojiPickerDropdown');
    if (dropdown.style.display === 'none') {
        dropdown.style.display = 'block';
        initEmojiPicker();
    } else {
        dropdown.style.display = 'none';
    }
}

function selectEmoji(emoji) {
    selectedEmoji = emoji;
    document.getElementById('emojiPickerBtn').textContent = emoji;
    document.getElementById('newCategoryIcon').value = emoji;
    document.getElementById('emojiPickerDropdown').style.display = 'none';
}

// Close emoji picker when clicking outside
document.addEventListener('click', function(e) {
    var picker = document.getElementById('emojiPickerDropdown');
    var btn = document.getElementById('emojiPickerBtn');
    if (picker && btn && !picker.contains(e.target) && e.target !== btn) {
        picker.style.display = 'none';
    }
});

function createCustomCategory() {
    var icon = document.getElementById('newCategoryIcon').value || '📁';
    var catId = document.getElementById('newCategoryId').value.trim();
    var label = document.getElementById('newCategoryLabel').value.trim();
    var color = document.getElementById('newCategoryColor').value;
    
    var msgEl = document.getElementById('createCategoryMessage');
    
    if (!catId) {
        msgEl.innerHTML = '<span style="color:#dc2626;">Please enter a category ID</span>';
        msgEl.style.display = 'block';
        return;
    }
    
    if (!label) {
        label = catId.charAt(0).toUpperCase() + catId.slice(1);
    }
    
    // Check if category already exists
    if (masterCategories[catId]) {
        msgEl.innerHTML = '<span style="color:#dc2626;">Category "' + catId + '" already exists</span>';
        msgEl.style.display = 'block';
        return;
    }
    
    // Save to server
    $.ajax({
        url: 'background.php?createCategory=1',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            id: catId,
            icon: icon,
            label: label,
            color: color
        }),
        success: function(response) {
            msgEl.innerHTML = '<span style="color:#166534;">✓ Category "' + label + '" created successfully!</span>';
            msgEl.style.display = 'block';
            
            // Clear form
            document.getElementById('newCategoryId').value = '';
            document.getElementById('newCategoryLabel').value = '';
            document.getElementById('emojiPickerBtn').textContent = '📁';
            document.getElementById('newCategoryIcon').value = '📁';
            
            // Reload categories
            setTimeout(function() {
                msgEl.style.display = 'none';
                loadCategories();
            }, 1500);
        },
        error: function(xhr) {
            var err = 'Failed to create category';
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.error) err = resp.error;
            } catch(e) {}
            msgEl.innerHTML = '<span style="color:#dc2626;">' + err + '</span>';
            msgEl.style.display = 'block';
        }
    });
}

function renderExistingCategories() {
    var html = '';
    var sortedCats = Object.keys(masterCategories).sort();
    sortedCats.forEach(function(catId) {
        var display = masterCategories[catId];
        html += '<span style="display:inline-flex; align-items:center; gap:4px; padding:4px 10px; background:#e0e7ff; color:#3730a3; border-radius:16px; font-size:13px;">';
        html += display;
        html += '<button onclick="deleteCategory(\'' + catId + '\');" style="background:none; border:none; color:#6366f1; cursor:pointer; font-size:14px; padding:0 0 0 4px;" title="Delete category">×</button>';
        html += '</span>';
    });
    $('#existingCategoriesList').html(html || '<span style="color:#64748b;">No categories defined</span>');
}

function deleteCategory(catId) {
    if (!confirm('Delete category "' + catId + '"? Modules will be unassigned from this category.')) return;
    
    $.ajax({
        url: 'background.php?deleteCategory=1',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id: catId }),
        success: function() {
            loadCategories();
        },
        error: function(xhr) {
            alert('Failed to delete category');
        }
    });
}

function loadCategories() {
    $.ajax({
        url: 'background.php?getCategoryMap=1',
        dataType: 'json',
        success: function(data) {
            // Extract just the labels from category objects
            var cats = data.categories || {};
            masterCategories = {};
            Object.keys(cats).forEach(function(catId) {
                masterCategories[catId] = (cats[catId].icon || '') + ' ' + (cats[catId].label || catId);
            });
            renderExistingCategories();
            renderCategoriesUI();
        },
        error: function(xhr, status, error) {
            $('#categoriesContainer').html('<p style="color:#dc2626; text-align:center;">Failed to load categories: ' + error + '</p>');
        }
    });
}

function renderCategoriesUI() {
    $.ajax({
        url: 'background.php?getModuleCategories=1',
        dataType: 'json',
        success: function(data) {
            // Extract just the categories arrays from module data
            var rawModules = data.modules || {};
            moduleCategories = {};
            Object.keys(rawModules).forEach(function(moddir) {
                if (typeof rawModules[moddir] === 'object' && rawModules[moddir].categories) {
                    moduleCategories[moddir] = rawModules[moddir].categories;
                } else if (Array.isArray(rawModules[moddir])) {
                    moduleCategories[moddir] = rawModules[moddir];
                } else {
                    moduleCategories[moddir] = [];
                }
            });
            originalCategories = JSON.parse(JSON.stringify(moduleCategories));
            
            var html = '';
            var sortedModules = Object.keys(moduleCategories).sort();
            
            // Build category buttons row
            var catOptions = '';
            Object.keys(masterCategories).sort().forEach(function(catId) {
                catOptions += '<span class="category-badge" data-category="' + catId + '" style="display:inline-block; padding:3px 8px; margin:2px; border-radius:12px; font-size:12px; cursor:pointer; border:1px solid #e5e7eb; background:#f1f5f9;">' + masterCategories[catId] + '</span>';
            });
            
            sortedModules.forEach(function(moddir) {
                var cats = moduleCategories[moddir] || [];
                var catDisplay = cats.map(function(c) {
                    return '<span style="display:inline-block; padding:2px 8px; margin:2px; border-radius:12px; font-size:11px; background:#dbeafe; color:#1e40af;">' + (masterCategories[c] || c) + '</span>';
                }).join('');
                
                if (!catDisplay) catDisplay = '<span style="color:#94a3b8; font-size:12px;">No categories</span>';
                
                html += '<div class="content-item category-item" data-moddir="' + moddir + '" style="display:flex; flex-direction:column; padding:12px; margin-bottom:8px; background:white; border:1px solid #e5e7eb; border-radius:8px;">';
                html += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">';
                html += '<div class="name" style="font-weight:500;">' + moddir + '</div>';
                html += '<button class="btn-scan" onclick="toggleCategoryEditor(\'' + moddir + '\');" style="padding:4px 10px; font-size:12px;">Edit</button>';
                html += '</div>';
                html += '<div class="category-display" id="catdisplay_' + moddir.replace(/[^a-zA-Z0-9]/g, '_') + '">' + catDisplay + '</div>';
                html += '<div class="category-editor" id="cateditor_' + moddir.replace(/[^a-zA-Z0-9]/g, '_') + '" style="display:none; margin-top:10px; padding:10px; background:#f8fafc; border-radius:6px;">';
                
                // Category checkboxes
                Object.keys(masterCategories).sort().forEach(function(catId) {
                    var checked = cats.indexOf(catId) !== -1 ? ' checked' : '';
                    html += '<label style="display:inline-block; margin:4px 8px; cursor:pointer;">';
                    html += '<input type="checkbox" class="cat-checkbox" data-moddir="' + moddir + '" data-category="' + catId + '"' + checked + ' onchange="updateModuleCategory(\'' + moddir + '\');">';
                    html += ' ' + masterCategories[catId];
                    html += '</label>';
                });
                
                html += '</div>';
                html += '</div>';
            });
            
            $('#categoriesContainer').html(html);
            
            var withCats = sortedModules.filter(function(m) { return moduleCategories[m] && moduleCategories[m].length > 0; }).length;
            $('#categoryStats').text(withCats + ' of ' + sortedModules.length + ' modules have categories');
        },
        error: function() {
            $('#categoriesContainer').html('<p style="color:#dc2626; text-align:center;">Failed to load module categories</p>');
        }
    });
}

function toggleCategoryEditor(moddir) {
    var safeId = moddir.replace(/[^a-zA-Z0-9]/g, '_');
    $('#cateditor_' + safeId).slideToggle(200);
}

function updateModuleCategory(moddir) {
    var cats = [];
    $('.cat-checkbox[data-moddir="' + moddir + '"]:checked').each(function() {
        cats.push($(this).data('category'));
    });
    moduleCategories[moddir] = cats;
    
    // Update display
    var safeId = moddir.replace(/[^a-zA-Z0-9]/g, '_');
    var catDisplay = cats.map(function(c) {
        return '<span style="display:inline-block; padding:2px 8px; margin:2px; border-radius:12px; font-size:11px; background:#dbeafe; color:#1e40af;">' + (masterCategories[c] || c) + '</span>';
    }).join('');
    if (!catDisplay) catDisplay = '<span style="color:#94a3b8; font-size:12px;">No categories</span>';
    $('#catdisplay_' + safeId).html(catDisplay);
    
    // Check if anything changed
    categoriesChanged = JSON.stringify(moduleCategories) !== JSON.stringify(originalCategories);
    $('#saveCategoriesBtn').prop('disabled', !categoriesChanged);
    if (categoriesChanged) {
        $('#saveCategoriesBtn').css('background', '#3b82f6');
    }
    
    // Update stats
    var withCats = Object.keys(moduleCategories).filter(function(m) { return moduleCategories[m] && moduleCategories[m].length > 0; }).length;
    $('#categoryStats').text(withCats + ' of ' + Object.keys(moduleCategories).length + ' modules have categories');
}

function saveModuleCategories() {
    if (!categoriesChanged) return;
    
    $('#saveCategoriesBtn').prop('disabled', true).html('<span class="spinner"></span> Saving...');
    
    // Find modules that changed
    var changes = [];
    Object.keys(moduleCategories).forEach(function(moddir) {
        var oldCats = (originalCategories[moddir] || []).sort().join(',');
        var newCats = (moduleCategories[moddir] || []).sort().join(',');
        if (oldCats !== newCats) {
            changes.push({ moddir: moddir, categories: moduleCategories[moddir] });
        }
    });
    
    // Save each change
    var saved = 0;
    var errors = 0;
    
    function saveNext() {
        if (changes.length === 0) {
            if (errors === 0) {
                originalCategories = JSON.parse(JSON.stringify(moduleCategories));
                categoriesChanged = false;
                $('#saveCategoriesBtn').html('Save Categories').prop('disabled', true);
                showStatus('Categories saved successfully', false);
            } else {
                $('#saveCategoriesBtn').html('Save Categories').prop('disabled', false);
                showStatus(errors + ' categories failed to save', true);
            }
            return;
        }
        
        var change = changes.shift();
        $.ajax({
            url: 'background.php?saveModuleCategory',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(change),
            success: function() {
                saved++;
                saveNext();
            },
            error: function() {
                errors++;
                saveNext();
            }
        });
    }
    
    saveNext();
}

function filterCategoryModules() {
    var search = $('#categoryModuleSearch').val().toLowerCase();
    $('.category-item').each(function() {
        var moddir = $(this).data('moddir').toLowerCase();
        if (!search || moddir.indexOf(search) !== -1) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
}

function deleteModule(moddir, title) {
    if (!confirm('Are you sure you want to permanently delete "' + title + '"?\n\nThis will remove all content in the ' + moddir + ' folder.')) {
        return;
    }
    
    var item = $('#delitem_' + moddir);
    item.css('opacity', '0.5');
    item.find('button').prop('disabled', true).html('<span class="spinner"></span> Deleting...');
    
    $.ajax({
        url: 'background.php?deleteModule=' + moddir,
        success: function(results) {
            item.slideUp(300, function() { $(this).remove(); });
            showStatus('Module "' + title + '" deleted successfully', false);
            
            // Remove from installed versions cache
            delete installedModuleVersions[moddir];
            
            // Check if any modules left
            setTimeout(function() {
                if ($('#deleteModulesContainer .content-item').length === 0) {
                    $('#deleteModulesContainer').html('<p style="color:#64748b; text-align:center;">No modules installed</p>');
                }
            }, 350);
        },
        error: function(xhr) {
            item.css('opacity', '1');
            item.find('button').prop('disabled', false).html('🗑️ Delete');
            var err = xhr.responseJSON ? xhr.responseJSON.error : 'Failed to delete module';
            showStatus(err, true);
        }
    });
}

// ==================== REARRANGE MODULES TAB ====================

var sortableInitialized = false;
var orderChanged = false;

function initSortable() {
    if (sortableInitialized) return;
    
    $('#sortableModules').sortable({
        handle: '.drag-handle',
        placeholder: 'sortable-module ui-state-highlight',
        update: function(event, ui) {
            orderChanged = true;
            $('#saveOrderBtn').prop('disabled', false).css('background', '#3b82f6');
            $('#rearrangeStatus').text('Unsaved changes').css('color', '#f59e0b');
        }
    });
    sortableInitialized = true;
}

function saveModuleOrder() {
    if (!orderChanged) return;
    
    var moddirs = [];
    var hidden = [];
    
    $('#sortableModules .sortable-module').each(function() {
        var moddir = $(this).data('moddir');
        moddirs.push(moddir);
        if ($(this).data('hidden') === 'hidden') {
            hidden.push(moddir);
        }
    });
    
    var url = 'modules.php?moddirs=' + moddirs.join(',');
    if (hidden.length > 0) {
        url += '&hidden=' + hidden.join(',');
    }
    
    $('#saveOrderBtn').prop('disabled', true).html('<span class="spinner"></span> Saving...');
    
    $.ajax({
        url: url,
        success: function() {
            orderChanged = false;
            $('#saveOrderBtn').html('✓ Saved').css('background', '#10b981');
            $('#rearrangeStatus').text('Order saved successfully').css('color', '#10b981');
            setTimeout(function() {
                $('#saveOrderBtn').html('💾 Save Order').css('background', '#3b82f6').prop('disabled', true);
                $('#rearrangeStatus').text('');
            }, 2000);
        },
        error: function() {
            $('#saveOrderBtn').html('💾 Save Order').prop('disabled', false);
            $('#rearrangeStatus').text('Failed to save').css('color', '#dc2626');
            showStatus('Failed to save module order', true);
        }
    });
}

function resetToDefaultOrder() {
    if (!confirm('Reset all modules to their default order? This will undo any custom sorting.')) return;
    
    $('#resetDefaultBtn').prop('disabled', true).html('<span class="spinner"></span> Resetting...');
    
    $.ajax({
        url: 'modules.php?resetdefault=1',
        success: function() {
            showStatus('Modules reset to default order', false);
            location.reload();
        },
        error: function() {
            $('#resetDefaultBtn').prop('disabled', false).html('🔄 Reset to Default');
            showStatus('Failed to reset order', true);
        }
    });
}

function saveAsDefaultOrder() {
    if (!confirm('Save the current module order as the new default?')) return;
    
    $('#saveAsDefaultBtn').prop('disabled', true).html('<span class="spinner"></span> Saving...');
    
    $.ajax({
        url: 'modules.php?backupdefault=1',
        success: function() {
            $('#saveAsDefaultBtn').html('✓ Saved').css('background', '#10b981');
            showStatus('Current order saved as default', false);
            setTimeout(function() {
                $('#saveAsDefaultBtn').html('💾 Save as Default').css('background', '#64748b').prop('disabled', false);
            }, 2000);
        },
        error: function() {
            $('#saveAsDefaultBtn').prop('disabled', false).html('💾 Save as Default');
            showStatus('Failed to save default', true);
        }
    });
}

// ==================== VISIBILITY TAB ====================

var visibilityChanged = false;

function markVisibilityChanged() {
    visibilityChanged = true;
    $('#saveVisibilityBtn').prop('disabled', false).css('background', '#3b82f6');
}

function setAllVisibility(state) {
    $('.visibility-select').val(state);
    markVisibilityChanged();
}

function saveModuleVisibility() {
    if (!visibilityChanged) return;
    
    var moddirs = [];
    var hidden = [];
    var blocked = [];
    
    $('.visibility-item').each(function() {
        var moddir = $(this).data('moddir');
        var state = $(this).find('.visibility-select').val();
        moddirs.push(moddir);
        
        if (state === 'homepage' || state === 'blocked') {
            hidden.push(moddir);
        }
        if (state === 'blocked') {
            blocked.push(moddir);
        }
    });
    
    var url = 'background.php?updateVisibility=1';
    url += '&moddirs=' + moddirs.join(',');
    url += '&hidden=' + hidden.join(',');
    url += '&blocked=' + blocked.join(',');
    
    $('#saveVisibilityBtn').prop('disabled', true).html('<span class="spinner"></span> Saving...');
    
    $.ajax({
        url: url,
        success: function() {
            visibilityChanged = false;
            $('#saveVisibilityBtn').html('✓ Saved').css('background', '#10b981');
            showStatus('Visibility settings saved', false);
            setTimeout(function() {
                $('#saveVisibilityBtn').html('💾 Save Visibility').css('background', '#3b82f6').prop('disabled', true);
            }, 2000);
        },
        error: function() {
            $('#saveVisibilityBtn').html('Save Visibility').prop('disabled', false);
            showStatus('Failed to save visibility settings', true);
        }
    });
}

// ============================================
// Quiz System Functions
// ============================================

var currentQuiz = null;
var questionCounter = 0;

function loadQuizzes() {
    $('#quizList').html('<p>Loading quizzes...</p>');
    $.ajax({
        url: 'background.php?getQuizzes=1',
        dataType: 'json',
        success: function(quizzes) {
            if (!quizzes || quizzes.length === 0) {
                $('#quizList').html('<p style="color:#64748b;">No quizzes yet. Click "New Quiz" to create one.</p>');
                return;
            }
            var html = '';
            quizzes.forEach(function(quiz) {
                var status = quiz.is_active ? '<span style="color:#10b981;">Active</span>' : '<span style="color:#94a3b8;">Inactive</span>';
                html += '<div class="content-item" style="margin-bottom:10px;">';
                html += '<div class="info">';
                html += '<div class="name">' + escapeHtml(quiz.title) + '</div>';
                html += '<div class="meta">' + quiz.question_count + ' questions | ' + quiz.submission_count + ' submissions | ' + status + '</div>';
                html += '</div>';
                html += '<div class="actions">';
                html += '<button class="btn-install" onclick="editQuiz(' + quiz.quiz_id + ');" style="margin-right:5px;">Edit</button>';
                html += '<button class="btn-eject" onclick="deleteQuiz(' + quiz.quiz_id + ', \'' + escapeHtml(quiz.title).replace(/'/g, "\\'") + '\');">Delete</button>';
                html += '</div></div>';
            });
            $('#quizList').html(html);
        },
        error: function() {
            $('#quizList').html('<p style="color:#dc2626;">Failed to load quizzes</p>');
        }
    });
}

function createNewQuiz() {
    currentQuiz = null;
    questionCounter = 0;
    $('#quizId').val('');
    $('#quizTitle').val('');
    $('#quizDescription').val('');
    $('#quizTimeLimit').val('0');
    $('#quizPassword').val('');
    $('#quizActive').prop('checked', true);
    $('#quizShowResults').prop('checked', true);
    $('#questionsContainer').html('');
    $('#quizListView').hide();
    $('#quizEditorView').show();
}

function editQuiz(quizId) {
    $.ajax({
        url: 'background.php?getQuiz=' + quizId,
        dataType: 'json',
        success: function(quiz) {
            currentQuiz = quiz;
            questionCounter = 0;
            $('#quizId').val(quiz.quiz_id);
            $('#quizTitle').val(quiz.title);
            $('#quizDescription').val(quiz.description || '');
            $('#quizTimeLimit').val(quiz.time_limit || 0);
            $('#quizPassword').val(quiz.password || '');
            $('#quizActive').prop('checked', quiz.is_active == 1);
            $('#quizShowResults').prop('checked', quiz.show_results == 1);
            
            $('#questionsContainer').html('');
            if (quiz.questions) {
                quiz.questions.forEach(function(q) {
                    addQuestion(q.question_type, q);
                });
            }
            
            $('#quizListView').hide();
            $('#quizEditorView').show();
        },
        error: function() {
            showStatus('Failed to load quiz', true);
        }
    });
}

function backToQuizList() {
    $('#quizEditorView').hide();
    $('#quizListView').show();
    loadQuizzes();
}

function addQuestion(type, existingData) {
    questionCounter++;
    var qid = 'q_' + questionCounter;
    var data = existingData || {};
    
    var html = '<div class="question-item" id="' + qid + '" data-type="' + type + '" style="background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:15px; margin-bottom:15px;">';
    html += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">';
    html += '<strong>' + getQuestionTypeLabel(type) + '</strong>';
    html += '<button type="button" onclick="removeQuestion(\'' + qid + '\');" style="background:#ef4444; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;">Remove</button>';
    html += '</div>';
    
    html += '<div style="margin-bottom:10px;">';
    html += '<label style="display:block; margin-bottom:5px;">Question Text</label>';
    html += '<textarea class="question-text" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:4px; min-height:60px;">' + escapeHtml(data.question_text || '') + '</textarea>';
    html += '</div>';
    
    // Media attachment section
    html += '<div style="margin-bottom:10px; padding:10px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px;">';
    html += '<label style="display:block; margin-bottom:5px; font-weight:500; color:#0369a1;">📎 Attach Media (optional)</label>';
    html += '<div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">';
    html += '<select class="media-type" style="padding:8px; border:1px solid #cbd5e1; border-radius:4px;">';
    html += '<option value="">No media</option>';
    html += '<option value="image"' + (data.media_type === 'image' ? ' selected' : '') + '>Image</option>';
    html += '<option value="video"' + (data.media_type === 'video' ? ' selected' : '') + '>Video</option>';
    html += '<option value="audio"' + (data.media_type === 'audio' ? ' selected' : '') + '>Audio</option>';
    html += '<option value="pdf"' + (data.media_type === 'pdf' ? ' selected' : '') + '>PDF Document</option>';
    html += '<option value="file"' + (data.media_type === 'file' ? ' selected' : '') + '>Other File</option>';
    html += '</select>';
    html += '<input type="text" class="media-url" id="media-url-' + qid + '" value="' + escapeHtml(data.media_url || '') + '" placeholder="URL or path to media file" style="flex:1; min-width:200px; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">';
    html += '</div>';
    html += '<div style="margin-top:8px; display:flex; gap:10px; align-items:center;">';
    html += '<input type="file" class="media-file-input" id="media-file-' + qid + '" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.ppt,.pptx" style="display:none;" onchange="uploadQuizMedia(\'' + qid + '\', this);">';
    html += '<button type="button" class="btn-scan" onclick="document.getElementById(\'media-file-' + qid + '\').click();" style="padding:6px 12px; font-size:0.9em;">📤 Upload File</button>';
    html += '<span class="upload-status" id="upload-status-' + qid + '" style="font-size:0.85em; color:#64748b;"></span>';
    html += '</div>';
    html += '<div style="font-size:0.85em; color:#64748b; margin-top:5px;">Upload a file or enter a path to existing content (e.g., /modules/en-my-module/video.mp4)</div>';
    html += '</div>';
    
    html += '<div style="display:flex; gap:15px; margin-bottom:10px;">';
    html += '<div><label>Points: </label><input type="number" class="question-points" value="' + (data.points || 1) + '" min="1" style="width:60px; padding:5px; border:1px solid #cbd5e1; border-radius:4px;"></div>';
    html += '</div>';
    
    if (type === 'multiple_choice') {
        html += '<div class="options-container">';
        html += '<label style="display:block; margin-bottom:5px;">Answer Options (check the correct answer)</label>';
        var options = data.options || ['', '', '', ''];
        var correctAnswer = data.correct_answer || '';
        for (var i = 0; i < 4; i++) {
            var checked = (options[i] && options[i] === correctAnswer) ? ' checked' : '';
            html += '<div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;">';
            html += '<input type="radio" name="correct_' + qid + '" class="correct-option"' + checked + '>';
            html += '<input type="text" class="option-input" value="' + escapeHtml(options[i] || '') + '" placeholder="Option ' + (i+1) + '" style="flex:1; padding:8px; border:1px solid #cbd5e1; border-radius:4px;">';
            html += '</div>';
        }
        html += '</div>';
    } else if (type === 'short_answer') {
        html += '<div style="color:#64748b; font-size:0.9em;">Students will provide a text response. You will grade this manually.</div>';
    } else if (type === 'file_upload') {
        html += '<div style="color:#64748b; font-size:0.9em;">Students will upload a file. You will grade this manually.</div>';
    }
    
    html += '</div>';
    
    $('#questionsContainer').append(html);
}

function getQuestionTypeLabel(type) {
    switch(type) {
        case 'multiple_choice': return 'Multiple Choice';
        case 'short_answer': return 'Short Answer';
        case 'file_upload': return 'File Upload';
        default: return type;
    }
}

function removeQuestion(qid) {
    $('#' + qid).remove();
}

function uploadQuizMedia(qid, input) {
    if (!input.files || !input.files[0]) return;
    
    var file = input.files[0];
    var maxSize = 100 * 1024 * 1024; // 100MB limit
    
    if (file.size > maxSize) {
        showStatus('File too large. Maximum size is 100MB.', true);
        return;
    }
    
    var $status = $('#upload-status-' + qid);
    var $urlInput = $('#media-url-' + qid);
    var $mediaType = $('#' + qid).find('.media-type');
    
    $status.html('<span class="spinner"></span> Uploading...');
    
    var formData = new FormData();
    formData.append('quizMedia', file);
    
    $.ajax({
        url: 'storage.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(result) {
            $urlInput.val(result.url);
            $status.html('<span style="color:#10b981;">✓ Uploaded: ' + result.filename + '</span>');
            
            // Auto-detect media type from extension
            var ext = result.filename.split('.').pop().toLowerCase();
            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(ext) !== -1) {
                $mediaType.val('image');
            } else if (['mp4', 'webm'].indexOf(ext) !== -1) {
                $mediaType.val('video');
            } else if (['mp3', 'wav', 'ogg'].indexOf(ext) !== -1) {
                $mediaType.val('audio');
            } else if (ext === 'pdf') {
                $mediaType.val('pdf');
            } else {
                $mediaType.val('file');
            }
        },
        error: function(xhr) {
            var err = xhr.responseJSON ? xhr.responseJSON.error : 'Upload failed';
            $status.html('<span style="color:#dc2626;">✗ ' + err + '</span>');
        }
    });
}

function saveCurrentQuiz() {
    var quiz = {
        quiz_id: $('#quizId').val() || null,
        title: $('#quizTitle').val(),
        description: $('#quizDescription').val(),
        time_limit: parseInt($('#quizTimeLimit').val()) || 0,
        password: $('#quizPassword').val() || '',
        is_active: $('#quizActive').is(':checked') ? 1 : 0,
        show_results: $('#quizShowResults').is(':checked') ? 1 : 0,
        questions: []
    };
    
    if (!quiz.title) {
        showStatus('Please enter a quiz title', true);
        return;
    }
    
    $('.question-item').each(function() {
        var $q = $(this);
        var type = $q.data('type');
        var question = {
            question_type: type,
            question_text: $q.find('.question-text').val(),
            points: parseInt($q.find('.question-points').val()) || 1,
            media_type: $q.find('.media-type').val() || '',
            media_url: $q.find('.media-url').val() || ''
        };
        
        if (type === 'multiple_choice') {
            question.options = [];
            question.correct_answer = '';
            $q.find('.option-input').each(function(i) {
                var optVal = $(this).val();
                question.options.push(optVal);
                if ($q.find('.correct-option').eq(i).is(':checked')) {
                    question.correct_answer = optVal;
                }
            });
        }
        
        quiz.questions.push(question);
    });
    
    $.ajax({
        url: 'background.php?saveQuiz=1',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(quiz),
        success: function(response) {
            showStatus('Quiz saved successfully', false);
            backToQuizList();
        },
        error: function() {
            showStatus('Failed to save quiz', true);
        }
    });
}

function deleteQuiz(quizId, title) {
    if (!confirm('Delete quiz "' + title + '"? This will also delete all submissions.')) return;
    
    $.ajax({
        url: 'background.php?deleteQuiz=' + quizId,
        success: function() {
            showStatus('Quiz deleted', false);
            loadQuizzes();
        },
        error: function() {
            showStatus('Failed to delete quiz', true);
        }
    });
}

// Results functions
function loadQuizzesForResults() {
    $.ajax({
        url: 'background.php?getQuizzes=1',
        dataType: 'json',
        success: function(quizzes) {
            var html = '<option value="">-- Select a quiz --</option>';
            if (quizzes && quizzes.length > 0) {
                quizzes.forEach(function(quiz) {
                    html += '<option value="' + quiz.quiz_id + '">' + escapeHtml(quiz.title) + ' (' + quiz.submission_count + ' submissions)</option>';
                });
            }
            $('#resultsQuizDropdown').html(html);
        }
    });
}

function loadSubmissions() {
    var quizId = $('#resultsQuizDropdown').val();
    if (!quizId) {
        $('#submissionsList').html('');
        return;
    }
    
    $('#submissionsList').html('<p>Loading submissions...</p>');
    $.ajax({
        url: 'background.php?getQuizSubmissions=' + quizId,
        dataType: 'json',
        success: function(submissions) {
            if (!submissions || submissions.length === 0) {
                $('#submissionsList').html('<p style="color:#64748b;">No submissions yet.</p>');
                return;
            }
            
            var html = '<table style="width:100%; border-collapse:collapse;">';
            html += '<tr style="background:#f1f5f9;"><th style="padding:10px; text-align:left; border-bottom:2px solid #e5e7eb;">Student</th><th style="padding:10px; text-align:left; border-bottom:2px solid #e5e7eb;">Submitted</th><th style="padding:10px; text-align:left; border-bottom:2px solid #e5e7eb;">Score</th><th style="padding:10px; text-align:left; border-bottom:2px solid #e5e7eb;">Status</th><th style="padding:10px; border-bottom:2px solid #e5e7eb;">Actions</th></tr>';
            
            submissions.forEach(function(sub) {
                var date = new Date(sub.submitted_at * 1000).toLocaleString();
                var score = sub.graded ? (sub.score + '/' + sub.max_score) : '-';
                var status = sub.graded ? '<span style="color:#10b981;">Graded</span>' : '<span style="color:#f59e0b;">Pending</span>';
                
                html += '<tr style="border-bottom:1px solid #e5e7eb;">';
                html += '<td style="padding:10px;">' + escapeHtml(sub.student_name) + (sub.secret_key ? ' <span style="color:#6366f1; font-style:italic;">[' + escapeHtml(sub.secret_key) + ']</span>' : '') + '</td>';
                html += '<td style="padding:10px;">' + date + '</td>';
                html += '<td style="padding:10px;">' + score + '</td>';
                html += '<td style="padding:10px;">' + status + '</td>';
                html += '<td style="padding:10px;">';
                html += '<button class="btn-install" onclick="viewSubmission(' + sub.submission_id + ');" style="margin-right:5px;">View/Grade</button>';
                html += '<button class="btn-eject" onclick="deleteSubmission(' + sub.submission_id + ');">Delete</button>';
                html += '</td></tr>';
            });
            html += '</table>';
            
            $('#submissionsList').html(html);
        },
        error: function() {
            $('#submissionsList').html('<p style="color:#dc2626;">Failed to load submissions</p>');
        }
    });
}

var currentSubmission = null;

function viewSubmission(submissionId) {
    $.ajax({
        url: 'background.php?getSubmissionDetails=' + submissionId,
        dataType: 'json',
        success: function(sub) {
            currentSubmission = sub;
            
            var html = '<div class="form-section" style="background:white; border:1px solid #e5e7eb; border-radius:8px; padding:20px; margin-bottom:20px;">';
            html += '<h3 style="margin-top:0;">' + escapeHtml(sub.quiz_title) + '</h3>';
            html += '<p><strong>Student:</strong> ' + escapeHtml(sub.student_name) + '</p>';
            if (sub.secret_key) {
                html += '<p><strong>Secret Key:</strong> <span style="color:#6366f1;">' + escapeHtml(sub.secret_key) + '</span></p>';
            }
            html += '<p><strong>Submitted:</strong> ' + new Date(sub.submitted_at * 1000).toLocaleString() + '</p>';
            if (sub.graded) {
                html += '<p><strong>Score:</strong> ' + sub.score + '/' + sub.max_score + ' (' + Math.round((sub.score/sub.max_score)*100) + '%)</p>';
            }
            html += '</div>';
            
            if (sub.answers && sub.answers.length > 0) {
                sub.answers.forEach(function(ans, i) {
                    html += '<div class="form-section answer-grade" data-answer-id="' + ans.answer_id + '" style="background:white; border:1px solid #e5e7eb; border-radius:8px; padding:20px; margin-bottom:15px;">';
                    html += '<h4 style="margin-top:0;">Q' + (i+1) + ': ' + escapeHtml(ans.question_text) + ' <span style="color:#64748b; font-weight:normal;">(' + ans.points + ' pts)</span></h4>';
                    html += '<p><strong>Type:</strong> ' + getQuestionTypeLabel(ans.question_type) + '</p>';
                    
                    if (ans.question_type === 'multiple_choice' && ans.options) {
                        html += '<p><strong>Options:</strong></p><ul>';
                        ans.options.forEach(function(opt) {
                            var marker = (opt === ans.correct_answer) ? ' [Correct]' : '';
                            var style = (opt === ans.answer_text) ? 'font-weight:bold;' : '';
                            if (opt === ans.answer_text && opt === ans.correct_answer) style += 'color:#10b981;';
                            else if (opt === ans.answer_text && opt !== ans.correct_answer) style += 'color:#dc2626;';
                            html += '<li style="' + style + '">' + escapeHtml(opt) + marker + (opt === ans.answer_text ? ' [Student Answer]' : '') + '</li>';
                        });
                        html += '</ul>';
                    } else {
                        html += '<p><strong>Student Answer:</strong></p>';
                        html += '<div style="background:#f8fafc; padding:10px; border-radius:4px; margin-bottom:10px;">' + (ans.answer_text ? escapeHtml(ans.answer_text) : '<em>No answer</em>') + '</div>';
                    }
                    
                    if (ans.file_path) {
                        html += '<p><strong>Uploaded File:</strong> <a href="' + ans.file_path + '" target="_blank">Download</a></p>';
                    }
                    
                    html += '<div style="display:flex; gap:15px; align-items:center; margin-top:10px; padding-top:10px; border-top:1px solid #e5e7eb;">';
                    html += '<label>Points Awarded: <input type="number" class="grade-points" value="' + (ans.points_awarded || 0) + '" min="0" max="' + ans.points + '" style="width:60px; padding:5px; border:1px solid #cbd5e1; border-radius:4px;"> / ' + ans.points + '</label>';
                    html += '<label style="flex:1;">Feedback: <input type="text" class="grade-feedback" value="' + escapeHtml(ans.feedback || '') + '" style="width:100%; padding:5px; border:1px solid #cbd5e1; border-radius:4px;"></label>';
                    html += '</div>';
                    html += '</div>';
                });
            }
            
            $('#submissionDetails').html(html);
            $('#submissionsListView').hide();
            $('#submissionDetailView').show();
        },
        error: function() {
            showStatus('Failed to load submission details', true);
        }
    });
}

function backToSubmissions() {
    $('#submissionDetailView').hide();
    $('#submissionsListView').show();
}

function saveGrades() {
    if (!currentSubmission) return;
    
    var grades = {};
    $('.answer-grade').each(function() {
        var answerId = $(this).data('answer-id');
        grades[answerId] = {
            points: parseFloat($(this).find('.grade-points').val()) || 0,
            feedback: $(this).find('.grade-feedback').val()
        };
    });
    
    $.ajax({
        url: 'background.php?gradeSubmission=1',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            submission_id: currentSubmission.submission_id,
            grades: grades
        }),
        success: function(response) {
            showStatus('Grades saved! Score: ' + response.score + '/' + response.max_score, false);
            backToSubmissions();
            loadSubmissions();
        },
        error: function() {
            showStatus('Failed to save grades', true);
        }
    });
}

function deleteSubmission(submissionId) {
    if (!confirm('Delete this submission? This cannot be undone.')) return;
    
    $.ajax({
        url: 'background.php?deleteSubmission=' + submissionId,
        success: function() {
            showStatus('Submission deleted', false);
            loadSubmissions();
        },
        error: function() {
            showStatus('Failed to delete submission', true);
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include "foot.php"; ?>
