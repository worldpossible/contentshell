<?php

#-------------------------------------------
# This is an example of a minimal admin page.
# All admin pages should use this as a template.
# First we import the basic functions, including
# $lang support database functions, etc.
#-------------------------------------------
require_once("common.php");

#-------------------------------------------
# check if they're authorized - cookie based
# (they get a login form if not)
#-------------------------------------------
if (!authorized()) { exit(); }

#-------------------------------------------
# - define our page title (arbitrary)
# - include a .js file if you want
# - set the navigation tab (see nav.php)
#-------------------------------------------
$page_title = "Title";
$page_script = "";
$page_nav = "";

#-------------------------------------------
# include the head, which does everything you need
# done before you start your own output
#-------------------------------------------
include "head.php";

?>

<!-- whatever you want goes here, for example -->
<h1>Our modueles are in: <?php echo getAbsModPath(); ?></h1>

<!-- and finish off with a few closing tags-->
<?php include "foot.php" ?>
