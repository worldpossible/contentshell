<?php
require_once("common.php");
# we used to let the path be current directory, but then
# we had cookies getting set that were difficult to unset
# (if you don't know what directory they were set in, even
# unsetting at "/" doesn't work) -- so now we set and
# unset everything at the root
setcookie("rachel-auth", null, -1, "/");
header( "Location: //$_SERVER[HTTP_HOST]/" . getbaseurl() );
exit;
?>
