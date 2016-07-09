#!/bin/sh

set -e

if [ ! $1 ]
then
    echo Usage: `basename $0` '(hostname | ip)'
    exit 1;
fi

# files that are already highly compressed (zim, videos) will probably
# transfer faster without the -z option (which uses zip compression)
rsync -a  rsync://$1/rachelmods/fr-kalite /media/RACHEL/rachel/modules/
rsync -a  rsync://$1/rachelmods/fr-wikipedia /media/RACHEL/rachel/modules/
rsync -a  rsync://$1/rachelmods/fr-wikivoyage /media/RACHEL/rachel/modules/
rsync -a  rsync://$1/rachelmods/fr-wiktionary /media/RACHEL/rachel/modules/
rsync -az rsync://$1/rachelmods/fr-ebooksgratuits /media/RACHEL/rachel/modules/
rsync -az rsync://$1/rachelmods/fr-haitifutur /media/RACHEL/rachel/modules/
