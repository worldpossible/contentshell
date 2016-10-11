<?php

    # script to sort/hide modules from the command line
    # -- just calls the function, but you can run this
    # from a shell script

    if (isset($argv[1]) && is_readable($argv[1])) {

        require_once("admin/common.php");
        sortmods($argv[1]);

    } else {

        echo "Usage: php sortmods.php scripts/file.modules\n";
        exit(1);

    }

?>
