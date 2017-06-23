#!/bin/bash

#-------------------------------------------
# This script is always run after doing a contentshell update,
# which means you must consider the following:
#
#   1. The script should be re-runnable without causing trouble
#   2. The script should work on any version of RACHEL
#   3. The script should complete quickly and exit cleanly
#
# This takes some doing, but it's important. Modify this
# script carefully and always test, test, test the results
# on a few platforms.
#-------------------------------------------

main() {

    setVariables
    #checkVariables
    installESP

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
        if [[ `uname -n` = "WRTD-303N-Server" ]]; then
            isPlusVer1=1
        elif [[ `uname -n` = "WAPD-235N-Server" ]]; then
            isPlusVer2=1
        fi
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
    adminDir=$webDir/admin
    modDir=$webDir/modules

}

checkVariables() {

    echo "      isPi: " $isPi;
    echo "    isPlus: " $isPlus;
    echo "isPlusVer1: " $isPlusVer1
    echo "isPlusVer2: " $isPlusVer2
    echo "    webDir: " $webDir;
    echo "  adminDir: " $adminDir;
    echo "    modDir: " $modDir;
    echo " scriptDir: " $scriptDir;

}

# install our remote management software if needed
installESP() {
    startScript=$scriptDir/rachelStartup.sh

    # the RACHEL-Plus v1 didn't have esp at all, and some devices
    # had Weaved instead. So if we don't see esp yet, clear out
    # Weaved and install esp
    if [[ $isPlusVer1 && -z `grep esp-checker.php $startScript` ]]; then
        uninstallWeaved
        # remove weaved from startup
        sed -i '/Weaved/ s/^#*/#/' $startScript
        # add esp to startup, right before Kiwix
        sed -i '/Updating the Kiwix library/ i # Start esp - inserted by post-update.sh' $startScript
        sed -i '/Updating the Kiwix library/ i echo $(date) - Start esp check process' $startScript
        sed -i '/Updating the Kiwix library/ i php /root/rachel-scripts/esp-checker.php &' $startScript

    # for the v2 Plus, we've been installing esp since the beginning,
    # but we want to change the name to something more clear
    elif [[ $isPlusVer2 && -z `grep esp-checker.php $startScript` ]]; then
        # for clarity and to make future updates to rachelStartup.sh easier
        # we give it a slightly more descriptive name
        sed -i 's/\/checker.php/\/esp-checker.php/' $startScript
        # could rm, but this is slightly safer if the cp later fails
        mv $scriptDir/checker.php $scriptDir/esp-checker.php
        # we only want to kill on this broader name if we're sure we
        # haven't updated to the new name yet
        pkill -f checker.php
    fi

    # on any version of the Plus, we update the script and restart it
    if [[ $isPlus ]]; then
        # install contentshell's esp over previous versions
        cp $adminDir/esp-checker.php $scriptDir/esp-checker.php
        chmod 744 $scriptDir/esp-checker.php
        # restart esp
        pkill -f esp-checker.php
        php $scriptDir/esp-checker.php > /dev/null 2>&1 &
    fi

}

# remove weaved services - code taken from cap-rachel-configure.sh by Sam Kinch
uninstallWeaved() {

    # Stop all Weaved services
    for i in `ls /usr/bin/Weaved*.sh`; do
        $i stop
    done

    # Remove Weaved files
    rm -rf /usr/bin/weaved*
    rm -rf /usr/bin/Weaved*
    rm -rf /etc/weaved

    # Remove Weaved from crontab
    crontab -l | grep -v weaved | cat > /tmp/.crontmp
    crontab /tmp/.crontmp

}

main "$@"
