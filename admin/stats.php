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
  height: calc(100vh - 180px);
  width: 100%;
}

.info-box { background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 6px; padding: 15px; margin-bottom: 20px; }
.info-box h3 { margin-top: 0; color: #0369a1; }
.warning-box { background: #fff7ed; border: 1px solid #f97316; border-radius: 6px; padding: 15px; margin-bottom: 15px; }
.warning-box h4 { margin-top: 0; color: #c2410c; }
</style>

<script>

function fadePanel(html, time){
    $("#stats_message").fadeOut( time, function() {
        $("#main_panel").html(html);
        $("#main_panel").fadeIn(time).delay(5000);
    });
}

function loadStats() {
    $("#stats_message").show();
    $("#stats_message h4").html('Generating Statistics. This may take a minute <span class="spinner"></span>');
    
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
            $("#stats_message h4").html('<span style="color:#dc2626;">Error: ' + (response.responseJSON ? response.responseJSON.responseText : 'Failed to load stats') + '</span>');
        }
    });
}

$(function() {
    loadStats();
});

</script>

<div class="info-box" style="background:#eff6ff; border:2px solid #3b82f6; margin-bottom:20px;">
    <h3 style="margin-top:0; color:#1e40af;">DataPost - Share Usage Data with Your Organization</h3>
    <p><strong>DataPost</strong> is a service that securely transmits usage statistics from your RACHEL device back to your organization or the original purchaser. This helps track educational impact, identify popular content, and plan for future deployments.</p>
    <p style="margin-bottom:0; font-size:0.9em; color:#64748b;">Contact World Possible for more information about enabling DataPost on your device.</p>
</div>

<div class="warning-box" style="margin-bottom:15px;">
    <h4 style="margin-top:0;">About Usage Statistics</h4>
    <p>Statistics are generated from the nginx access log and show all recorded activity.</p>
    <p style="margin-bottom:0; font-size:0.9em; color:#92400e;"><strong>Privacy Note:</strong> If DataPost is enabled, anonymized usage information will be shared with the original purchaser or your organization to help measure educational impact.</p>
</div>

<div id="stats_message">
  <h4 class="section-heading">Generating Statistics. This may take a minute <span class="spinner"></span></h4>
</div>

<iframe id="stats_frame" frameborder="0" width="100%" height="100vh"></iframe>

<?php include "foot.php" ?>