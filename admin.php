<?php

require_once("common.php");

# cookie-based authorization
if (!authorized()) { exit(); }
if (isset($_GET['logout'])) { clearcookie(); exit; }

# If we've got a list of moddirs, we update the DB to
# reflect that ordering. This all takes place as an AJAX
# request, and the success/failure is returned via HTTP
# code and reflected in the "Save" button on the page.
if (isset($_GET['moddirs'])) {
    updatemods();
    exit;
}

# We also allow shutting down the server so as to avoid
# damaging the SD/HD. This requires that www-data has
# sudo access to /sbin/shutdown, which should be set up
# automatically during rachelpiOS installation
if (isset($_POST['shutdown'])) {
    exec("sudo /sbin/shutdown now", $exec_out, $exec_err);
    if ($exec_err) {
        echo $lang['shutdown_failed'];
    } else {
        echo $lang['restart_ok'];
    }
    exit;
} else if (isset($_POST['reboot'])) {
    exec("sudo /sbin/shutdown -r now", $exec_out, $exec_err);
    if ($exec_err) {
        echo $lang['restart_failed'];
    } else {
        echo $lang['restart_ok'];
    }
    exit;
}

?><!DOCTYPE html>
<html lang="<?php echo $lang['langcode'] ?>">
<head>
<meta charset="utf-8">
<title>RACHEL Admin</title>
<link rel="stylesheet" href="css/normalize-1.1.3.css">
<link rel="stylesheet" href="css/ui-lightness/jquery-ui-1.10.4.custom.min.css">
<link rel="stylesheet" href="css/admin-style.css">
<script src="js/jquery-1.10.2.min.js"></script>
<script src="js/jquery-ui-1.10.4.custom.min.js"></script>
<script>

    function setButtonsActive () {
        $("#savebut").css("color", "");
        $("#savebut").html("<?php echo $lang['save_changes'] ?>");
        $("#savebut").prop("disabled", false);
        $("#resetbut").prop("disabled", false);
    }

    // all languages represented - populated onload below
    var langs = [];

    // onload
    $(function() {
        // detect changes to sorting and hiding
        $("#sortable").sortable({ change: setButtonsActive });
        $(":checkbox").change( setButtonsActive );

        // create language filtering options XXX optimize by using native JS?
        // first we grab each language code by going through the modules
        var langhash = {};
        $("input[type=checkbox]").each(function () {
            match = $(this).attr('id').match(/^(..)-/);
            if (match[0]) { langhash[ match[1] ] = true; }
        });
        // then we convert it to an array
        for (var lang in langhash) {
            if(langhash.hasOwnProperty(lang)) {
                langs.push(lang);
            }
        }
        // then we create a button for each
        langs.sort();
        langs.reverse();
        var langsLength = langs.length
        for (var i = 0; i < langsLength; ++i) {
            $("#controls").prepend(
                "<button onclick=\"changelang(this, '"+langs[i]+"', true);\"><?php echo $lang['hide'] ?> "+langs[i]+"</button>\n"
            );
        }

        $("#controls").prepend("&nbsp;&bull;&nbsp;\n");

        // add a sort button
        for (var i = 0; i < langsLength; ++i) {
            $("#controls").prepend(
                "<button onclick=\"sortlang(this, '"+langs[i]+"');\"><?php echo $lang['sort'] ?> "+langs[i]+"</button>\n"
            );
        }

    });

    // button click calls this to save the module order & hiding
    function saveModState() {
        $("#savebut").html("Saving...");
        $("#savebut").prop("disabled", true);
        $("#resetbut").prop("disabled", true);
        var ordered = $("#sortable").sortable("toArray");
        var hidden = [];
        var orderedLength = ordered.length;
        for (var i = 0; i < orderedLength; ++i) {
            if ($("#"+ordered[i]+"-hidden").prop("checked")) {
                hidden.push(ordered[i]);
            }
        }
        //alert("admin.php?moddirs=" + ordered.join(",") + "&hidden=" + hidden.join(","));
        $.ajax({
            url: "admin.php?moddirs=" + ordered.join(",")
                + "&hidden=" + hidden.join(","),
            success: function() {
                $("#savebut").css("color", "green");
                $("#savebut").html("&#10004; <?php echo $lang['saved'] ?>");
            },
            error: function() {
                $("#savebut").css("color", "#c00");
                $("#savebut").html("X <?php echo $lang['not_saved_error'] ?>");
                $("#resetbut").prop("disabled", false);
            }
        });
    }

    function changeall(myself, value) {
        $("input[type=checkbox]").each(function () {
            $(this).prop("checked", value);
        });
        setButtonsActive();
        $(myself).blur();
    }

    function changelang(myself, lang, value) {
        var regex = new RegExp("^"+lang+"-");
        $("input[type=checkbox]").each(function () {
            if ($(this).attr('id').match(regex)) {
                $(this).prop("checked", value);
            }
        });
        setButtonsActive();
        $(myself).blur();
        //$(myself).html("yo");
    }

    function sortlang(myself, lang) {
        var items = $("#sortable").children("li");
        //alert(items[0].id);
        //console.log(items);
        var regex = new RegExp("^"+lang+"-");
        items.sort(function(a,b) {
            if (a.id.match(regex) && b.id.match(regex)) {
                if (a.id < b.id) { return -1; }
                if (a.id > b.id) { return 1; }
                return 0;
            } else {
                if (a.id.match(regex)) { return -1; }
                if (b.id.match(regex)) { return 1; }
                if (a.id < b.id) { return -1; }
                if (a.id > b.id) { return 1; }
                return 0;
            }
        });
        //console.log(items);
        $("#sortable").empty().html(items);
        //var itemsLength = items.length;
    }

