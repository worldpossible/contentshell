<?php

require_once("common.php");
$preflang = getlang();
require_once("lang/lang.$preflang.php");

# perform a cheap version of basic auth
if (!( isset($_SERVER['PHP_AUTH_USER'])
    && $_SERVER['PHP_AUTH_USER'] == "admin"
    && isset($_SERVER['PHP_AUTH_PW'])
    && $_SERVER['PHP_AUTH_PW'] == "Rachel+1")
) {
    header('WWW-Authenticate: Basic realm="RACHEL Admin"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'You need permission to view this page.';
# even thoug it works to send html (with a redirect) instead of text,
# this apparently breaks login: credentials don't stick until you
# get re-logged out without the redirect
#    echo '<html><head><title>You need permission to view this page</title>';
#    echo '</head>';
#    echo '<meta http-equiv="refresh" content="2;URL=index.php"></head>';
#    echo '<body>You need permission to view this page.</body></html>';
    exit;
}

# If we've got a list of moddirs, we update the DB to
# reflect that ordering. This all takes place as an AJAX
# request, and the success/failure is reflected in the
# "Save" button on the page.
if (isset($_GET['moddirs'])) {
    # if we don't do this, even caught db problems
    # will print to the browser (as a "200 OK"), breaking
    # our ability to signal failure
    ini_set('display_errors', '0');
    $position = 1;
    try {
        $db = getdb();
        if (!$db) { throw new Exception($db->lastErrorMsg); }
        # figure out which modules to hide
        $hidden= array();
        foreach (explode(",", $_GET['hidden']) as $moddir) {
            $hidden[$moddir] = 1;
        }
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
        error_log($ex);
        header("HTTP/1.1 500 Internal Server Error");    
        exit;
    }
    header("HTTP/1.1 200 OK");    
    exit;

# We also allow shutting down the server so as to avoid
# damaging the SD/HD. This requires that www-data has
# sudo access to /sbin/shutdown, which should be set up
# automatically during rachelpiOS installation
} else if (isset($_POST['shutdown'])) {
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

# the admin can set a default language for the system
} else if (isset($_GET['lang'])) {

    $db = getdb();
    # if (!$db) { throw new Exception($db->lastErrorMsg); }
    # do all this error stuff below with exceptions so we
    # can easily check for this one too...

    $queries = array(
        "CREATE TABLE IF NOT EXISTS settings ( key VARCHAR(255), val VARCHAR(255) )",
        "CREATE UNIQUE INDEX IF NOT EXISTS key ON settings (key)",
        "DELETE FROM settings WHERE key = 'lang'"
    );
        error_log("ho");
    if ($_GET['lang'] != "default") {
        error_log("huh");
        $cleanlang = $db->escapeString($_GET['lang']);
        error_log("on");
        array_push($queries, "INSERT INTO settings VALUES ('lang', '$cleanlang')");
        error_log("no");
    }    
        error_log("you");

    $error = 0;
    foreach ($queries as $query) {
        error_log($query);
        $rv = $db->exec($query);
        if (!$rv) {
            $error = 1;
            break;
        }
    }

    if ($error) {
        error_log($db->lastErrorMsg());
        header("HTTP/1.1 500 Internal Server Error");    
        exit;
    } else {
        header("HTTP/1.1 200 OK");    
        exit;
    }

}

?><!DOCTYPE html>
<html lang="<?php echo $preflang ?>">
<head>
<meta charset="utf-8">
<title>RACHEL Admin</title>
<link rel="stylesheet" href="css/normalize-1.1.3.css">
<link rel="stylesheet" href="css/ui-lightness/jquery-ui-1.10.4.custom.min.css">
<style>
    body { margin: 10px; }
    button { margin: 3px; padding: .25em 1em; }
    .ui-icon { background-image: url(css/ui-lightness/images/ui-icons_ef8c08_256x240.png); }
    #sortable { list-style-type: none; margin: 0; padding: 0; }
    #sortable li {
        margin: 0 3px 3px 3px;
        padding: .25em;
        padding-left: 1.5em;
        height: 1em; width: 40em;
        overflow: hidden;
        position: relative;
    }
    #sortable li span { position: absolute; margin-left: -1.3em; }
    #sortable .checkbox { position: absolute; right: 10px; top: 5px; font-size: small; color: gray; }
    .error { border: 1px solid #c00; background: #fee; color: #c00; padding: 10px; }
    .error h2, .error h3 { margin: 0 0 10px 0; }
    .error p { margin: 0 }
</style>
<script src="js/jquery-1.10.2.min.js"></script>
<script src="js/jquery-ui-1.10.4.custom.min.js"></script>
<script>

    // onload
    $(function() {

        // detect changes to sorting and hiding
        $("#sortable").sortable({
            change: function(event, ui) {
                $("#modbut").css("color", "");
                $("#modbut").html("<?php echo $lang['save_changes'] ?>");
                $("#modbut").prop("disabled", false);
            }
        });
        $(":checkbox").change( function() {
                $("#modbut").css("color", "");
                $("#modbut").html("<?php echo $lang['save_changes'] ?>");
                $("#modbut").prop("disabled", false);
        });
// not needed?
//        $("#sortable").disableSelection();

        // detect changes to the language
        $("#langsel").change( function() {
                $("#langbut").css("color", "");
                $("#langbut").html("<?php echo $lang['save_changes'] ?>");
                $("#langbut").prop("disabled", false);
        });
        $("#langbut").prop("disabled", true);

    });

    // button click calls this to save the module order & hiding
    function saveModState() {
        $("#modbut").html("Saving...");
        $("#modbut").prop("disabled", true);
        var ordered = $("#sortable").sortable("toArray");
        var hidden = [];
        for (var i = 0; i < ordered.length; ++i) {
            if ($("#"+ordered[i]+"-hidden").prop("checked")) {
                hidden.push(ordered[i]);
            }
        }
        //alert("admin.php?moddirs=" + ordered.join(",") + "&hidden=" + hidden.join(","));
        $.ajax({
            url: "admin.php?moddirs=" + ordered.join(",")
                + "&hidden=" + hidden.join(","),
            success: function() {
                $("#modbut").css("color", "green");
                $("#modbut").html("&#10004; <?php echo $lang['saved'] ?>");
            },
            error: function() {
                $("#modbut").css("color", "#c00");
                $("#modbut").html("X <?php echo $lang['not_saved_error'] ?>");
            }
        });
    }

    // button click calls this to save the language selection
    // hacky!!! can't we make this and the above into one function?
    function saveLangState() {
        $("#langbut").html("Saving...");
        $("#langbut").prop("disabled", true);
        //alert( $("#langsel").val() );
        $.ajax({
            url: "admin.php?lang=" + $("#langsel").val(),
            success: function() {
                $("#langbut").css("color", "green");
                $("#langbut").html("&#10004; <?php echo $lang['saved'] ?>");
                // it's nice if it actually changes the language (almost) right away
                var rand = Math.floor(Math.random()*1000000) // or it won't really refresh
                setTimeout("window.location.href = 'admin.php?" + rand + "#lang'", 500);
            },
            error: function() {
                $("#langbut").css("color", "#c00");
                $("#langbut").html("X <?php echo $lang['not_saved_error'] ?>");
            }
        });
    }

</script>
</head>
<body>

<div style="float: right;">
<a href="index.php"><?php echo $lang['home'] ?></a> &bull;
<a href="<?php echo "http://x:x@$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]" ?>"><?php echo $lang['logout'] ?></a>
</div>
<h1>RACHEL <?php echo $lang['admin'] ?></h1>

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
    $found_nohtmlf = false;
    echo "<p>$lang[found_in] /modules/:</p><ul id=\"sortable\">\n";
    foreach (array_keys($fsmods) as $moddir) {
        if ($fsmods[$moddir]['nohtmlf']) {
            $found_nohtmlf = true;
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
            echo "<small style=\"color: green;\">(new)</small>\n";
            $disabled = "";
        }
        echo "</li>\n";
    }
    echo "</ul>\n";
    
    echo "<button id='modbut' onclick=\"saveModState();\"$disabled>" . $lang['save_changes'] . "</button>\n";

    if ($found_nohtmlf) {
        echo "<h3>The following modules were ignored because they had no index.htmlf</h3><ul>\n";
        foreach (array_keys($fsmods) as $moddir) {
            if ($fsmods[$moddir]['nohtmlf']) {
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

echo "
    <div style='margin: 50px 0 50px 0; padding: 10px; border: 1px solid lightgray; background: #eee;'>
    <a name='lang'></a>
    <h3 style='margin: 0 0 10px 0;'>$lang[set_lang]</h3>

    <p>$lang[set_lang_blurb_1]</p>

    <select id='langsel'>
    <option value='default'>$lang[use_browser]</option>
";


if ($db) {
    # get that db module list
    $rv = $db->query("SELECT val FROM settings WHERE key = 'lang' LIMIT 1");
    if ($rv) {
        $rv = $rv->fetchArray();
        $dblang = $rv['val'];
    }
}
foreach (available_langs() as $alang) {
    $selected = "";
    if ($alang == $dblang) {
        $selected = " selected";
    }
    echo "<option value='$alang'$selected>$lang[force] $alang</option>";
}

echo "
    </select>
    <button id='langbut' onclick='saveLangState();'>$lang[save_changes]</button>
    <p><b>$lang[set_lang_blurb_2]</b></p>
    </div>
";

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

</body>
</html>
