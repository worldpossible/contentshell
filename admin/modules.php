<?php
#-------------------------------------------
# see minimal.php for an explanation of the system
#-------------------------------------------
require_once("common.php");
if (!authorized()) { exit(); }

#-------------------------------------------
# First we take care of things that don't result in HTML
# 
# If we've got a list of moddirs, we update the DB to
# reflect that ordering. This all takes place as an AJAX
# request, and the success/failure is returned via HTTP
# code and reflected in the "Save" button on the page.
#-------------------------------------------
if (isset($_GET['moddirs'])) {
    updatemods();
    exit;
}

#-------------------------------------------
# Now we can load in our HTML head and start output
#-------------------------------------------
$page_title = $lang['modules'];
$page_script = "js/modules.js";
$page_nav = "modules";
include "head.php";

$fsmods = getmods_fs();

# if there's no modules directory, we can't do anything
if ($fsmods) {

    # at this point, checking the db is just informational
    # -- and we warn about it at the top - but later we
    # will try to actually read/write to the DB and we
    # will need to check this before doing those
    $db = getdb();
    # XXX this testing should be moved to common.php and
    # show the error on all pages (include as part of head.php or nav.php)
    if (!$db) {
        echo "<div class=\"error\">
              <h2>Couldn't Open Database</h2>
              <h3>You probably need to <tt>chmod 777</tt>
              the admin directory</h3>
              <p>Until you do, saving the sort order and hiding modules
              will not work.<br>Everything in the modules directory will show
              up in alphabetical order</p>
              </div>
        ";
    } else {
        # we do a test write so we can signal problems to the user
        $rv = $db->exec("CREATE TABLE writetest (col INTEGER)");
        if (!$rv) {
            echo "<div class=\"error\">
                  <h2>Couldn't Write To Database</h2>
                  <h3>You probably need to <tt>chmod 666 admin.sqlite</tt>
                  and <tt>chmod 777</tt> the web root directory</h3>
                  <p>Until you do, saving the sort order and hiding modules
                  will not work.<br>Everything in the modules directory will show
                  up in alphabetical order</p>
                  </div>
            ";
        } else {
           $db->exec("DROP TABLE writetest"); 
        }
    }

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
        <p style=\"margin-bottom: 0;\">$lang[admin_instructions]</p>
        <p id=\"controls\"><button onclick=\"changeall(this, true);\">$lang[hide_all]</button><button onclick=\"changeall(this, false);\">$lang[show_all]</button></p>
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

    echo "<h2>$lang[no_mods_found]</h2>\n";
    # hacky -- but we need to let the js know it's ok to leave
    # the page without an alert (see beforeunload in modules.js)
    echo "<button id='savebut' style='display: none;' disabled>";

}

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
    kiwix_restart();

    header("HTTP/1.1 200 OK");    

}

if (is_rachelplus()) {
    $checked = show_local_content_link() ? " checked" : "";
    echo "<hr style='margin: 20px 0;'>\n";
    echo "<p style='background: #ddd; padding: 10px 5px; border-radius: 5px; font-size: small;'>\n";
    echo "<input type='checkbox' id='localcontent'$checked> Show the <b>en-local_content</b> module as <b>\"LOCAL CONTENT\"</b> link in RACHEL header</p>";
}
    

include "foot.php";

?>
