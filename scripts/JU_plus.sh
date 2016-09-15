#!/bin/sh

set -e

if [ ! $1 ]
then
    echo Usage: `basename $0` '(hostname | ip)'
    exit 1;
fi

# files that are already highly compressed (zim, videos) will probably
# transfer faster without the -z option (which uses zip compression)
rsync -av  --del rsync://$1/rachelmods/en-kalite /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-afristory /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-ck12 /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-file_share /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-GCF2015 /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-ebooks /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-infonet /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-math_expression /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-scratch /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-musictheory /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-olpc /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-practical_action /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-understanding_algebra /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-PhET /media/RACHEL/rachel/modules/
rsync -av  --del rsync://$1/rachelmods/en-radiolab /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-powertyping /media/RACHEL/rachel/modules/
rsync -av  --del rsync://$1/rachelmods/en-wikipedia_for_schools /media/RACHEL/rachel/modules/
rsync -av  --del rsync://$1/rachelmods/en-wikivoyage /media/RACHEL/rachel/modules/
rsync -av  --del rsync://$1/rachelmods/en-wiktionary /media/RACHEL/rachel/modules/
rsync -av  --del rsync://$1/rachelmods/en-worldmap /media/RACHEL/rachel/modules/
rsync -av  --del rsync://$1/rachelmods/en-wikipedia /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-oya /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-law_library /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-fantastic_phonics /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-scale-of-universe /media/RACHEL/rachel/modules/
rsync -av  --del rsync://$1/rachelmods/en-TED /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-fairshake /media/RACHEL/rachel/modules/
rsync -avz --del rsync://$1/rachelmods/en-saylor /media/RACHEL/rachel/modules/


