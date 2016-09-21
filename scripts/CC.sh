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

echo 'adding custom modules for CC'
rsync -avz --del rsync://$1/rachelmods/en-ccbooks-custom /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-fantastic_phonics /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-TED-custom /media/RACHEL/rachel/modules/

echo 'bring in admin.sqlite'
rsync -avz --del rsync://$1/rachelmods/extra-build-files/EN-PLUS.admin.sqlite /media/RACHEL/rachel/admin.sqlite

echo 'df -h'
df -h
cd $HOME
