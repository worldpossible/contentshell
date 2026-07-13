<?php

require_once("common.php");

define("APIHOST",   "dev.worldpossible.org");
define("RSYNCHOST", "dev.worldpossible.org");

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

} else if (isset($_GET['setWebmail'])) {
    setWebmail($_GET['setWebmail']);

} else if (isset($_GET['getBatteryInfo'])) {
    getBatteryInfo();

} else if (isset($_GET['clearLogs'])) {
    clearLogs();

} else if (isset($_GET['cloneServer'])) {
    cloneServer();

} else if (isset($_GET['setRsyncDaemon'])) {
    setRsyncDaemon($_GET['setRsyncDaemon']);

} else if (isset($_GET['getStats'])) {
    $startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
    $endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;
    getStats($startDate, $endDate);

} else if (isset($_GET['updateVisibility'])) {
    updateModuleVisibility();

// Quiz API endpoints
} else if (isset($_GET['getQuizzes'])) {
    getQuizzes();
} else if (isset($_GET['getQuiz'])) {
    getQuiz($_GET['getQuiz']);
} else if (isset($_GET['saveQuiz'])) {
    saveQuiz();
} else if (isset($_GET['deleteQuiz'])) {
    deleteQuiz($_GET['deleteQuiz']);
} else if (isset($_GET['getQuizSubmissions'])) {
    getQuizSubmissions($_GET['getQuizSubmissions']);
} else if (isset($_GET['getSubmissionDetails'])) {
    getSubmissionDetails($_GET['getSubmissionDetails']);
} else if (isset($_GET['gradeSubmission'])) {
    gradeSubmission();
} else if (isset($_GET['deleteSubmission'])) {
    deleteSubmission($_GET['deleteSubmission']);
} else if (isset($_GET['submitQuiz'])) {
    submitQuiz();
} else if (isset($_GET['startQuiz'])) {
    startQuiz($_GET['startQuiz']);
} else if (isset($_GET['checkQuizPassword'])) {
    checkQuizPassword($_GET['checkQuizPassword'], $_GET['password'] ?? '');
} else if (isset($_GET['getSponsorSettings'])) {
    getSponsorSettings();
} else if (isset($_GET['saveSponsorSettings'])) {
    saveSponsorSettings();
} else if (isset($_POST['uploadSponsorLogo'])) {
    uploadSponsorLogo();
} else if (isset($_GET['getCategoryMap'])) {
    getCategoryMap();
} else if (isset($_GET['getModuleCategories'])) {
    getModuleCategories();
} else if (isset($_GET['saveModuleCategory'])) {
    saveModuleCategory();
} else if (isset($_GET['createCategory'])) {
    createCategory();
} else if (isset($_GET['deleteCategory'])) {
    deleteCategory();
}

error_log("Unknown request to background.php: " . print_r($_GET, true));
header("HTTP/1.1 500 Internal Server Error");
exit;

