<?php
require_once("common.php");
if (!authorized()) { exit(); }
$page_title = $lang['update'];
$page_script = "";
$page_nav = "update";
include "head.php";
?>

<!-- whatever you want goes here -->
<h2>getbasedir(): <?php echo getbasedir(); ?></h2>
<h2>getbaseurl(): <?php echo getbaseurl(); ?></h2>
<h2>getrelmodpath(): <?php echo getrelmodpath(); ?></h2>

<!-- and finish off with a few closing tags-->
<?php include "foot.php" ?>
