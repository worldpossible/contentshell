#/bin/sh

# lists Apache's open filehandles - useful for making sure you're
# not leaving anything open (like I was with the db, for example)

sudo lsof -c httpd | awk '{ print $9 }' | sort | uniq -c | sort -n
