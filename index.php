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
<base target="content">
</head>

<body>
<div id="rachel">
    <!-- Why show IP here? Some installations have WiFi and Ethernet, and
         maybe you're on one but need to know the other. Also helps if my.content
         isn't working on some client devices. Also nice for when you need to ssh
         or rsync. It's visible in the Admin panel too, but it's more convenient here. -->
    <div id="ip"><?php showip(); ?></div>
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
