<?php require_once("admin/common.php"); ?>
<!DOCTYPE html>
<html lang="<?php echo $lang['langcode'] ?>">
<head>
<meta charset="utf-8">
<title>RACHEL - <?php echo $lang['home'] ?></title>
<link rel="stylesheet" href="css/normalize-1.1.3.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/ui-lightness/jquery-ui-1.10.4.custom.min.css">
<script src="js/jquery-1.10.2.min.js"></script>
<script src="js/jquery-ui-1.10.4.custom.min.js"></script>
<!-- I know this is not ideal UI, but it is based on real-world issues:
     because we can't provide navigation back to the home page (like on
     kalite and kiwix), it is difficult for users to find the front page
     again. This keeps it open in the background. We tried opening all
     content in a *single* seperate window named "content" but then
     going back to the main tab and clicking a second subject without
     closing the first tab resulted in no apparent action (though the
     "content" tab did in fact load the requested info in the background).
     The end result of all this is that we decided the best choice of
     lousy choices was to open everything in a new window/tab. Thus:
-->
<base target="_blank">
</head>

<body>
<div id="rachel">
    <!-- Why show IP here? Some installations have WiFi and Ethernet, and
         maybe you're on one but need to know the other. Also helps if my.content
         isn't working on some client devices. Also nice for when you need to ssh
         or rsync. It's visible in the Admin panel too, but it's more convenient here. -->
    <div id="ip">
        <?php showip();
        # on the RACHEL-Plus we also show a battery meter
        # XXX abstract this and the admin one into one piece of code
        if (is_rachelplus()) {
            echo '
                <script>
                    refreshRate = 1000 * 60 * 10; // ten minutes on front page, be very conservative
                    function getBatteryInfo() {
                        $.ajax({
                            url: "admin/background.php?getBatteryInfo=1",
                            success: function(results) {
                                //console.log(results);
                                var vert = 0; // shows full charge (each icon down 12px)
                                if      (results.level < 20) { vert = -48; }
                                else if (results.level < 40) { vert = -36; }
                                else if (results.level < 60) { vert = -24; }
                                else if (results.level < 80) { vert = -12; }
                                var horz = 0; // 0 shows discharging, 40px offset shows charging
                                if (results.status == "charging" ) { horz = 40 }
                                $("#battery").css({
                                    background: "url(\'art/battery-level-sprite-light.png\')",
                                    backgroundPosition: horz+"px "+vert+"px",
                                });
                                $("#battery").prop("title", results.level + "%");
                            },
                            complete: function() {
                                setTimeout(getBatteryInfo, refreshRate);
                            }
                        });
                    }
                    $(getBatteryInfo); // onload
                </script>
                <br><b>Battery</b>: <div id="battery"></div><span id="perc"></span>
            ';
        }
        ?>
    </div>
    <div id="adminnav"><a href="admin/modules.php"><?php echo $lang['admin'] ?></a></div>
</div>

<div class="menubar cf">
    <ul>
    <li><a href="index.php" target="_self"><?php echo strtoupper($lang['home']) ?></a></li>
    <li><a href="about.html" target="_self"><?php echo strtoupper($lang['about']) ?></a></li>
    <?php
        if (show_local_content_link()) {
            echo "<li><a href=\"http://$_SERVER[SERVER_ADDR]:8090/\" target=\"_self\">LOCAL CONTENT</a></li>";
        }
    ?>
    </ul>
</div>

<div id="content">

<?php

    $modcount = 0;

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
        foreach (array_values($fsmods) as $mod) {
            if ($mod['hidden'] || !$mod['fragment']) { continue; }
            $dir  = $mod['dir'];
            $moddir  = $mod['moddir'];
            include $mod['fragment'];
            ++$modcount;
        }

    }

    if ($modcount == 0) {
        echo $lang['no_mods_error'];
    }

?>

</div>

<div class="menubar cf" style="margin-bottom: 80px; position: relative;">
    <ul>
    <li><a href="index.php" target="_self"><?php echo strtoupper($lang['home']) ?></a></li>
    <li><a href="about.html" target="_self"><?php echo strtoupper($lang['about']) ?></a></li>
    </ul>
</div>

</body>
</html>
