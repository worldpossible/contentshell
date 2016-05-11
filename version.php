<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>RACHEL Version Info</title>
    <style>
        table { border-collapse: collapse; }
        tr { border-top: 1px solid lightgray; }
        td { padding: 10px; }
    </style>
  </head>
  <body>

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
    if (!$os) {
        foreach (glob("/etc/*-release") as $filename) {
            $os = file_get_contents($filename);
            break;
        }
    }

    # this works on remaining unix systems (i.e. Mac OS)
    if (!$os) { $os = exec("uname -srmp"); }

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

<h1>RACHEL Version Info</h1>
<table>
<tr><td>Hardware</td><td><?php echo $hardware ?></td></tr>
<tr><td>OS</td><td><?php echo $os ?></td></tr>
<tr><td>RACHEL Installer</td><td><?php echo $rachel_installer_version ?></td></tr>
<tr><td>KA Lite</td><td><?php echo $kalite_version ?></tr>
<tr><td>Kiwix</td><td><?php echo $kiwix_version ?></td></tr>
<tr><td>Content Shell</td><td>2016.04.07</td></tr>

<?php
    # get module info
    require_once("common.php");
    foreach (getmods_fs() as $mod) {
        echo "<tr><td>$mod[moddir]</td><td>$mod[version]</td></tr>\n";
    }
?>

</table>

<p style="margin-top: 40px;">Note: these were the versions at the time of install. If you have modified
your installation they may be out of date.</p>

  </body>
</html>
