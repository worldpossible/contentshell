<header id="headbar">
  <b>RACHEL Admin</b>
  <nav>
    <ul id="nav">
      <li><a href="storage.php" <?php if ($page_nav == "storage")  { echo ' class="active"'; } ?> target="_top">Content Management</a></li>
      <li><a href="device.php" <?php if ($page_nav == "device")  { echo ' class="active"'; } ?> target="_top">Device Settings</a></li>
      <li><a href="stats.php"   <?php if ($page_nav == "stats")    { echo ' class="active"'; } ?> target="_top">DataPost</a></li>
      <li><a href="logout.php"><?php echo $lang['logout'] ?></a></li>
      <li><a href="../index.php" target="_top" style="opacity: 0.7;">← <?php echo $lang['home'] ?></a></li>
    </ul>
  </nav>
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
</header>
