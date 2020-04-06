<?php

require_once("common.php");

define("APIHOST",   "dev.worldpossible.org");
define("RSYNCHOST", "dev.worldpossible.org");
#define("RSYNCHOST", "192.168.1.6");
# XXX be sure to test with a bad host and see how it behaves

if (isset($_GET['getRemoteModuleList'])) {
    getRemoteModuleList();

} else if (isset($_GET['getLocalModuleList'])) {
    getLocalModuleList();

} else if (isset($_GET['addModules'])) {
    addModules($_GET['addModules']);

} else if (isset($_GET['deleteModule'])) {
    deleteModule($_GET['deleteModule']);

} else if (isset($_GET['cancelTask'])) {
    cancelTask($_GET['cancelTask']);

} else if (isset($_GET['cancelAll'])) {
    cancelAll();

} else if (isset($_GET['retryTask'])) {
    retryTask( $_GET['retryTask']);

} else if (isset($_GET['getTasks'])) {
    getTasks();

} else if (isset($_GET['wifistat'])) {
    wifiStatus();

} else if (isset($_GET['selfUpdate'])) {
    selfUpdate();

} else if (isset($_GET['modUpdate'])) {
    addModules($_GET['modUpdate']);

} else if (isset($_GET['setLocalContent'])) {
    setLocalContent($_GET['setLocalContent']);

} else if (isset($_GET['getBatteryInfo'])) {
    getBatteryInfo();

} else if (isset($_GET['clearLogs'])) {
    clearLogs();

} else if (isset($_GET['cloneServer'])) {
    cloneServer();

} else if (isset($_GET['setRsyncDaemon'])) {
    setRsyncDaemon($_GET['setRsyncDaemon']);

}
error_log("Unknown request to background.php: " . print_r($_GET, true));
header("HTTP/1.1 500 Internal Server Error");
exit;

#-------------------------------------------
# functions
#-------------------------------------------

