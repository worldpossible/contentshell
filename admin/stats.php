<?php
require_once("common.php");
if (!authorized()) { exit(); }

$page_title  = $lang['stats'];
$page_script = "";
$page_nav    = "stats";
include "head.php";

?>

<style>
body { 
  margin:0px;
  padding:0px;
  overflow:hidden"
}

.content {
  width:100%;

}

#stats_frame {
  margin:0px;
  padding:0px;
  display: block;
  border: none;
  height: 100vh;
  width: 100%;
}
</style>

<script>

function fadePanel(html, time){
    $("#stats_message").fadeOut( time, function() {
        $("#main_panel").html(html);
        $("#main_panel").fadeIn(time).delay(5000);
    });
}

$.ajax({
    url: "background.php?getStats",
    success: function(response) {
        var doc = document.getElementById('stats_frame').contentWindow.document;
        doc.open();
        doc.write(response);
        doc.close();
        $("#stats_message").hide();
    },
    error: function(response){
        $("#stats_message").html(response.responseJSON['responseText']);
    }
});


</script>

<div id="stats_message">
  <h4 class="section-heading">Generating Statistics. This may take a minute  <div class="spinner-border text-info" role="status"></span></div>
  </h4>
</div>

<iframe id="stats_frame" frameborder="0" width="100%" height="100vh" ></iframe> 


