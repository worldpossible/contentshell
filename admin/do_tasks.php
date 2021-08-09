<?php

#-------------------------------------------
# This script reads tasks from a queue in the
# database and runs them. As they run, it updates
# the # database with their output.
# As a special case with rsync, it parses some
# of the output to record progress.
#
# Usage: do_tasks.php
#-------------------------------------------
require_once("common.php");

# because this runs as a seperate process, logging is a bit of a pain
# -- syslog doesn't seem to work so we log to our own file - only used
# for debugging, really
define("VERBOSE", false);
function mylog ($msg) {
    if (VERBOSE) {
        file_put_contents("/tmp/do_tasks.log", $msg . "\n", FILE_APPEND);
    }
}

$absmodpath = getAbsModPath(); # needed to run finish_install.sh

mylog("starting tasks");

#-------------------------------------------
# input checking - there should be no input
#-------------------------------------------
if (!empty($argv[1])) {
    mylog("arguments ignored... exiting.");
    exit(1);
}

#-------------------------------------------
# pid checking - if there's already a copy running, we bail
#-------------------------------------------
$pidfile = "/var/run/rachel_do_tasks.pid";
if (file_exists($pidfile)) {
    # we verify that the pid really is running
    $pid = preg_replace("/\D+/", "", file_get_contents($pidfile));
    $output = exec("ps $pid | sed 1d");
    if (!empty($output)) {
        mylog("already running... exiting.");
        exit(0);
    } else {
        # something went wrong, let's try again
        mylog("pidfile found but no process... restarting.");
        unlink($pidfile);
    }
}

# we're clear - let's run
file_put_contents($pidfile, getmypid());

# make sure we clear our pid file when we finish
function do_tasks_cleanup() {
    global $pidfile;
    unlink($pidfile);
}
register_shutdown_function('do_tasks_cleanup');

# grab a database connection
$db = getdb();

