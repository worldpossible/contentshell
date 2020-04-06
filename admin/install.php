<?php
require_once("common.php");
if (!authorized()) { exit(); }
$page_title = "Install";
$page_script = "";
$page_nav = "install";
include "head.php";

$mods_fs = getmods_fs();

$known_servers = array(
    "jeremy" => "192.168.1.10",
    "jfield" => "192.168.1.6",
);

# here is where we handle the "advanced" installation options,
# because of the file upload option, there's compatibility issues
# doing it in ajax through our background.php script, so we do it the
# old fashioned way here:

if (isset($_POST['advanced_install'])) {

    $error = false;

    # figure out which server we're using
    if (isset($_POST['server_custom']) && preg_match("/\w/", $_POST['server_custom'])) {

       $server = $_POST['server_custom'];

    } else if (isset($_POST['server'])) {

        if (isset($known_servers[ $_POST['server'] ])) {
            $server = $known_servers[ $_POST['server'] ];
        } else {
            $server = $_POST['server'];
        }

    } else {
        $error = true;
    }
    $server = preg_replace("/[^a-zA-Z0-9\-\.\_]+/", "", $server);

    # figure out which .modules file we're using
    if (!empty($_FILES['mfile_upload']['tmp_name'])) {
        $mfile = $_FILES['mfile_upload']['tmp_name']; 
    } else if (isset($_POST['mfile'])) {
        $mfile = preg_replace("/[^a-zA-Z0-9\-\.\_]+/", "", $_POST['mfile']);
        $mfile = "../scripts/" . $mfile . ".modules";
    } else {
        $error = true;
    }

    if (!$error) {
        installmods($mfile, $server);
    }

    # we redirect to ourseles avoid a reload calling another installation
    header("Location: http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
    exit;

}

?>

<style>
    /* existing modules list (for deleting) */
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
    #modlist button {
        font-size: small;
        position: absolute;
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

    #availformbox {
        background: #ddd;
        border-radius: 5px;
        padding: 5px;
    }

    /* available module list */
    #availform input {
        margin: 5px;
        color: #ccc;
    }
    #availform button {
        margin: 5px;
    }
    #available { /* the select box itself */
        margin: 5px;
        width: 20em;
    }
    #availspin, #advspin {
        margin: 0; padding: 0;
        position: relative;
        top: 8px;
        left: 5px;
        display: none;
    }

    /* advanced install */
    #advancedbut{
        font-size: x-small;
        background: #eee;
        border: 1px solid #ccc;
        float: right;
    }
    #advanced {
        xclear: right;
        xfloat: right;
        display: none;
    }
    #advanced input, #advanced select {
        margin: 5px;
    }
    #advanced select { min-width: 15em; }
    /* make submit look like button in our style.css */
    #advanced input[type=submit] {
        margin: 3px; padding: .25em 1em;
    }

    /* task list */
    #tasks {
        font-family: monospace;
        list-style: none;
        padding: 5px 10px;
        margin: 0;
        background: #ddd;
        /*display: inline-block; */
        border-radius: 5px;
        position: relative;
        /* width: 700px; */
    }
    #tasks li { border-top: 1px solid #ccc; padding: 10px 0px; }
    #tasks li:first-child { border-top: none; }
    #tasks button, #clearall {
        font-size: x-small;
        background: #eee;
        border: 1px solid #ccc;
        float: right;
        /* for when it used to turn to a spinner */
        /* height: 24px; */
        width: 5em;
        margin: 0 0 5px 5px;
    }
    #clearall {
	margin: 2em 1em 0 0;
	width: auto;
	display: none;
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
        padding: 1%;
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
    .multi-instructions {
	font-size: x-small;
	margin: 0 0 4px 6px;
    }
    .multi-instructions code {
	display: inline-block;
	border: 1px solid #999;
	padding: 1px;
        border-radius: 3px;
    }
</style>

<script>

var ajaxTimeout = 12000;
var taskRefreshRate = 3000;

var knownServers = <?php echo json_encode($known_servers); ?>;

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
    var alang = langCount[a.substring(0,2)];
    var blang = langCount[b.substring(0,2)];
    var aname = a.toLowerCase();
    var bname = b.toLowerCase();
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

// These functions are for managing part of the UI
// as a unit -- the "disabled" state of the "Add Modules" section
function setButtonState() {
    // the "download" button should only be
    // enabled if something is selected
    if ($("#available").val()) {
        $("#addmodbut").prop("disabled", false);
    } else {
        $("#addmodbut").prop("disabled", true);
    }
}
function disableAvailUI() {
    setButtonState();
    $("#available").prop("disabled", true);
    $("#livesearch").prop("disabled", true);
}
function enableAvailUI() {
    setButtonState();
    $("#available").prop("disabled", false);
    $("#livesearch").prop("disabled", false);
} 

