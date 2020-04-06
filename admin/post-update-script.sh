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

# XXX currently this script might restart the webserver,
# which will kill any currently installing modules
# it doesn't seem to be related to signals (tried trapping
# them) but rather the pipes in do_tasks.php that read
# rsync getting closed (or perhaps rsync getting killed?)
# so you'd have to start there in fixing that, or perhaps
# just disallow selfUpdate while items are installing
# (probably should queue the selfUpdate like a module?)

main() {

    setVariables
    #checkVariables
    installESP
    installAWStats
    installRsyncdConf

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
        elif [[ `uname -n | grep CMAL` ]]; then
            isPlusVer3=1
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
    echo "isPlusVer3: " $isPlusVer3
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
        sed -i '/Updating the Kiwix library/ i # Start esp - inserted by post-update-script.sh' $startScript
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
        cp $adminDir/externals/esp-checker.php $scriptDir/esp-checker.php
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

installAWStats() {
    # we only install on the plus
    awstatsDir=/media/RACHEL/awstats
    if [[ $isPlus ]]; then

        # copy over the latest version, but leave data intact
        rsync -a --exclude '/data/' $adminDir/externals/awstats/ $awstatsDir/

        # create data directory (should be harmless if already there)
        mkdir -p $awstatsDir/data

        # symlink the conf file (should be harmless if already there)
        ln -sf $awstatsDir/awstats.conf /etc/

        # the v3 uses nginx instead of lighttpd
        if [[ $isPlusVer3 ]]; then
            sed -i '/LogFile/ s/httpd\/access_log/nginx\/access.log/' $awstatsDir/awstats.conf
        fi

        # everything is already taken care of on the v3 plus, but on the v1/v2:
        if [[ $isPlus1 || $isPlus2 ]];  then

            # next we update lighttpd configuration to run awstats on
            # its own port, and to use a fixed hostname in the log
            # -- be careful not to do it twice (or more!)
            lighttpdConf=/usr/local/etc/lighttpd.conf
            if [[ -z `grep 'start awstats changes' $lighttpdConf` ]]; then
                # append the conf addtions
                cat $awstatsDir/lighttpd.awstats.conf >> $lighttpdConf
                # restart webserver - note that while it may be smarter
                # to do this once at the end, we actually need this to happen
                # before we do the log processing below, so do it now
                restartWebserver
                # after changing the server log config we have to filter the
                # existing log for server hostname/ip -- this can take a few
                # minutes so we background it -- we also need to clear out
                # any collated awstats data after we're done fixing the
                # log because that data will be based on the unfixed log
                perl -pi -e 's/ \S+ / RACHEL /' /var/log/httpd/access_log && rm -rf $awstatsDir/data/* &
            fi
        fi
        # that's the end of the plus 1/2 code

        # next we need to add a line to crontab to collate the log data
        crontabFile=/etc/crontab
        if [[ -z `grep 'awstats upkeep' $crontabFile` ]]; then
            # DON'T FORGET THE NEWLINE AT THE END
            printf "%s\n%s%s\n" "# awstats upkeep" \
            "*/10 * * * * root $awstatsDir/tools/awstats_updateall.pl now " \
            "-awstatsprog=$awstatsDir/wwwroot/cgi-bin/awstats.pl > /dev/null" >> $crontabFile
        else
            # earlier scripts botched this entry by failing at include a trailing newline
            # so we need to detect and fix that here:
            if [[ -z `perl -ne 'print "eol\n" if m[awstats.pl > /dev/null\n]' $crontabFile` ]]; then
                sed -i '/awstats.pl > \/dev\/null/ s/null/null\n/' $crontabFile
            fi
        fi

    fi
}

# restarts the webserver if the flag to do so has been set
restartWebserver() {
    if [[ $isPlus1 || $isPlus2 ]]; then
        killall -INT lighttpd && /usr/bin/lighttpd -f /usr/local/etc/lighttpd.conf
    fi
}

# doesn't run rsync, but puts a conf file in place so it
# can be turned on if wanted
installRsyncdConf() {
    if [[ $isPlus ]]; then
        if [[ -f /etc/rsyncd.conf ]]; then
            mv /etc/rsyncd.conf /etc/rsyncd.conf.prev
        fi
        cp -r $adminDir/externals/rsyncd.conf /etc/
    fi
}

main "$@"
