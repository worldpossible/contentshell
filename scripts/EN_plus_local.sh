sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-afristory /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-ck12 /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-file_share /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-GCF2015 /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-ebooks /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-hesperian_health /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-infonet /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-math_expression /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-asst_medical /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-medline_plus /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-scratch /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-musictheory /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-olpc /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-practical_action /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-understanding_algebra /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-PhET /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-radiolab /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-powertyping /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-iicba /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-wikibooks /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-wikipedia_for_schools /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-wikisource /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-wikiversity /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-wikivoyage /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-wiktionary /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-windows_apps /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-worldmap /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-wikipedia /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-oya /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-law_library /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-fantastic_phonics /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-wikipedia /media/RACHEL/rachel/modules/.
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-TED/TED2* /media/RACHEL/rachel/modules/.
sudo mkdir /media/RACHEL/kacontent
sudo rsync -Pavz rsync://192.168.1.74/rachelmods/modules/en-kalite/content/* /media/RACHEL/kacontent/.
rsync -avy rsync://192.168.1.74/rachelmods/modules/en-kalite/content_khan_en.sqlite /root/.kalite/database/.
sudo rsync -avy rsync://192.168.1.74/rachelmods/admin.* /media/RACHEL/rachel/.
sudo cp /media/RACHEL/rachel/admin.encap.sqlite /media/RACHEL/rachel/admin.sqlite
sudo mkdir /media/RACHEL/kiwix
sudo mkdir /media/RACHEL/kiwix/data
sudo mkdir /media/RACHEL/kiwix/data/library
sudo touch /media/RACHEL/kiwix/data/library/library.xml
sudo echo 'update script, update content shell - options 11, 7'

