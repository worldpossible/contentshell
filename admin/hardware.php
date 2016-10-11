<?php
require_once("common.php");
if (!authorized()) { exit(); }
$page_title = $lang['hardware'];
$page_script = "";
$page_nav = "hardware";
include "head.php";

echo "
    <style>
    h2 { border-bottom: 1px solid #ccc; }
    #spacetable { border-spacing: 15px; border-collapse: separate; }
    #spacetable td { border-bottom: 1px solid #ccc; padding: 5px; }
    .bartd { padding: 0; background: #eef; color: #669; text-align: right; width: 200px; position: relative; border: 1px solid #ccc; }
    .barused { position: absolute; top: 0; left: 0; background: #cce; padding-bottom: 12px; height: 1em; }
    .barusedtxt { position: absolute; top: 0; left: 0; padding: 5px; }
    .baravailtxt { position: absolute; top: 0; right: 0; padding: 5px; }
    </style>
";

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

#-------------------------------------------
# get the disk usage and format it as best we can
#-------------------------------------------
echo "<h2>$lang[storage_usage]</h2>\n";

exec("df -h", $output, $rval);
$usage_rows = array();
$usage_supported = false;
if (is_rachelpi()) {

    $usage_supported = true;

    foreach ($output as $line) {
        list($fs, $size, $used, $avail, $perc, $name) = preg_split("/\s+/", $line);
        if (!preg_match("/^\/dev/", $fs)) { continue; }
        if ($name == "/") { $name = "SD Card (RACHEL modules)"; }
        array_push( $usage_rows, array(
            "name"  => $name, "size"  => $size, "used"  => $used,
            "avail" => $avail, "perc"  => $perc,
        ));
    }

} else if (is_rachelplus()) {

    $usage_supported = true;

    $partitions = array(
        "/media/preloaded" => "Admin (preloaded)",
        "/media/uploaded"  => "Teacher (uploaded)",
        "/media/RACHEL"    => "RACHEL (RACHEL modules)"
    );

    foreach ($output as $line) {
        list($fs, $size, $used, $avail, $perc, $name) = preg_split("/\s+/", $line);
        if (!isset($partitions[$name])) { continue; }
        $name = $partitions[$name];
        array_push( $usage_rows, array(
            "name"  => $name, "size"  => $size, "used"  => $used,
            "avail" => $avail, "perc"  => $perc,
        ));
    }

# this handles output on OSX, and probably some other unix variants
} else if (preg_match("/Filesystem.+Size.+Used.+Avail.+Capacity.+iused.+ifree/", $output[0])) {

    $usage_supported = true;

    foreach ($output as $line) {
        list($fs, $size, $used, $avail, $perc, $iused, $ifree, $iusedperc, $name, $name2) = preg_split("/\s+/", $line);
        if (!preg_match("/^\/dev/", $fs)) { continue; }
        if ($name2) {$name .= " $name2"; }
        $size  = preg_replace("/i$/", "B", $size);
        $used  = preg_replace("/i$/", "B", $used);
        $avail = preg_replace("/i$/", "B", $avail);
        array_push( $usage_rows, array(
            "name"  => $name, "size"  => $size, "used"  => $used,
            "avail" => $avail, "perc"  => $perc,
        ));
    }

}

if ($usage_supported) {

    echo "<table id=\"spacetable\">\n";
    echo "<tr><th>$lang[location]</th><th>$lang[size]</th><th>$lang[used]/$lang[available]</th><th>$lang[percentage]</th></tr>\n";
    foreach ($usage_rows as $row) {
        echo "
            <tr><td>$row[name]</td><td>$row[size]</td>
            <td class=\"bartd\">
            <div class=\"barused\" style=\"width: $row[perc];\"></div>
            <div class=\"barusedtxt\">$row[used]</div>
            <div class=\"baravailtxt\">$row[avail]</div>
            </td>
            <td>$row[perc]</td></tr>
        ";
    }
    echo "</table>\n";

} else {

    echo "<h3>Unknown System - Unformatted Output</h3>";
    echo "<pre style=\"background: #fff;\">";
    echo implode("", $output);
    echo "</pre>";
}

#-------------------------------------------
# We also offer a shutdown option for raspberry pi systems
# (which otherwise might corrupt themselves when unplugged)
#-------------------------------------------
echo "<h2>$lang[system_shutdown]</h3>";
if (is_rachelpi()) {
    echo "
        <h3>Rachel-Pi</h3>
        <div style='padding: 10px; border: 1px solid red; background: #fee;'>
        <form action='hardware.php' method='post'>
        <input type='submit' name='shutdown' value='$lang[shutdown]' onclick=\"if (!confirm('$lang[confirm_shutdown]')) { return false; }\">
        <input type='submit' name='reboot' value='$lang[restart]' onclick=\"if (!confirm('$lang[confirm_restart]')) { return false; }\">
        </form>
        $lang[shutdown_blurb]
        </div>
    ";
} else if (is_rachelplus()) {
    echo "
        <h3>RACHEL-Plus</h3>
        <img src='art/intel-cap-power-button.png' width='250' height='170'>
        $lang[rplus_safe_shutdown]
        <div style='padding: 10px; border: 1px solid red; background: #fee;'>
        <form action='hardware.php' method='post'>
        <input type='submit' name='shutdown' value='$lang[shutdown]' onclick=\"if (!confirm('$lang[confirm_shutdown]')) { return false; }\">
        <input type='submit' name='reboot' value='$lang[restart]' onclick=\"if (!confirm('$lang[confirm_restart]')) { return false; }\">
        </form>
        </div>
    ";
} else {
    echo "
        <h3>$lang[unknown_system]</h3>
        <p>$lang[shutdown_not_supported]</p>
    ";
}

include "foot.php"

?>