</script>
</head>
<body>

<?php $nav_admin = true; include "admin-nav.php"?>

<div id="content">
<?php

$basedir = "modules";

# if there's no modules directory, we can't do anything
if (is_dir($basedir)) {

    # at this point, checking the db is just informational
    # -- and we warn about it at the top - but later we
    # will try to actually read/write to the DB and we
    # will need to check this before doing those
    $db = getdb();
    if (!$db) {
        echo "<div class=\"error\">\n";
        echo "<h2>Couldn't Open Database</h2>\n";
        echo "<h3>You probably need to <tt>chmod 777</tt>\n";
        echo "the web root directory</h3>\n";
        echo "<p>Until you do, saving the sort order and hiding modules\n";
        echo "will not work.<br>Everything in the modules directory will show\n";
        echo "up in alphabetical order\n</p></div>";
    } else {
        # we do a test write so we can signal problems to the user
        $rv = $db->exec("CREATE TABLE writetest (col INTEGER)");
        if (!$rv) {
            echo "<div class=\"error\">\n";
            echo "<h2>Couldn't Write To Database</h2>\n";
            echo "<h3>You probably need to <tt>chmod 666 admin.sqlite</tt>\n";
            echo "and <tt>chmod 777</tt> the web root directory</h3>\n";
            echo "<p>Until you do, saving the sort order and hiding modules\n";
            echo "will not work.<br>Everything in the modules directory will show\n";
            echo "up in alphabetical order\n</p></div>";
        } else {
           $db->exec("DROP TABLE writetest"); 
        }
    }

    $fsmods = getmods_fs();
    $dbmods = getmods_db();

    foreach (array_keys($dbmods) as $moddir) {
        if (isset($fsmods[$moddir])) {
            $fsmods[$moddir]['position'] = $dbmods[$moddir]['position'];
            $fsmods[$moddir]['hidden'] = $dbmods[$moddir]['hidden'];
        }
    }

    # sorting function in the common.php module
    uasort($fsmods, 'bypos');

    # display the sortable list
    $disabled = " disabled";
    $nofragment = false;
    echo "
        <p>$lang[admin_instructions]</p>
        <p id=\"controls\">
        <button onclick=\"changeall(this, true);\">$lang[hide_all]</button>
        <button onclick=\"changeall(this, false);\">$lang[show_all]</button>
        </p>
        <ul id=\"sortable\">
    ";
    foreach (array_keys($fsmods) as $moddir) {
        if (!$fsmods[$moddir]['fragment']) {
            $nofragment = true;
            continue;
        }
        echo "<li id=\"$moddir\" class=\"ui-state-default\">\n";
        if ($fsmods[$moddir]['hidden']) {
            $checked = " checked";
        } else {
            $checked = "";
        }
        echo "\t<span class=\"checkbox\"><input type=\"checkbox\" id=\"$moddir-hidden\"$checked>\n";
        echo "\t<label for=\"$moddir-hidden\">$lang[hide]</label></span>\n";
        echo "\t<span class=\"ui-icon ui-icon-arrowthick-2-n-s\"></span>\n";
        echo "\t$moddir - " . $fsmods[$moddir]['title'];
        if ($fsmods[$moddir]['position'] < 1) {
            echo " <small style=\"color: green;\">(new)</small>\n";
            $disabled = "";
        }
        echo "</li>\n";
    }
    echo "</ul>\n";
    
    echo "<button id='savebut' onclick=\"saveModState();\"$disabled>" . $lang['save_changes'] . "</button>\n";
    echo "<button id='resetbut' onclick=\"location.reload();\" disabled>" . $lang['reset'] . "</button>\n";

    if ($nofragment) {
        echo "<h3>The following modules were ignored because they had no rachel-index.php</h3><ul>\n";
        foreach (array_keys($fsmods) as $moddir) {
            if (!$fsmods[$moddir]['fragment']) {
                echo "<li> $moddir </li>\n";
            }
        }
        echo "</ul>\n";
    }

    # we update the db with whatever we've seen in the filesystem
    if ($db) {

        # insert anything we found in the fs that wasn't in the db
        foreach (array_keys($fsmods) as $moddir) {
            if (!isset($dbmods[$moddir])) {
                $db_moddir =   $db->escapeString($moddir);
                $db_title  =   $db->escapeString($fsmods[$moddir]['title']);
                $db_position = $db->escapeString($fsmods[$moddir]['position']);
                $db->exec(
                    "INSERT into modules (moddir, title, position, hidden) " .
                    "VALUES ('$db_moddir', '$db_title', '$db_position', '0')"
                );
            }
        }

        # delete anything from the db that wasn't in the fs
        foreach (array_keys($dbmods) as $moddir) {
            if (!isset($fsmods[$moddir])) {
                $db_moddir =   $db->escapeString($moddir);
                $db->exec("DELETE FROM modules WHERE moddir = '$db_moddir'");
            }
        }

    }

} else {

    echo "<h2>$lang[no_moddir_found]</h2>\n";

}

