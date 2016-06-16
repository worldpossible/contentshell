# files that are already highly compressed (zim, videos) will probably
# transfer faster without the -z option (which uses zip compression)
rsync -Pav  rsync://192.168.1.74/rachelmods/modules/fr-kalite /media/RACHEL/rachel/modules/
rsync -Pav  rsync://192.168.1.74/rachelmods/modules/fr-wikipedia /media/RACHEL/rachel/modules/
rsync -Pav  rsync://192.168.1.74/rachelmods/modules/fr-wikivoyage /media/RACHEL/rachel/modules/
rsync -Pav  rsync://192.168.1.74/rachelmods/modules/fr-wiktionary /media/RACHEL/rachel/modules/
rsync -Pavz rsync://192.168.1.74/rachelmods/modules/fr-ebooksgratuits /media/RACHEL/rachel/modules/
rsync -Pavz rsync://192.168.1.74/rachelmods/modules/fr-haitifutur /media/RACHEL/rachel/modules/
