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

            if (file_exists("$basedir/$moddir/index.htmlf")) {
                # old name - deprecated
                $indexhtmlf = "$basedir/$moddir/index.htmlf";
            } else {
                # new name - less confusing, and
                # will get syntax highlighting in editors
                $indexhtmlf = "$basedir/$moddir/rachel-index.php";
            }

            if (file_exists($indexhtmlf)) { // check for index fragment

                $content = file_get_contents($indexhtmlf);

                # pull the title from the file
                $title = "";
                preg_match("/<h2>(.+)<\/h2>/", $content, $match);
                if (isset($match[1])) {
                    // this removes any php from the title
                    $title = preg_replace("/<?php.+?>/", "", $match[1]);
                    // this removes any html from the title
                    $title = preg_replace("/<.+?>/", "", $title);
                }
                // if we didn't get a title, use the moddir name
                if (!$title) { $title = $moddir; }

                # pull the version from the file
                $version = "";
                preg_match("/version\s*=\s*([\d\.]+)/", $content, $match);
                if (isset($match[1])) {
                    $version = $match[1];
                }

                // save info about this module
                $fsmods{ $moddir } = array(
                    'dir'      => "$basedir/$moddir",
                    'moddir'   => $moddir,
                    'title'    => $title,
                    'position' => 0,
                    'hidden'   => false,
                    'nohtmlf'  => false,
                    'version'  => $version,
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
                    'version'  => "",
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

    $db = getdb();
    $dbmods = array();

    # opening the DB worked
    if ($db) {
        # get that db module list
        $rv = $db->query("SELECT * FROM modules");
        while ($row = $rv->fetchArray()) {
            $dbmods[$row['moddir']] = $row;
        }
    }

    return $dbmods;

}

#-------------------------------------------
# get a database handle - also init the table if needed
#-------------------------------------------
function getdb() {

    try {
        $db = new SQLite3("admin.sqlite");
    } catch (Exception $ex) {
        return null;
    }

    # in case this is the first time
    # - a bit wasteful to do this every time
    # but it saves errors in the log if the
    # db file gets lost and people don't
    # go to admin.php to re-initialize it
    $db->exec("
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

function availlang() {

    $basedir = "lang";
    $default_lang = "en";

    # if there's no options, don't bother trying
    # -- this actually means we'll render with blanks
    # for all translated text -- fatal error?
    if (!is_dir($basedir)) { return array( $default_lang ); }

    # first we get a list of all languages available (from the lang directory)
    $available_languages = array();
    $handle = opendir($basedir);
    while ($moddir = readdir($handle)) {
        if (preg_match("/^lang\.(..)\.php$/", $moddir, $matches)) {
            array_push($available_languages, $matches[1]);
        }
    }

    # if there's one option, return it
    if (sizeof($available_languages) == 1) {
        return array( $available_languages[0] );
    }

    return $available_languages;

}

#-------------------------------------------
# figure out the preferred language for this user
#-------------------------------------------
function getlang() {

    # we want the language codes as keys, not values
    $available_languages = array_flip(availlang());
    $langs;

    # now we pull the languages from header
    preg_match_all('~([\w-]+)(?:[^,\d]+([\d.]+))?~',
        strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), $matches, PREG_SET_ORDER);

    #print("<h1>" . $_SERVER['HTTP_ACCEPT_LANGUAGE'] . "</h1>");

    # this logic gets the qvalue for each language
    foreach($matches as $match) {

        list($a, $b) = explode('-', $match[1]) + array('', '');

        $value = isset($match[2]) ? (float) $match[2] : 1.0;

        if(isset($available_languages[$match[1]])) {
            $langs[$match[1]] = $value;
            continue;
        }

        # dialects (e.g. en-US) - don't overwrite if we've already got
        # a match on base language
        if(!isset($langs[$a]) && isset($available_languages[$a])) {
            $langs[$a] = $value - 0.1;
        }

    }

    # default to english if there's no match 
    if (!is_array($langs)) {
        return "en";
    }

    # order them by q weight
    arsort($langs);

    # return the first
    return key($langs);

}

?>
