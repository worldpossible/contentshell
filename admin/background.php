<?php

require_once("common.php");

define("APIHOST",   "dev.worldpossible.org");
define("RSYNCHOST", "dev.worldpossible.org");

if (isset($_GET['getRemoteModuleList'])) {
    getRemoteModuleList();

} else if (isset($_GET['getLocalModuleList'])) {
    getLocalModuleList();

} else if (isset($_GET['addModule'])) {
    addModule($_GET['addModule']);

} else if (isset($_GET['deleteModule'])) {
    deleteModule($_GET['deleteModule']);

} else if (isset($_GET['cancelTask'])) {
    cancelTask($_GET['cancelTask']);

} else if (isset($_GET['getTasks'])) {
    getTasks();

} else if (isset($_GET['wifistat'])) {
    wifiStatus();

} else if (isset($_GET['selfUpdate'])) {
    selfUpdate();

} else if (isset($_GET['modUpdate'])) {
    addModule($_GET['modUpdate']);

} else if (isset($_GET['setLocalContent'])) {
    setLocalContent($_GET['setLocalContent']);

}

error_log("Unknown request to background.php: " . print_r($_GET, true));
header("HTTP/1.1 500 Internal Server Error");
exit;

#-------------------------------------------
# functions
#-------------------------------------------

function getRemoteModuleList() {
    $json = file_get_contents("http://" . APIHOST . "/cgi/json_api_v1.pl");
    header('Content-Type: application/json');
    echo $json;
    exit;
}

function getLocalModuleList() {
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
    header('Content-Type: application/json');
    echo json_encode(array_values($fsmods)); # , JSON_PRETTY_PRINT); # this only works in 5.4+ -- RACHEL-PLUS has 5.3.10
    exit;
}

function deleteModule($moddir) {

    $deldir = getRelModPath() . "/" . $moddir;

    # XXX standardize and refactor all the error handling in this module
    if (preg_match("/[^\w\-\.]/", $moddir)) {
        log_error("deleteModule: Invalid Module Name");
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json");
        echo "{ \"error\" : \"deleteModule: Invalid Module Name\", \"moddir\" : \"$moddir\" }\n";
        exit;
    }

    # brutally dangerous? how to improve?
    exec("rm -rf '$deldir' 2>&1", $output, $rval);

    if ($rval == 0) {
        # restart kiwix so it sees what modules are visible/hidden
        kiwix_restart();
        header("HTTP/1.1 200 OK");
        header("Content-Type: application/json");
        echo "{ \"moddir\" : \"$moddir\" }\n";
    } else {
        $output = implode(", ", $output);
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json");
        echo "{ \"error\" : \"$output\", \"moddir\" : \"$moddir\" }\n";
    }

    exit;

}

function addModule($moddir) {


    # fire off our clever database updating rsync process
    exec("php rsync.php " . RSYNCHOST . " $moddir > /dev/null &", $output, $rval);

    if ($rval == 0) {
        header("HTTP/1.1 200 OK");
        header("Content-Type: application/json");
        echo "{ \"moddir\" : \"$moddir\" }\n";
    } else {
        $output = implode(", ", $output);
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json");
        echo "{ \"error\" : \"$output\", \"moddir\" : \"$moddir\" }\n";
    }

    exit;

}

