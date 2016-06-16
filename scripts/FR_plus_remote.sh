# files that are already highly compressed (zim, videos) will probably
# transfer faster without the -z option (which uses zip compression)
rsync -Pav  rsync://dev.worldpossible.org/rachelmods/fr-kalite /media/RACHEL/rachel/modules/
rsync -Pav  rsync://dev.worldpossible.org/rachelmods/fr-wikipedia /media/RACHEL/rachel/modules/
rsync -Pav  rsync://dev.worldpossible.org/rachelmods/fr-wikivoyage /media/RACHEL/rachel/modules/
rsync -Pav  rsync://dev.worldpossible.org/rachelmods/fr-wiktionary /media/RACHEL/rachel/modules/
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/fr-ebooksgratuits /media/RACHEL/rachel/modules/
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/fr-haitifutur /media/RACHEL/rachel/modules/