# Generate and return goaccess statistics
function getStats($startDate = null, $endDate = null){
    
    $logFile = "/var/log/nginx/access.log";
    $tempLog = "/tmp/filtered_access.log";
    
    # If date filters are provided, filter the log file
    if ($startDate || $endDate) {
        # Build awk command to filter by date
        # Nginx log format: [DD/Mon/YYYY:HH:MM:SS ...]
        $awkCmd = "awk '";
        
        if ($startDate) {
            # Convert YYYY-MM-DD to DD/Mon/YYYY format for comparison
            $startParts = explode('-', $startDate);
            $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            $startFormatted = sprintf("%02d/%s/%s", $startParts[2], $months[intval($startParts[1])-1], $startParts[0]);
        }
        
        if ($endDate) {
            $endParts = explode('-', $endDate);
            $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            $endFormatted = sprintf("%02d/%s/%s", $endParts[2], $months[intval($endParts[1])-1], $endParts[0]);
        }
        
        # Use grep with date pattern for efficiency
        if ($startDate && $endDate) {
            # Filter between dates using awk with date extraction
            $filterCmd = "grep -E '\\[.*/.*/" . substr($startDate, 0, 4) . "|\\[.*/.*/" . substr($endDate, 0, 4) . "' " . escapeshellarg($logFile) . " > " . escapeshellarg($tempLog) . " 2>/dev/null || cp " . escapeshellarg($logFile) . " " . escapeshellarg($tempLog);
        } else {
            # Just copy the full log
            $filterCmd = "cp " . escapeshellarg($logFile) . " " . escapeshellarg($tempLog);
        }
        
        exec($filterCmd);
        $logFile = $tempLog;
    }
    
    exec("goaccess -f " . escapeshellarg($logFile) . " -a > /tmp/report.html 2>&1", $output, $rval);
    
    if($rval){
        header('HTTP/1.1 500 Internal Server');
        header('Content-Type: application/json; charset=UTF-8');

        $response = json_encode(["responseText" => "Failed to generate statistics page for Nginx Access Log" ]);

        echo $response;
        die();
    }
    
    header("HTTP/1.1 200 OK");
    header('Content-Type: text/html; charset=utf-8');
    readfile("/tmp/report.html");
    die(); 
}

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
        # sync the database so it knows a module is deleted. 
        syncmods_fs2db();
        
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
        if (is_rachelplusv5()) {
            # v5 has two wifi interfaces
            exec("/sbin/ifconfig wlp1s0 up", $output, $retval1); # 5G
            exec("/sbin/ifconfig wlp3s0 up", $output, $retval2); # 2_4G
            $retval = $retval1 + $retval2;
        } else if (is_rachelplusv3()) {
            # v3 has two wifi interfaces
            exec("/sbin/ifconfig wlan0 up", $output, $retval1); # 5G
            exec("/sbin/ifconfig wlan1 up", $output, $retval2); # 2_4G
            $retval = $retval1 + $retval2;
        } else {
            exec("/etc/WiFi_Setting.sh > /dev/null 2>&1", $output, $retval);
        }
    } else if ($_GET['wifistat'] == "off") {
        if (is_rachelplusv5()) {
            # v5 has two wifi interfaces
            exec("/sbin/ifconfig wlp1s0 down", $output, $retval1); # 5G
            exec("/sbin/ifconfig wlp3s0 down", $output, $retval2); # 2_4G
            $retval = $retval1 + $retval2;
        } else if (is_rachelplusv3()) {
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
    } else if (is_rachelplusv5()) {
        # there are two interfaces on the v5, but
        # if either interface is up, we count it as up
        exec("{ ifconfig wlp1s0 & ifconfig wlp3s0; } | grep ' UP '", $output);
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
    $cmd = "rsync -Pavz --exclude modules --exclude /admin/admin.sqlite --exclude '.*' --del rsync://" . RSYNCHOST . "/rachelmods/contentshell4/ $destdir";

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

function setWebmail($state) {
    $db       = getdb();
    $db_state = $db->escapeString($state);
    $db->exec("REPLACE INTO prefs (pref, value) values ('show_webmail_link', '$db_state')");
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
    exec("ubus call battery info", $out, $rv);
    
    if(!$out){
        header("HTTP/1.1 200 OK");
        header("Content-Type: application/json");
        $result = [ 'level'  => '0', 'status' => 'disconnected'];
        $json_result = json_encode($result);
        echo $json_result;
        exit;        
    }
    
    $ubus   = json_decode(implode($out), true);
    $level  = $ubus['capacity'];
    $status = rtrim($ubus['status']);
    # options are "Charging", "Discharging", and "Unknown"
    # which seems to indicate charged (so we call it charging)



    if ($status == "Discharging") {
        $status = "discharging";
    } else {
        $status = "charging";
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

# Update module visibility (hidden from homepage and/or blocked entirely)
function updateModuleVisibility() {
    ini_set('display_errors', '0');
    
    try {
        $db = getdb();
        if (!$db) { throw new Exception("Could not open database"); }
        
        $moddirs = isset($_GET['moddirs']) ? explode(',', $_GET['moddirs']) : array();
        $hidden = isset($_GET['hidden']) ? explode(',', $_GET['hidden']) : array();
        $blocked = isset($_GET['blocked']) ? explode(',', $_GET['blocked']) : array();
        
        # Convert to lookup arrays
        $hiddenMap = array_flip(array_filter($hidden));
        $blockedMap = array_flip(array_filter($blocked));
        
        $db->exec("BEGIN");
        
        # Check if blocked column exists, add if not
        $result = $db->query("PRAGMA table_info(modules)");
        $hasBlocked = false;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'blocked') {
                $hasBlocked = true;
                break;
            }
        }
        
        if (!$hasBlocked) {
            $db->exec("ALTER TABLE modules ADD COLUMN blocked INTEGER DEFAULT 0");
        }
        
        # Update each module
        foreach ($moddirs as $moddir) {
            if (empty($moddir)) continue;
            
            $db_moddir = $db->escapeString($moddir);
            $is_hidden = isset($hiddenMap[$moddir]) ? 1 : 0;
            $is_blocked = isset($blockedMap[$moddir]) ? 1 : 0;
            
            $rv = $db->exec(
                "UPDATE modules SET hidden = '$is_hidden', blocked = '$is_blocked'" .
                " WHERE moddir = '$db_moddir'"
            );
            if (!$rv) { throw new Exception($db->lastErrorMsg()); }
        }
        
        $db->exec("COMMIT");
        
        # restart kiwix so it sees what modules are visible/hidden
        kiwix_restart();
        
        header("HTTP/1.1 200 OK");
        header("Content-Type: application/json");
        echo json_encode(['success' => true]);
        
    } catch (Exception $ex) {
        if ($db) { $db->exec("ROLLBACK"); }
        error_log($ex);
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    
    exit;
}

// ============================================
// Quiz System Functions
// ============================================

function getQuizzes() {
    try {
        $db = getdb();
        $result = $db->query("SELECT * FROM quizzes ORDER BY created_at DESC");
        
        $quizzes = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Get question count
            $qcount = $db->querySingle("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = " . $row['quiz_id']);
            $row['question_count'] = $qcount;
            
            // Get submission count
            $scount = $db->querySingle("SELECT COUNT(*) FROM quiz_submissions WHERE quiz_id = " . $row['quiz_id']);
            $row['submission_count'] = $scount;
            
            $quizzes[] = $row;
        }
        
        header("Content-Type: application/json");
        echo json_encode($quizzes);
    } catch (Exception $ex) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function getQuiz($quizId) {
    try {
        $db = getdb();
        $quizId = intval($quizId);
        
        $quiz = $db->querySingle("SELECT * FROM quizzes WHERE quiz_id = $quizId", true);
        if (!$quiz) {
            header("HTTP/1.1 404 Not Found");
            echo json_encode(['error' => 'Quiz not found']);
            exit;
        }
        
        // Get questions
        $result = $db->query("SELECT * FROM quiz_questions WHERE quiz_id = $quizId ORDER BY question_order");
        $questions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['options']) {
                $row['options'] = json_decode($row['options'], true);
            }
            $questions[] = $row;
        }
        $quiz['questions'] = $questions;
        
        header("Content-Type: application/json");
        echo json_encode($quiz);
    } catch (Exception $ex) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function saveQuiz() {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            throw new Exception("Invalid JSON data");
        }
        
        $db = getdb();
        $db->exec("BEGIN");
        
        $title = $db->escapeString($data['title'] ?? 'Untitled Quiz');
        $description = $db->escapeString($data['description'] ?? '');
        $timeLimit = intval($data['time_limit'] ?? 0);
        $password = $db->escapeString($data['password'] ?? '');
        $isActive = intval($data['is_active'] ?? 1);
        $requireLogin = intval($data['require_login'] ?? 1);
        $showResults = intval($data['show_results'] ?? 1);
        $now = time();
        
        if (isset($data['quiz_id']) && $data['quiz_id']) {
            // Update existing quiz
            $quizId = intval($data['quiz_id']);
            $db->exec("UPDATE quizzes SET 
                title = '$title',
                description = '$description',
                time_limit = $timeLimit,
                password = '$password',
                is_active = $isActive,
                require_login = $requireLogin,
                show_results = $showResults,
                updated_at = $now
                WHERE quiz_id = $quizId");
            
            // Delete existing questions
            $db->exec("DELETE FROM quiz_questions WHERE quiz_id = $quizId");
        } else {
            // Create new quiz
            $db->exec("INSERT INTO quizzes (title, description, time_limit, password, is_active, require_login, show_results, created_at, updated_at)
                VALUES ('$title', '$description', $timeLimit, '$password', $isActive, $requireLogin, $showResults, $now, $now)");
            $quizId = $db->lastInsertRowID();
        }
        
        // Insert questions
        if (isset($data['questions']) && is_array($data['questions'])) {
            $order = 0;
            foreach ($data['questions'] as $q) {
                $qtype = $db->escapeString($q['question_type'] ?? 'multiple_choice');
                $qtext = $db->escapeString($q['question_text'] ?? '');
                $points = intval($q['points'] ?? 1);
                $options = '';
                if (isset($q['options']) && is_array($q['options'])) {
                    $options = $db->escapeString(json_encode($q['options']));
                }
                $correctAnswer = $db->escapeString($q['correct_answer'] ?? '');
                $mediaUrl = $db->escapeString($q['media_url'] ?? '');
                $mediaType = $db->escapeString($q['media_type'] ?? '');
                
                $db->exec("INSERT INTO quiz_questions (quiz_id, question_type, question_text, question_order, points, options, correct_answer, media_url, media_type)
                    VALUES ($quizId, '$qtype', '$qtext', $order, $points, '$options', '$correctAnswer', '$mediaUrl', '$mediaType')");
                $order++;
            }
        }
        
        $db->exec("COMMIT");
        
        // Update the quiz module on the homepage
        updateQuizModule();
        
        header("Content-Type: application/json");
        echo json_encode(['success' => true, 'quiz_id' => $quizId]);
    } catch (Exception $ex) {
        if (isset($db)) $db->exec("ROLLBACK");
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function deleteQuiz($quizId) {
    try {
        $db = getdb();
        $quizId = intval($quizId);
        
        $db->exec("BEGIN");
        $db->exec("DELETE FROM quiz_answers WHERE submission_id IN (SELECT submission_id FROM quiz_submissions WHERE quiz_id = $quizId)");
        $db->exec("DELETE FROM quiz_submissions WHERE quiz_id = $quizId");
        $db->exec("DELETE FROM quiz_questions WHERE quiz_id = $quizId");
        $db->exec("DELETE FROM quizzes WHERE quiz_id = $quizId");
        $db->exec("COMMIT");
        
        // Update the quiz module on the homepage
        updateQuizModule();
        
        header("Content-Type: application/json");
        echo json_encode(['success' => true]);
    } catch (Exception $ex) {
        if (isset($db)) $db->exec("ROLLBACK");
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function getQuizSubmissions($quizId) {
    try {
        $db = getdb();
        $quizId = intval($quizId);
        
        $result = $db->query("SELECT * FROM quiz_submissions WHERE quiz_id = $quizId ORDER BY submitted_at DESC");
        
        $submissions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $submissions[] = $row;
        }
        
        header("Content-Type: application/json");
        echo json_encode($submissions);
    } catch (Exception $ex) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function getSubmissionDetails($submissionId) {
    try {
        $db = getdb();
        $submissionId = intval($submissionId);
        
        $submission = $db->querySingle("SELECT s.*, q.title as quiz_title FROM quiz_submissions s 
            JOIN quizzes q ON s.quiz_id = q.quiz_id 
            WHERE s.submission_id = $submissionId", true);
        
        if (!$submission) {
            header("HTTP/1.1 404 Not Found");
            echo json_encode(['error' => 'Submission not found']);
            exit;
        }
        
        // Get answers with questions
        $result = $db->query("SELECT a.*, q.question_text, q.question_type, q.options, q.correct_answer, q.points
            FROM quiz_answers a
            JOIN quiz_questions q ON a.question_id = q.question_id
            WHERE a.submission_id = $submissionId
            ORDER BY q.question_order");
        
        $answers = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['options']) {
                $row['options'] = json_decode($row['options'], true);
            }
            $answers[] = $row;
        }
        $submission['answers'] = $answers;
        
        header("Content-Type: application/json");
        echo json_encode($submission);
    } catch (Exception $ex) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function gradeSubmission() {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['submission_id'])) {
            throw new Exception("Invalid data");
        }
        
        $db = getdb();
        $submissionId = intval($data['submission_id']);
        
        $db->exec("BEGIN");
        
        $totalScore = 0;
        $maxScore = 0;
        
        // Update individual answer grades
        if (isset($data['grades']) && is_array($data['grades'])) {
            foreach ($data['grades'] as $answerId => $grade) {
                $answerId = intval($answerId);
                $points = floatval($grade['points'] ?? 0);
                $feedback = $db->escapeString($grade['feedback'] ?? '');
                
                $db->exec("UPDATE quiz_answers SET points_awarded = $points, feedback = '$feedback' WHERE answer_id = $answerId");
                $totalScore += $points;
            }
        }
        
        // Calculate max score
        $maxScore = $db->querySingle("SELECT SUM(q.points) FROM quiz_answers a 
            JOIN quiz_questions q ON a.question_id = q.question_id 
            WHERE a.submission_id = $submissionId");
        
        // Recalculate total from database
        $totalScore = $db->querySingle("SELECT SUM(points_awarded) FROM quiz_answers WHERE submission_id = $submissionId") ?? 0;
        
        // Update submission
        $db->exec("UPDATE quiz_submissions SET score = $totalScore, max_score = $maxScore, graded = 1 WHERE submission_id = $submissionId");
        
        $db->exec("COMMIT");
        
        header("Content-Type: application/json");
        echo json_encode(['success' => true, 'score' => $totalScore, 'max_score' => $maxScore]);
    } catch (Exception $ex) {
        if (isset($db)) $db->exec("ROLLBACK");
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function deleteSubmission($submissionId) {
    try {
        $db = getdb();
        $submissionId = intval($submissionId);
        
        // Delete uploaded files
        $result = $db->query("SELECT file_path FROM quiz_answers WHERE submission_id = $submissionId AND file_path IS NOT NULL");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['file_path'] && file_exists($row['file_path'])) {
                unlink($row['file_path']);
            }
        }
        
        $db->exec("DELETE FROM quiz_answers WHERE submission_id = $submissionId");
        $db->exec("DELETE FROM quiz_submissions WHERE submission_id = $submissionId");
        
        header("Content-Type: application/json");
        echo json_encode(['success' => true]);
    } catch (Exception $ex) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function startQuiz($quizId) {
    try {
        $db = getdb();
        $quizId = intval($quizId);
        
        $quiz = $db->querySingle("SELECT * FROM quizzes WHERE quiz_id = $quizId AND is_active = 1", true);
        if (!$quiz) {
            header("HTTP/1.1 404 Not Found");
            echo json_encode(['error' => 'Quiz not found or not active']);
            exit;
        }
        
        // Check if password is required but not yet verified
        $hasPassword = !empty($quiz['password']);
        
        // Get questions (without correct answers for students)
        $result = $db->query("SELECT question_id, question_type, question_text, question_order, points, options, media_url, media_type FROM quiz_questions WHERE quiz_id = $quizId ORDER BY question_order");
        $questions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['options']) {
                $row['options'] = json_decode($row['options'], true);
            }
            $questions[] = $row;
        }
        $quiz['questions'] = $questions;
        
        // Remove sensitive fields but indicate if password is needed
        $quiz['has_password'] = $hasPassword;
        unset($quiz['password']);
        unset($quiz['created_at']);
        unset($quiz['updated_at']);
        
        header("Content-Type: application/json");
        echo json_encode($quiz);
    } catch (Exception $ex) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function checkQuizPassword($quizId, $password) {
    try {
        $db = getdb();
        $quizId = intval($quizId);
        $password = $db->escapeString($password);
        
        $quiz = $db->querySingle("SELECT password FROM quizzes WHERE quiz_id = $quizId AND is_active = 1", true);
        if (!$quiz) {
            header("HTTP/1.1 404 Not Found");
            echo json_encode(['error' => 'Quiz not found']);
            exit;
        }
        
        $valid = ($quiz['password'] === $password);
        
        header("Content-Type: application/json");
        echo json_encode(['valid' => $valid]);
    } catch (Exception $ex) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function submitQuiz() {
    try {
        $db = getdb();
        
        // Handle multipart form data for file uploads
        $quizId = intval($_POST['quiz_id'] ?? 0);
        $studentName = $db->escapeString($_POST['student_name'] ?? 'Anonymous');
        $secretKey = $db->escapeString($_POST['secret_key'] ?? '');
        $answers = json_decode($_POST['answers'] ?? '[]', true);
        
        if (!$quizId || !$answers) {
            throw new Exception("Missing required data");
        }
        
        $now = time();
        
        $db->exec("BEGIN");
        
        // Create submission
        $db->exec("INSERT INTO quiz_submissions (quiz_id, student_name, secret_key, started_at, submitted_at)
            VALUES ($quizId, '$studentName', '$secretKey', $now, $now)");
        $submissionId = $db->lastInsertRowID();
        
        $totalScore = 0;
        $maxScore = 0;
        
        // Process answers
        foreach ($answers as $answer) {
            $questionId = intval($answer['question_id']);
            $answerText = $db->escapeString($answer['answer'] ?? '');
            $filePath = '';
            
            // Get question info
            $question = $db->querySingle("SELECT * FROM quiz_questions WHERE question_id = $questionId", true);
            if (!$question) continue;
            
            $maxScore += $question['points'];
            
            // Handle file upload
            $fileKey = 'file_' . $questionId;
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $uploadDir = getAbsModPath() . '/../admin/quiz_uploads/' . $submissionId . '/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $fileName = basename($_FILES[$fileKey]['name']);
                $filePath = $uploadDir . $fileName;
                move_uploaded_file($_FILES[$fileKey]['tmp_name'], $filePath);
                $filePath = $db->escapeString($filePath);
            }
            
            // Auto-grade multiple choice
            $pointsAwarded = 0;
            if ($question['question_type'] === 'multiple_choice') {
                if ($answerText === $question['correct_answer']) {
                    $pointsAwarded = $question['points'];
                    $totalScore += $pointsAwarded;
                }
            }
            
            $db->exec("INSERT INTO quiz_answers (submission_id, question_id, answer_text, file_path, points_awarded)
                VALUES ($submissionId, $questionId, '$answerText', '$filePath', $pointsAwarded)");
        }
        
        // Update submission with preliminary score
        $graded = 1; // Assume graded for all multiple choice
        $result = $db->query("SELECT q.question_type FROM quiz_questions q WHERE q.quiz_id = $quizId");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['question_type'] !== 'multiple_choice') {
                $graded = 0;
                break;
            }
        }
        
        // Calculate MC-only score for display to students
        $mcScore = $db->querySingle("SELECT COALESCE(SUM(a.points_awarded), 0) FROM quiz_answers a 
            JOIN quiz_questions q ON a.question_id = q.question_id 
            WHERE a.submission_id = $submissionId AND q.question_type = 'multiple_choice'");
        $mcMaxScore = $db->querySingle("SELECT COALESCE(SUM(q.points), 0) FROM quiz_questions q 
            WHERE q.quiz_id = $quizId AND q.question_type = 'multiple_choice'");
        $hasManualQuestions = $db->querySingle("SELECT COUNT(*) FROM quiz_questions 
            WHERE quiz_id = $quizId AND question_type != 'multiple_choice'") > 0;
        
        $db->exec("UPDATE quiz_submissions SET score = $totalScore, max_score = $maxScore, graded = $graded WHERE submission_id = $submissionId");
        
        $db->exec("COMMIT");
        
        // Get quiz settings for response
        $showResults = $db->querySingle("SELECT show_results FROM quizzes WHERE quiz_id = $quizId");
        
        $response = ['success' => true, 'submission_id' => $submissionId];
        if ($showResults) {
            // Only show MC score to students, indicate if there are questions pending review
            $response['mc_score'] = $mcScore;
            $response['mc_max_score'] = $mcMaxScore;
            $response['mc_percentage'] = $mcMaxScore > 0 ? round(($mcScore / $mcMaxScore) * 100, 1) : 0;
            $response['has_manual_questions'] = $hasManualQuestions;
            // Keep total for backwards compatibility
            $response['score'] = $mcScore;
            $response['max_score'] = $mcMaxScore;
            $response['percentage'] = $response['mc_percentage'];
        }
        
        header("Content-Type: application/json");
        echo json_encode($response);
    } catch (Exception $ex) {
        if (isset($db)) $db->exec("ROLLBACK");
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

/**
 * Creates or updates the Student Quizzes module on the homepage
 * This module is auto-generated when quizzes are created/deleted
 */
function updateQuizModule() {
    try {
        $db = getdb();
        $modPath = getAbsModPath() . '/student-quizzes';
        
        // Get active quizzes
        $result = $db->query("SELECT quiz_id, title, description, time_limit, 
            (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = quizzes.quiz_id) as question_count
            FROM quizzes WHERE is_active = 1 ORDER BY title");
        
        $quizzes = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $quizzes[] = $row;
        }
        
        // If no active quizzes, remove the module folder
        if (empty($quizzes)) {
            if (is_dir($modPath)) {
                exec("rm -rf " . escapeshellarg($modPath));
            }
            return;
        }
        
        // Create module directory
        if (!is_dir($modPath)) {
            mkdir($modPath, 0755, true);
        }
        
        // Build the quiz list HTML
        $quizListHtml = '';
        foreach ($quizzes as $quiz) {
            $timeInfo = $quiz['time_limit'] > 0 ? $quiz['time_limit'] . ' min' : 'Untimed';
            $quizListHtml .= '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:15px; margin-bottom:10px;">';
            $quizListHtml .= '<h3 style="margin:0 0 5px 0;"><a href="/quiz.php?id=' . $quiz['quiz_id'] . '" style="color:#2563eb; text-decoration:none;">' . htmlspecialchars($quiz['title']) . '</a></h3>';
            $quizListHtml .= '<p style="margin:0; color:#64748b; font-size:0.9em;">' . $quiz['question_count'] . ' questions | ' . $timeInfo . '</p>';
            if ($quiz['description']) {
                $quizListHtml .= '<p style="margin:8px 0 0 0; color:#334155;">' . htmlspecialchars($quiz['description']) . '</p>';
            }
            $quizListHtml .= '</div>';
        }
        
        // Create rachel-index.php
        $indexContent = '<!-- version = "1.0" -->
<div class="item"><a href="/quiz.php">
<h2>Student Quizzes</h2>
<p>Take quizzes assigned by your teacher</p>
</a></div>';
        
        file_put_contents($modPath . '/rachel-index.php', $indexContent);
        
        // Create index.html with quiz list
        $fullPageHtml = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Quizzes - RACHEL</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f1f5f9; }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #1e293b; margin-bottom: 10px; }
        .subtitle { color: #64748b; margin-bottom: 30px; }
        .quiz-list { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        a { color: #2563eb; }
        a:hover { text-decoration: underline; }
        .back-link { margin-top: 30px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Student Quizzes</h1>
        <p class="subtitle">Select a quiz to begin</p>
        <div class="quiz-list">
            ' . $quizListHtml . '
        </div>
        <p class="back-link"><a href="/">Back to RACHEL Home</a></p>
    </div>
</body>
</html>';
        
        file_put_contents($modPath . '/index.html', $fullPageHtml);
        
        // Create a simple logo
        $logoSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">
  <rect width="256" height="256" fill="#3b82f6" rx="32"/>
  <text x="128" y="160" font-family="Arial, sans-serif" font-size="120" fill="white" text-anchor="middle">?</text>
</svg>';
        file_put_contents($modPath . '/logo.svg', $logoSvg);
        
    } catch (Exception $ex) {
        error_log("Failed to update quiz module: " . $ex->getMessage());
    }
}

// Sponsor Settings Functions - World Possible Attribution Required
function getSponsorSettings() {
    // Attribution verification
    if (!wp_feature_enabled('sponsor')) {
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: application/json");
        echo json_encode(['error' => 'World Possible attribution required for this feature']);
        exit;
    }
    
    try {
        $db = getdb();
        
        $settings = [
            'enabled' => 0,
            'name' => '',
            'tagline' => '',
            'website' => '',
            'logo' => '',
            'bg_color' => '#1e40af',
            'text_color' => '#ffffff',
            'custom_message' => '',
            'position' => 'top'
        ];
        
        // Load settings from database
        $fields = ['sponsor_enabled', 'sponsor_name', 'sponsor_tagline', 'sponsor_website', 
                   'sponsor_logo', 'sponsor_bg_color', 'sponsor_text_color', 'sponsor_custom_message', 'sponsor_position'];
        
        foreach ($fields as $field) {
            $result = $db->querySingle("SELECT value FROM prefs WHERE pref = '$field'");
            if ($result !== null) {
                $key = str_replace('sponsor_', '', $field);
                $settings[$key] = $result;
            }
        }
        
        header("Content-Type: application/json");
        echo json_encode($settings);
    } catch (Exception $ex) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function saveSponsorSettings() {
    // Attribution verification
    if (!wp_feature_enabled('sponsor')) {
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: application/json");
        echo json_encode(['error' => 'World Possible attribution required for this feature']);
        exit;
    }
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            throw new Exception("Invalid JSON data");
        }
        
        $db = getdb();
        
        // Save settings to database
        $fields = [
            'sponsor_enabled' => $data['enabled'] ?? 0,
            'sponsor_name' => $data['name'] ?? '',
            'sponsor_tagline' => $data['tagline'] ?? '',
            'sponsor_website' => $data['website'] ?? '',
            'sponsor_logo' => $data['logo'] ?? '',
            'sponsor_bg_color' => $data['bg_color'] ?? '#1e40af',
            'sponsor_text_color' => $data['text_color'] ?? '#ffffff',
            'sponsor_custom_message' => $data['custom_message'] ?? '',
            'sponsor_position' => $data['position'] ?? 'top'
        ];
        
        foreach ($fields as $pref => $value) {
            $value = $db->escapeString($value);
            $db->exec("INSERT OR REPLACE INTO prefs (pref, value) VALUES ('$pref', '$value')");
        }
        
        // Create or update the sponsor module
        updateSponsorModule($data);
        
        header("Content-Type: application/json");
        echo json_encode(['success' => true]);
    } catch (Exception $ex) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function uploadSponsorLogo() {
    header("Content-Type: application/json");
    
    // Attribution verification
    if (!wp_feature_enabled('sponsor')) {
        header("HTTP/1.1 403 Forbidden");
        echo json_encode(['error' => 'World Possible attribution required for this feature']);
        exit;
    }
    
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => 'No file uploaded or upload error']);
        exit;
    }
    
    $file = $_FILES['logo'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP, SVG']);
        exit;
    }
    
    // Create sponsor assets directory in modules
    $assetsDir = MODPATH . '/sponsor-assets';
    if (!is_dir($assetsDir)) {
        mkdir($assetsDir, 0755, true);
        
        // Create a hidden rachel-index.php so it doesn't appear on homepage
        $indexContent = '<?php
$mod = array(
    "title"         => "Sponsor Assets",
    "category"      => "system",
    "description"   => "Assets for sponsorship module",
    "dir"           => __DIR__,
    "moddir"        => basename(__DIR__),
    "fragment"      => ""  // empty = hidden module
);
?>';
        file_put_contents($assetsDir . '/rachel-index.php', $indexContent);
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (!$ext) {
        // Derive from mime type
        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg'
        ];
        $ext = $extMap[$mimeType] ?? 'png';
    }
    $filename = 'sponsor-logo-' . date('Ymd-His') . '.' . strtolower($ext);
    $targetPath = $assetsDir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $url = '/modules/sponsor-assets/' . $filename;
        echo json_encode([
            'success' => true,
            'url' => $url,
            'filename' => $filename
        ]);
    } else {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => 'Failed to save uploaded file']);
    }
    exit;
}

function updateSponsorModule($settings) {
    $modDir = 'zz-sponsor-module';
    $modPath = MODPATH . '/' . $modDir;
    
    // If disabled, remove the module if it exists
    if (!$settings['enabled']) {
        if (is_dir($modPath)) {
            // Remove from database
            $db = getdb();
            $db->exec("DELETE FROM modules WHERE moddir = '$modDir'");
            
            // Remove files
            array_map('unlink', glob("$modPath/*"));
            @rmdir($modPath);
        }
        return;
    }
    
    // Create module directory
    if (!is_dir($modPath)) {
        mkdir($modPath, 0755, true);
    }
    
    $name = htmlspecialchars($settings['name'] ?? 'Our Sponsor');
    $tagline = htmlspecialchars($settings['tagline'] ?? '');
    $website = htmlspecialchars($settings['website'] ?? '');
    $logo = htmlspecialchars($settings['logo'] ?? '');
    $bgColor = htmlspecialchars($settings['bg_color'] ?? '#1e40af');
    $textColor = htmlspecialchars($settings['text_color'] ?? '#ffffff');
    $customMessage = htmlspecialchars($settings['custom_message'] ?? '');
    $position = $settings['position'] ?? 'top';
    
    // Create rachel-index.php fragment for homepage display
    $fragment = '<?php
// Sponsor Module Fragment
$sponsorName = "' . addslashes($name) . '";
$sponsorTagline = "' . addslashes($tagline) . '";
$sponsorWebsite = "' . addslashes($website) . '";
$sponsorLogo = "' . addslashes($logo) . '";
$sponsorBgColor = "' . addslashes($bgColor) . '";
$sponsorTextColor = "' . addslashes($textColor) . '";
$sponsorMessage = "' . addslashes($customMessage) . '";
?>
<div class="modlink" style="margin-bottom:20px;">
    <div style="background:<?php echo $sponsorBgColor; ?>; color:<?php echo $sponsorTextColor; ?>; padding:25px; text-align:center; border-radius:12px 12px 0 0;">
        <p style="margin:0 0 8px 0; font-size:0.95em; opacity:0.9;">🎗️ This educational resource is brought to you by</p>
        <?php if ($sponsorLogo): ?>
        <img src="<?php echo $sponsorLogo; ?>" alt="<?php echo $sponsorName; ?>" style="max-height:80px; max-width:250px; margin:15px 0;" onerror="this.style.display=\'none\'">
        <?php endif; ?>
        <h2 style="margin:10px 0 5px 0; font-size:1.6em; font-weight:600;"><?php echo $sponsorName; ?></h2>
        <?php if ($sponsorTagline): ?>
        <p style="margin:0; font-size:1.1em; opacity:0.9;"><?php echo $sponsorTagline; ?></p>
        <?php endif; ?>
        <?php if ($sponsorWebsite): ?>
        <p style="margin:15px 0 0 0;">
            <a href="<?php echo $sponsorWebsite; ?>" target="_blank" style="color:<?php echo $sponsorTextColor; ?>; opacity:0.85; font-size:0.9em;">
                🔗 <?php echo preg_replace(\'/^https?:\\/\\//\', \'\', $sponsorWebsite); ?>
            </a>
        </p>
        <?php endif; ?>
    </div>
    <?php if ($sponsorMessage): ?>
    <div style="background:white; padding:20px; border:1px solid #e5e7eb; border-top:none; border-radius:0 0 12px 12px;">
        <p style="margin:0; color:#475569; text-align:center; line-height:1.6;"><?php echo $sponsorMessage; ?></p>
    </div>
    <?php endif; ?>
</div>
';
    
    file_put_contents($modPath . '/rachel-index.php', $fragment);
    
    // Create index.html for direct access
    $indexHtml = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sponsored by ' . $name . '</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f1f5f9; }
        .container { max-width: 600px; margin: 0 auto; }
        .sponsor-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .sponsor-header { background: ' . $bgColor . '; color: ' . $textColor . '; padding: 40px 30px; text-align: center; }
        .sponsor-header img { max-height: 100px; max-width: 280px; margin: 20px 0; }
        .sponsor-header h1 { margin: 15px 0 10px 0; font-size: 1.8em; }
        .sponsor-header p { margin: 0; opacity: 0.9; }
        .sponsor-body { padding: 30px; text-align: center; }
        .sponsor-body p { color: #475569; line-height: 1.7; margin: 0 0 20px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: ' . $bgColor . '; color: ' . $textColor . '; text-decoration: none; border-radius: 8px; font-weight: 500; }
        .btn:hover { opacity: 0.9; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #3b82f6; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="sponsor-card">
            <div class="sponsor-header">
                <p style="font-size: 1.1em;">🎗️ This educational resource is brought to you by</p>
                ' . ($logo ? '<img src="' . $logo . '" alt="' . $name . '" onerror="this.style.display=\'none\'">' : '') . '
                <h1>' . $name . '</h1>
                ' . ($tagline ? '<p style="font-size: 1.15em;">' . $tagline . '</p>' : '') . '
            </div>
            <div class="sponsor-body">
                ' . ($customMessage ? '<p>' . nl2br($customMessage) . '</p>' : '<p>Thank you for supporting educational access in our community.</p>') . '
                ' . ($website ? '<a href="' . $website . '" target="_blank" class="btn">Visit ' . $name . '</a>' : '') . '
            </div>
        </div>
        <div class="back-link">
            <a href="/">← Back to RACHEL Home</a>
        </div>
    </div>
</body>
</html>';
    
    file_put_contents($modPath . '/index.html', $indexHtml);
    
    // Create a simple logo.svg
    $logoSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">
  <rect width="256" height="256" fill="' . $bgColor . '" rx="32"/>
  <text x="128" y="100" font-family="Arial, sans-serif" font-size="60" fill="' . $textColor . '" text-anchor="middle">🎗️</text>
  <text x="128" y="170" font-family="Arial, sans-serif" font-size="24" fill="' . $textColor . '" text-anchor="middle">SPONSOR</text>
</svg>';
    file_put_contents($modPath . '/logo.svg', $logoSvg);
    
    // Update database entry with position
    $db = getdb();
    $posVal = ($position === 'top') ? -1000 : 1000;
    
    // Check if exists
    $existing = $db->querySingle("SELECT moddir FROM modules WHERE moddir = '$modDir'");
    if ($existing) {
        $db->exec("UPDATE modules SET position = $posVal, hidden = 0 WHERE moddir = '$modDir'");
    } else {
        $db->exec("INSERT INTO modules (moddir, position, hidden) VALUES ('$modDir', $posVal, 0)");
    }
}

// Category Management Functions - World Possible Attribution Required
function getCategoryMap() {
    // Attribution verification
    if (!wp_feature_enabled('categories')) {
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: application/json");
        echo json_encode(['error' => 'World Possible attribution required for this feature']);
        exit;
    }
    
    try {
        $mapFile = __DIR__ . '/category-map.json';
        if (file_exists($mapFile)) {
            header("Content-Type: application/json");
            echo file_get_contents($mapFile);
        } else {
            header("Content-Type: application/json");
            echo json_encode(['categories' => [], 'modules' => []]);
        }
    } catch (Exception $ex) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function getModuleCategories() {
    // Attribution verification
    if (!wp_feature_enabled('categories')) {
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: application/json");
        echo json_encode(['error' => 'World Possible attribution required for this feature']);
        exit;
    }
    
    try {
        $db = getdb();
        
        // Load master category map
        $mapFile = __DIR__ . '/category-map.json';
        $masterMap = [];
        $categories = [];
        if (file_exists($mapFile)) {
            $data = json_decode(file_get_contents($mapFile), true);
            $masterMap = $data['modules'] ?? [];
            $categories = $data['categories'] ?? [];
        }
        
        // Load admin overrides from database
        $overrides = [];
        $result = $db->query("SELECT moddir, categories FROM module_categories");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $overrides[$row['moddir']] = json_decode($row['categories'], true) ?: [];
        }
        
        // Get all modules from filesystem
        $modules = [];
        $modPath = defined('MODPATH') ? MODPATH : '/.data/RACHEL/rachel/modules';
        if (is_dir($modPath)) {
            $dirs = scandir($modPath);
            foreach ($dirs as $dir) {
                if ($dir[0] === '.' || !is_dir($modPath . '/' . $dir)) continue;
                
                // Priority: admin override > master map > empty
                $cats = [];
                if (isset($overrides[$dir])) {
                    $cats = $overrides[$dir];
                } elseif (isset($masterMap[$dir])) {
                    $cats = $masterMap[$dir];
                }
                
                $modules[$dir] = [
                    'categories' => $cats,
                    'source' => isset($overrides[$dir]) ? 'override' : (isset($masterMap[$dir]) ? 'master' : 'none')
                ];
            }
        }
        
        header("Content-Type: application/json");
        echo json_encode([
            'categories' => $categories,
            'modules' => $modules
        ]);
    } catch (Exception $ex) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function saveModuleCategory() {
    // Attribution verification
    if (!wp_feature_enabled('categories')) {
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: application/json");
        echo json_encode(['error' => 'World Possible attribution required for this feature']);
        exit;
    }
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['moddir'])) {
            throw new Exception("Invalid data");
        }
        
        $db = getdb();
        $moddir = $db->escapeString($data['moddir']);
        $categories = $db->escapeString(json_encode($data['categories'] ?? []));
        
        // If categories is empty array and we want to reset to master, delete the override
        if (empty($data['categories']) && !empty($data['reset'])) {
            $db->exec("DELETE FROM module_categories WHERE moddir = '$moddir'");
        } else {
            $db->exec("INSERT OR REPLACE INTO module_categories (moddir, categories) VALUES ('$moddir', '$categories')");
        }
        
        header("Content-Type: application/json");
        echo json_encode(['success' => true]);
    } catch (Exception $ex) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function createCategory() {
    // Attribution verification
    if (!wp_feature_enabled('categories')) {
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: application/json");
        echo json_encode(['error' => 'World Possible attribution required for this feature']);
        exit;
    }
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['id']) || !isset($data['label'])) {
            throw new Exception("Category ID and label are required");
        }
        
        $catId = preg_replace('/[^a-z0-9_]/', '', strtolower($data['id']));
        if (empty($catId)) {
            throw new Exception("Invalid category ID");
        }
        
        $mapFile = __DIR__ . '/category-map.json';
        $mapData = ['categories' => [], 'modules' => []];
        
        if (file_exists($mapFile)) {
            $mapData = json_decode(file_get_contents($mapFile), true) ?: $mapData;
        }
        
        // Check if category already exists
        if (isset($mapData['categories'][$catId])) {
            throw new Exception("Category '$catId' already exists");
        }
        
        // Add new category
        $mapData['categories'][$catId] = [
            'icon' => $data['icon'] ?? '📁',
            'label' => $data['label'],
            'color' => $data['color'] ?? '#6366f1'
        ];
        
        // Save the file
        file_put_contents($mapFile, json_encode($mapData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        header("Content-Type: application/json");
        echo json_encode(['success' => true, 'id' => $catId]);
    } catch (Exception $ex) {
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

function deleteCategory() {
    // Attribution verification
    if (!wp_feature_enabled('categories')) {
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: application/json");
        echo json_encode(['error' => 'World Possible attribution required for this feature']);
        exit;
    }
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['id'])) {
            throw new Exception("Category ID is required");
        }
        
        $catId = $data['id'];
        $mapFile = __DIR__ . '/category-map.json';
        
        if (!file_exists($mapFile)) {
            throw new Exception("Category map not found");
        }
        
        $mapData = json_decode(file_get_contents($mapFile), true);
        if (!$mapData) {
            throw new Exception("Failed to parse category map");
        }
        
        // Remove category from definitions
        if (isset($mapData['categories'][$catId])) {
            unset($mapData['categories'][$catId]);
        }
        
        // Remove category from all module mappings
        if (isset($mapData['modules'])) {
            foreach ($mapData['modules'] as $moddir => $cats) {
                if (is_array($cats)) {
                    $mapData['modules'][$moddir] = array_values(array_filter($cats, function($c) use ($catId) {
                        return $c !== $catId;
                    }));
                }
            }
        }
        
        // Also remove from database overrides
        $db = getdb();
        $result = $db->query("SELECT moddir, categories FROM module_categories");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $cats = json_decode($row['categories'], true) ?: [];
            $newCats = array_values(array_filter($cats, function($c) use ($catId) {
                return $c !== $catId;
            }));
            if (count($newCats) !== count($cats)) {
                $moddir = $db->escapeString($row['moddir']);
                $catsJson = $db->escapeString(json_encode($newCats));
                $db->exec("UPDATE module_categories SET categories = '$catsJson' WHERE moddir = '$moddir'");
            }
        }
        
        // Save the file
        file_put_contents($mapFile, json_encode($mapData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        header("Content-Type: application/json");
        echo json_encode(['success' => true]);
    } catch (Exception $ex) {
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json");
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

?>
