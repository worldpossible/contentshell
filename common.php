<?php

#-------------------------------------------
# returns an associative array of modules from the
# filesystem - does not check the database at all
#-------------------------------------------
function getmods_fs() {

    $basedir = "modules";

    if (!is_dir($basedir)) { return false; }

    # first we get a list of all the modules from the filesystem
    $fsmods = array();
    $handle = opendir($basedir);
    while ($moddir = readdir($handle)) {
        if (preg_match("/^\./", $moddir)) continue; // skip hidden files
        if (is_dir("$basedir/$moddir")) { // look in dirs only
            if (file_exists("$basedir/$moddir/index.htmlf")) { // check for index fragment
                $content = file_get_contents("$basedir/$moddir/index.htmlf");
                preg_match("/<h2>(.+)<\/h2>/", $content, $match);
                $title = "";
                if (isset($match[1])) {
                    $title = preg_replace("/<?php.+?>/", "", $match[1]); // this removes and php from the title
                    $title = preg_replace("/<.+?>/", "", $title); // this removes any html from the title
                }
                if (!$title) { $title = $moddir; } // if we didn't get a title, use the moddir name
                // save info about this module
                $fsmods{ $moddir } = array(
                    'dir'      => "$basedir/$moddir",
                    'moddir'   => $moddir,
                    'title'    => $title,
                    'position' => 0,
                    'hidden'   => false,
                    'nohtmlf'  => false,
                );
            } else {
                # save info about this incomplete module
                $fsmods{ $moddir } = array(
                    'dir'      => "$basedir/$moddir",
                    'moddir'   => $moddir,
                    'title'    => $moddir,
                    'position' => 0,
                    'hidden'   => true,
                    'nohtmlf'  => true,
                );
            }
        }
    }
    closedir($handle);

    return $fsmods;

}

#-------------------------------------------
# returns an associative array of modules from the
# database - does not check the filesystem at all
#-------------------------------------------
function getmods_db() {

    try {
        $db = new SQLite3("admin.sqlite");
    } catch (Exception $ex) {
        echo "<h2>" . $ex->getMessage() . "</h2>" .
             "<h3>You may need to change permissions on the RACHEL " .
             "root directory using: chmod 777</h3>";
    }

    # opening the DB worked
    if (!isset($ex)) {

        # in case this is the first time
        $db->query("
            CREATE TABLE IF NOT EXISTS modules (
                module_id INTEGER PRIMARY KEY,
                moddir    VARCHAR(255),
                title     VARCHAR(255),
                position  INTEGER,
                hidden    INTEGER
            )
        ");

        # get that db module list, and populate fsmods with
        # the position info from the database
        $rv = $db->query("SELECT * FROM modules");
        $dbmods = array();
        while ($row = $rv->fetchArray()) {
            $dbmods[$row['moddir']] = $row;
            if (isset($fsmods[$row['moddir']])) {
                $fsmods[$row['moddir']]['position'] = $row['position'];
                $fsmods[$row['moddir']]['hidden'] = $row['hidden'];
            }
        }

    }

}

#-------------------------------------------
# get a database handle - also init the table if needed
#-------------------------------------------
function getdb() {

    $db = new SQLite3("admin.sqlite");

    # in case this is the first time
    # - a bit wasteful to do this every time
    # but it saves errors in the log if the
    # db file gets lost and people don't
    # go to admin.php to re-initialize it
    $db->query("
        CREATE TABLE IF NOT EXISTS modules (
            module_id INTEGER PRIMARY KEY,
            moddir    VARCHAR(255),
            title     VARCHAR(255),
            position  INTEGER,
            hidden    INTEGER
        )
    ");

    return $db;

}

#-------------------------------------------
# sort by db position, then alphabetically by moddir,
# if there's no db position put alphabetically at top
#-------------------------------------------
function bypos($a, $b) {
if (!isset($a['position'])) { $a['position'] = 0; }
    if (!isset($b['position'])) { $b['position'] = 0; }
    if ($a['position'] == $b['position']) {
        return strcmp(strtolower($a['moddir']), strtolower($b['moddir']));
    } else {
        return $a['position'] - $b['position'];
    }
}

?>
