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
rsync -av  --del rsync://$1/rachelmods/fr-kalite /media/RACHEL/rachel/modules/
rsync -av  --del rsync://$1/rachelmods/fr-wikipedia /media/RACHEL/rachel/modules/
rsync -av  --del rsync://$1/rachelmods/fr-wikivoyage /media/RACHEL/rachel/modules/
rsync -av  --del rsync://$1/rachelmods/fr-wiktionary /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/fr-ebooksgratuits /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/fr-haitifutur /media/RACHEL/rachel/modules/
