<?php require_once("common.php"); ?>
<!DOCTYPE html>
<html lang="<?php echo $lang['langcode']; ?>">
  <head>
    <meta charset="utf-8">
    <title>RACHEL Admin - Version Info</title>
    <link rel="stylesheet" href="css/normalize-1.1.3.css">
    <link rel="stylesheet" href="css/ui-lightness/jquery-ui-1.10.4.custom.min.css">
    <link rel="stylesheet" href="css/admin-style.css">
  </head>
<body>

<?php $nav_version = true; include "admin-nav.php" ?>

<div id="content">
<?php
    
    # this should work on debian variants
    foreach (glob("/etc/*-release") as $filename) {
        $filecont = file_get_contents($filename);
        if (preg_match("/PRETTY_NAME=\"(.+?)\"/", $filecont, $matches)) {
            $os = $matches[1];
            break;
        }
    }

    # this should work on redhat variants
    if (!isset($os)) {
        foreach (glob("/etc/*-release") as $filename) {
            $os = file_get_contents($filename);
            break;
        }
    }

    # this works on remaining unix systems (i.e. Mac OS)
    if (!isset($os)) { $os = exec("uname -srmp"); }

    # this gets the hardware version on rpi systems
    $hardware = "";
    unset($output, $matches);
    exec("dmesg | grep 'Machine model'", $output);
    if (isset($output[0]) && preg_match("/Machine model: (.+)/", $output[0], $matches)) {
        $hardware = $matches[1];
    } else {
        exec("arch", $output);
        if ($output) {
            $hardware = $output[0];
        }
    }

    $rachel_installer_version = "?";
    if (file_exists("/etc/rachelinstaller-version")) {
        $rachel_installer_version = file_get_contents("/etc/rachelinstaller-version");
    }

    $kalite_version = "?";
    if (file_exists("/etc/kalite-version")) {
        $kalite_version = file_get_contents("/etc/kalite-version");
    }

    $kiwix_version = "?";
    if (file_exists("/etc/kiwix-version")) {
        $kiwix_version = file_get_contents("/kiwix-version");
    }

?>

<h2>RACHEL Version Info</h2>
<table class="version">
<tr><td>Hardware</td><td><?php echo $hardware ?></td></tr>
<tr><td>OS</td><td><?php echo $os ?></td></tr>
<tr><td>RACHEL Installer</td><td><?php echo $rachel_installer_version ?>*</td></tr>
<tr><td>KA Lite</td><td><?php echo $kalite_version ?>*</tr>
<tr><td>Kiwix</td><td><?php echo $kiwix_version ?>*</td></tr>
<tr><td>Content Shell</td><td>2016.04.07*</td></tr>

<?php
    # get module info
    require_once("common.php");
    foreach (getmods_fs() as $mod) {
        echo "<tr><td>$mod[moddir]</td><td>$mod[version]</td></tr>\n";
    }
?>

</table>

<ul style="margin-top: 40px;">
<li>A blank indicates the item predates versioning.</li>
<li>A ? indicates the version could not be determined, and perhaps the item is not actually installed</li>
<li>A * indicates the version number was recorded at installation; if you have modified your installation this info may be out of date</li>
</ul>

</div>
</body>
</html>
