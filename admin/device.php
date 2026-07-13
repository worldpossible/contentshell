<?php
#-------------------------------------------
# Device Settings - Combined Settings and Hardware page
#-------------------------------------------
require_once("common.php");
if (!authorized()) { exit(); }

$page_title = "Device Settings";
$page_script = "js/hardware.js";
$page_nav = "device";
include "head.php";

#-------------------------------------------
# Handle shutdown/reboot requests
#-------------------------------------------
if (isset($_POST['shutdown'])) {
    exec("sudo sh -c 'sleep 3; /sbin/poweroff;' > /dev/null 2>&1 &", $exec_out, $exec_err);
    if ($exec_err) {
        echo $lang['shutdown_failed'];
    } else {
        echo $lang['shutdown_ok'];
    }
    include "foot.php";
    exit;
} else if (isset($_POST['reboot'])) {
    exec("sudo sh -c 'sleep 3; /sbin/reboot;' > /dev/null 2>&1 &", $exec_out, $exec_err);
    if ($exec_err) {
        echo $lang['restart_failed'];
    } else {
        echo $lang['restart_ok'];
    }
    include "foot.php";
    exit;
}

#-------------------------------------------
# Handle password change
#-------------------------------------------
$password_message = '';
$password_error = false;

# CLI password change support
if (isset($argv) && isset($argv[1]) && isset($argv[2])) {
    $old = $argv[1];
    $new = $argv[2];
    if (!check_password($old)) {
        echo $lang['wrong_old_pass'] . "\n";
        exit(1);
    }
    if ($new == "") {
        echo $lang['missing_new_pass'] . "\n";
        exit(1);
    }
    set_password($new);
    echo $lang['change_pass_success'] . "\n";
    exit(0);
}

# Web form password change
if (isset($_POST['oldpass'])) {
    $old  = $_POST['oldpass'];
    $new  = $_POST['newpass'];
    $new2 = $_POST['newpass2'];
    if (!check_password($old)) {
        $password_message = $lang['wrong_old_pass'];
        $password_error = true;
    } else if ($new == "") {
        $password_message = $lang['missing_new_pass'];
        $password_error = true;
    } else if ($new != $new2) {
        $password_message = $lang['password_mismatch'];
        $password_error = true;
    } else {
        set_password($new);
        $password_message = $lang['change_pass_success'];
        $password_error = false;
    }
}

#-------------------------------------------
# Helper functions for storage display
#-------------------------------------------
function convertToBytes($size) {
    $size = trim($size);
    $unit = strtoupper(substr($size, -1));
    $value = floatval(substr($size, 0, -1));
    
    switch ($unit) {
        case 'T': return $value * 1024 * 1024 * 1024 * 1024;
        case 'G': return $value * 1024 * 1024 * 1024;
        case 'M': return $value * 1024 * 1024;
        case 'K': return $value * 1024;
        default: return $value;
    }
}

function formatBytes($bytes) {
    if ($bytes >= 1024 * 1024 * 1024 * 1024) {
        return round($bytes / (1024 * 1024 * 1024 * 1024), 1) . 'T';
    } else if ($bytes >= 1024 * 1024 * 1024) {
        return round($bytes / (1024 * 1024 * 1024), 1) . 'G';
    } else if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . 'M';
    } else if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . 'K';
    }
    return $bytes . 'B';
}

function getRetailSize($bytes) {
    $gb = $bytes / (1000 * 1000 * 1000);
    if ($gb <= 128) return "128 GB";
    if ($gb <= 256) return "256 GB";
    if ($gb <= 500) return "500 GB";
    if ($gb <= 512) return "512 GB";
    if ($gb <= 1000) return "1 TB";
    if ($gb <= 2000) return "2 TB";
    if ($gb <= 4000) return "4 TB";
    if ($gb <= 8000) return "8 TB";
    return round($gb / 1000, 0) . " TB";
}

function getUptime() {
    exec("uptime -p", $output, $result);
    if ($result == 1 || !isset($output[0])) return null;
    
    $uptime = $output[0];
    $suggest_reboot = (strpos($uptime, 'day') !== false || 
                       strpos($uptime, 'week') !== false || 
                       strpos($uptime, 'month') !== false);
    
    return ['uptime' => $uptime, 'suggest_reboot' => $suggest_reboot];
}

