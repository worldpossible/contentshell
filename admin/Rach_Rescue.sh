#!/bin/bash
# --------------------------------------------------------------------------------------
# Script: Rach_Rescue.sh | Rev: 1.13 (200422)
# Author: Steve Bashford | Email: Steve@AnalyticsOcean.Net
# Action: Executes Rachel Plus utilities upldaded via admin->setting form
# --------------------------------------------------------------------------------------

UPLU="Upd_Rescue" # default script name (.sh & .zip)
LOGX="log.php"    # User log file (on rachel Plus)
FAIL="fail.log"   # failre log populated via rescue upd script
UNZP="unzip.exe"  # Unzip executable (pswd protected)
WDIR="/.data/RACHEL/rachel/admin" # Working dir path

# Generate log file:
  ulf_f(){
    STRG='"<a href=\"settings.php\">Go to settings tab</a>";'
    echo -e '<?php'          > "${LOGX}"
    echo 'echo "<br>";'     >> "${LOGX}"
    echo "echo ${MSG}"      >> "${LOGX}"
    echo 'echo "<br><br>";' >> "${LOGX}"
    echo "echo ${STRG}"     >> "${LOGX}"
    echo "?>"               >> "${LOGX}"
  }

# Install zip utility:
  if   [ -z "$(command -v zip)" ]; then
       apt-get install zip
  fi

# Delete existing script (security):
  if   [ -e "${UPLU}.sh" ]; then
       rm "${UPLU}".sh
  fi

# Delete existing fail.log (if found):
  if   [ -e "${WDIR}/${FAIL}" ]; then
       rm "${FAIL}"
  fi

# Unzip update utility:
  if   [ ! -e "${WDIR}/${UPLU}.zip" ]; then
       MSG='"Update file not found";'
       ulf_f
       exit 0;
  else MSG='"Extracting Update file";'
       ulf_f
       "${WDIR}"/"${UNZP}"
       rm "${UPLU}".zip
  fi

# Run update utiliy:
  if   [ ! -e "${UPLU}.sh" ]; then
       MSG='"Utility not found";'
       ulf_f
       exit 0;
  else MSG="\"Update in progress: $(date)\";"
       ulf_f
       ./"${UPLU}".sh
  fi

# Test for error (update script flags error):
  if   [ -e "${WDIR}/${FAIL}" ]; then
       MSG='"Update completed: Error";'
       ulf_f
       exit 0;
  else MSG='"Update completed: Pass";'
       ulf_f
  fi

exit 0;
