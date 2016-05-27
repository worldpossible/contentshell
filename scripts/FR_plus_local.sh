sudo rm -r /media/RACHEL/rachel/modules/en-TED/TEDx*
sudo rm -r /media/RACHEL/rachel/modules/en-TED/TED@*
sudo rm -r /media/RACHEL/rachel/modules/en-TED/TEDSalo*
sudo rm -r /media/RACHEL/rachel/modules/en-TED/TEDIndi*

sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/fr-ebooksgratuits /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/fr-haitifutur /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/fr-phet-haiti /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/fr-wikipedia /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/fr-wikibooks /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/fr-wikipedia /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/fr-wikisource /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/fr-wikiversity /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/fr-wikivoyage /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/fr-wiktionary /media/RACHEL/rachel/modules/.
sudo mkdir /media/RACHEL/kacontent
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/fr-kalite/content/* /media/RACHEL/kacontent/.
