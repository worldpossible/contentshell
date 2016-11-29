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


$host = $argv[1];
$moddir = $argv[2];
$relmodpath = getrelmodpath();
$cmd = "rsync -Pavz rsync://$host/rachelmods/$moddir $relmodpath/";
if (EXTRA_LOGGING) { error_log("$cmd"); }

#-------------------------------------------
# record our effort in the DB - we do this even before
# running the command so that if it fails so completely
# we can't even record that failure, we still have a record
#-------------------------------------------
$db = getdb();
$db_cmd = $db->escapeString($cmd);
$db_moddir = $db->escapeString($moddir);
$db_started = $db->escapeString(time());

#-------------------------------------------
# if there's already an rsync running, we queue this instead
# of running it
#-------------------------------------------
$pidfile = "/tmp/rachel-rsync.pid";
if (file_exists($pidfile)) {
    # we verify that the pid really is running
    $pid = preg_replace("/\D+/", "", file_get_contents($pidfile));
    $output = exec("ps $pid | sed 1d");
    if (!empty($output)) {
        # there's a legit process... queue our task and leave
        if (EXTRA_LOGGING) { error_log("rsync already running... adding to queue"); }
        $db->exec("
            INSERT INTO tasks ( moddir, command )
            VALUES ( '$db_moddir', '$db_cmd' )
        ");
        exit(0);
    } else {
        # something went wrong, let's try again
        if (EXTRA_LOGGING) { error_log("dead rsync - restarting..."); }
        unlink($pidfile);
    }
}
# we're clear - let's run
file_put_contents($pidfile, getmypid());
# make sure we clear our pid file when we finish
function rsync_cleanup() {
    global $pidfile;
    unlink($pidfile);
}
register_shutdown_function('rsync_cleanup');


#-------------------------------------------
# record ourselves in the DB
#-------------------------------------------
error_log("inserting task...");
$db->exec("
    INSERT INTO tasks (
        moddir, command, started, files_done, data_done, data_rate
    ) VALUES (
        '$db_moddir', '$db_cmd', '$db_started', 0, 0, ''
    )
");
$db_task_id = $db->escapeString($db->lastInsertRowID());

#-------------------------------------------
# we run rsync commands over and over until all tasks are done
#-------------------------------------------
while ($db_task_id) {

    #-------------------------------------------
    # here we actually fire off the process and see what happens 
    #-------------------------------------------
    if (EXTRA_LOGGING) { error_log("opening process: $cmd"); }
    $pipedefs = array(
       0 => array("pipe", "r"), // stdin
       1 => array("pipe", "w"), // stdout
       2 => array("pipe", "w")  // stderr
    );
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
        $db->exec("
            UPDATE tasks SET
                pid = '$db_pid',
                completed = '$db_completed',
                last_update = '$db_completed',
                stdout_tail = '$db_stdout_tail',
                stderr_tail = '$db_stderr_tail',
                retval = '$db_retval'
            WHERE task_id = '$db_task_id'
        ");
        if (EXTRA_LOGGING) { error_log($db_stdout_tail); }
        if (EXTRA_LOGGING) { error_log($db_stderr_tail); }
        exit(0);
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
    $files_done = 0;
    $data_done = 0;
    while (true) {

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

                # Check this most recent line to see if it indicates
                # a completed file. If so, we count that and the data
                # transferred.
                if (preg_match("/^\s+(\d+) 100%\s+(\S+)/", $line, $matches)) {
                    ++$files_done;
                    $data_done += $matches[1];
                    $data_rate = $matches[2];
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
                        last_update = '$db_last_update',
                        files_done  = '$files_done',
                        data_done   = '$data_done',
                        data_rate   = '$data_rate'
                    WHERE task_id   = '$db_task_id'
                ");
#                if (EXTRA_LOGGING) { error_log("updating db with:\n'$db_stdout_tail'"); }

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

    # run any installation script if present
    # XXX this needs better error checking and feedback
    $path = "$relmodpath/$moddir";
    $script = "finish_install.sh";
    if (file_exists("$path/$script")) {
        if (EXTRA_LOGGING) { error_Log("running $path/$script"); }
        $output = array();
        exec("cd $path && bash $script 2>&1", $output, $rval);
        # put some record of our problem in the db
        if ($rval != 0) {
            $db_stderr_tail .= implode("\n", $output);
            $db_retval = $rval;
        }
    }

    if (EXTRA_LOGGING) { error_log("updating db"); }
    $db->exec("
        UPDATE tasks SET
            completed = '$db_completed',
            last_update = '$db_completed',
            stderr_tail = '$db_stderr_tail',
            files_done  = '$files_done',
            data_done   = '$data_done',
            data_rate   = '$data_rate',
            retval = '$db_retval'
        WHERE task_id = '$db_task_id'
    ");

    #proc_terminate($proc);
    if (EXTRA_LOGGING) { error_Log("all done."); }

    # check for the next task
    $next = $db->querySingle("
        SELECT task_id, moddir, command
          FROM tasks
         WHERE pid IS NULL
           AND dismissed IS NULL
         ORDER BY task_id LIMIT 1
    ", true);
    $db_started = $db->escapeString(time());
    if ($next) {
        if (EXTRA_LOGGING) { error_Log("New task found, rsyncing $next[moddir]..."); }
        $db_task_id = $next['task_id'];
        $cmd = $next['command'];
        $moddir = $next['moddir'];
        $db->exec("
            UPDATE tasks
               SET started = '$db_started',
                   files_done = '0',
                   data_done = '0',
                   data_rate = ''
             WHERE task_id = '$db_task_id'
        ");
    } else {
        if (EXTRA_LOGGING) { error_Log("No more tasks, exiting..."); }
        # no more tasks -- yay
        break;
    }

}

# restart kiwix so it sees what modules are visible/hidden
# -- we could do this after each module but it seems a bit
# much... let's try doing it after installs/updates are complete
# and see if anyone complains
kiwix_restart();

if (EXTRA_LOGGING) { error_Log("Goodbye."); }
exit(0);
    

?>
