#!/bin/sh

set -e

if [ ! $1 ]
then
    echo Usage: `basename $0` '(hostname | ip)'
    exit 1;
fi

# files that are already highly compressed (zim, videos) will probably
# transfer faster without the -z option (which uses zip compression)
rsync -av  --del rsync://$1/rachelmods/es-kalite /media/RACHEL/rachel/modules/
rsync -av  --del rsync://$1/rachelmods/es-wikipedia /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-wikihow /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-ap_didact /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-biblioteca /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-blockly_games /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-cnbguatemala /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-ebooks /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-educalab /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-file_share /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-guatemala /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-guias /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-hesperian_health /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-medline_plus /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-soluciones /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-vedoque /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/es-windows_apps /media/RACHEL/rachel/modules/
