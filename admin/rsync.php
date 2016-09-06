<?php

#-------------------------------------------
# This script runs rsync from php, and logs output
# and information to the database -- the intent is
# for another process to read from the database to
# get the status of the rsync command as it works
#
# Usage: rsync.php host module
#-------------------------------------------

require_once("common.php");

# The extra logging will go to the apache error log
# even though this doesn't really run under appache
define("EXTRA_LOGGING", true);

# input checking
if (empty($argv[2])) {
    error_log("Usage: $argv[0] host module");
    error_log("Missing arguments to $argv[0]");
    exit(1);
}
if (!preg_match("/^(localhost|dev\.worldpossible\.org|\d+\.\d+\.\d+\.\d+)$/", $argv[1])) {
    error_log("Usage: $argv[0] host module");
    error_log("Invalid hostname/IP to $argv[0]");
    exit(1);
}
if (preg_match("/[^\w\.\-]/", $argv[2])) {
    error_log("Usage: $argv[0] host module");
    error_log("Invalid characters in module name");
    exit(1);
}

# get ready to pipe
$pipedefs = array(
   0 => array("pipe", "r"), // stdin
   1 => array("pipe", "w"), // stdout
   2 => array("pipe", "w")  // stderr
);

$host = $argv[1];
$moddir = $argv[2];
$relmodpath = getrelmodpath();
$cmd = "rsync -Pavz rsync://$host/rachelmods/$moddir $relmodpath/";
#$cmd = "ping -c 10 localhost";
if (EXTRA_LOGGING) { error_log("$cmd"); }

# task_id   INTEGER PRIMARY KEY,
# command   VARCHAR(255),
# pid       INTEGER,
# stdout_tail TEXT,
# stderr_tail TEXT,
# started     INTEGER, -- timestamp
# last_update INTEGER, -- timestamp
# completed   INTEGER, -- timestamp
# dismissed   INTEGER, -- timestamp
# retval    INTEGER

#-------------------------------------------
# record our effort in the DB - we do this even before
# running the command so that if it fails so completely
# we can't even record that failure, we still have a record
#-------------------------------------------
$db = getdb();
$db_cmd = $db->escapeString($cmd);
$db_started = $db->escapeString(time());
$db->exec("INSERT INTO tasks (command, started) VALUES ('$db_cmd', '$db_started')");
$db_task_id = $db->escapeString($db->lastInsertRowID());

#-------------------------------------------
# here we actually fire off the process and see what happens 
#-------------------------------------------
if (EXTRA_LOGGING) { error_log("opening process"); }
$proc = proc_open($cmd, $pipedefs, $pipes);
$info = proc_get_status($proc);
# if it's already dead, record and exit
if (!$info['running']) {
    if (EXTRA_LOGGING) { error_log("process completed instantly"); }
    $db_pid = $db->escapeString($info['pid']);
    $db_completed = $db->escapeString(time());
    $db_stdout_tail = $db->escapeString(fread($pipes[1], 1024));
    $db_stderr_tail = $db->escapeString(fread($pipes[2], 1024));
    $db_retval = $db->escapeString($info['exitcode']);
    # we auto-dismiss if it completed successfully
    $db_dismissed = $info['exitcode'] == 0 ? "'$db_completed'" : "NULL";
    $db->exec("
        UPDATE tasks SET
            pid = '$db_pid',
            completed = '$db_completed',
            last_update = '$db_completed',
            dismissed = $db_dismissed,
            stdout_tail = '$db_stdout_tail',
            stderr_tail = '$db_stderr_tail',
            retval = '$db_retval'
        WHERE task_id = '$db_task_id'
    ");
    if (EXTRA_LOGGING) { error_log($db_stdout_tail); }
    if (EXTRA_LOGGING) { error_log($db_stderr_tail); }
    exit;
} else {
    $db_pid = $db->escapeString($info['pid']);
    $db_last_update = $db->escapeString(time());
    $db->exec("
        UPDATE tasks SET
            pid = '$db_pid',
            last_update = '$db_last_update'
        WHERE task_id = '$db_task_id'
    ");
}

if (EXTRA_LOGGING) { error_log("opened process, now reading"); }

#-------------------------------------------
# We keep reading from the pipe, one char at a time
# and store up lines, writing $tail_length at a time to
# the DB. We also limit ourselves to one write
# per $frequency seconds.
#-------------------------------------------
$line = "";
$lines = array();
$cr = false;
$frequency = 2;
$tail_length = 2;
$lastone = false;
while (1) {

    $char = fgetc($pipes[1]);

    # we don't do this test at the top of the loop
    # because we need to send the last bits from
    # the pipe to the database, so instead we set a flag
    if (feof($pipes[1])) {
        $lastone = true;
        $line .= $char;
    }

    if ($char == "\n" || $char == "\r" || $lastone) {

        # skip empty lines
        if (preg_match("/\S/", $line)) {

            # if the previous line had a carriage return
            # it means we should replace instead of append
            if ($cr) {
                if (count($lines) > 0) { array_pop( $lines ); }
                array_push($lines, $line);
                $cr = false;
            } else {
                array_push($lines, $line);
                $lines = array_slice($lines, -$tail_length);
            }

        }

        # we've recorded the line in the array, clear it
        $line = "";

        # keep track of whether this line should be replaced
        if ($char == "\r") { $cr = true; }

        # we will update the db with the latest output
        # periodically, and also on our last output
        if ( $db_last_update < time() - $frequency || $lastone ) {

            $db_stdout_tail = $db->escapeString(implode("\n", $lines));
            $db_last_update = $db->escapeString(time());
            $db->exec("
                UPDATE tasks SET
                    stdout_tail = '$db_stdout_tail',
                    last_update = '$db_last_update'
                WHERE task_id = '$db_task_id'
            ");
            if (EXTRA_LOGGING) { error_log("updating db with:\n'$db_stdout_tail'"); }

        }

        if ($lastone) { break; }

    } else {
        # just capture this character
        $line .= $char;
    }

}

#error_log("in lines array: " . implode("\n", $lines));
#error_log("in pipe: " . fread($pipes[2], 1024));


# grab whatever is left in stderr (up to 1 kb)
$db_stderr_tail = $db->escapeString(fread($pipes[2], 1024));

#-------------------------------------------
# we're done reading, so now we write the final results
# to the db, including the exitcode and any stderr output
#-------------------------------------------
if (EXTRA_LOGGING) { error_log("finished reading, calling fclose(0)"); }
fclose($pipes[0]);
if (EXTRA_LOGGING) { error_log("calling fclose(1)"); }
fclose($pipes[1]);
if (EXTRA_LOGGING) { error_log("calling fclose(2)"); }
fclose($pipes[2]);
if (EXTRA_LOGGING) { error_log("pipes closed, calling proc_close()"); }

# close the process
$db_retval = $db->escapeString(proc_close($proc));
$db_completed = $db->escapeString(time());
$db_dismissed = $db_retval == 0 ? "'$db_completed'" : "NULL";

$db->exec("
    UPDATE tasks SET
        completed = '$db_completed',
        last_update = '$db_completed',
        dismissed = $db_dismissed,
        stderr_tail = '$db_stderr_tail',
        retval = '$db_retval'
    WHERE task_id = '$db_task_id'
");

if (EXTRA_LOGGING) { error_log("finished updating db, "); }
#proc_terminate($proc);
if (EXTRA_LOGGING) { error_Log("all done."); }

?>
