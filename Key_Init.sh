#!/bin/bash
# --------------------------------------------------------------------------------------
# Script: Key_Init.sh | Rev: 1.0
# Author: Steve Bashford | Email: Steve@AnalyticsOcean.Net
# Action: Adds ssh key to the current bash terminal (not a perminent solution)
# --------------------------------------------------------------------------------------

# Add key:
  eval `ssh-agent -s`
  ssh-add
  sudo ssh-agent bash
  ssh-add /home/wp/.ssh/id_git
