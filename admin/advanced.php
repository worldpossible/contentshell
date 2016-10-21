<?php
require_once("common.php");
if (!authorized()) { exit(); }
$page_title = "Advanced";
$page_script = "";
$page_nav = "advanced";
include "head.php";

$mods_fs = getmods_fs();

?>

<style>
    #modlist {
        list-style: none;
        padding-left: 10px;
    }
    #modlist li {
        width: 20em;
        position: relative;
        border-bottom: 1px solid #999;
        margin-bottom: 10px;
    }
    #modlist a {
        text-decoration: none;
        position: absolute;
        display: inline-block;
        border: 1px solid #999;
        right: 0; bottom: 0;
        padding: 1px 5px 0 5px;
        margin: 0 0 -1px 0;
    }
    #modlist img {
        position: absolute;
        right: 0; top: 50%;
        margin-top: -12px;
        margin-right: -32px;
    }
    #available {
        margin: 10px;
    }
    #availspin {
        margin: 0; padding: 0;
        position: relative;
        top: 8px;
        left: 5px;
        display: none;
    }
    #tasks {
        font-family: monospace;
        list-style: none;
        padding: 5px 10px;
        margin: 0;
        background: #ddd;
        display: inline-block;
        border-radius: 5px;
        position: relative;
        width: 700px;
    }
    #tasks li { border-top: 1px solid #ccc; padding: 10px 0px; }
    #tasks li:first-child { border-top: none; }
    #tasks button {
        font-size: x-small;
        background: #eee;
        border: 1px solid #ccc;
        float: right;
        /* for when it used to turn to a spinner */
        /* height: 24px; */
        width: 5em;
        margin: 0 0 5px 5px;
    }
    #tasks img {
        position: absolute;
        right: 0; top: 50%;
        margin-top: -12px;
        margin-right: -32px;
    }
    .progbar {
        width: 400px;
        border: 1px solid #999;
        position: relative;
    }
    .progbarin {
        width: 0;
        background: #999;
        white-space: nowrap;
        padding: 4px;
        font-family: sans-serif;
    }
    .progbarend {
        white-space: nowrap;
        padding: 4px;
        position: absolute;
        top: 0; right: 0;
    }
    .details {
        clear: right;
        margin-top: 10px;
    }
</style>

<script>

var ajaxTimeout = 10000;
var taskRefreshRate = 2000;

// need to convert the string that comes back because
// we need to escape "." in the selector name
function sel (text) {
    var selector = "#" + text;
    selector = selector.replace(/\./, "\\.");
    return selector;
}

// populated on load so that we can sort
// the languages with the most modules first
var langCount = {};
function compare(a,b) {
    var alang = langCount[a.lang];
    var blang = langCount[b.lang];
    var aname = a.moddir.toLowerCase();
    var bname = b.moddir.toLowerCase();
    if (alang > blang) return -1; 
    if (alang < blang) return 1; 
    if (aname < bname) return -1; 
    if (aname > bname) return 1; 
    return 0;
} 

// convert number of seconds to a HH:MM:SS duration
function secondsToHms(t) {
    t = Number(t);
    var h = Math.floor(t / 3600);
    var m = Math.floor(t % 3600 / 60);
    var s = Math.floor(t % 3600 % 60);
    return ((h > 0 ? h + ":" + (m < 10 ? "0" : "") : "") + m + ":" + (s < 10 ? "0" : "") + s);
}

// add (i.e. rsync) a module
function addMod() {

    $("#addmodbut").prop("disabled", true);
    var moddir = $("#available").val()
    $.ajax({
        url: "background.php?addModule=" + moddir,
        success: function(results) {
            // take the added item out of the available list
            $("#available option[value=\"" + sel(results.moddir).substring(1)  + "\"]").remove();
            // add the added item to the deleteable list
/*
            $("#modlist").prepend(
                "<li id=\"" + results.moddir + "\">" + results.moddir +
                "<a href=\"javascript:void(0)\" onclick=\"delMod('" + results.moddir + "')\">del</a></li>\n"
            );
*/
            // re-enable the button
            $("#addmodbut").prop("disabled", false);
        },
        error: function(xhr, status, error) {
            console.log("failure");
            // notify via button
        }
    });

}

