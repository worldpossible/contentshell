#!/bin/bash

# This script is always run after doing a contentshell update,
# which means you must consider the following:
#
#   1. The script should be re-runnable without causing trouble
#   2. The script should work on any version of RACHEL
#   3. The script should complete quickly and exit cleanly
#
# This takes some doing, but it's important
main() {

    setVariables
    checkVariables

}

# set up some globals that will be useful later
setVariables() {

    # these directories function as flags to identify the system
    plusWebDir=/media/RACHEL/rachel
    piWebDir=/var/www/rachel

    # check which system we're on and set variables accordingly
    if [[ -d $plusWebDir ]]; then
        # we're on a RACHEL-Plus
        isPlus=1
        webDir=$plusWebDir
    elif [[ -d $piWebDir ]]; then
        # we're on a RACHEL-Pi
        isPi=1
        webDir=$piWebDir
    else
        echo "Unknown System -- exiting"
        exit 1
    fi

    # vars that are the same on both go here
    scriptDir=/root/rachel-scripts

}

checkVariables() {

    if [[ $isPlus ]]; then
        echo "isPlus: " $isPlus;
    fi
    if [[ $isPi ]]; then
        echo "isPi: " $isPi;
    fi

    echo "webDir: " $webDir;
    echo "scriptDir: " $scriptDir;

}

# install our remote management software if needed
installESP() {
    if [[ $isRachelPlus ]]; then
        echo "Not Implemented... skipping";
    fi
}

main "$@"
