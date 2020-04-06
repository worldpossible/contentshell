<?php

# FILE TAKEN FROM:
# https://github.com/rachelproject/esp/blob/master/client/checker.php
# release: v1.0.1 (b416c32)

# checker.php
# 
# This script checks in with the dev server, then polls it
# to see if we want it to connect. If it gets a request to
# connect, it creates ssh tunnels for our desired ports, and
# then manages those processes.
#

define("DEBUG", false);

# some useful things to know
$scriptpath =  dirname(__FILE__);
$scriptname = basename(__FILE__);
$scriptpid  = getmypid();

# check we're the only one running or there will be blood
$existing_pid = exec("ps x | grep $scriptname | grep -v $scriptpid | grep -v grep | awk '{ print $1 }'");
if ($existing_pid) {
    echo "$scriptname already running (pid: $existing_pid)... exiting.\n";
    exit(1);
}

# this is the unique device ID. We use the last six of the eth0 mac address
$id = exec("ifconfig | grep eth0 | awk '{ print $5 }' | sed s/://g | grep -o '.\{6\}$'");
if (!$id) {
    # CAP3 uses a different ethernet identifier
    $id = exec("ifconfig | grep enp2s0 | awk '{ print $5 }' | sed s/://g | grep -o '.\{6\}$'");
}

if (DEBUG) { echo  "Device ID: $id\n"; }

# these are the ports we want to tunnel
$ports = array(
    "80",   # <- http
    "81",   # <- kiwix
    "8008", # <- kalite
    "22"    # <- ssh
);

$host = "dev.worldpossible.org";
$name = "esp";
$url  = "http://$host/$name/server.php";
$findflag = "$name-findflag"; # for finding our procs

# we use these as sensible limits for when the
# server tells us to use a different interval
$std_interval =  20; # default polling rate, in seconds
$min_interval =   5; # fast rate (while connected)
$max_interval = 600; # slow rate (when offline)
$interval = $std_interval;

# check if there is already an ssh tunnel
#if (DEBUG) { echo  "Startup check...\n"; }
#$pids = getpids();
#if ($pids) {
#    if (DEBUG) { echo "Existing processes -- mark as conntected\n"; }
#    $response = @file_get_contents( "$url?id=$id&connected=1" );
#} else {
#    if (DEBUG) { echo "No existing processes -- mark as disconntected\n"; }
#    $response = @file_get_contents( "$url?id=$id&disconnected=1" );
#}
# 
# whoops -- restarting with dynamic ports doesn't work... so:
killpids();
@file_get_contents( "$url?id=$id&disconnected=1" );

