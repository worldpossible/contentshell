#!/bin/bash
# ---------------------------------------------------------------------------------------------
# Script: Rev_CntntShl.sh | Rev: 1.0
# Author: Steve Bashford  | Email: Steve@AnalyticsOcean.Net
# Action: Change the conetent shell rev in file rachel/admin/version.php
# ---------------------------------------------------------------------------------------------

# Set vars:
  REVX="$1" # New rev
  FILE="version.php" # Target file

# Test input rev:
  tir_f(){
    if   [ -z "${REVX}" ]; then
         echo -e "Enter new rev number: \c"
         read REVX
         tir_f
    else tnr_f
    fi
  }

# Invalid rev message:
  ivm_f(){
    echo -e "\n${MSG1}\n${MSG2}\n"
    REVX=""
    tir_f
  }

# Test new rev:
  tnr_f(){
    CHAR=$(echo "${REVX}" | awk -F"." '{print NF-1}')
    STRG=$(echo "${#REVX}")
    MSG1="Invalid new rev: ${REVX}"
    MSG2="Note: Format = X.X.X where X=single-digit number [0-9]"

    if   [[ "${REVX}" == *['!'@#\$%^\&*()_+a-z,A-Z]* ]]; then ivm_f
    elif [ "${CHAR}" -ne "2" ] || [ "${STRG}" -ne "5" ]; then ivm_f
    elif [ $(echo "${REVX}" | grep '[0-9][0-9]')      ]; then ivm_f
    elif [ $(echo "${REVX}" | grep '[.][.]')          ]; then ivm_f
    else gcr_f
    fi
  }

# Get current rev:
  gcr_f(){
    REVX="v${REVX}"
    REV1=$(grep '"cur_contentshell' ${FILE} | awk -F ">" '{print $2}' | awk -F "<" '{print $1}')
    echo -e "\n  Current rev: ${REV1}"
    echo      "  Updated rev: ${REVX}"
    echo -e "\n  Update content shell rev: ${REVX}? [enter]\n"
    read -n1
    sudo sed -i "s|${REV1}|${REVX}|" "${FILE}"
  }

# Start script:
  tir_f