function cancelTask($task_id) {

    $db = getdb();

    $db_task_id = $db->escapeString($task_id);
    $rv = $db->query("SELECT pid, retval, completed FROM tasks WHERE task_id = $db_task_id");
    error_log("SELECT pid, retval, completed FROM tasks WHERE task_id = $db_task_id");
    $task = $rv->fetchArray(SQLITE3_ASSOC);

    if (!$task) {
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json");
        echo "{ \"error\" : \"No Such Task: $task_id\" }\n";
        exit;
    }

    if ($task['pid'] and !$task['completed']) {
        exec("kill $task[pid]", $output, $rval);
    }

    $db_dismissed = $db->escapeString(time());
    $db_retval = $task['retval'] ? $task['retval'] : 1;
    $db->exec("
        UPDATE tasks SET
            dismissed = '$db_dismissed',
            retval    = '$db_retval'
        WHERE task_id = '$db_task_id'
    ");

    header("HTTP/1.1 200 OK");
    header("Content-Type: application/json");
    echo "{ \"task_id\" : \"$task_id\" }\n";
    exit;

}

function getTasks() {

    $db = getdb();
    $rv = $db->query("SELECT * FROM tasks WHERE dismissed IS NULL");
    $tasks = array();
    $modules = array();
    while ($row = $rv->fetchArray(SQLITE3_ASSOC)) {
        $row['tasktime'] = time(); // so we can calculate against server time, not browser time
        if ($row['completed'] && $row['retval'] == 0) {
            // if the command completed successfully
            // auto dismiss and notify the client
            $row['dismissed'] = time();
            $db_dismissed = $db->escapeString($row['dismissed']);
            $db_task_id = $db->escapeString($row['task_id']);
            $db->exec("UPDATE tasks SET dismissed = '$db_dismissed' WHERE task_id = '$db_task_id'");
            // we also get the latest version number and return it
            // this is a bit inefficient, getting them all when we just need one
            if (empty($modules)) { $modules = getmods_fs(); } // a bit of cacheing
            $row['version'] = $modules[ $row['moddir'] ]['version'];
        }
        array_push($tasks, $row);
    }
    header("HTTP/1.1 200 OK");
    header("Content-Type: application/json");
    echo json_encode($tasks); # , JSON_PRETTY_PRINT); # this only works in 5.4+ -- RACHEL-PLUS has 5.3.10
    exit;

}

function wifiStatus() {

    if ($_GET['wifistat'] == "on") {
         exec("/etc/WiFi_Setting.sh > /dev/null 2>&1", $output, $retval);
    } else if ($_GET['wifistat'] == "off") {
	exec("/sbin/ifconfig wlan0 down", $output, $retval);
    } else if ($_GET['wifistat'] == "check") {
	$retval = 0;
    } else {
	# unknown command
	$retval = 1;
    }

    # there was a problem
    if ($retval > 0) {
        header("HTTP/1.1 500 Internal Server Error");
        exit;
    }

    # we always finish by sending back the status
    $wifistat = 0;
    exec("ifconfig wlan0 | grep ' UP '", $output);

    if ($output) { $wifistat = 1; }

    header("HTTP/1.1 200 OK");
    header("Content-Type: application/json");
    echo "{ \"wifistat\" : \"$wifistat\" }\n";
    exit;

}

function selfUpdate() {

    if (!empty($_GET['check'])) {
        $json = file_get_contents("http://" . APIHOST . "/cgi/updatecheck.pl");
        if (empty($json)) {
	    error_log("selfUpdate failed: no JSON at http://" . APIHOST . "/cgi/updatecheck.pl");
            header("HTTP/1.1 500 Internal Server Error");
            exit;
        }
        header('Content-Type: application/json');
        echo $json;
        exit;
    }

    # we install two directories up from here
    $destdir = dirname(dirname(__FILE__));
    # we also don't want to overwrite the modules directory or the admin.sqite file
    # as those are often customized by the user, also skip hidden files
    # lastly - it's important that we keep the trailing "/" on the source because we're
    # putting the contents into a directory of a different name, and don't want to
    # create a directory called "contentshell" in there
    $cmd = "rsync -Pavz --exclude modules --exclude /admin/admin.sqlite --exclude '.*' --del rsync://" . RSYNCHOST . "/rachelmods/contentshell/ $destdir";

    exec($cmd, $output, $retval);
    if ($retval == 0) {
        $cmd = "bash $destdir/admin/post-update-script.sh";
        exec($cmd, $output, $retval);
        if ($retval == 0) {
            # pull the version info from the new file - if the HTML changes too much this will break
            preg_match("/id=\"cur_contentshell\"[^>]*>(.+?)</s", file_get_contents("version.php"), $matches);
            if ($matches[1]) {
                $version = $matches[1];
                header("HTTP/1.1 200 OK");
                header("Content-Type: application/json");
                echo "{ \"version\" : \"$version\" }\n";
                exit;
            }
        }
    }

    error_log("selfUpdate Failed: cmd returned $retval, " . implode(", ", $output));
    header("HTTP/1.1 500 Internal Server Error");
    exit;

}

function setLocalContent($state) {
    $db = getdb();
    $db_state = $db->escapeString($state);
    $db->exec("UPDATE prefs SET value = '$db_state' WHERE pref = 'show_local_content_link'");
    header("HTTP/1.1 200 OK");
    header("Content-Type: application/json");
    echo "{ \"status\" : \"OK\" }\n";
    exit;
}

?>
