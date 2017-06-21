#!/usr/bin/php
<?php

# This script installs, then sorts & hides modules
# according to a given .modules file

$dir = dirname(__FILE__) . "/scripts/";

if (isset($argv[1]) && isset($argv[2])) {

    $file = "$dir/$argv[1].modules";

    if (is_readable($file)) {

        # figure out where we're getting stuff from
        switch ($argv[2]) {
            case "dev":
                $server = "dev.worldpossible.org";
                break;
            case "jeremy":
                $server = "192.168.1.74";
                break;
            case "jfield":
                $server = "192.168.1.6";
                break;
            default:
                $server = $argv[2];
        }

        require_once("admin/common.php");
        installmods($file, $server);
        exit(0);

    }

}

echo "Usage: php installmods.php modulesfile server\n";
exit(1);

?>
