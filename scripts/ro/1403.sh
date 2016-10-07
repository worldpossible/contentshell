# bin bash
# USB v9a 20160926

sudo rm -r /media/RACHEL/rachel/modules/en-wikipedia
sudo rm -r /media/RACHEL/rachel/modules/en-hesperian_health
sudo rm -r /media/RACHEL/rachel/modules/en-medline_plus
sudo rm -r /media/RACHEL/rachel/modules/en-windows_apps
sudo rm -r /media/RACHEL/rachel/modules/es-wikipedia
sudo rm -r /media/RACHEL/rachel/modules/fr-wikipedia
sudo rm -r /media/RACHEL/rachel/modules/es-hesperian_health
sudo rm -r /media/RACHEL/rachel/modules/en-medline_plus

rsync -Pavz rsync://dev.worldpossible.org/rachelmods/en-saylor /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/en-fairshake /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/en-oya /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/en-law_library /media/RACHEL/rachel/modules/.

#install weaved
echo 'install weaved'

echo 'put the modules files somewhere'