# enter our infinite loop
$network_fail = false;
while (true) {

    $response = @file_get_contents( "$url?id=$id" );

    if ($response === false) {
        $network_fail = true;
        if (DEBUG) { echo "Can't contact server -- waiting $interval seconds.\n"; }
        # This probably means we're not online - a common case, really.
        sleep($interval);
        # We back off our polling rate up to $max_interval. The reason we don't
        # just jump straight to the max is because during startup network can
        # take some time to come up, and so we want to keep trying quickly
        # at first rather that go straight to a 10 minute timeout... but we'll get there
        if ($interval < $max_interval) {
            $interval += $std_interval;
        }
        continue;
    } else if ($network_fail == true) {
        # when we recover from a network fail, we go back to our
        # standard polling rate -- even if the server had given
        # us some other rate at some point
        $network_fail = false;
        $interval = $std_interval;
    }

    if (DEBUG) { echo "Server says: $response\n"; }

    if ($response == "OK") {

        # The most common response is just "OK" (with no number), which
        # requires no action at all. Everything else is handled below:

    } else if (preg_match("/OK (\d+)/", $response, $match)) {

        # This means the server is requesting you change
        # the polling frequency -- this allows us to tell
        # devices to back off under load

        $interval = $match[1];
        # let's trust the server...
        #if ($interval < $min_interval) {
        #    $interval = $min_interval;
        #} else if ($interval > $max_interval) {
        #    $interval = $max_interval;
        #}
        # well, almost trust it...
        if ($interval < 2) {
            $interval = 2;
        }
        if (DEBUG) { echo "Polling interval set to: $interval\n"; }

    } else if (preg_match("/CONNECT (\d+)/", $response, $match)) {

        # This requests a connection -- we use the number provided
        # as the starting point for the new port numbers
        $offset = $match[1];

        # clear out anything that's there
        killpids();

        # connect each port, using sequential numbers starting at the offset
        $i = 0;
        foreach ($ports as $rport) {
            # a bit complex - we need to
            # 1) specify the private key for login
            # 2) add a harmless string that will let us find the ssh process
            # 3) skip host checking
            # 4) configure some things so that failed connections get closed
            # 5) run the actual revesre tunnel on the right port
            # 6) send all output to the dustbin so PHP can return
            $lport = $offset + $i++;
            exec(
                "ssh -i $scriptpath/$name.sshkey -S $findflag " .
                "-o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no " .
                "-o ExitOnForwardFailure=yes -o ServerAliveInterval=20 -o ServerAliveCountMax=3 " .
                "-fN -R $lport:localhost:$rport $name@$host > /dev/null 2>&1 &",
                $output, $retval
            );
            if ($retval > 0) {
                $output = implode("\n", $output);
                if (DEBUG) { echo "Error: ($retval) - $output\n"; }
                $response = file_get_contents( "$url?id=$id&error=1" );
                killpids();
                continue 2;
            }
        }

        if (DEBUG) { echo "Waiting for tunnels to fork\n"; }
        $success = false;
        $retries = 0;
        $max_retries = 10;
        while (true) {
            $pids = getpids();
            if (count($pids) == count($ports)) {
                $success = true;
                break;
            }
            if (++$retries > $max_retries) {
                break;
            }
            sleep(1);
        }

        if ($success) {
            if (DEBUG) { echo "Tunneling success\n"; }
            $response = file_get_contents( "$url?id=$id&connected=1" );
        } else {
            if (DEBUG) { echo "Tunneling failed\n"; }
            killpids();
            $response = file_get_contents( "$url?id=$id&error=1" );
        }

        # once we're connected, we poll quickly so we can
        # pick up disconnect messages quickly
        $interval = $min_interval;

    } else if ($response == "DISCONNECT") {

        killpids();
        $response = file_get_contents( "$url?id=$id&disconnected=1" );

    # after disconnecting, we go back to the standard polling rate
        $interval = $std_interval;
        
    }

# XXX We don't bother checking the pids for now -- until we get around
# to writing the code to do something useful with lost connections.
# Instead we let ssh keepalives and timeouts clean things up and it
# may require us to issue a disconnect/reconnect in the admin interface.
#    if ($pids) {
#        echo "CHECKING pids:\n";
#        $newpids = array();
#        foreach ($pids as $pid) {
#            echo "\t$pid...";
#            if (!$pid || ! file_exists("/proc/$pid")) {
#                $response = file_get_contents( "$url?id=$id&error=1" );
#                echo "GONE.\n";
#            } else {
#                echo "OK.\n";
#                array_push($newpids, $pid);
#            }
#        }
#        # we refill it with only the pids that still existed
#        $pids = $newpids;
#    }

    # and now we chill
    sleep($interval);

}

function getpids() {

    global $findflag;

    # the ' ? ' detects the forked/detached tunnel, as opposed to the
    # short-lived process that forks it off (they otherwise look the same)
    exec("ps x | grep $findflag | grep ' ? ' | grep -v grep", $output, $retval);

    $pids = array();
    if ($output) {
        foreach ($output as $proc) {
            $pid = preg_replace("/^ *(\d+).*/", "$1", $proc);
            array_push($pids, $pid);
        }
    }

    return $pids;

}

function killpids() {
    $pids = getpids();
    if ($pids) {
        foreach ($pids as $pid) {
            if (DEBUG) { echo "Killing $pid...\n"; }
            exec("kill $pid");
            # check result?
        }
    }
}

?>
