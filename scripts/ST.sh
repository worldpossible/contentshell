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

rsync -avz --del rsync://$1/rachelmods/fr-kaos /media/RACHEL/rachel/modules/
