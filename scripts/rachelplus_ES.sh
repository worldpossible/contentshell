rsync -Pavz rsync://dev.worldpossible.org/rachelmods/biblioteca-es /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/guatemala-es /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/guias_es /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/vedoque-es /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/medline_plus-es /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/hesperian_health-es /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/wikipedia-es /media/RACHEL/rachel/modules/.
/var/kiwix/bin/kiwix-manage /media/RACHEL/kiwix/data/library/library.xml  add /media/RACHEL/rachel/modules/wikipedia-es/data/content/wikipedia_es_all_2016-02.zim  --indexPath=/media/RACHEL/rachel/modules/wikipedia-es/data/index/wikipedia_es_all_2016-02.zim.idx
sudo cp /media/RACHEL/rachel/modules/wikipedia-es/rachel-index.php /media/RACHEL/rachel/modules/wikipedia-es/index.htmlf

rsync -Pavz rsync://dev.worldpossible.org/rachelmods/ebooks-es /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/windows_apps-es /media/RACHEL/rachel/modules/.

rsync -Pavz rsync://dev.worldpossible.org/rachelmods/soluciones_es /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/wikihow-es /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/ap_didact_es /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/cnbguatemala-es /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/file_share-es /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/kalite-es /media/RACHEL/rachel/modules/.
#enable spanish as language in kalite
cp -r /usr/share/kalite/data/khan/topics.json /usr/share/kalite/data/khan/topics.json.bak 
sudo rm -r /usr/share/kalite/data/khan/topics.json
cp -r /media/RACHEL/rachel/modules/kalite-es/topics.json /usr/share/kalite/data/khan/
cp -r /root/.kalite/locale/dubbed_video_mappings.json /root/.kalite/locale/dubbed_video_mappings.json.bak
cp -r /media/RACHEL/rachel/modules/kalite-es/dubbed_video_mappings.json /root/.kalite/locale/   
ln -s /media/RACHEL/rachel/modules/kalite-es/content/* /media/RACHEL/kacontent/.

rsync -Pavz rsync://dev.worldpossible.org/rachelmods/educalab-es /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/blockly_games-es /media/RACHEL/rachel/modules/.
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/wikivoyage-es /media/RACHEL/rachel/modules/.
/var/kiwix/bin/kiwix-manage /media/RACHEL/kiwix/data/library/library.xml  add /media/RACHEL/rachel/modules/wikivoyage-es/data/content/wikivoyage_es_all_2016-04.zim  --indexPath=/media/RACHEL/rachel/modules/wikivoyage-es/data/index/wikivoyage_es_all_2016-04.zim.idx
sudo cp /media/RACHEL/rachel/modules/wikivoyage-es/rachel-index.php /media/RACHEL/rachel/modules/wikivoyage-es/index.htmlf
rsync -Pavz rsync://dev.worldpossible.org/rachelmods/extra-build-files/admin.es.cap.sqlite /media/RACHEL/rachel/admin.sqlite

sudo rm -r /media/RACHEL/rachel/modules/educalab_es
sudo rm -r /media/RACHEL/rachel/modules/fr_*
##wikivoyage and wikipedia must be added to kiwix library