// delete (i.e. rm -rf) a module
function delMod(moddir) {

/*
    if (!confirm("Are you sure you want to delete " + moddir + "?")) {
        return false;
    }
*/

    $(sel(moddir)).css({ opacity: 0.5 });
    $(sel(moddir)).append("<img src=\"../art/spinner.gif\">");
    $(sel(moddir)).children()[0].remove();

    $.ajax({
        url: "background.php?deleteModule=" + moddir,
        success: function(results) {
            $(sel(results.moddir)).remove();
            populateRemoteModuleList();
        },
        error: function(xhr, status, error) {
            var results = JSON.parse(xhr.responseText);
            $(sel(moddir)).css({
                "background-color" : "#fcc",
                "padding" : "5px",
                "margin-left" : "-5px",
                "border" : "1px solid #933",
                "color" : "#933",
                "font-weight" : "bold",
                "opacity" : "1.0"
            });
            $(sel(moddir)).html( "Internal Error: " + results.error );
        }
    });

}

function cancelTask(task_id, mybutton) {

    $(mybutton).parent().css({ opacity: 0.5 });
    // $(mybutton).parent().append("<img src=\"../art/spinner.gif\">");
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

// check if we have any background tasks running
function pollTasks() {

    var freshtime = 5; // number of seconds after which we consider a task "stale"

    $.ajax({
        url: "background.php?getTasks=1",
        success: function(results) {

            $("#tasks").empty();


            var hadtasks = false;
            var reloadLocal = false;

            // loop through and display the tasks
            var arrayLength = results.length;
            for (var i = 0; i < arrayLength; i++) {

                // for completed tasks we get a task with a "dismissed" time set
                if (results[i].dismissed) {
                    reloadLocal = true;
                    continue;
                }

                var age = secondsToHms(results[i].tasktime - results[i].started);

                // detect stalled tasks and mark them XXX should move this logic
                // to background.php methinks
                var infoflag = "";
                var butlabel = "cancel";
                if (results[i].last_update < results[i].tasktime - freshtime) {
                    // we no longer add a retry button, just let them manually do it again
                    //newHTML += " <button onclick=\"retryTask('" + results[i].task_id + "', this)\">retry</button>";
                    butlabel = "clear"; 
                    if(results[i].completed) {
                        infoflag = " <span style=\"color: #933; font-weight: bold;\">failed</span>";
                    } else {
                        infoflag = " <span style=\"color: #933; font-weight: bold;\">stalled</span>";
                    }
                }

                var newHTML = (
                    "<li id='task" + results[i].task_id + "'>" +
                    "<button onclick=\"cancelTask('" + results[i].task_id + "', this)\">" + butlabel + "</button>" +
                    "<button onclick=\"toggleDetails('#details-" + results[i].task_id + "');\">details</button>"
                );

                // get info about the size (files and data)
                // this is from a cached copy of the remote module list
                // which means it might not be there if this task is already
                // being displayed before it's populated
                if (remotemodlist[ results[i].moddir ]) {
                    var total_files = remotemodlist[ results[i].moddir ].file_count;
                    var total_data  = remotemodlist[ results[i].moddir ].ksize;
                    var files_done  = results[i].files_done;
                    var data_done   = Math.round( results[i].data_done / 1024 );
                    var files_perc = Math.round((files_done / total_files) * 100);
                    var data_perc  = Math.round((data_done / total_data) * 100);
                } else {
                    var total_files = "-";
                    var total_data  = "-";
                    var files_done  = "-";
                    var data_done   = "-";
                    var files_perc = "-";
                    var data_perc  = "-";
                }

                detailstyle = "style='display: none;'";
                if (detailShow["#details-" + results[i].task_id]) {
                    detailstyle = "style='display: block;'";
                }

                // put the actual readout in there
                newHTML += (
                    "<div class='progbar'><div class='progbarin' style='width: " + data_perc + "%;'>" +
                    results[i].moddir + infoflag + "<div class='progbarend'>" + results[i].data_rate +
                    "</div></div></div>" +
                    "<div " + detailstyle + " class='details' id='details-" + results[i].task_id + "'>" +
                    "<b>command:</b> " + results[i].command + "<br>" +
                    "<b>runtime:</b> " + age + infoflag + "<br>" +
                    "<b>files_done:</b> " + files_done + " out of " + total_files + " ( " + files_perc + "% )<br>" +
                    "<b>data_done:</b> "  + data_done  + " out of " + total_data  + " ( " + data_perc  + "% )<br>" +
                    "<b>data_rate:</b> "  + results[i].data_rate + "<br>"
                );

                // error takes precedence over normal output (we could show both?)
                var out_tail = "";
                if (results[i].stderr_tail) {
                    out_tail = results[i].stderr_tail.replace(/\n+/g, "<br>");
                } else if (results[i].stdout_tail) {
                    out_tail = results[i].stdout_tail.replace(/\n+/g, "<br>");
                }
                newHTML += "<b>latest output:</b><p style=\"margin: 0 0 0 20px;\">" + out_tail + "</p></div></li>";

                $("#tasks").append(newHTML);

                hadtasks = true;

            }

            if (!hadtasks) {
                $("#tasks").append("<li>None</li>");
            }

            // if there were any completed tasks, we update the local module list
            if (reloadLocal) {
                populateLocalModuleList();
            }

        },
        error: function(xhr, status, error) {
            console.log("Failed to get tasks");
        },
        complete: function(xhr, status) {
            setTimeout("pollTasks()", taskRefreshRate);
        },
        timeout: ajaxTimeout
    });

}

// toggles the visibility of "details" for the transfer
// which is easy enough with jquery... but since we refresh
// every few seconds we have to keep a record of the state, too
var detailShow = {};
function toggleDetails(id) {
    $(id).toggle();
    if (detailShow[id]) {
        delete detailShow[id];        
    } else {
        detailShow[id] = true;
    }
}

// populate the remote module list (downloadable)
// -- this is called once the local module list is known
//
// store a copy so we know how big these things are for our progress meter
var remotemodlist = {};
function populateRemoteModuleList() {

    // before we make changes to the list, we adjust the UI
    $("#addmodbut").prop("disabled", true); // - disable button
    $("#availspin").show();                 // - add spinner
    $("#available").empty();                // - empty the list
    $("#available").append("<option>Loading...</option>"); // add "Loading" text

    // now we make our ajax call to get the latest list
    $.ajax({
        url: "background.php?getRemoteModuleList=1",
        success: function(results) {

            // this will allow us to sort stuff with the
            // most popular module languages first
            var arrayLength = results.length;
            for (var i = 0; i < arrayLength; i++) {
                if (results[i].lang in langCount) {
                    ++langCount[ results[i].lang ];
                } else {
                    langCount[ results[i].lang ] = 1;
                }
            }
            // now do the sort
            results.sort(compare);

            // clear the existing list and spinner
            $("#availspin").hide();
            $("#available").empty();

            // now we can populate the list
            var lastLang = false;
            for (var i = 0; i < arrayLength; i++) {

                // store it for later (by moddir)
                remotemodlist[results[i].moddir] = results[i];

                // skip if it already appears locally
                if ( $('#modlist').find(sel(results[i].moddir)).length ) {
                    continue;
                }

                // put a separator if it's a different language
                if (lastLang && lastLang != results[i].moddir.substring(0,2)) {
                    $("#available").append("<option disabled>──────────</option>\n");
                }
                lastLang = results[i].moddir.substring(0,2);

                // calc size in GB
                var gbsize = ((results[i].ksize/1024)/1024).toFixed(1)
                if (gbsize <  0.1) { gbsize = "0.1"; }

                // finally, add the entry for this module
                $("#available").append(
                    "<option value=\"" + results[i].moddir + "\">"
                    + results[i].moddir + " -- "
                    + gbsize + " GB</option>"
                );
            }

            // now that we've got a list we can add the button back
            $("#addmodbut").prop("disabled", false); // disable button

        },
        error: function(myxhr, mystatus, myerror) {
            $("#availspin").hide();
            $("#available").empty();
            $("#available").append("<option>Internal Error</option>");
        },
        timeout: ajaxTimeout
    });

}

var firstCall = true;
function populateLocalModuleList() {

    // populate the local module list (deletable)
    $.ajax({
        url: "background.php?getLocalModuleList=1",
        success: function(results) {
            $("#modlist").empty();
            var arrayLength = results.length;
            for (var i = 0; i < arrayLength; i++) {
                $("#modlist").append(
                    "<li id=\"" + results[i].moddir + "\">" + results[i].moddir +
                    "<a href=\"javascript:void(0)\" onclick=\"delMod('" + results[i].moddir + "')\">del</a></li>\n"
                );
            }
        },
        error: function(myxhr, mystatus, myerror) {
            $("#modlist").empty();
            $("#modlist").append("<li>getLocalModuleList: Internal Error</li>");
        },
        complete: function(xhr, status) {
            // first time we get the local we populate the remote list too...
            // we don't fire this off until after we've heard back
            // because we need to know what's local to filter the remote list
            if (firstCall) {
                populateRemoteModuleList();
                firstCall = false;
            }
        },
        timeout: ajaxTimeout
    });

}

// onload
$(function() {

    // remote list gets populated automatically the
    // first time we call this
    populateLocalModuleList();

    // start polling the task list
    pollTasks();

});

</script>

<h3>Add Modules</h3>
<form style="position: relative;">
    <select id="available">
    </select>
    <button id="addmodbut" onclick="addMod();" disabled>Submit</button>
    <img src="../art/spinner.gif" id="availspin">
</form>

<h3>Currently Adding</h3>
<ul id="tasks">
</ul>

<h3>Delete Modules</h3>
<ul id="modlist">
</ul>

<?php include "foot.php" ?>
