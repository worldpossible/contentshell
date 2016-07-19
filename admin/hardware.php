<?php
require_once("common.php");
if (!authorized()) { exit(); }
$page_title = $lang['update'];
$page_script = "";
$page_nav = "hardware";
include "head.php";

#-------------------------------------------
# We also allow shutting down the server so as to avoid
# damaging the SD/HD. This requires that www-data has
# sudo access to /sbin/shutdown, which should be set up
# automatically during rachelpiOS installation
# XXX should make this work for RACHEL-Plus too
#-------------------------------------------
if (isset($_POST['shutdown'])) {
    exec("sudo /sbin/shutdown now", $exec_out, $exec_err);
    if ($exec_err) {
        echo $lang['shutdown_failed'];
    } else {
        echo $lang['restart_ok'];
    }
    exit;
} else if (isset($_POST['reboot'])) {
    exec("sudo /sbin/shutdown -r now", $exec_out, $exec_err);
    if ($exec_err) {
        echo $lang['restart_failed'];
    } else {
        echo $lang['restart_ok'];
    }
    exit;
}

# Totally separate from module management, we also offer
# a shutdown option for raspberry pi systems (which otherwise
# might corrupt themselves when unplugged)
if (file_exists("/usr/bin/raspi-config") ||
    file_exists("/etc/fake-raspi")) { # for testing on non-raspi systems
    echo "
        <h2>Rachel Pi</h2>
        <div style='padding: 10px; border: 1px solid red; background: #fee;'>
        <form action='hardware.php' method='post'>
        <input type='submit' name='shutdown' value='$lang[shutdown_system]' onclick=\"if (!confirm('$lang[confirm_shutdown]')) { return false; }\">
        <input type='submit' name='reboot' value='$lang[restart_system]' onclick=\"if (!confirm('$lang[confirm_restart]')) { return false; }\">
        </form>
        $lang[shutdown_blurb]
        </div>
    ";
} else if (is_rachelplus()) {
    echo "
        <h2>RACHEL-Plus</h2>
        <p>$lang[rplus_safe_shutdown]</p>
    ";
} else {
    echo "
        <h2>$lang[unknown_system]</h2>
        <p>$lang[shutdown_not_supported]</p>
    ";
}

include "foot.php"

?>
