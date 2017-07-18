<?php
require_once("common.php");
if (!authorized()) { exit(); }
$page_title = $lang['version'];
$page_script = "";
$page_nav = "version";
include "head.php";
?>

<style>
    #avail_something {
        color: #393;
        font-size: small;
        float: right;
        display: none;
        font-weight: normal;
        margin-right: 5px;
    }
    .progbar {
        width: 200px;
        border: 1px solid #999;
        position: relative;
        float: right;
        display: none;
        margin-left: 20px;
    }
    .progbarin {
        width: 0;
        background: #999;
        white-space: nowrap;
        font-family: sans-serif;
        padding: 2px;
    }
    th {
        background: #ddd;
    }
</style>

<script>

// cache of module info - we get it when we check for updates
// and then we use it for generating the progress bar later
var modules = {};

// this function is called in two ways - depending on the
// the contents of the button - 1) to check for available
// updates, and 2) to actually get the update for contentshell
// module updates are handled separately
function selfUpdate() {

    var button = $("#updatebut");

    button.prop("disabled", true);
    $("#spinner").show();

    if (button.html().match(/^Check/)) {

        // this checks the dev server for a list of version numbers
        // we use this to display what updates are available
        // XXX we've already got the info (in var modules) if there
        // were unfinished tasks, so we should really split apart
        // getting the info and using it so we never have to do it twice
        $.ajax({
            url: "background.php?selfUpdate=1&check=1",
            success: function(results) {
                modules = results; // save this for later
                // check we've got a decent looking version number
                if (results.contentshell.match(/^v\d+\.\d+\.\d+$/)) {
                    if (results.contentshell == $("#cur_contentshell").html()) {
                        button.css("color", "green");
                        button.html("&#10004; Up to date");
                    } else {
                        button.html(results.contentshell + " Available");
                        button.prop("disabled", false);
                    }
                } else {
                    button.css("color", "#c00");
                    button.html("X Internal Error (1)");
                }
                var updates_available = false;
                // now we go down the list looking for installed modules
                // that need updating as well
                for (var mod in results) {
                    // being safe with hashes in javascript
                    if (results.hasOwnProperty(mod)) {
                        // contentshell is a special case handled above
                        if (mod == "contentshell") { continue; }
                        // if both versions exist, and there's any difference, we call it an "update"
                        if ( $("#cur_"+mod).html() && results[mod].version && $("#cur_"+mod).html() != results[mod].version ) {
                            console.log("'"+$("#cur_"+mod).html()+"' vs. '"+results[mod].version+"'");
                            $("#avail_"+mod).html(results[mod].version + " Available");
                            $("#avail_"+mod).show();
                            updates_available = true;
                        }
                    }
                }
                // this indicates there was at least *one* content update available
                if (updates_available) {
                    $("#avail_something").show();
                    $("#update_all_button").show();
                }
            },
            error: function() {
                button.css("color", "#c00");
                button.html("X Can't Connect");
            },
            complete: function() {
                $("#spinner").hide();
            }
        })

    } else if (button.html().match(/ Available$/)) {

        // this actually requests the update - contentshell updates
        // are relatively small, so we just do a spinner, not a progress bar
        // module updates are handled elsewhere
        $.ajax({
            url: "background.php?selfUpdate=1",
            success: function(results) {
                button.css("color", "green");
                button.html("&#10004; Up to date");
                $("#cur_contentshell").html(results.version);
            },
            error: function() {
                button.css("color", "#c00");
                button.html("X Internal Error (2)");
            },
            complete: function() {
                $("#spinner").hide();
            }
        });

    } else {
        // invalid
        button.prop("disabled", true);
        $("#spinner").hide();
    }

}

// this requests an update for a single module
function modUpdate(moddir) {

    var button = $("#avail_"+moddir);
    var prog = $("#prog_"+moddir);
    var progin = $("#progin_"+moddir);

    button.prop("disabled", true);

    $.ajax({
        url: "background.php?modUpdate="+moddir,
        success: function(results) {
            button.hide();
            prog.show();
            progin.html('Waiting...');
            pollTasks();
        },
        error: function() {
            button.css("color", "#c00");
            button.html("X Internal Error (3)");
        },
    });

}

