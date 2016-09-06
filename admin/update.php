<?php
require_once("common.php");
if (!authorized()) { exit(); }
$page_title = $lang['update'];
$page_script = "";
$page_nav = "update";
include "head.php";
?>

<div id="progressbar"></div>


<script>
// called by jsonp respopnse

$.ajax({
    url: "background.php?getLocalModuleList=1",
    success: function(results) {
        console.log("success");
        $("#modlist").html("success");
        var arrayLength = results.length;
        for (var i = 0; i < arrayLength; i++) {
            $("#modlist").append(
                "<li id>" + results[i].moddir +
                " (<a href=\"#\" onclick=\"deleteMod('" + results[i].moddir +
                "');\">del</a>)</li>"
            );
        }
    },
    error: function(myxhr, mystatus, myerror) {
        console.log("failure");
        $("#modlist").html("failure");
    }
});

</script>

<!-- whatever you want goes here -->
<ul id="modlist"></ul>



<!-- and finish off with a few closing tags-->
<?php include "foot.php" ?>
