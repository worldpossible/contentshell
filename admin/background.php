<?php

require_once("common.php");

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

}

error_log("Unknown request to background.php");
header("HTTP/1.1 500 Internal Server Error");
exit;

#-------------------------------------------
# functions
#-------------------------------------------

function getRemoteModuleList() {
    $json = file_get_contents("http://dev.worldpossible.org/cgi/json_api_v1.pl");
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
    echo json_encode(array_values($fsmods), JSON_PRETTY_PRINT);
    exit;
}

function deleteModule($moddir) {

    $deldir = getrelmodpath() . "/" . $moddir;

    # XXX standardize and refactor all the error handling in this module
    if (preg_match("/[^\w\-\.]/", $moddir)) {
        log_error("deleteModule: Invalid Module Name");
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json");
        echo "{ \"error\" : \"deleteModule: Invalid Module Name\", \"moddir\" : \"$moddir\" }\n";
        exit;
    }

    # brutally dangerous? how to improve?
    exec("rm -rf $deldir 2>&1", $output, $rval);

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

function addModule($moddir) {

    # fire off our clever database updating rsync process
    exec("php rsync.php localhost $moddir > /dev/null &", $output, $rval);

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
    $task = $rv->fetchArray(SQLITE3_ASSOC);

    if (!$task || !$task['pid']) {
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json");
        echo "{ \"error\" : \"No Such Task: $task_id\" }\n";
        exit;
    }

    if (!$task['completed']) {
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
    while ($row = $rv->fetchArray(SQLITE3_ASSOC)) {
        $row['tasktime'] = time(); // so we can calculate against server time, not browser time
        array_push($tasks, $row);
    }
    header("HTTP/1.1 200 OK");
    header("Content-Type: application/json");
    echo json_encode($tasks, JSON_PRETTY_PRINT);
    exit;

}

?>