// add (i.e. rsync) a module
function addMods(moddirs, server) {

    disableAvailUI();

    // we take an optional array of moddirs as an argument
    // (used by advanced install's checkReinstall()) but
    // if it's omitted, we use what's selected in the selection
    // box (normal behavior)
    if (!moddirs) {
        moddirs = $("#available").val();
    }

    // optional server argument, turn it into a query string addition
    if (server) {
        server = "&server=" + server;
    } else {
        server = "";
    }

    // nothing selected - nothing to do
    if (!moddirs) {
        enableAvailUI();
        return false;
    }

console.log("background.php?addModules=" + moddirs + server);

    // turn it into a comma-separated list
    moddirs = moddirs.join();

    $.ajax({
        url: "background.php?addModules=" + moddirs + server,
        success: function(results) {

            // honestly I'm not clear on why moddirs is available
            // inside the callback, but it is, so until I see weird
            // behavior I won't bother passing it in a more explicit way

            // take the added item out of the available list by adding
            // them to "current tasks"
            moddirs = moddirs.split(",");
            for (var i = 0; i < moddirs.length; ++i) {
                current_task_moddirs[ moddirs[i] ] = 1;;
            }

            // update the list to reflect this module is already added
            drawRemoteModuleList();
        },
        error: function(xhr, status, error) {
            console.log("failure");
            // XXX notify via button
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
            drawRemoteModuleList();
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

function cancelTask(task_id, moddir, mybutton) {

    $(mybutton).parent().css({ opacity: 0.5 });
    // $(mybutton).parent().append("<img src=\"../art/spinner.gif\">");
    $(mybutton).parent().find("button").prop("disabled", true);
    $(mybutton).parent().find("button").blur();

    $.ajax({
        url: "background.php?cancelTask=" + task_id,
        success: function(results) {
            delete current_task_moddirs[moddir];
            drawRemoteModuleList();
        },
        error: function(xhr, status, error) {
            var results = JSON.parse(xhr.responseText);
            console.log(error);
        }
    });
}

function retryTask(task_id, moddir, mybutton) {

    $(mybutton).parent().css({ opacity: 0.5 });
    $(mybutton).parent().find("button").prop("disabled", true);
    $(mybutton).parent().find("button").blur();

    $.ajax({
        url: "background.php?retryTask=" + task_id,
        success: function(results) {
            delete current_task_moddirs[moddir];
            drawRemoteModuleList();
        },
        error: function(xhr, status, error) {
            var results = JSON.parse(xhr.responseText);
            console.log(error);
        }
    });
}

function cancelAll() {
    $("#tasks").css({ opacity: 0.5 });	
    $("#tasks").find("button").prop("disabled", true);
    $("#tasks").find("button").blur();
    $.ajax({
        url: "background.php?cancelAll=1",
        success: function(results) {
            current_task_moddirs = [];
            drawRemoteModuleList();
        },
        error: function(xhr, status, error) {
            console.log(error);
            $("#tasks").css({ opacity: 1.0 });	
            $("#tasks").find("button").prop("disabled", false);
        }
    });
}

// check if we have any background tasks running
var current_task_moddirs = {};
function pollTasks() {

    var freshtime = 15; // number of seconds after which we consider a task "stale"

    $.ajax({
        url: "background.php?getTasks=1",
        success: function(results) {

            $("#tasks").empty();

            var hadtasks = false;

            // loop through and display the tasks
            var arrayLength = results.length;
            for (var i = 0; i < arrayLength; i++) {

                // for completed tasks we get a task with a "dismissed" time set
                // XXX known bug: if there are other windows open reading tasks
                // we'll miss the "dismissed" flag (the other window will get it)
                // so either we need send background.php more info (like a list
                // of the tasks we're monitoring) or we need to keep track ourselves.
                // but it all gets fixed when all the tasks complete anyway...
                if (results[i].dismissed) {
                    addToLocal(results[i].moddir);
                    continue;
                }

                var age = secondsToHms(results[i].tasktime - results[i].started);

                // detect stalled tasks and mark them XXX should move this logic
                // to background.php methinks
                var infoflag = "";
                var butlabel = "cancel";
                var retryButton = "";
                if (results[i].last_update < results[i].tasktime - freshtime) {
                    // we no longer add a retry button, just let them manually do it again
                    //newHTML += " <button type=\"button\" onclick=\"retryTask('" + results[i].task_id + "', this)\">retry</button>";
                    butlabel = "clear"; 
                    if(results[i].completed) {
                        infoflag = " <span style=\"color: #933; font-weight: bold;\">failed</span>";
                        // add a retry button
                        retryButton = " <button type=\"button\" onclick=\"retryTask('"
                                + results[i].task_id + "', '"
                                + results[i].moddir + "', this)\">retry</button>";
                    } else {
                        if (results[i].started) {
                            infoflag = " <span style=\"color: #933; font-weight: bold;\">stalled</span>";
                        } else {
                            infoflag = " <span style=\"color: #393; font-weight: bold;\">waiting</span>";
                            results[i].data_rate = "";
                        }
                    }
                }

                var newHTML = (
                    "<li id='task" + results[i].task_id + "'>" +
                    "<button type=\"button\" onclick=\"cancelTask('"
                        + results[i].task_id + "', '"
                        + results[i].moddir + "', this)\">" + butlabel + "</button>" +
                    retryButton +
                    "<button type=\"button\" onclick=\"toggleDetails('#details-" + results[i].task_id + "');\">details</button>"
                );

                // get info about the size (files and data)
                // this is from a cached copy of the remote module list
                // which means it might not be there if this task is already
                // being displayed before it's populated
                if (remotemod_hash[ results[i].moddir ]) {
                    var total_files = remotemod_hash[ results[i].moddir ].file_count;
                    var total_data  = remotemod_hash[ results[i].moddir ].ksize;
                    var files_done  = results[i].files_done;
                    var data_done   = Math.round( results[i].data_done / 1024 );
                    var files_perc = Math.round((files_done / total_files) * 100);
                    var data_perc  = Math.round((data_done / total_data) * 100);
                    // woudl be data_perc, but there's 1% padding (on each end)
                    var progbarin_width  = Math.round((data_done / total_data) * 98);
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
                    "<div class='progbar'><div class='progbarin' style='width: " + progbarin_width + "%;'>" +
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
		$("#clearall").show();

            }

            if (!hadtasks) {
                $("#tasks").append("<li>None</li>");

		// remove the 'clear all' button if there's nothing to clear
		$("#clearall").hide();
                // perhaps the reason there is nothing is because someone
                // just did a 'clear all', in which case we should reset
                // the opacity so that future tasks will be opaque
                $("#tasks").css({ opacity: 1.0 });
                // when all the tasks are done, we get a fresh copy of
                // the local module list, in case anything funny happened
                populateLocalModuleList();
                // XXX unintended side effect (but maybe ok?) this makes
                // it so that when there are no tasks (normal state)
                // the local "modlist" gets updated every poll -- that
                // means if you `mkdir modules/foo` then "foo" will show
                // up here without a page load. Cool, but perhaps too
                // much unintended server load? Also, if there are tasks
                // then it *doesn't* update the modlist until they're done.
                // Sort of weird.
            }

        },
        error: function(xhr, status, error) {
            // XXX if we can't connect (to ourselves - background.php) the
            // interface just stops updating (no "stalled" messsage)
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

// gets data about the remote modules from the central server
var remotemod_hash = {};
var remotemod_arr  = [];
function getRemoteModuleInfo(drawCallback) {

    $.ajax({
        url: "background.php?getRemoteModuleList=1",
        success: function(results) {

            // remove contentshell - should background.php do it for us?
            delete results['contentshell'];

            // do some processing and copy from hash to array
            remotemod_arr = [];
            for (var key in results) {
                if (results.hasOwnProperty(key)) {

                    // determine language from the moddir name (two leading chars)
                    results[key]['lang'] = results[key]['moddir'].substring(0,2);

                    // we calculate the size in gb for legibility
                    var gbsize = ((results[key]['ksize']/1024)/1024).toFixed(1);
                    if (gbsize <  0.1) { gbsize = "0.1"; }
                    results[key]['gbsize'] = gbsize;

                    // figure out which languages have the most modules
                    if (results[key]['lang'] in langCount) {
                        ++langCount[ results[key]['lang'] ];
                    } else {
                        langCount[ results[key]['lang'] ] = 1;
                    }

                    // put it into the sortable array
                    remotemod_arr.push( results[key]['moddir'] );

                }
            }

            // store the result hash for later
            remotemod_hash = results;
            // sort the array
            remotemod_arr.sort(compare);
        },

        // in case of error, the remotemod_hash will be empty
        complete: drawCallback,

        timeout: ajaxTimeout
    });

}

function drawRemoteModuleList() {

    $("#available").empty();

    if (!remotemod_arr.length) {
        $("#available").append("<option>Internal Error</option>");
    }

    // now we can populate the list
    var lastLang = false;
    var arrayLength = remotemod_arr.length;
    for (var i = 0; i < arrayLength; i++) {

        // the array is just for order, the module data is in the hash
        var module = remotemod_hash[ remotemod_arr[i] ];

        // we skip modules that are already installed (in the local list, via DOM)
        // and also those that are currently installing (tracked in current_task_moddir)
        if ( $('#modlist').find(sel(module['moddir'])).length
                || current_task_moddirs[module['moddir']]) {
            continue;
        }

        // put a separator if it's a different language
        if (lastLang && lastLang != module['lang']) {
            $("#available").append("<option disabled>──────────</option>\n");
        }
        lastLang = module['lang'];

        // finally, add the entry for this module
        $("#available").append(
            "<option value=\"" + module['moddir'] + "\">"
            + module['moddir'] + " -- "
            + module['gbsize'] + " GB</option>"
        );
    }

    // on the initial run we have to do these
    // and it doesn't really hurt the rest of the time
    $("#availspin").hide();
    enableAvailUI();

}

// populate the remote module list (downloadable)
// -- this is called once the local module list is known
//
// store a copy so we know how big these things are for our progress meter
function initialRemoteModuleList() {

    // before we make changes to the list, we adjust the UI
    disableAvailUI();
    $("#availspin").show();  // - add spinner
    $("#available").append("<option>Loading...</option>"); // add "Loading" text
    getRemoteModuleInfo(drawRemoteModuleList);
    
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
                addToLocal(results[i].moddir);
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
                initialRemoteModuleList();
                firstCall = false;
            }
        },
        timeout: ajaxTimeout
    });

}

function addToLocal (moddir) {
    // we have to check if it's already there first
    if (!$(sel(moddir)).length
            // and that it's not just a sort command
            && moddir != ".modules sort") {
        $("#modlist").append(
            "<li id=\"" + moddir + "\">" + moddir +
            "<button type=\"button\" onclick=\"delMod('" + moddir +
            "')\">delete</button></li>\n"
        );
    }
    delete current_task_moddirs[ moddir ];
}

// onload
$(function() {

    //console.log("page load");

    // remote list gets populated automatically the
    // first time we call this
    populateLocalModuleList();

    // start polling the task list
    pollTasks();

    // disable enter on form
    $("#availform").keypress(function(event) {
        if (event.which == '13') {
            event.preventDefault();
        }
    });

    // set download button to enable/disable with selection
    $("#available").change(setButtonState);

});

function lsfocus(i) {
    if (i.value == i.defaultValue) { i.value = ""; i.style.color = "#000"; }
}
function lsblur(i) {
    if (i.value == "") { i.value = i.defaultValue; i.style.color = "#ccc"; }
}
function lsinput(i) {
    if (!i.value.match(/\w/)) {
        // if the field is blank, show everything
        $("#available > option").each(function(index) {
            $(this).show();
        });
    } else {
        // otherwise filter to text matches
        var parts = i.value.split(/\s+/);
        $("#available > option").each(function(index) {
            for (i = 0; i < parts.length; ++i) {
                if (parts[i].length == 0) { continue; }
                regex = new RegExp(parts[i], 'i');
                if (this.value.match(regex)) {
                    $(this).show();
                } else {
                    $(this).hide();
                    break;
                }
            }
        });
    }
}

function toggleAdvanced() {
    $('#advanced').toggle();
    $('#availdiv').toggle();
    if ($('#advancedbut').html() == "advanced") {
        $('#advancedbut').html("standard");
    } else {
        $('#advancedbut').html("advanced");
    }
}

function checkInstall() {
    // if we're reinstalling the current set we don't need to do
    // a real form submit (no filesystem interaction) so we can
    // intercept and use the ajax methods here
    // XXX this will not work when we add lang support
    if ( $('select[name="mfile"]').val() == "reinstall existing set" ) {

        modlist = [];
        $("#modlist li").each( function(idx, li) {
            modlist.push(li.id);
        });

        // see which server they want
        var server = whichServer();

        // if we don't check that there's something there,
        // it installs everything - and that would be bad
        if (modlist.length) {
            addMods(modlist, server);
        }
        toggleAdvanced();
        return false;

    // we're installing from another (RACHEL) server -- get a .modules
    // file from that server and then 
    // XXX this will not work when we add lang support
    } else if ( $('select[name="mfile"]').val() == "clone from server" ) {

        // they didn't enter a server -- focus and bail
        if (! $("#server_custom").val().match(/\w/)) {
            $("#server_custom").focus();
            return false;
        }

        $("#advspin").show();
        $("#advnotice").html("");

        // see which server they want
        var server = whichServer();

        // the response here is not json,
        // just a raw .modules file (i.e. text/plain)
        $.ajax({
            url: "background.php?cloneServer=" + server,
            success: function(results) {
                console.log("SUCCESS: " + results);
                toggleAdvanced();
            },
            error: function(xhr, status, error) {
                $("#advnotice").css("color", "#c00");
                $("#advnotice").html("X " + error + " : '" + server + "'");
            },
            complete: function(xhr, status) {
                $("#advspin").hide();
            }
        });
        return false;
    }

}

function whichServer() {
    // see which server they want
    var server = null;
    if ($("#server_custom").val() && $("#server_custom").val().match(/\w/)) {
        server = $("#server_custom").val();
    } else if (knownServers.hasOwnProperty($("#server").val())) {
        server = knownServers[ $("#server").val() ];
    } else if ($("#server").val()) {
        server = $("#server").val();
    }
    return server;
}

// this just disables the server dropdown if the person
// selects cloning a server (we don't allow cloning the master servers)
function checkClone() {
    // XXX this will not work when we add lang support
    if ($("#mfile").val() == "clone from server") {
        $("#server").prop("disabled", true);
        $("#server_custom").focus();
    } else {
        $("#server").prop("disabled", false);
    }
}

// disables server dropdown if the person enters one manually
function serverCustomInput() {
    if ($("#server_custom").val().match(/\w/)) {
        $("#server").prop("disabled", true);
    } else {
        $("#server").prop("disabled", false);
    }
}

</script>

<h3>Add Modules</h3>
<div id="availformbox">

    <button id="advancedbut" type="button" onclick="toggleAdvanced();">advanced</button>

    <form id="advanced" method="post" onsubmit="return checkInstall();" enctype="multipart/form-data">
        <table>
        <tr>
        <th>.modules:</th>
        <td>
        <select name="mfile" onchange="checkClone();" id="mfile">
        <!-- when this is internationalized, the value will be different -->
        <!-- than the contents of the <option> tag -->
        <option value="reinstall existing set">reinstall existing set</option>
        <option value="clone from server">clone from server</option>
        <option disabled>──────────</option>
<?php
        # are we reliably in the admin directory
        # to just do it like this?
        $handle = opendir("../scripts");
        $mfiles = array();
        $has_full = false;
        while ($mfile = readdir($handle)) {
            if (preg_match("/^\./", $mfile)) { continue; }
            if (!preg_match("/\.modules$/", $mfile)) { continue; }
            if ($mfile == "full.modules") { $has_full = true; continue; }
            $mfile = preg_replace("/\.modules$/","", $mfile);
            array_push($mfiles, $mfile);
        }
        natcasesort($mfiles);
        if ($has_full) {
            echo "<option>full</option>\n";
        }
        foreach ($mfiles as $mfile) {
            echo "<option>$mfile</option>\n";
        }
?>
        </select>
        </td>
        <td>
           <input type="hidden" name="MAX_FILE_SIZE" value="4096" />
        <i>&mdash; or &mdash;</i> upload <input type="file" name="mfile_upload">
        </td>
        </tr>
        <tr>
        <th>server:</th>
        <td>
        <select name="server" id="server">
            <option>dev.worldpossible.org</option>
            <option>jeremy</option>
            <option>jfield</option>
        </select>
        </td>
        <td>
        <i>&mdash; or &mdash;</i> specify host <input type="text" name="server_custom" id="server_custom" oninput="serverCustomInput()" value="">
        </td>
        </table>
        <input type="submit" name="advanced_install" value="Install">
        <img src="../art/spinner.gif" id="advspin">
        <span id="advnotice"></span>
    </form>

    <form id="availform"><!-- submitted via ajax -->
        <div id="availdiv">
        <input id="livesearch" onfocus="lsfocus(this)" onblur="lsblur(this)" oninput="lsinput(this)" value="Live Search" disabled><br>
        <select id="available" size="10" multiple></select>
	<div class="multi-instructions"><code>shift</code> or <code>ctrl</code> click to select multiple modules</div>
        <button type="button" id="addmodbut" onclick="addMods();" disabled>Download</button>
        <img src="../art/spinner.gif" id="availspin">
        </div>

        <div style="clear: both;"></div>
    </form>
</div>

<button id="clearall" onclick="cancelAll();">cancel all</button>
<h3>Currently Adding</h3>
<ul id="tasks">
</ul>

<h3>Delete Modules</h3>
<ul id="modlist">
</ul>

<?php include "foot.php" ?>
