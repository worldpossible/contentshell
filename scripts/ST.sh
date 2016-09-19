#!/bin/sh

set -e # stop on error

if [ ! $1 ]
then
    echo Usage: `basename $0` '(hostname | ip)'
    exit 1;
fi

# files that are already highly compressed (zim, videos) will probably
# transfer faster without the -z option (which uses zip compression)
set -x # echo commands

echo 'this should be run after running the base script'
echo 'removing unused modules'
sudo rm -r /media/RACHEL/rachel/modules/en-fairshake
sudo rm -r /media/RACHEL/rachel/modules/en-scratch
sudo rm -r /media/RACHEL/rachel/modules/en-law_library
sudo rm -r /media/RACHEL/rachel/modules/es-kalite
sudo rm -r /media/RACHEL/rachel/modules/es-wikipedia
sudo rm -r /media/RACHEL/rachel/modules/es-windows_apps

echo 'adding custom modules for ST'
rsync -avz --del rsync://$1/rachelmods/fr-kaos /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/fr-phet-haiti /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/fr-wikibooks /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-wikibooks /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/fr-wikiversity /media/RACHEL/rachel/modules/

echo 'bring in admin.sqlite'
rsync -avz --del rsync://192.168.1.74/rachelmods/admin.sqlite/st.admin.sqlite /media/RACHEL/rachel/admin.sqlite

echo 'df -h'
