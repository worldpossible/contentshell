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
    
    exec("cat /etc/*-release", $output);

    # this should work on debian variants
    foreach (array_values($output) as $line) {
	if (preg_match("/PRETTY_NAME=\"(.+?)\"/", $line, $matches)) {
	    $os = $matches[1];
	    break;
	}
    }

    # this should work on redhat variants
    if (!$os) { $os = $output[0]; }

    # this works on remaining unix systems (i.e. Mac OS)
    if (!$os) { $os = exec("uname -srmp"); }

?>

<h1>RACHEL Version Info</h1>
<table>
<tr><td>OS</td><td><?php echo $os ?></td></tr>
<tr><td>RACHEL Installer</td><td></td></tr>
<tr><td>KA Lite</td><td><?php passthru("cat /etc/kalite-version") ?></tr>
<tr><td>Kiwix</td><td><?php passthru("cat /etc/kiwix-version") ?></td></tr>
<tr><td>Content Shell</td><td></td></tr>

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