# Totally separate from module management, we also offer
# a shutdown option for raspberry pi systems (which otherwise
# might corrupt themselves when unplugged)
if (file_exists("/usr/bin/raspi-config") ||
    file_exists("/etc/fake-raspi-config")) { # for testing on non-raspi systems
    echo "
        <div style='margin: 50px 0 50px 0; padding: 10px; border: 1px solid red; background: #fee;'>
        <form action='admin.php' method='post'>
        <input type='submit' name='shutdown' value='$lang[shutdown_system]' onclick=\"if (!confirm('$lang[confirm_shutdown]')) { return false; }\">
        <input type='submit' name='reboot' value='$lang[restart_system]' onclick=\"if (!confirm('$lang[confirm_restart]')) { return false; }\">
        </form>
        $lang[shutdown_blurb]
        </div>
    ";
}

?>
</div>
</body>
</html>
<?php

# called if the user hits the "Save" button
function updatemods () {

    # if we don't turn off visible errors, even caught db
    # exceptions will print to the browser (as a "200 OK"),
    # breaking our ability to signal failure
    ini_set('display_errors', '0');

    $position = 1;

    try {

        $db = getdb();
        if (!$db) { throw new Exception($db->lastErrorMsg); }

        # figure out which modules to hide
        $hidden= array();
        if (isset($_GET['hidden'])) {
            foreach (explode(",", $_GET['hidden']) as $moddir) {
                $hidden[$moddir] = 1;
            }
        }
        $db->exec("BEGIN");

        # go to the DB and set the new order and new hidden state
        foreach (explode(",", $_GET['moddirs']) as $moddir) {
            $moddir = $db->escapeString($moddir);
            if (isset($hidden[$moddir])) { $is_hidden = 1; } else { $is_hidden = 0; }
            $rv = $db->exec(
                "UPDATE modules SET position = '$position', hidden = '$is_hidden'" .
                " WHERE moddir = '$moddir'"
            );
            if (!$rv) { throw new Exception($db->lastErrorMsg()); }
            ++$position;
        }

    } catch (Exception $ex) {

        $db->exec("ROLLBACK");
        error_log($ex);
        header("HTTP/1.1 500 Internal Server Error");    
        exit;

    }

    $db->exec("COMMIT");

    # restart kiwix so it sees what modules are visible/hidden
    if (is_rachelpi()) {
        exec("sudo service kiwix restart");
    } else if (is_rachelplus()) {
        exec("bash /root/rachel-scripts/rachelKiwixStart.sh");
    }

    header("HTTP/1.1 200 OK");    

}

?>