function updateAllMods() {

    $("#update_all_button").prop("disabled", true);
    $("#update_all_spinner").show();

    // You should know this is the strangest
    // jquery selector I've ever used! - Flynn Code Rider
    // (find all buttons, id starting with "avail_", and visible)
    var updates =  $('button[id^="avail_"]:visible');

    // go through and get the moddir of each module with
    // an update available  and udpate the interface
    var moddirs = [];
    for (var i = 0; i < updates.length; ++i) {
        var moddir = updates[i].id.substring(6); // remove the "avail_" to get the moddir
        $("#avail_" +moddir).hide(); // hide the module's update button
        $("#prog_"  +moddir).show(); // show the module's progress bar
        $("#progin_"+moddir).html('Waiting...'); // add this inside the progress bar
        moddirs.push(moddir);
    }

    // make it a comma-separated list
    moddir_list = moddirs.join();

    $.ajax({
        url: "background.php?addModules=" + moddir_list,
        success: function(results) {
            // start polling (are we already polling?)
            pollTasks();

            // update button UI
            $("#update_all_spinner").hide();
            $("#update_all_button").css("color", "green");
            $("#update_all_button").html("&#10004; Update Process Started");

        },
        error: function(xhr, status, error) {
            // notify via button
            $("#update_all_spinner").hide();
            $("#update_all_button").css("color", "#c00");
            $("#update_all_button").html("X Internal Error (6)");
        }
    });



}

// this checks the status of updates
var polling = false;
function pollTasks() {

    if (polling) {
        return;
    }

//console.log("Polling");

    $.ajax({
        url: "background.php?getTasks=1&includeVersion=1",
        success: function(results) {

            var in_progress = false;
            var arrayLength = results.length;

            // we go through the results (tasks) and update
            // the view accordingly
            for (var i = 0; i < arrayLength; i++) {

                var button = $("#avail_"  + results[i].moddir);
                var prog   = $("#prog_"   + results[i].moddir);
                var progin = $("#progin_" + results[i].moddir);

                if (results[i].retval > 0) {

                    // there was a problem with the rsync -- error out
                    button.css("color", "#c00");
                    button.html("X Internal Error (4)");
                    button.prop("disabled", true);
                    prog.hide();
                    button.show();

                } else if (results[i].completed && results[i].retval == 0) {

                    // the rsync completed normally
                    button.css("color", "green");
                    button.html("&#10004; Up to date");
                    button.prop("disabled", true);
                    prog.hide();
                    button.show();
                    $("#cur_" + results[i].moddir).html(results[i].version);

                } else if (results[i].started) {

                    // the first time the page is viewed (before any button clicking)
                    // the state of the button and progress bar will need to be changed
                    // -- but to avoid having a flag, we just do it every time
                    button.hide();
                    prog.show();

                    // this means we're downloading
                    // get info about the size (files and data)
                    // this is from a cached copy of the remote module list
                    // which means it might not be there if this task is already
                    // being displayed before it's populated
                    if (modules[ results[i].moddir ]) {
                        var total_data  = modules[ results[i].moddir ].ksize;
                        var data_done   = Math.round( results[i].data_done / 1024 );
                        // the number multiplied at the end has to match the physical
                        // width of the progressbar as set in the css
                        var data_perc  = Math.round((data_done / total_data) * 200);
                    } else {
// XXX why do we do another check here? is this to do with visiting the
// XXX page when updates are already underway?
                        $.ajax({
                            url: "background.php?selfUpdate=1&check=1",
                            success: function(results) { modules = results; },
                        });
                    }

                    progin.width(data_perc);
                    progin.html('Updating...');
                    in_progress = true;

                } else {
                    console.log("Waiting: " + results[i].moddir);
                    // we get here if we haven't reached any new stage yet
                    // (shows "Waiting...") which counts as "in_progress"
                    in_progress = true;
                }
            }
            // if there's anything still happening,
            // we need to poll again
// we have to be smarter about when to poll - sometimes new tasks
// don't show up but we still want to poll again until they do
// -- perhaps have a flag that a task has been started?
//            if (in_progress) {
//                    console.log("Set to Poll again in 2 seconds...");
                    // clear the mutex, and poll
                    setTimeout("polling = false; pollTasks();", 2000);
                    polling = true;
//            }


        },
        error: function() {
            button.css("color", "#c00");
            button.html("X Internal Error (5)");
            polling = false;
        },
    });

}


// this cancels a task
/*
function cancelTask(task_id, mybutton) {

    $(mybutton).parent().css({ opacity: 0.5 });
    $(mybutton).parent().find("button").prop("disabled", true);
    $(mybutton).parent().find("button").blur();

    $.ajax({
        url: "background.php?cancelTask=" + task_id,
        success: function(results) {
            //console.log(results.task_id);
        },
        error: function(xhr, status, error) {
            var results = JSON.parse(xhr.responseText);
            console.log(error);
        }
    });
}
*/