function getBatteryInfo() {
    exec("ubus call battery info", $output, $result);
    if (!$output) return null;
    
    $ubus = json_decode(implode($output), true);
    if (!$ubus) return null;
    
    $level = $ubus['capacity'];
    $status = rtrim($ubus['status']);
    if ($status == "Unknown") $status = "Fully Charged";
    
    return ['status' => $status, 'level' => $level];
}

?>
<style>
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
.section-tabs button:hover { color: #334155; }
.section-tabs button.active {
    color: #2563eb;
    border-bottom-color: #2563eb;
}
.tab-content { display: none; }
.tab-content.active { display: block; }

.info-box { background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 6px; padding: 15px; margin-bottom: 20px; }
.info-box h3 { margin-top: 0; color: #0369a1; }
.warning-box { background: #fff7ed; border: 1px solid #f97316; border-radius: 6px; padding: 15px; margin-bottom: 15px; }
.warning-box h4 { margin-top: 0; color: #c2410c; }

.form-section {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}
.form-section h3 {
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #e5e7eb;
    color: #1e293b;
}
.form-row {
    margin-bottom: 15px;
}
.form-row label {
    display: block;
    font-size: 0.9em;
    color: #64748b;
    margin-bottom: 5px;
}
.form-row input[type="text"],
.form-row input[type="password"] {
    width: 100%;
    max-width: 300px;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 14px;
}
.btn-primary {
    padding: 10px 20px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
}
.btn-primary:hover { background: #2563eb; }
.btn-danger {
    padding: 10px 20px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
}
.btn-danger:hover { background: #dc2626; }

.message { padding: 10px 15px; border-radius: 6px; margin-bottom: 15px; }
.message.success { background: #dcfce7; color: #166534; border: 1px solid #22c55e; }
.message.error { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }

.stats-table { width: 100%; border-collapse: collapse; }
.stats-table th, .stats-table td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
.stats-table tr:hover { background: #f8fafc; }

.storage-card {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}
.storage-card h4 { margin: 0 0 10px 0; color: #1e293b; }

.shutdown-box {
    padding: 20px;
    border: 2px solid #ef4444;
    background: #fef2f2;
    border-radius: 8px;
    margin-top: 20px;
}
</style>

<div class="info-box">
    <h3>⚙️ Device Settings</h3>
    <p>Configure your RACHEL device settings, view hardware information, and manage system options.</p>
</div>

<div class="section-tabs">
    <button class="active" onclick="showTab('password')">🔐 Password</button>
    <button onclick="showTab('system')">📊 System Info</button>
    <button onclick="showTab('storage')">💾 Storage</button>
    <?php if (is_rachelplus()) { ?>
    <button onclick="showTab('wifi')">📶 WiFi</button>
    <?php } ?>
    <button onclick="showTab('power')">⚡ Power</button>
    <button onclick="showTab('sponsor')">🎗️ Sponsorship</button>
    <button onclick="showTab('services')">🔗 Other Services</button>
    <?php if (is_rachelplus()) { ?>
    <button onclick="showTab('advanced')">🔧 Advanced</button>
    <?php } ?>
</div>

<!-- Password Tab -->
<div id="tab-password" class="tab-content active">
    <div class="warning-box">
        <h4>⚠️ Important Note</h4>
        <p style="margin-bottom:0;">This password <strong>only controls access to this Admin section</strong>. Your RACHEL device may have other passwords for different services (SSH, WiFi, etc.) that are managed separately. Changing this password will not affect those other passwords.</p>
    </div>
    
    <div class="form-section">
        <h3>🔐 <?php echo $lang['change_password']; ?></h3>
        
        <?php if ($password_message) { ?>
        <div class="message <?php echo $password_error ? 'error' : 'success'; ?>">
            <?php echo $password_message; ?>
        </div>
        <?php } ?>
        
        <form action="device.php" method="post">
            <div class="form-row">
                <label><?php echo $lang['old_password']; ?></label>
                <input type="password" name="oldpass" required>
            </div>
            <div class="form-row">
                <label><?php echo $lang['new_password']; ?></label>
                <input type="password" name="newpass" required>
            </div>
            <div class="form-row">
                <label><?php echo $lang['new_password2']; ?></label>
                <input type="password" name="newpass2" required>
            </div>
            <button type="submit" class="btn-primary"><?php echo $lang['update']; ?></button>
        </form>
    </div>
</div>

<!-- System Info Tab -->
<div id="tab-system" class="tab-content">
    <?php
    $uptime = getUptime();
    $bat_info = getBatteryInfo();
    
    # Device ID
    if (file_exists("/root/rachel-scripts/esp-checker.php")) {
        $interface = is_rachelplusv3() ? "enp2s0" : "eth0";
        $id = strtoupper(exec("ifconfig | grep $interface | awk '{ print $5 }' | sed s/://g | grep -o '.\\{6\\}$'"));
        echo "<div class=\"form-section\"><h3>🆔 Device ID: $id</h3></div>";
    }
    ?>
    
    <div class="form-section">
        <h3>📊 System Status</h3>
        <table class="stats-table">
            <?php if ($uptime) { ?>
            <tr><td style="width:200px;">System Uptime</td><td><strong><?php echo $uptime['uptime']; ?></strong></td></tr>
            <?php } ?>
            <?php if ($bat_info) { ?>
            <tr><td>Battery Status</td><td><strong><?php echo $bat_info['status']; ?></strong></td></tr>
            <tr><td>Battery Level</td><td><strong><?php echo $bat_info['level']; ?>%</strong></td></tr>
            <?php } ?>
            <tr><td>Server Address</td><td><strong><?php echo $_SERVER['SERVER_ADDR'] ?? 'Unknown'; ?></strong></td></tr>
        </table>
    </div>
    
</div>

<!-- Storage Tab -->
<div id="tab-storage" class="tab-content">
    <div class="form-section">
        <h3>💾 <?php echo $lang['storage_usage']; ?></h3>
        
        <?php
        exec("df -h", $output, $rval);
        $usage_rows = array();
        $usage_supported = false;
        
        if (is_rachelpi()) {
            $usage_supported = true;
            foreach ($output as $line) {
                list($fs, $size, $used, $avail, $perc, $name) = preg_split("/\s+/", $line);
                if (!preg_match("/^\/dev/", $fs)) { continue; }
                if ($name == "/") { $name = "SD Card (RACHEL modules)"; }
                array_push($usage_rows, array(
                    "name" => $name, "size" => $size, "used" => $used,
                    "avail" => $avail, "perc" => $perc,
                ));
            }
        } else if (is_rachelplus()) {
            $usage_supported = true;
            if (file_exists("/.data/RACHEL")) {
                $partitions = array("/.data" => "RACHEL Content (SSD)");
            } else {
                $partitions = array(
                    "/media/preloaded" => "Admin (preloaded)",
                    "/media/uploaded"  => "Teacher (uploaded)",
                    "/media/RACHEL"    => "RACHEL (RACHEL modules)"
                );
            }
            foreach ($output as $line) {
                list($fs, $size, $used, $avail, $perc, $name) = preg_split("/\s+/", $line);
                if (!isset($partitions[$name])) { continue; }
                $name = $partitions[$name];
                array_push($usage_rows, array(
                    "name" => $name, "size" => $size, "used" => $used,
                    "avail" => $avail, "perc" => $perc,
                ));
            }
        }
        
        if ($usage_supported && !empty($usage_rows)) {
            foreach ($usage_rows as $row) {
                $size_bytes = convertToBytes($row['size']);
                $used_bytes = convertToBytes($row['used']);
                $avail_bytes = convertToBytes($row['avail']);
                $reserved_bytes = $size_bytes - ($used_bytes + $avail_bytes);
                $reserved = formatBytes($reserved_bytes);
                $retail_size = getRetailSize($size_bytes);
                ?>
                <div class="storage-card">
                    <h4><?php echo $row['name']; ?></h4>
                    <table class="stats-table">
                        <tr><td>SSD Size (retail)</td><td><strong><?php echo $retail_size; ?></strong></td></tr>
                        <tr><td>Formatted Capacity</td><td><?php echo $row['size']; ?></td></tr>
                        <tr><td>Used Storage</td><td><?php echo $row['used']; ?></td></tr>
                        <tr><td>Free Storage</td><td><strong style="color:#10b981;"><?php echo $row['avail']; ?></strong></td></tr>
                        <tr><td>System Reserved</td><td><?php echo $reserved; ?></td></tr>
                    </table>
                </div>
                <div class="warning-box">
                    <p style="margin:0;"><strong>⚠️ Warning:</strong> Do not exceed <?php echo $row['avail']; ?> when downloading new content — system breakage can occur. If deleting content, reboot to clear hidden trash folders before adding more content.</p>
                </div>
                <?php
            }
        } else {
            echo "<p>Storage information not available for this system.</p>";
        }
        ?>
    </div>
</div>

<!-- WiFi Tab (RACHEL-Plus only) -->
<?php if (is_rachelplus()) { ?>
<div id="tab-wifi" class="tab-content">
    <div class="form-section">
        <h3>📶 <?php echo $lang['wifi_control']; ?></h3>
        <div style="display:flex; align-items:center; gap:15px; margin-bottom:15px;">
            <span><?php echo $lang['current_status']; ?>:</span>
            <span id="wifistat" style="font-weight:600;">&nbsp;</span>
        </div>
        <div style="display:flex; gap:10px;">
            <button class="btn-primary" onclick="wifiStatus('on');"><?php echo $lang['turn_on']; ?></button>
            <button class="btn-danger" onclick="wifiStatus('off');"><?php echo $lang['turn_off']; ?></button>
        </div>
        <div class="warning-box" style="margin-top:15px;">
            <p style="margin:0;"><?php echo $lang['wifi_warning']; ?></p>
        </div>
    </div>
</div>
<?php } ?>

<!-- Power Tab -->
<div id="tab-power" class="tab-content">
    <div class="form-section">
        <h3>⚡ <?php echo $lang['system_shutdown']; ?></h3>
        
        <?php if (is_rachelplus()) { ?>
        <div style="margin-bottom:20px;">
            <?php
            if (is_rachelplusv5() || is_rachelplusv3()) {
                echo "<img src='art/ecs-cap-power-button.png' width='178' height='178' style='float:right; margin-left:20px;'>";
            } else {
                echo "<img src='art/intel-cap-power-button.png' width='250' height='170' style='float:right; margin-left:20px;'>";
            }
            ?>
            <p><?php echo $lang['rplus_safe_shutdown']; ?></p>
            <div style="clear:both;"></div>
        </div>
        <?php } else if (is_rachelpi()) { ?>
        <p><?php echo $lang['shutdown_blurb']; ?></p>
        <?php } ?>
        
        <div class="shutdown-box">
            <form action="device.php" method="post" style="display:flex; gap:10px;">
                <button type="submit" name="shutdown" class="btn-danger" onclick="if (!confirm('<?php echo $lang['confirm_shutdown']; ?>')) { return false; }">
                    🔴 <?php echo $lang['shutdown']; ?>
                </button>
                <button type="submit" name="reboot" class="btn-primary" onclick="if (!confirm('<?php echo $lang['confirm_restart']; ?>')) { return false; }">
                    🔄 <?php echo $lang['restart']; ?>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Sponsorship Tab -->
<div id="tab-sponsor" class="tab-content">
    <div class="info-box" style="margin-bottom:20px;">
        <h4>🎗️ Device Sponsorship</h4>
        <p style="margin-bottom:0;">Add a sponsorship module to the home page to credit the organization that deployed this RACHEL device. This creates a special module that appears on the content library home page.</p>
    </div>
    
    <div class="form-section">
        <h3>Sponsor Information</h3>
        
        <div id="sponsorMessage" style="display:none; padding:10px 15px; border-radius:6px; margin-bottom:15px;"></div>
        
        <div class="form-row">
            <label style="display:flex; align-items:center; gap:10px;">
                <input type="checkbox" id="sponsorEnabled" onchange="toggleSponsorPreview();" style="width:18px; height:18px;">
                <span>Enable sponsorship module on home page</span>
            </label>
        </div>
        
        <div id="sponsorFields" style="margin-top:20px;">
            <div class="form-row">
                <label>Organization Name *</label>
                <input type="text" id="sponsorName" placeholder="e.g., Acme Foundation" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
            </div>
            
            <div class="form-row">
                <label>Tagline / Message</label>
                <input type="text" id="sponsorTagline" placeholder="e.g., Bringing education to underserved communities" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
            </div>
            
            <div class="form-row">
                <label>Website URL (optional)</label>
                <input type="url" id="sponsorWebsite" placeholder="e.g., https://www.example.org" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
            </div>
            
            <div class="form-row">
                <label>Logo</label>
                <div style="display:flex; gap:10px; align-items:flex-start; flex-wrap:wrap;">
                    <div style="flex:1; min-width:200px;">
                        <input type="file" id="sponsorLogoFile" accept="image/*" onchange="previewSponsorLogo(this);" style="margin-bottom:8px;">
                        <input type="text" id="sponsorLogo" placeholder="Or enter URL: /modules/... or https://..." style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                        <p style="color:#64748b; font-size:0.85em; margin-top:5px;">Upload an image or enter a URL to an existing image.</p>
                    </div>
                    <div id="sponsorLogoPreview" style="width:120px; height:80px; border:1px dashed #cbd5e1; border-radius:6px; display:flex; align-items:center; justify-content:center; background:#f8fafc; overflow:hidden;">
                        <span style="color:#94a3b8; font-size:0.8em;">No logo</span>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <label>Background Color</label>
                <div style="display:flex; gap:10px; align-items:center;">
                    <input type="color" id="sponsorBgColor" value="#1e40af" style="width:60px; height:40px; border:1px solid #cbd5e1; border-radius:6px; cursor:pointer;">
                    <input type="text" id="sponsorBgColorText" value="#1e40af" style="width:100px; padding:10px; border:1px solid #cbd5e1; border-radius:6px;" onchange="document.getElementById('sponsorBgColor').value = this.value; toggleSponsorPreview();">
                    <span style="color:#64748b; font-size:0.9em;">Header background color</span>
                </div>
            </div>
            
            <div class="form-row">
                <label>Text Color</label>
                <div style="display:flex; gap:10px; align-items:center;">
                    <input type="color" id="sponsorTextColor" value="#ffffff" style="width:60px; height:40px; border:1px solid #cbd5e1; border-radius:6px; cursor:pointer;">
                    <input type="text" id="sponsorTextColorText" value="#ffffff" style="width:100px; padding:10px; border:1px solid #cbd5e1; border-radius:6px;" onchange="document.getElementById('sponsorTextColor').value = this.value; toggleSponsorPreview();">
                    <span style="color:#64748b; font-size:0.9em;">Header text color</span>
                </div>
            </div>
            
            <div class="form-row">
                <label>Custom Message (optional)</label>
                <textarea id="sponsorCustomMessage" rows="3" placeholder="Add any additional information about the sponsor or deployment..." style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;"></textarea>
            </div>
            
            <div class="form-row">
                <label>Module Position</label>
                <select id="sponsorPosition" style="padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                    <option value="top">Top of home page (first module)</option>
                    <option value="bottom">Bottom of home page (last module)</option>
                </select>
            </div>
        </div>
        
        <div style="margin-top:20px;">
            <button onclick="saveSponsorSettings();" class="btn-primary">💾 Save Sponsorship Settings</button>
        </div>
        
        <!-- Preview -->
        <div id="sponsorPreview" style="margin-top:30px; display:none;">
            <h4 style="margin-bottom:15px;">Preview</h4>
            <div id="sponsorPreviewContent" style="border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; max-width:600px;"></div>
        </div>
    </div>
</div>

<!-- Other Services Tab -->
<div id="tab-services" class="tab-content">
    <div class="warning-box" style="margin-bottom:20px;">
        <h4>⚠️ Important Security Notice</h4>
        <p style="margin-bottom:0;">Your RACHEL device runs several services that have their own login credentials. <strong>The password you set on this page only controls access to the RACHEL Admin interface.</strong> You should change the default passwords for these other services to secure your device.</p>
    </div>
    
    <div class="form-section">
        <h3>🔗 Other Admin Interfaces</h3>
        <p style="color:#64748b; margin-bottom:20px;">Click the links below to access other administrative interfaces on this RACHEL device. Each service has its own login credentials that should be changed from the defaults.</p>
        
        <div class="service-list">
            <!-- CAP / LuCI Interface -->
            <div class="service-item">
                <div class="service-icon">🌐</div>
                <div class="service-info">
                    <h4>CAP Hardware Control (LuCI)</h4>
                    <p>Network configuration, WiFi settings, firewall rules, DHCP server, and system administration.</p>
                    <div class="service-details">
                        <span class="detail-label">Port:</span> 8080<br>
                        <span class="detail-label">Default Username:</span> <code>root</code><br>
                        <span class="detail-label">Default Password:</span> <code>rachel</code> (or device-specific)
                    </div>
                    <a href="//<?php echo $_SERVER['HTTP_HOST']; ?>:8080" target="_blank" class="btn-primary" style="text-decoration:none; display:inline-block; margin-top:10px;">
                        Open CAP Interface →
                    </a>
                </div>
            </div>
            
            <!-- EasyConnect Interface -->
            <div class="service-item">
                <div class="service-icon">📚</div>
                <div class="service-info">
                    <h4>EasyConnect Content Management</h4>
                    <p>Content catalogue, module downloads, device branding, and content import tools.</p>
                    <div class="service-details">
                        <span class="detail-label">Port:</span> 8090<br>
                        <span class="detail-label">Default Username:</span> <code>admin</code><br>
                        <span class="detail-label">Default Password:</span> <code>admin</code>
                    </div>
                    <a href="//<?php echo $_SERVER['HTTP_HOST']; ?>:8090" target="_blank" class="btn-primary" style="text-decoration:none; display:inline-block; margin-top:10px;">
                        Open EasyConnect →
                    </a>
                </div>
            </div>
            
            <!-- KA Lite Interface -->
            <div class="service-item">
                <div class="service-icon">🎓</div>
                <div class="service-info">
                    <h4>KA Lite (Khan Academy)</h4>
                    <p>Khan Academy offline learning platform with student tracking, coach tools, and video management.</p>
                    <div class="service-details">
                        <span class="detail-label">Port:</span> 8008<br>
                        <span class="detail-label">Default Username:</span> <code>admin</code><br>
                        <span class="detail-label">Default Password:</span> Set during installation (often <code>admin</code>)
                    </div>
                    <a href="//<?php echo $_SERVER['HTTP_HOST']; ?>:8008" target="_blank" class="btn-primary" style="text-decoration:none; display:inline-block; margin-top:10px;">
                        Open KA Lite →
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="form-section" style="margin-top:20px;">
        <h3>🔒 SSH / Terminal Access</h3>
        <p>For advanced administration, you can connect via SSH (Secure Shell) to access the command line.</p>
        <div class="service-details" style="background:#f8fafc; padding:15px; border-radius:6px; margin-top:10px;">
            <span class="detail-label">SSH Port:</span> 22<br>
            <span class="detail-label">Username:</span> <code>root</code> or <code>cap</code><br>
            <span class="detail-label">Connection:</span> <code>ssh root@<?php echo $_SERVER['HTTP_HOST']; ?></code>
        </div>
        <p style="color:#64748b; font-size:0.9em; margin-top:15px;">⚠️ SSH access provides full control over the device. Only use if you are comfortable with Linux command line administration.</p>
    </div>
</div>

<style>
.service-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.service-item {
    display: flex;
    gap: 15px;
    padding: 20px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}
.service-icon {
    font-size: 2em;
    width: 50px;
    text-align: center;
}
.service-info {
    flex: 1;
}
.service-info h4 {
    margin: 0 0 8px 0;
    color: #1e293b;
}
.service-info p {
    margin: 0 0 10px 0;
    color: #64748b;
}
.service-details {
    font-size: 0.9em;
    color: #475569;
    line-height: 1.6;
}
.service-details code {
    background: #e5e7eb;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
}
.detail-label {
    font-weight: 600;
    color: #374151;
}
</style>

<!-- Advanced Tab (RACHEL-Plus only) -->
<?php if (is_rachelplus()) { ?>
<div id="tab-advanced" class="tab-content">
    <div class="warning-box" style="margin-bottom:20px;">
        <h4>⚠️ Caution</h4>
        <p style="margin-bottom:0;">The settings in the advanced hardware control can cause your RACHEL to stop working. Do not change unless you know what you are doing.</p>
    </div>
    
    <div class="form-section">
        <h3>🔧 Advanced Hardware Control</h3>
        <p>To modify advanced hardware settings (WiFi configuration, DHCP server, Firewall rules, Network interfaces, etc.) you can access the CAP administrative interface.</p>
        <p style="margin-top:15px;">
            <a href="//<?php echo $_SERVER['HTTP_HOST']; ?>:8080" target="_blank" class="btn-primary" style="text-decoration:none; display:inline-block;">
                🔗 Open CAP Interface
            </a>
        </p>
        <p style="color:#64748b; font-size:0.9em; margin-top:15px;">The CAP interface will open in a new browser tab at port 8080.</p>
    </div>
</div>
<?php } ?>

<script>
$(function() {
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
    var tabs = ['password', 'system', 'storage', <?php if (is_rachelplus()) echo "'wifi', "; ?>'power', 'sponsor', 'services'<?php if (is_rachelplus()) echo ", 'advanced'"; ?>];
    var tabIndex = tabs.indexOf(tab);
    if (tabIndex === -1) tabIndex = 0;
    tab = tabs[tabIndex]; // Normalize to valid tab
    $('.section-tabs button').eq(tabIndex).addClass('active');
    $('#tab-' + tab).addClass('active');
    
    // Update URL hash without triggering hashchange
    if (window.location.hash !== '#' + tab) {
        history.replaceState(null, null, '#' + tab);
    }
    
    // Load sponsor settings when tab is shown
    if (tab === 'sponsor') {
        loadSponsorSettings();
    }
}

// Sponsor settings functions
var sponsorLogoFile = null;

function loadSponsorSettings() {
    $.ajax({
        url: 'background.php?getSponsorSettings=1',
        dataType: 'json',
        success: function(data) {
            $('#sponsorEnabled').prop('checked', data.enabled == 1);
            $('#sponsorName').val(data.name || '');
            $('#sponsorTagline').val(data.tagline || '');
            $('#sponsorWebsite').val(data.website || '');
            $('#sponsorLogo').val(data.logo || '');
            $('#sponsorBgColor').val(data.bg_color || '#1e40af');
            $('#sponsorBgColorText').val(data.bg_color || '#1e40af');
            $('#sponsorTextColor').val(data.text_color || '#ffffff');
            $('#sponsorTextColorText').val(data.text_color || '#ffffff');
            $('#sponsorCustomMessage').val(data.custom_message || '');
            $('#sponsorPosition').val(data.position || 'top');
            
            // Update logo preview
            updateSponsorLogoPreview(data.logo);
            
            toggleSponsorPreview();
        }
    });
}

function previewSponsorLogo(input) {
    if (input.files && input.files[0]) {
        sponsorLogoFile = input.files[0];
        var reader = new FileReader();
        reader.onload = function(e) {
            $('#sponsorLogoPreview').html('<img src="' + e.target.result + '" style="max-width:100%; max-height:100%; object-fit:contain;">');
            // Clear the URL field since we're uploading
            $('#sponsorLogo').val('');
            toggleSponsorPreview();
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function updateSponsorLogoPreview(url) {
    if (url) {
        $('#sponsorLogoPreview').html('<img src="' + url + '" style="max-width:100%; max-height:100%; object-fit:contain;" onerror="$(this).parent().html(\'<span style=color:#ef4444;font-size:0.8em>Load error</span>\')">');
    } else {
        $('#sponsorLogoPreview').html('<span style="color:#94a3b8; font-size:0.8em;">No logo</span>');
    }
}

function saveSponsorSettings() {
    var settings = {
        enabled: $('#sponsorEnabled').is(':checked') ? 1 : 0,
        name: $('#sponsorName').val(),
        tagline: $('#sponsorTagline').val(),
        website: $('#sponsorWebsite').val(),
        logo: $('#sponsorLogo').val(),
        bg_color: $('#sponsorBgColor').val(),
        text_color: $('#sponsorTextColor').val(),
        custom_message: $('#sponsorCustomMessage').val(),
        position: $('#sponsorPosition').val()
    };
    
    if (settings.enabled && !settings.name) {
        showSponsorMessage('Please enter an organization name', true);
        return;
    }
    
    // If there's a file to upload, do that first
    if (sponsorLogoFile) {
        var formData = new FormData();
        formData.append('uploadSponsorLogo', '1');
        formData.append('logo', sponsorLogoFile);
        
        $.ajax({
            url: 'background.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.url) {
                    settings.logo = response.url;
                    $('#sponsorLogo').val(response.url);
                    sponsorLogoFile = null; // Clear after successful upload
                    doSaveSponsorSettings(settings);
                } else {
                    showSponsorMessage('Failed to upload logo', true);
                }
            },
            error: function() {
                showSponsorMessage('Failed to upload logo', true);
            }
        });
    } else {
        doSaveSponsorSettings(settings);
    }
}

function doSaveSponsorSettings(settings) {
    $.ajax({
        url: 'background.php?saveSponsorSettings=1',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(settings),
        success: function(response) {
            showSponsorMessage('Sponsorship settings saved successfully!', false);
        },
        error: function() {
            showSponsorMessage('Failed to save settings', true);
        }
    });
}

function showSponsorMessage(msg, isError) {
    var $el = $('#sponsorMessage');
    $el.text(msg)
       .css({
           display: 'block',
           background: isError ? '#fee2e2' : '#dcfce7',
           color: isError ? '#991b1b' : '#166534',
           border: '1px solid ' + (isError ? '#ef4444' : '#22c55e')
       });
    setTimeout(function() { $el.fadeOut(); }, 3000);
}

function toggleSponsorPreview() {
    var enabled = $('#sponsorEnabled').is(':checked');
    var name = $('#sponsorName').val() || 'Organization Name';
    var tagline = $('#sponsorTagline').val();
    var logo = $('#sponsorLogo').val();
    var bgColor = $('#sponsorBgColor').val();
    var textColor = $('#sponsorTextColor').val();
    var customMessage = $('#sponsorCustomMessage').val();
    
    // Use file preview if available
    var logoPreviewImg = $('#sponsorLogoPreview img');
    var logoSrc = logoPreviewImg.length ? logoPreviewImg.attr('src') : logo;
    
    // Sync color inputs
    $('#sponsorBgColorText').val(bgColor);
    $('#sponsorTextColorText').val(textColor);
    
    if (!enabled) {
        $('#sponsorPreview').hide();
        return;
    }
    
    var html = '<div style="background:' + bgColor + '; color:' + textColor + '; padding:20px; text-align:center;">';
    html += '<p style="margin:0 0 5px 0; font-size:0.9em; opacity:0.9;">This educational resource is brought to you by</p>';
    if (logoSrc) {
        html += '<img src="' + logoSrc + '" alt="' + name + '" style="max-height:60px; max-width:200px; margin:10px 0;" onerror="this.style.display=\'none\'">';
    }
    html += '<h3 style="margin:10px 0 5px 0; font-size:1.4em;">' + escapeHtml(name) + '</h3>';
    if (tagline) {
        html += '<p style="margin:0; opacity:0.9;">' + escapeHtml(tagline) + '</p>';
    }
    html += '</div>';
    
    if (customMessage) {
        html += '<div style="background:white; padding:15px; border-top:1px solid #e5e7eb;">';
        html += '<p style="margin:0; color:#475569;">' + escapeHtml(customMessage) + '</p>';
        html += '</div>';
    }
    
    $('#sponsorPreviewContent').html(html);
    $('#sponsorPreview').show();
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Bind color picker change events
$(function() {
    $('#sponsorBgColor').on('change input', function() {
        $('#sponsorBgColorText').val(this.value);
        toggleSponsorPreview();
    });
    $('#sponsorTextColor').on('change input', function() {
        $('#sponsorTextColorText').val(this.value);
        toggleSponsorPreview();
    });
    $('#sponsorName, #sponsorTagline, #sponsorCustomMessage').on('input', function() {
        toggleSponsorPreview();
    });
    $('#sponsorLogo').on('input', function() {
        // When URL is typed, update preview and clear file
        var url = $(this).val();
        if (url) {
            sponsorLogoFile = null;
            $('#sponsorLogoFile').val('');
            updateSponsorLogoPreview(url);
        }
        toggleSponsorPreview();
    });
});
</script>

<?php include "foot.php"; ?>