function getRemoteModuleList() {
    #$json = file_get_contents("http://" . APIHOST . "/cgi/json_api_v1.pl");
    # using this call takes about 1/4 the time
    $json = file_get_contents("http://" . APIHOST . "/cgi/updatecheck.pl");
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

# takes a comma separated list of moddirs and install them
# in the background
function addModules($moddirs) {

    $host = RSYNCHOST;
    if (!empty($_GET['server'])) {
        $host = preg_replace("/[^\w\d\.\-\:\/]/", "", $_GET['server']);
    }

    $relmodpath = getrelmodpath();

    $moddirs = explode(",", $moddirs);

    $zip = "z";
    if (preg_match("/^[\d\.]+$/", $host)) {
        $zip = "";
    }

    foreach ($moddirs as $moddir) {

        # use rsync -z for remote hosts, not for LAN
        # (the CPU overhead of zip actually slows it down on a fast network)
        $cmd = "rsync -Pav$zip --del rsync://$host/rachelmods/$moddir $relmodpath/";

        # insert a task into the DB
        $db        = getdb();
        $db_cmd    = $db->escapeString($cmd);
        $db_moddir = $db->escapeString($moddir);
        $db->exec("
            INSERT INTO tasks (moddir, command)
            VALUES ('$db_moddir', '$db_cmd')
        ");

    }

    # fire off our clever database updating rsync process
    exec("php do_tasks.php > /dev/null &", $output, $rval);

    if ($rval == 0) {
        header("HTTP/1.1 200 OK");
        header("Content-Type: application/json");
        echo "{ \"status\" : \"OK\" }\n";
    } else {
        $output = implode(", ", $output);
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json");
        echo "{ \"error\" : \"$output\" }\n";
    }

    exit;

}

function cancelTask($task_id) {

    $db = getdb();

    $db_task_id = $db->escapeString($task_id);
    $rv = $db->query("SELECT pid, retval, completed FROM tasks WHERE task_id = $db_task_id");
    #error_log("SELECT pid, retval, completed FROM tasks WHERE task_id = $db_task_id");
    $task = $rv->fetchArray(SQLITE3_ASSOC);

    if (!$task) {
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json");
        echo "{ \"error\" : \"No Such Task: $task_id\" }\n";
        exit;
    }

    if ($task['pid'] and !$task['completed']) {
        # the process will be a subprocess of a shell (sh -c rsync)
        # so we have to use pkill -P, which kills children of a given process
        exec("pkill -P $task[pid]", $output, $rval);
        error_log("pkill -P $task[pid]");
        error_log("killing output: " . implode($output));
        error_log("killing rval: $rval");
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

# too much copied from cancelTask() and getTasks -- XXX abstraction needed
function cancelAll() {

    $db = getdb();

    # get tasks that will need to be killed
    $rv = $db->query("
        SELECT pid FROM tasks
         WHERE pid IS NOT NULL
           AND completed IS NULL
           AND dismissed IS NULL
    ");
    $running = array();
    while ($task = $rv->fetchArray(SQLITE3_ASSOC)) {
	array_push($running, $task);
    }


    # mark everything as "dismissed" - we have to do this
    # before killing processes or do_tasks.php will pick up
    # the next one before we mark it
    $db_dismissed = $db->escapeString(time());
    # i think using a transaction lets us release the file lock sooner XXX test that
    $db->exec("BEGIN");
    $db->exec("
        UPDATE tasks SET
               dismissed = '$db_dismissed'
         WHERE dismissed IS NULL
    ");
    $db->exec("COMMIT");

    # now we go back and kill any processes that were actually running
    # -- there's probably only one, but we loop it just in case
    foreach ($running as $task) {
        # the process will be a subprocess of a shell (sh -c rsync)
        # so we have to use pkill -P, which kills children of a given process
        exec("pkill -P $task[pid]", $output, $rval);
    }

    header("HTTP/1.1 200 OK");
    header("Content-Type: application/json");
    echo "{ \"status\" : \"OK\" }\n";
    exit;

}

function retryTask($task_id) {

    # XXX mostly copied from cancelTask() and addTask()
    # -- abstract this stuff and reduce duplication

    $db = getdb();

    $db_task_id = $db->escapeString($task_id);
    $rv = $db->query("SELECT pid, retval, completed, command, moddir FROM tasks WHERE task_id = $db_task_id");
    #error_log("SELECT pid, retval, completed FROM tasks WHERE task_id = $db_task_id");
    $task = $rv->fetchArray(SQLITE3_ASSOC);

    if (!$task) {
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json");
        echo "{ \"error\" : \"No Such Task: $task_id\" }\n";
        exit;
    }

    if ($task['pid'] and !$task['completed']) {
        # the process will be a subprocess of a shell (sh -c rsync)
        # so we have to use pkill -P, which kills children of a given process
        exec("pkill -P $task[pid]", $output, $rval);
        error_log("pkill -P $task[pid]");
        error_log("killing output: " . implode($output));
        error_log("killing rval: $rval");
    }

    $db_dismissed = $db->escapeString(time());
    $db_retval = $task['retval'] ? $task['retval'] : 1;
    $db->exec("
        UPDATE tasks SET
            dismissed = '$db_dismissed',
            retval    = '$db_retval'
        WHERE task_id = '$db_task_id'
    ");

    $db_cmd    = $db->escapeString($task['command']);
    $db_moddir = $db->escapeString($task['moddir']);
    $db->exec("
        INSERT INTO tasks (moddir, command)
        VALUES ('$db_moddir', '$db_cmd')
    ");

    # fire off our clever database updating rsync process
    exec("php do_tasks.php > /dev/null &", $output, $rval);

    if ($rval == 0) {
        header("HTTP/1.1 200 OK");
        header("Content-Type: application/json");
        echo "{ \"status\" : \"OK\" }\n";
    } else {
        $output = implode(", ", $output);
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json");
        echo "{ \"error\" : \"$output\" }\n";
    }

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
            if (!empty($_GET['includeVersion'])) {
                // if requested, we also get the latest version number and return it
                // this is so when an update task completes it can update the UI with the new version number
                // it's a bit inefficeint as it gets all version numbers when we just need one
                if (empty($modules)) { $modules = getmods_fs(); } // a bit of caching 
                $row['version'] = $modules[ $row['moddir'] ]['version'];
            }
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
        if (is_rachelplusv3()) {
            # v3 has two wifi interfaces
            exec("/sbin/ifconfig wlan0 up", $output, $retval1); # 5G
            exec("/sbin/ifconfig wlan1 up", $output, $retval2); # 2_4G
            $retval = $retval1 + $retval2;
        } else {
            exec("/etc/WiFi_Setting.sh > /dev/null 2>&1", $output, $retval);
        }
    } else if ($_GET['wifistat'] == "off") {
        if (is_rachelplusv3()) {
            # v3 has two wifi channels
            exec("/sbin/ifconfig wlan0 down", $output, $retval1); # 5G
            exec("/sbin/ifconfig wlan1 down", $output, $retval2); # 2_4G
            $retval = $retval1 + $retval2;
        } else {
            exec("/sbin/ifconfig wlan0 down", $output, $retval);
        }
    } else if ($_GET['wifistat'] == "check") {
        # we don't do anything yet -- see below
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
    if (is_rachelplusv3()) {
        # there are two interfaces on the v3, but
        # if either interface is up, we count it as up
        exec("{ ifconfig wlan1 & ifconfig wlan0; } | grep ' UP '", $output);
    } else {
        exec("ifconfig wlan0 | grep ' UP '", $output);
    }

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
    $db->exec("REPLACE INTO prefs (pref, value) values ('show_local_content_link', '$db_state')");
    header("HTTP/1.1 200 OK");
    header("Content-Type: application/json");
    echo "{ \"status\" : \"OK\" }\n";
    exit;
}

// XXX this doesn't work yet -- something weired about starting
// an rsync process under php
function setRsyncDaemon($state) {
    # save the prefrence
    $db = getdb();
    $db_state = $db->escapeString($state);
    $db->exec("REPLACE INTO prefs (pref, value) values ('run_rsyncd', '$db_state')");
    # start the daemon (or stop it)
    if ($state) {
        error_log("starting rsyncd");
        exec("rsync --daemon > /dev/null 2>&1", $out, $rv);
        error_log("rv: $rv");
        error_log("out: " . print_r($out, true));
    } else {
        error_log("stoping rsyncd");
        exec("pkill -f 'rsync --daemon'", $out, $rv);
        error_log("rv: $rv");
        error_log("out: " . print_r($out, true));
    }
    header("HTTP/1.1 200 OK");
    header("Content-Type: application/json");
    echo "{ \"status\" : \"OK\" }\n";
    exit;

}

function getBatteryInfo() {

    # CAP1 & CAP2 (Gemtek)
    $chargefile = "/tmp/batteryLastChargeLevel";
    $statusfile = "/tmp/chargeStatus";
    if (file_exists($chargefile)) {
        $level  = rtrim(file_get_contents($chargefile));
        $status = rtrim(file_get_contents($statusfile));
        # status is an unsigned number that indicates the
        # load on the battery -- it varies constantly, but
        # we've determined that -20 indicates discharge
        # (i.e. not plugged in)
        if ($status <= -20) {
            $status = "discharging";
        } else {
            $status = "charging";
        }

    # CAP3 (ECS CMAL100)
    } else if (file_exists("/usr/bin/ubus")) {
        exec("ubus call battery info", $out, $rv);
        $ubus = json_decode(implode($out), true);
        $level = $ubus['capacity'];
        $status = rtrim($ubus['status']);
        # options are "Charging", "Discharging", and "Unknown"
        # which seems to indicate charged (so we call it charging)
        if ($status == "Discharging") {
            $status = "discharging";
        } else {
            $status = "charging";
        }
    }

    header("HTTP/1.1 200 OK");
    header("Content-Type: application/json");
    echo "{ \"level\" : \"$level\", \"status\" : \"$status\" }\n";
    exit;
}

function clearLogs() {
    if (is_rachelplus()) {
        exec("rm /var/log/httpd/access_log /var/log/httpd/error_log /media/RACHEL/filteredlog", $out, $rv);
        $rv = 0;
        if ($rv == 0) {

            # make sure they are there (even if empty) in case someone looks)
            exec("touch /var/log/httpd/access_log /var/log/httpd/error_log", $out, $rv);

            # now we need to restart the server after clearing the logs
            # but we need to do that after sending out our response and closing
            # (found on stackoverflow)
            ob_end_clean();
            header("HTTP/1.1 200 OK");
            header("Connection: close");
            ignore_user_abort(true); // just to be safe
            ob_start();
            echo "{ \"status\" : \"OK\" }\n";
            $size = ob_get_length();
            header("Content-Length: $size");
            ob_end_flush(); // Strange behaviour, will not work
            flush(); // Unless both are called !
            // Do post-processing here 

            //echo "{ \"status\" : \"NOTOK\" }\n";
            # client should be closed now
            exec("killall lighttpd");
            # shouldn't ever get here but...

            exit;
        }
    }
    header("HTTP/1.1 500 Internal Server Error");
    exit;
}

# retrieves a .modules file from a user-supplied server
# -- this must be another RACHEL server
function cloneServer() {
    # though the intent is an IP or hostname, we do
    # accept appended port numbers and paths so that
    # fancy-pants types can access special configurations
    $server = preg_replace("/[^\w\d\.\-\:\/]+/", "", $_GET['cloneServer']);
    # we have to suppress warnings here or they go to the browser
    # and interfere with our ability to send headers
    $contents = @file_get_contents("http://$server/export-modfile.php");
    if ($contents === false) {
        if ($http_response_header[0]) {
            header($http_response_header[0]);
        } else {
            # probably means the server wasn't found
            # - not officially a 404, but close enough
            header("HTTP/1.1 404 Not Found");
        }
    } else {
        
        # XXX: alternately we could update installmods() to take
        # a string as well as a file, but for now:
        $tempfile = tempnam(sys_get_temp_dir(), "rachelclone");
        file_put_contents($tempfile, $contents);
        installmods($tempfile, $server);
        header("HTTP/1.1 200 OK");
        header("Content-Type: application/json");
        echo "{ \"status\" : \"OK\" }\n";

    }

    exit;

}

?>