// onload - poll the tasks for what's in progress
$(function() { pollTasks() });

</script>

<?php

# this should work on debian variants
foreach (glob("/etc/*-release") as $filename) {
    $filecont = file_get_contents($filename);
    if (preg_match("/PRETTY_NAME=\"(.+?)\"/", $filecont, $matches)) {
        $os = $matches[1];
        break;
    }
}

# this should work on redhat variants
if (!isset($os)) {
    foreach (glob("/etc/*-release") as $filename) {
        $os = file_get_contents($filename);
        break;
    }
}

# this works on remaining unix systems (i.e. Mac OS)
if (!isset($os)) { $os = exec("uname -srmp"); }

# this gets the hardware version on rpi systems
$hardware = "";
unset($output, $matches);
exec("dmesg 2>&1 | grep 'Machine model'", $output);
if (isset($output[0]) && preg_match("/Machine model: (.+)/", $output[0], $matches)) {
    $hardware = $matches[1];
} else {

    # rachel plus idenitifcation
    $plusmodel = exec("uname -n");
    if ($plusmodel == "WRTD-303N-Server") {
        $hardware .= "Intel CAP 1.0";
    } else if ($plusmodel == "WAPD-235N-Server") {
        $hardware .= "Intel CAP 2.0";
    }

    exec("arch", $output);
    if ($output) {
        if ($hardware) {
            $hardware .= " ($output[0])";
        } else {
            $hardware = $output[0];
        }
    }

}

$rachel_installer_version = "?";
if (file_exists("/etc/rachelinstaller-version")) {
    $rachel_installer_version = file_get_contents("/etc/rachelinstaller-version");
}

$kalite_version = exec("export USER=`whoami`; kalite --version;");
if (!$kalite_version || !preg_match("/^[\d\.]+$/", $kalite_version)) {
    $kalite_version = "?";
}

$kiwix_version = exec("cat /var/kiwix/application.ini | grep ^Version | cut -d= -f2");
if (!$kiwix_version || !preg_match("/^[\d\.]+$/", $kiwix_version)) {
    $kiwix_version = "?";
}

?>

<h2>RACHEL Version Info</h2>
<table class="version">
<tr><th colspan="2">System Sofware</th></tr>
<tr><td>Hardware</td><td><?php echo $hardware ?></td></tr>
<tr><td>OS</td><td><?php echo $os ?></td></tr>
<tr><td>RACHEL Installer</td><td><?php echo $rachel_installer_version ?></td></tr>
<tr><td>KA Lite</td><td><?php echo $kalite_version ?></tr>
<tr><td>Kiwix</td><td><?php echo $kiwix_version ?></td></tr>
<tr><td>Content Shell</td><td>

    <span id="cur_contentshell">v2.3.4</span>
    <div style="float: right; margin-left: 20px;">
        <div style="float: left; width: 24px; height: 24px; margin-top: 2px;">
            <img src="../art/spinner.gif" id="spinner" style="display: none;">
        </div>
        <button id="updatebut" onclick="selfUpdate();" style="margin-left: 5px;">Check for Updates</button>
    </div>


</td></tr>
<tr><th colspan="2">Module<div id='avail_something'>Module Updates Available Below</div></th></tr>

<?php
    # get module info
    foreach (getmods_fs() as $mod) {
        echo "
            <tr><td>$mod[moddir]</td><td>
              <span id='cur_$mod[moddir]'>$mod[version]</span>
              <button style='display: none; float: right; margin-left: 20px;' id='avail_$mod[moddir]' onclick=\"modUpdate('$mod[moddir]');\"></button>
              <div class='progbar' id='prog_$mod[moddir]'><div class='progbarin' id='progin_$mod[moddir]'></div></div>
            </td></tr>
        ";
        #echo "<div class='update_available' id='avail_$mod[moddir]'> Update Available</span></td></tr>\n";
    }
?>

<tr>
<td colspan="2" style="text-align: right;">
    <img src="../art/spinner.gif" id="update_all_spinner" style="display: none; vertical-align: text-bottom;">
    <button id="update_all_button" onclick="updateAllMods();" style="display: none;">Update All Modules</button>
</td>
</tr>

</table>

<ul style="margin-top: 40px;">
<li>blank indicates the item predates versioning.</li>
<li>? indicates the version could not be determined, and perhaps the item is not actually installed</li>
</ul>

</div>
</body>
</html>
