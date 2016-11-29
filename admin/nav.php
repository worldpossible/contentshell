<div id="headbar">
    <b>RACHEL <?php echo $lang['admin']; ?></b>
    <ul id="nav">
        <li><a href="modules.php"<?php if ($page_nav == "modules") { echo ' class="active"'; } ?>><?php echo $lang['modules'] ?></a></li>
        <li><a href="hardware.php"<?php if ($page_nav == "hardware") { echo ' class="active"'; } ?>><?php echo $lang['hardware'] ?></a></li>
        <li><a href="version.php"<?php if ($page_nav == "version") { echo ' class="active"'; } ?>><?php echo $lang['version'] ?></a></li>
        <li><a href="install.php"<?php if ($page_nav == "install") { echo ' class="active"'; } ?>><?php echo $lang['install'] ?></a></li-->
        <li><a href="stats.php"<?php if ($page_nav == "stats") { echo ' class="active"'; } ?>><?php echo $lang['stats'] ?></a></li>
        <li><a href="settings.php"<?php if ($page_nav == "settings") { echo ' class="active"'; } ?>><?php echo $lang['settings'] ?></a></li>
        <li><a href="logout.php"><?php echo $lang['logout'] ?></a></li>
    </ul>
    <div id="ip"><?php showip(); ?></div>
</div>
