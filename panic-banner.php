<?php
/**
 * panic-banner.php — Degraded mode warning banner
 *
 * Included at the top of index.php. Shows a prominent warning when the device
 * is running on eMMC fallback storage because the internal SSD failed.
 * Provides an "Attempt Recovery" button that runs sda-recover.php via AJAX.
 *
 * Detection: checks for /.data/.emmc-fallback flag file written by
 * rachel-data-setup.sh when it falls back to the eMMC data partition.
 */

$is_emmc_fallback = file_exists('/.data/.emmc-fallback');

if ($is_emmc_fallback):
    // Check if SDA hardware is present at all
    $sda_present = file_exists('/dev/sda');
    $sda1_present = file_exists('/dev/sda1');
?>
<style>
.panic-banner {
    background: #92400e;
    color: #fef3c7;
    padding: 0;
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    position: relative;
    z-index: 900;
    border-bottom: 3px solid #d97706;
}
.panic-inner {
    max-width: 900px;
    margin: 0 auto;
    padding: 14px 20px;
}
.panic-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}
.panic-icon {
    font-size: 1.3em;
    line-height: 1;
}
.panic-title {
    font-size: 1.05em;
    font-weight: 700;
    margin: 0;
}
.panic-body {
    font-size: 0.9em;
    line-height: 1.5;
    opacity: 0.95;
    margin-bottom: 10px;
}
.panic-body strong {
    color: #fde68a;
}
.panic-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.panic-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border-radius: 6px;
    font-size: 0.85em;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s;
}
.panic-btn-recover {
    background: #fef3c7;
    color: #78350f;
}
.panic-btn-recover:hover {
    background: #fde68a;
}
.panic-btn-recover:disabled {
    opacity: 0.7;
    cursor: wait;
}
.panic-btn-dismiss {
    background: transparent;
    color: #fef3c7;
    border-color: rgba(254,243,199,0.3);
    font-size: 0.8em;
    padding: 6px 12px;
}
.panic-btn-dismiss:hover {
    border-color: #fef3c7;
    background: rgba(254,243,199,0.1);
}
.panic-status {
    margin-top: 12px;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 0.9em;
    display: none;
}
.panic-status.show { display: block; }
.panic-status.info { background: rgba(254,243,199,0.1); }
.panic-status.success { background: rgba(74,222,128,0.15); border: 1px solid rgba(74,222,128,0.3); }
.panic-status.error { background: rgba(0,0,0,0.15); border: 1px solid rgba(254,243,199,0.1); }
.panic-log {
    margin-top: 8px;
    max-height: 200px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 0.8em;
    white-space: pre-wrap;
    background: rgba(0,0,0,0.3);
    padding: 10px;
    border-radius: 6px;
    display: none;
}
.panic-log.show { display: block; }
.panic-hw-note {
    font-size: 0.85em;
    opacity: 0.75;
    margin-top: 8px;
    font-style: italic;
}
@keyframes spin { to { transform: rotate(360deg); } }
.panic-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid #78350f;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    vertical-align: middle;
}
</style>

<div class="panic-banner" id="panicBanner">
    <div class="panic-inner">
        <div class="panic-header">
            <span class="panic-icon">⚠️</span>
            <h2 class="panic-title">Internal Storage Issue — Limited Content Available</h2>
        </div>
        <div class="panic-body">
            <p>The internal hard drive could not be mounted. Only <strong>upload and sharing modules</strong> are available right now.
            The full content library will return once the drive is recovered.</p>
            <?php if (!$sda_present): ?>
            <p class="panic-hw-note">The hard drive is not detected. This may require physical inspection if recovery doesn't help.</p>
            <?php endif; ?>
        </div>
        <div class="panic-actions">
            <?php if ($sda_present): ?>
            <button class="panic-btn panic-btn-recover" id="recoverBtn" onclick="attemptSdaRecovery();">
                🔧 Attempt to Recover Hard Drive
            </button>
            <?php else: ?>
            <button class="panic-btn panic-btn-recover" id="recoverBtn" onclick="attemptSdaRecovery();" disabled>
                ⛔ Hard Drive Not Detected
            </button>
            <?php endif; ?>
            <button class="panic-btn panic-btn-dismiss" onclick="document.getElementById('panicBanner').style.display='none';">
                Continue with limited content ↓
            </button>
        </div>
        <div class="panic-status" id="recoverStatus"></div>
        <div class="panic-log" id="recoverLog"></div>
    </div>
</div>

<script>
function attemptSdaRecovery() {
    var btn = document.getElementById('recoverBtn');
    var status = document.getElementById('recoverStatus');
    var logBox = document.getElementById('recoverLog');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="panic-spinner"></span> Running disk check — please wait...';
    status.className = 'panic-status show info';
    status.textContent = 'Running disk check. This can take a long time on large drives — it is safe to leave it running overnight.';
    logBox.className = 'panic-log';
    logBox.textContent = '';
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'sda-recover.php', true);
    xhr.timeout = 300000; // 5 min timeout for large drives
    xhr.onload = function() {
        try {
            var resp = JSON.parse(xhr.responseText);
        } catch(e) {
            status.className = 'panic-status show error';
            status.textContent = 'Unexpected response from recovery script.';
            btn.disabled = false;
            btn.innerHTML = '🔧 Attempt to Recover Hard Drive';
            return;
        }
        
        if (resp.log) {
            logBox.className = 'panic-log show';
            logBox.textContent = resp.log;
        }
        
        if (resp.status === 'recovered') {
            status.className = 'panic-status show success';
            status.textContent = resp.message;
            btn.innerHTML = '✅ Recovery Successful!';
            setTimeout(function() { location.reload(); }, 3000);
        } else if (resp.status === 'ok') {
            status.className = 'panic-status show success';
            status.textContent = resp.message;
            setTimeout(function() { location.reload(); }, 2000);
        } else {
            status.className = 'panic-status show error';
            status.textContent = resp.message;
            btn.disabled = false;
            btn.innerHTML = '🔧 Retry Recovery';
        }
    };
    xhr.onerror = function() {
        status.className = 'panic-status show error';
        status.textContent = 'Could not reach the recovery service. The device may be restarting.';
        btn.disabled = false;
        btn.innerHTML = '🔧 Retry Recovery';
    };
    xhr.ontimeout = function() {
        status.className = 'panic-status show error';
        status.textContent = 'Recovery timed out. The drive may be too damaged for automatic repair.';
        btn.disabled = false;
        btn.innerHTML = '🔧 Retry Recovery';
    };
    xhr.send();
}
</script>

<?php endif; // $is_emmc_fallback ?>
