<?php

#-------------------------------------------
# Just by including this module you get browser language
# detection and access to the $lang associative array
#-------------------------------------------
# require_once("common.php"); # require ourself? why?
require_once("lang/lang." . getlang() . ".php");

#-------------------------------------------
# returns an associative array of modules from the
# filesystem - does not check the database at all
#-------------------------------------------
function getmods_fs() {

    # usually we're in the right place
    $basedir = "modules";

    # but actually when scripts call us during install
    # we might not be... so be specific
    $absdir = $basedir;
    if (file_exists("/media/RACHEL/rachel/modules")) {
        # rachel plus
        $absdir = "/media/RACHEL/rachel/modules";
    } else if (file_exists("/var/www/modules")) {
        # rachel pi
        $absdir = "/var/www/modules";
    }
    if (!is_dir($absdir)) { return array(); }

    # first we get a list of all the modules from the filesystem
    $fsmods = array();
    $handle = opendir($absdir);
    while ($moddir = readdir($handle)) {

        if (preg_match("/^\./", $moddir)) continue; // skip hidden files

        if (is_dir("$absdir/$moddir")) { // look in dirs only

            $fragment = "";
            if (file_exists("$absdir/$moddir/rachel-index.php")) {
                # new name - less confusing, and
                # will get syntax highlighting in editors
                $fragment = "$basedir/$moddir/rachel-index.php";
            } else if (file_exists("$absdir/$moddir/index.htmlf")) {
                # old name - deprecated
                $fragment = "$basedir/$moddir/index.htmlf";
            }

            if ($fragment) { // check for index fragment

                $content = file_get_contents($fragment);

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
                    'fragment' => $fragment,
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
                    'fragment'  => false,
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

function available_langs() {

    $basedir = "lang";
    $default_lang = "en";

    # if there's no options, don't bother trying
    # -- this actually means we'll render with blanks
    # for all translated text -- fatal error?
    if (!is_dir($basedir)) { return array( $default_lang ); }

    # first we get a list of all languages available (from the lang directory)
    $available_langs = array();
    $handle = opendir($basedir);
    while ($moddir = readdir($handle)) {
        if (preg_match("/^lang\.(..)\.php$/", $moddir, $matches)) {
            array_push($available_langs, $matches[1]);
        }
    }

    # if there's one option, return it
    if (sizeof($available_langs) == 1) {
        return array( $available_langs[0] );
    }

    return $available_langs;

}

# This function returns the language preferred by the
# browser - out of the available languages. If the browser
# wants a language we don't have, we just return "en".
function browser_lang() {

    # we want the language codes as keys, not values
    $available_langs = array_flip(available_langs());
    $browser_langs;

    # now we pull the languages from header
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        preg_match_all('~([\w-]+)(?:[^,\d]+([\d.]+))?~',
            strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), $matches, PREG_SET_ORDER);
    } else {
        # bad robot (probably) -- fake it to avoid errors
        $matches = array(array('en','en'));
    }

    #print("<h1>" . $_SERVER['HTTP_ACCEPT_LANGUAGE'] . "</h1>");

    # this logic gets the qvalue for each language
    foreach($matches as $match) {

        list($a, $b) = explode('-', $match[1]) + array('', '');

        $value = isset($match[2]) ? (float) $match[2] : 1.0;

        if(isset($available_langs[$match[1]])) {
            $browser_langs[$match[1]] = $value;
            continue;
        }

        # dialects (e.g. en-US) - don't overwrite if we've already got
        # a match on base language
        if(!isset($browser_langs[$a]) && isset($available_langs[$a])) {
            $browser_langs[$a] = $value - 0.1;
        }

    }

    # default to english if there's no match 
    if (!is_array($browser_langs)) {
        return "en";
    }

    # order them by q weight
    arsort($browser_langs);

    # return the first
    return key($browser_langs);

}

#-------------------------------------------
# there's several things that can affect which langauge
# gets displayed - this function figures all that out
# (actually there's not any more, so this is very simple)
#-------------------------------------------
function getlang() {

    # if there was no user or admin setting use the browser's request
    return browser_lang();

}

function authorized() {

    global $lang;

    # special case
    if (isset($_SERVER['PHP_CLI_TESTING'])) {
        return true;
    }

    # if we've got a good cookie, return true
    if (isset($_COOKIE['rachel-auth']) && $_COOKIE['rachel-auth'] == "admin") {
        return true;

    # if we've got good user/pass, issue cookie
    } else if (isset($_POST['user']) && isset($_POST['pass']) &&
            $_POST['user'] == "admin" && md5($_POST['pass']) == "d54f4a435aca0ed313c2a7a0b9914d78") {
        setcookie("rachel-auth", "admin");
        header(
            "Location: //$_SERVER[HTTP_HOST]"
            . strtok($_SERVER["REQUEST_URI"],'?')
        );

    # if we've got nothing or bad user/pass, show login page
    } else {
        $indexurl = dirname($_SERVER["REQUEST_URI"]);
        print <<<EOT
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Login</title>
  </head>
  <body bgcolor="#cccccc" onload="document.getElementById('user').focus()">
    <center>
    <h1>$lang[admin] $lang[login]</h1>
    <p><a href="$indexurl">&larr; $lang[back]</a></p>
    <form method="POST">
    <table cellpadding="10">
    <tr><td>$lang[user]</td><td><input name="user" id="user"></td></tr>
    <tr><td>$lang[pass]</td><td><input name="pass" type="password"></td></tr>
    <tr><td colspan="2" align="right"><input type="submit" value="$lang[login]"></td></tr>
    </table>
    </center>
    </form>
  </body>
</html>
EOT;
    }

}

function clearcookie() {
    setcookie("rachel-auth", "");
    header(
        "Location: //$_SERVER[HTTP_HOST]/"
        . dirname($_SERVER["REQUEST_URI"])
    );
}

# this function updates the database to match the modules that
# are in the filesystem
function syncmods_fs2db() {

    # get info on the modules in the filesystem
    $fsmods = getmods_fs();
    # get info on the modules in the database
    $dbmods = getmods_db();

    $db = getdb();
    if ($db) {

        $db->exec("BEGIN");

        # insert anything we found in the fs that wasn't in the db
        foreach (array_keys($fsmods) as $moddir) {
            if (!isset($dbmods[$moddir])) {
                $db_moddir =   $db->escapeString($moddir);
                $db_title  =   $db->escapeString($fsmods[$moddir]['title']);
                $db_position = $db->escapeString($fsmods[$moddir]['position']);
                $db->exec(
                    "INSERT into modules (moddir, title, position, hidden) " .
                    "VALUES ('$db_moddir', '$db_title', '$db_position', '0')"
                );
            }
        }

        # delete anything from the db that wasn't in the fs
        foreach (array_keys($dbmods) as $moddir) {
            if (!isset($fsmods[$moddir])) {
                $db_moddir =   $db->escapeString($moddir);
                $db->exec("DELETE FROM modules WHERE moddir = '$db_moddir'");
            }
        }

        $db->exec("COMMIT");

    }

}

function sortmods($file) {

    # we're going to read in the .modules file here and
    # if successful, use it to update the order and visibility
    # of all the modules in the filesystem
    $fh = fopen($file, "r");
    if ($fh) {

        $hidden = array();
        $sorted = array();
        while (($line = fgets($fh)) !== false) {
            # remove all whitespace
            $line = preg_replace("/\s+/", "", $line);
            # skip comments and blank lines
            if (preg_match("/^#/", $line) || !preg_match("/\S/", $line)) {
                continue;
            }
            # detect screwy files and bail
            # (module names can only be letters, numbers, underscore, hyphen, and dot)
            if (preg_match("/[^\w\.\-]/", $line)) {
                error_log("$file does not look like a valid .modules file");
                exit;
            }
            # flag hidden items in an associative array
            if (preg_match("/^\./", $line)) {
                $line = preg_replace("/^\./", "", $line);
                $hidden[$line] = 1;
            }
            # put all items in an ordered array
            array_push($sorted, $line);
        }

        # when run during install, there is no data in the db to update,
        # so it's important that we sync the database to match the filesystem first
        syncmods_fs2db();

        try {

            $db = getdb();
            if (!$db) { throw new Exception($db->lastErrorMsg); }
            $db->exec("BEGIN");

            # boink everything to the bottom and hide it
            $rv = $db->query("SELECT * FROM modules ORDER BY moddir");
            if (!$rv) { throw new Exception($db->lastErrorMsg()); }
            $position = 1000;
            while ($row = $rv->fetchArray()) {
                $res = $db->exec("UPDATE modules SET position = '$position', hidden = '1' WHERE moddir = '$row[moddir]'");
                #error_log("UPDATE modules SET position = '$position', hidden = '1' WHERE moddir = '$row[moddir]'");
                if (!$res) { throw new Exception($db->lastErrorMsg()); }
                ++$position;
            }

            # go to the DB and set the new order and new hidden state
            $position = 1;
            foreach ($sorted as $moddir) {
                $moddir = $db->escapeString($moddir);
                if (isset($hidden[$moddir])) { $is_hidden = 1; } else { $is_hidden = 0; }
                $rv = $db->exec(
                    "UPDATE modules SET position = '$position', hidden = '$is_hidden'" .
                    " WHERE moddir = '$moddir'"
                );
                #error_log("UPDATE modules SET position = '$position', hidden = '$is_hidden' WHERE moddir = '$moddir'");
                if (!$rv) { throw new Exception($db->lastErrorMsg()); }
                ++$position;
            }

        } catch (Exception $ex) {
            $db->exec("ROLLBACK");
            error_log($ex);
        }
        $db->exec("COMMIT");

    } else {
        error_log("modulesort() Couldn't Open File: $file");
    }

}

?>
