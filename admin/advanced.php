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
    }
    #tasks li { border-top: 1px solid #ccc; padding: 10px 0px; }
    #tasks li:first-child { border-top: none; }
    #tasks button {
        font-size: x-small;
        background: #eee;
        border: 1px solid #ccc;
        float: right;
        height: 24px;
        margin: 0 0 5px 5px;
    }
    #tasks img {
        position: absolute;
        right: 0; top: 50%;
        margin-top: -12px;
        margin-right: -32px;
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
            $("#modlist").prepend(
                "<li id=\"" + results.moddir + "\">" + results.moddir +
                "<a href=\"javascript:void(0)\" onclick=\"delMod('" + results.moddir + "')\">del</a></li>\n"
            );
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

    if (!confirm("Are you sure you want to delete " + moddir + "?")) {
        return false;
    }

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
    $(mybutton).parent().append("<img src=\"../art/spinner.gif\">");
    $(mybutton).parent().find("button").prop("disabled", true);
    $(mybutton).parent().find("button").blur();

    $.ajax({
        url: "background.php?cancelTask=" + task_id,
        success: function(results) {
            console.log(results.task_id);
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
            var arrayLength = results.length;
            if (arrayLength == 0) {
                $("#tasks").append("<li>None</li>");
            }

            // loop through and display the tasks
            for (var i = 0; i < arrayLength; i++) {

                var age = secondsToHms(results[i].tasktime - results[i].started);
                var newHTML = "<li id=\"task" + results[i].task_id + "\"><button onclick=\"cancelTask('" + results[i].task_id + "', this)\">Cancel</button>";

                if (results[i].last_update < results[i].tasktime - freshtime) {
                    newHTML += " <button onclick=\"retryTask('" + results[i].task_id + "', this)\">Retry</button>";
                }

                newHTML += "<b>command:</b> " + results[i].command + "<br>" +
                              "<b>runtime:</b> " + age;

                if (results[i].last_update < results[i].tasktime - freshtime) {
                    if(results[i].completed) {
                        newHTML += " <span style=\"color: #933; font-weight: bold;\">failed</span>";
                    } else {
                        newHTML += " <span style=\"color: #933; font-weight: bold;\">stalled</span>";
                    }
                }

                // error takes precedence over normal output (could show both?)
                var out_tail = "";
                if (results[i].stderr_tail) {
                    out_tail = results[i].stderr_tail.replace(/\n+/g, "<br>");
                } else if (results[i].stdout_tail) {
                    out_tail = results[i].stdout_tail.replace(/\n+/g, "<br>");
                }
                newHTML += "<br><b>latest output:</b><p style=\"margin: 0 0 0 20px;\">" + out_tail + "</p></li>";

                $("#tasks").append(newHTML);
/*
    // we need to make it so that polling doesn't blow away the cancelling tasks
                if (tasksInCancel.find(function (task) { task[0] == results[i].task_id })) { 
                    cancelTask( results[i].task_id );
                }
*/
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

// populate the remote module list (downloadable)
// -- this is called once the local module list is known
function populateRemoteModuleList() {

    // before we make changes to the list, we adjust the UI
    $("#addmodbut").remove(); // remove the button
    $("#available").empty();  // empty the list
    $("#available").append("<option>Loading...</option>"); // add "Loading" text
    $("#available").after("<img src=\"../art/spinner.gif\" id=\"availspin\">"); // add spinner

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
            $("#availspin").remove();
            $("#available").empty();

            // now we can populate the list
            var lastLang = false;
            for (var i = 0; i < arrayLength; i++) {

                // skip if it already appears locally
                if ( $('#modlist').find(sel(results[i].moddir)).length ) {
                    continue;
                }

                // put a separator if it's a different language
                if (lastLang && lastLang != results[i].moddir.substring(0,2)) {
                    $("#available").append("<option disabled>──────────</option>\n");
                }
                lastLang = results[i].moddir.substring(0,2);

                // finally, add the entry for this module
                $("#available").append(
                    "<option value=\"" + results[i].moddir + "\">"
                    + results[i].moddir + "</option>"
                );
            }

            // now that we've got a list we can add the button back
            $("#available").after("<button id=\"addmodbut\" onclick=\"addMod();\">Submit</button>");

        },
        error: function(myxhr, mystatus, myerror) {
            $("#available").empty();
            $("#availspin").remove();
            $("#available").append("<option>Internal Error</option>");
        },
        timeout: ajaxTimeout
    });

}

// onload
$(function() {

    // populate the local module list (deletable)
    $.ajax({
        url: "background.php?getLocalModuleList=1",
        success: function(results) {
            var arrayLength = results.length;
            for (var i = 0; i < arrayLength; i++) {
                $("#modlist").append(
                    "<li id=\"" + results[i].moddir + "\">" + results[i].moddir +
                    "<a href=\"javascript:void(0)\" onclick=\"delMod('" + results[i].moddir + "')\">del</a></li>\n"
                );
            }
        },
        error: function(myxhr, mystatus, myerror) {
            $("#modlist").append("<li>getLocalModuleList: Internal Error</li>");
        },
        complete: function(xhr, status) {
            // don't fire this off until after we've heard back
            populateRemoteModuleList();
        },
        // because we need to know what's local before we can populate the remote list
        timeout: ajaxTimeout
    });

    // start polling the task list
    pollTasks();

});

</script>

<h3>Add Modules</h3>
<form style="position: relative;">
    <select id="available">
    </select>
</form>

<h3>Currently Adding</h3>
<ul id="tasks">
</ul>

<!-- whatever you want goes here -->
<h3>Delete Modules</h3>
<ul id="modlist">
</ul>

<!-- and finish off with a few closing tags-->
<?php include "foot.php" ?>
