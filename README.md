# contentshell
HTML and related files for the shell of RACHEL

#instructions
After going through these steps, you will have an up and running RACHEL without Khan or Wikipedia

This will install our PHP based search tool, rsphider, art, CSS, and directory index tool _h5ai.

From here, you still have to add content modules.

cd /media/RACHEL/rachel

sudo git clone https://github.com/rachelproject/contentshell.git

sudo mv contentshell/* ./

sudo mkdir rachel/modules

sudo mv en_all.sh modules

cd modules

sudo chmod 775 ./en_all.sh

./en_all.sh

# license

Creative Commons - BY, SA, NC
