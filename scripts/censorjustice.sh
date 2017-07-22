#remove Art of War
rm -r /media/RACHEL/rachel/modules/en-ebooks/books/132.txt
#remove nude pregant picture
rm -r /media/RACHEL/rachel/modules/en-wikipedia_for_schools-static/images/627/62778.jpg.htm
#remove openstax Anatomy & Physiology, Conecepts of Biology, Biology, Human Biology, Chemistry, Chemstry: Atoms First
rm "/media/RACHEL/rachel/modules/en-openstax/Content/anatomy-physiology-8.80.pdf"
sed -i '/anatomy-physiology-8.80.pdf/d' /media/RACHEL/rachel/modules/en-openstax/OpenStax.html
rm "/media/RACHEL/rachel/modules/en-openstax/Content/concepts-of-biology-9.21.pdf"
sed -i '/concepts-of-biology-9.21.pdf/d' /media/RACHEL/rachel/modules/en-openstax/OpenStax.html
rm "/media/RACHEL/rachel/modules/en-openstax/Content/biology-10.99.pdf"
sed -i '/biology-10.99.pdf/d' /media/RACHEL/rachel/modules/en-openstax/OpenStax.html
rm "/media/RACHEL/rachel/modules/en-openstax/Content/human-biology--3.2(1).pdf"
sed -i '/human-biology--3.2(1).pdf/d' /media/RACHEL/rachel/modules/en-openstax/OpenStax.html
rm "/media/RACHEL/rachel/modules/en-openstax/Content/chemistry-9.422.pdf"
sed -i '/chemistry-9.422.pdf/d' /media/RACHEL/rachel/modules/en-openstax/OpenStax.html
rm "/media/RACHEL/rachel/modules/en-openstax/Content/chemistry-atoms-first-6.82.pdf"
sed -i '/chemistry-atoms-first-6.82.pdf/d' /media/RACHEL/rachel/modules/en-openstax/OpenStax.html
#print disk usage statistics
df -h
#print LAN MACID to Find which hardware unit this was in case disk usage was not correct
ifconfig
#poweroff
poweroff

