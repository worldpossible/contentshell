<?php
    require_once("common.php");
    $preflang = getlang();
    require_once("lang/lang.$preflang.php");
?>
<!DOCTYPE html>
<html lang="<?php echo $preflang ?>">
<head>
<meta charset="utf-8">
<title>RACHEL - <?php echo $lang['home'] ?></title>
<link rel="stylesheet" href="css/normalize-1.1.3.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/ui-lightness/jquery-ui-1.10.4.custom.min.css">
<script src="js/jquery-1.10.2.min.js"></script>
<script src="js/jquery-ui-1.10.4.custom.min.js"></script>
<script>
    // this sets the autocomplete handler for each module's text input field
    $(document).ready( function () {
        $(":text").each( function () {
            var myid = $(this).attr("id");
            if (myid) {
                var moddir = myid.replace(/_search$/, "");
                $("#"+myid).autocomplete({
                        source: "modules/"+moddir+"/search/suggest.php",
                });
            }
        });
    });
</script>
</head>

<body>
<div id="rachel" style="position: relative;">
    Rachel
    <div id="ip">
    <?php
        # some notes to prevent future regression:
        # the PHP suggested gethostbyname(gethostname())
        # brings back the unhelpful 127.0.0.1 on RPi systems,
        # as well as slowing down some Windows installations
        # with a DNS lookup. $_SERVER["SERVER_ADDR"] will just
        # display what's in the user's address bar, so also
        # not useful - using ifconfig/ipconfig is probably
        # the way to go, but may require some tweaking

        echo "<b>" . $lang['server-address'] . "</b><br>\n";
        if (preg_match("/^win/i", PHP_OS)) {
            # under windows it's ipconfig
            $output = shell_exec("ipconfig");
            preg_match("/IPv4 Address.+?: (.+)/", $output, $match);
            if (isset($match[1])) { echo "$match[1]<br>\n"; }
        } else if (preg_match("/^darwin/i", PHP_OS)) {
            # OSX is unix, but it's a little different
            exec("/sbin/ifconfig", $output);
            preg_match("/en0.+?inet (.+?) /", join("", $output), $match);
            if (isset($match[1])) { echo "$match[1]<br>\n"; }
        } else {
            # most likely linux based - so ifconfig should work
            exec("/sbin/ifconfig", $output);
            preg_match("/eth0.+?inet addr:(.+?) /", join("", $output), $match);
            if (isset($match[1])) { echo "LAN: $match[1]<br>\n"; }
            preg_match("/wlan0.+?inet addr:(.+?) /", join("", $output), $match);
            if (isset($match[1])) { echo "WIFI: $match[1]<br>\n"; }
        }

    ?>
    <div style="position: absolute; font-size: small; bottom: 6px; right: 8px;">
    <a href="admin.php" style="color: #999;"><?php echo $lang['admin'] ?></a> |
    <a href="stats.php" style="color: #999;"><?php echo $lang['stats'] ?></a> |
    <a href="version.php" style="color: #999;"><?php echo $lang['version'] ?></a>
    </div>
    </div>
</div>

<div class="menubar cf">
    <ul>
    <li><a href="index.php"><?php echo strtoupper($lang['home']) ?></a></li>
    <li><a href="about.html"><?php echo strtoupper($lang['about']) ?></a></li>
    </ul>
    
</div>

<div id="content">

<?php

    $fsmods = getmods_fs();

    # if there were any modules found in the filesystem
    if ($fsmods) {

        # get a list from the databases (where the sorting
        # and visibility is stored)
        $dbmods = getmods_db();

        # populate the module list from the filesystem 
        # with the visibility/sorting info from the database
        foreach (array_keys($dbmods) as $moddir) {
            if (isset($fsmods[$moddir])) {
                $fsmods[$moddir]['position'] = $dbmods[$moddir]['position'];
                $fsmods[$moddir]['hidden'] = $dbmods[$moddir]['hidden'];
            }
        }

        # custom sorting function in common.php
        uasort($fsmods, 'bypos');

        # whether or not we were able to get anything
        # from the DB, we show what we found in the filesystem
        $modcount = 0;
        foreach (array_values($fsmods) as $mod) {
            if ($mod['hidden'] || $mod['nohtmlf']) { continue; }
            $dir  = $mod['dir'];
            $moddir  = $mod['moddir'];
            if (file_exists("$mod[dir]/index.htmlf")) {
                # old name - deprecated
                include "$mod[dir]/index.htmlf";
            } else {
                # new name - less confusing, and
                # will get syntax highlighting in editors
                include "$mod[dir]/rachel-index.php";
            }
            ++$modcount;
        }

    }

    if ($modcount == 0) {
        echo $lang['no-mods-error'];
    }

?>

</div>

<div class="menubar cf" style="margin-bottom: 80px;">
    <ul>
    <li><a href="index.php"><?php echo strtoupper($lang['home']) ?></a></li>
    <li><a href="about.html"><?php echo strtoupper($lang['about']) ?></a></li>
    </ul>
</div>

</body>
</html>