#-------------------------------------------
# we run tasks from the queue until they're all done
#-------------------------------------------
$last_task_id = 0;
while (true) {

    # check for a task
    $next = $db->querySingle("
        SELECT task_id, moddir, command
          FROM tasks
         WHERE pid IS NULL
           AND dismissed IS NULL
         ORDER BY task_id LIMIT 1
    ", true);

    # if we're done, we're done
    if (!$next) {
        mylog("no more tasks; exiting.");
        break;
    }

    mylog("running task $next[task_id] ($next[moddir])");

    # detect looping on the same process
    if ($next['task_id'] == $last_task_id) {
        mylog("apparently stuck on task_id $last_task_id; exiting.");
        exit(1);
    }
    $last_task_id = $next['task_id'];

    # gather our stuff
    $db_task_id = $next['task_id'];
    $cmd        = $next['command'];
    $moddir     = $next['moddir'];

    # we throttle to 25MB/s because the CAP3 will otherwise
    # start locking up
    $cmd = preg_replace("/^rsync /", "rsync --bwlimit=125000 ", $cmd);

    #-------------------------------------------
    # here we actually fire off the process and see what happens 
    #-------------------------------------------
    mylog("opening process: $cmd");
    $db_started = $db->escapeString(time());

    $pipedefs = array(
       0 => array("pipe", "r"), # stdin
       1 => array("pipe", "w"), # stdout
       2 => array("pipe", "w")  # stderr
    );
    $proc = proc_open($cmd, $pipedefs, $pipes);
    $info = proc_get_status($proc);

    $db_pid         = $db->escapeString($info['pid']);
    $db_started     = $db->escapeString(time());
    $db_last_update = $db_started;
    $db->exec("
        UPDATE tasks SET
            pid         = '$db_pid',
            started     = '$db_started',
            last_update = '$db_last_update',
            files_done  = '0',
            data_done   = '0',
            data_rate   = ''
        WHERE task_id = '$db_task_id'
    ");

    # if it failed, record and move on
    if (!$info['running']) {
        mylog("process completed instantly - task_id $db_task_id");
        $db_stdout_tail = $db->escapeString(fread($pipes[1], 1024));
        $db_stderr_tail = $db->escapeString(fread($pipes[2], 1024));
        $db_retval      = $db->escapeString($info['exitcode']);
        $db_completed   = $db_started;
        $db->exec("
            UPDATE tasks SET
                completed   = '$db_completed',
                last_update = '$db_last_update',
                stdout_tail = '$db_stdout_tail',
                stderr_tail = '$db_stderr_tail',
                retval      = '$db_retval'
            WHERE task_id = '$db_task_id'
        ");
        continue;
    }

    mylog("process opened - task_id $db_task_id - now reading");

    #-------------------------------------------
    # We keep reading from the pipe, one char at a time
    # and store up lines, writing $tail_length at a time to
    # the DB. We also limit ourselves to one write
    # per $frequency seconds.
    #-------------------------------------------
    $line = "";        # - the current line, built one char at a time
    $lines = array();  # - array of completed lines (limited to $tail_length)
    $tail_length = 2;  # - only keep this many lines of output in $lines
    $frequency = 3;    # - how often can we write to the db (in seconds)?
    $cr = false;       # - flag: did the previous line have a carriage return?
    $lastone = false;  # - flag: is this the last line of output?
    $files_done = 0;   # - how many files have been completed? (from rsync)
    $data_done = 0;    # - how much data in completed files? (from rsync)
    $data_prog = 0;    # - how far along is the current file? (from rsync)
    $data_rate = 0;    # - how fast is the transfer going? (from rsync)

    # start reading
    while (true) {

        $char = fgetc($pipes[1]);

        # we don't do this EOF test at the top of the loop
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
                # file progress. If so, we count that and the data
                # transferred. Note: some versions of rsync include
                # commas in the numbers so we have to strip those out
                if (!preg_match("/0\s+0%\s+0\.00.B\/s\s+0:00:00/", $line) # file starting -- ignore
                        && preg_match("/^\s+([\d,]+)\s+(\d+)%\s+(\S+)/", $line, $matches)) { # file progress -- read
                    if ($matches[2] == "100") {
                        # completed file, add it to the total
                        $data_done += preg_replace("/,/", "", $matches[1]);
                        ++$files_done;
                    } else {
                        # partial file in progress, keep track seperately
                        $data_prog = preg_replace("/,/", "", $matches[1]);
                    }
                    $data_rate = $matches[3];
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
                $cur_data_done = $data_done + $data_prog;
                $db->exec("
                    UPDATE tasks SET
                        stdout_tail = '$db_stdout_tail',
                        last_update = '$db_last_update',
                        files_done  = '$files_done',
                        data_done   = '$cur_data_done',
                        data_rate   = '$data_rate'
                    WHERE task_id = '$db_task_id'
                ");
                mylog("updating db with: '$db_stdout_tail'");

            }

            if ($lastone) { break; }

        } else {
            # just capture this character
            $line .= $char;
        }

    }

    #mylog("in lines array: " . implode("\n", $lines));
    #mylog("in pipe: " . fread($pipes[2], 1024));

    # grab whatever is left in stderr (up to 1 kb)
    $db_stderr_tail = $db->escapeString(fread($pipes[2], 1024));

    #-------------------------------------------
    # we're done reading, so now we write the final results
    # to the db, including the exitcode and any stderr output
    #-------------------------------------------
    # mylog("finished reading, calling fclose(0)");
    fclose($pipes[0]);
    # mylog("calling fclose(1)");
    fclose($pipes[1]);
    # mylog("calling fclose(2)");
    fclose($pipes[2]);
    mylog("pipes closed, calling proc_close()");

    # close the process
    $db_retval = $db->escapeString(proc_close($proc));

    # run any installation script, if present
    # XXX this needs better error checking and feedback
    $path = "$absmodpath/$moddir";
    $finscript = "finish_install.sh";
    mylog("checking for $path/$finscript");
    if (file_exists("$path/$finscript")) {
        mylog("running $path/$finscript");
        $db->exec("
            UPDATE tasks SET
                stdout_tail = 'Running: $path/$finscript',
                last_update = '$db_last_update'
            WHERE task_id = '$db_task_id'
        ");
        $output = array();
        exec("cd $path && bash $finscript 2>&1", $output, $rval);
        # put some record of our problem in the db
        if ($rval == 0) {
            $db->exec("
                UPDATE tasks SET
                    stdout_tail = 'Finished: $path/$finscript',
                    last_update = '$db_last_update'
                WHERE task_id = '$db_task_id'
            ");
        } else {
            $db->exec("
                UPDATE tasks SET
                    stdout_tail = 'Failed: $path/$finscript',
                    last_update = '$db_last_update'
                WHERE task_id = '$db_task_id'
            ");
            $db_stderr_tail .= implode("\n", $output);
            $db_retval = $rval;
        }
    }

    mylog("updating db - task $db_task_id complete");
    $db_completed = $db->escapeString(time());
    $db->exec("
        UPDATE tasks SET
            completed = '$db_completed',
            last_update = '$db_completed',
            stderr_tail = '$db_stderr_tail',
            retval = '$db_retval'
        WHERE task_id = '$db_task_id'
    ");

}

# restart kiwix so it sees what modules are visible/hidden
# -- we could do this after each module but it seems a bit
# much... let's try doing it after installs/updates are complete
# and see if anyone complains. XXX the right way is to put this
# in the the module's finish_install.sh
mylog("restarting kiwix...");
kiwix_restart();

mylog("goodbye.");
exit(0);
    
?>
