<div id="headbar">
    <b>RACHEL <?php echo $lang['admin']; ?></b>
    <ul id="nav">
        <!-- target="_top" is for the stats page, which uses frames to display awstats (if installed) -->
        <li><a href="modules.php"<?php if ($page_nav == "modules") { echo ' class="active"'; } ?> target="_top"><?php echo $lang['modules'] ?></a></li>
        <li><a href="hardware.php"<?php if ($page_nav == "hardware") { echo ' class="active"'; } ?> target="_top"><?php echo $lang['hardware'] ?></a></li>
        <li><a href="version.php"<?php if ($page_nav == "version") { echo ' class="active"'; } ?> target="_top"><?php echo $lang['version'] ?></a></li>
        <li><a href="install.php"<?php if ($page_nav == "install") { echo ' class="active"'; } ?> target="_top"><?php echo $lang['install'] ?></a></li-->
        <li><a href="stats.php"<?php if ($page_nav == "stats") { echo ' class="active"'; } ?> target="_top"><?php echo $lang['stats'] ?></a></li>
        <li><a href="settings.php"<?php if ($page_nav == "settings") { echo ' class="active"'; } ?> target="_top"><?php echo $lang['settings'] ?></a></li>
        <li><a href="logout.php"><?php echo $lang['logout'] ?></a></li>
    </ul>
    <div id="ip">
        <?php showip();
        # on the RACHEL-Plus we also show a battery meter
        if (is_rachelplus()) {
            echo '
                <script>
                    refreshRate = 1000 * 60 * 1; // one minute on admin page, be conservative
                    function getBatteryInfo() {
                        $.ajax({
                            url: "background.php?getBatteryInfo=1",
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
                                    background: "url(\'art/battery-level-sprite-dark.png\')",
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
</div>
