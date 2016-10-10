<?php

#-------------------------------------------
# Just by including this module you get browser language
# detectino and access to the $lang associative array
#-------------------------------------------
require_once("common.php");
require_once("lang/lang." . getlang() . ".php");

#-------------------------------------------
# To avoid warnings, we specify UTC. Previously the system
# timezone was used, which was Etd/UTC for RACHEL-Pi and
# Asia/Shanghai for the RACHEL-Plus
#-------------------------------------------
date_default_timezone_set('Etc/UCT');

#-------------------------------------------
# returns an associative array of modules from the
# filesystem - does not check the database at all
#-------------------------------------------
function getmods_fs() {

    $modbase = getrelmodpath();

    if (!is_dir($modbase)) { return false; }

    # first we get a list of all the modules from the filesystem
    $fsmods = array();
    $handle = opendir($modbase);
    while ($moddir = readdir($handle)) {

        if (preg_match("/^\./", $moddir)) continue; // skip hidden files

        if (is_dir("$modbase/$moddir")) { // look in dirs only

            $fragment = "";
            if (file_exists("$modbase/$moddir/rachel-index.php")) {
                # new name - less confusing, and
                # will get syntax highlighting in editors
                $fragment = "$modbase/$moddir/rachel-index.php";
            } else if (file_exists("$modbase/$moddir/index.htmlf")) {
                # old name - deprecated
                $fragment = "$modbase/$moddir/index.htmlf";
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
                    'dir'      => "$modbase/$moddir",
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
                    'dir'      => "$modbase/$moddir",
                    'moddir'   => $moddir,
                    'title'    => $moddir,
                    'position' => 0,
                    'hidden'   => true,
                    'fragment' => false,
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
        while ($row = $rv->fetchArray(SQLITE3_ASSOC)) {
            $dbmods[$row['moddir']] = $row;
        }
    }

    return $dbmods;

}

#-------------------------------------------
# get a database handle - also init the table if needed
#-------------------------------------------
function getdb() {

    # we need to keep a copy so we can close it in a callback later
    global $_db;
    # and also because caching per-request is smart
    if (isset($_db)) {
        return $_db;
    }

    # not already connected? connect.
    try {
        $_db = new SQLite3(getadmindir()."/admin.sqlite");
    } catch (Exception $ex) {
        return null;
    }

    # allow blocking for 5 seconds while other
    # processes are writing to the db
    $_db->busyTimeout(5000);

    # It seems a bit wasteful to do this every time we grab a db connection.
    # However it's probably better than trying to detect if each table
    # exists, in the case of a new install or an upgrade.
    $_db->exec("BEGIN");
    $_db->exec("
        CREATE TABLE IF NOT EXISTS modules (
            module_id INTEGER PRIMARY KEY,
            moddir    VARCHAR(255),
            title     VARCHAR(255),
            position  INTEGER,
            hidden    INTEGER
        )
    ");
    $_db->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            task_id     INTEGER PRIMARY KEY,
            command     VARCHAR(255),
            pid         INTEGER,
            stdout_tail TEXT,
            stderr_tail TEXT,
            started     INTEGER, -- timestamp
            last_update INTEGER, -- timestamp
            completed   INTEGER, -- timestamp
            dismissed   INTEGER, -- timestamp
            retval      INTEGER
        )
    ");
    $_db->exec("
        CREATE TABLE IF NOT EXISTS users (
            user_id     INTEGER PRIMARY KEY,
            username    VARCHAR(255),
            password    VARCHAR(255),
            CONSTRAINT username UNIQUE (username)
        )
    ");
    $admin = $_db->querySingle("SELECT 1 FROM users WHERE username = 'admin'");
    if (!$admin) {
        $_db->exec("
            INSERT INTO users (username, password)
            VALUES ('admin', 'd54f4a435aca0ed313c2a7a0b9914d78')
        ");
    }
    $_db->exec("COMMIT");

    return $_db;

}

#-------------------------------------------
# If we don't do this, dangling filehandles build up
# and after a while we can't open any more... yikes. 
#-------------------------------------------
function cleanup() {
    global $_db;
    if (isset($_db)) {
        $_db->close();
        unset($_db);
    }
}
register_shutdown_function('cleanup');

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
    closedir($handle);

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

    # special case for test scripts
    if (isset($_SERVER['PHP_CLI_TESTING'])) {
        return true;
    }

    # if we've got a good cookie, return true
    if (isset($_COOKIE['rachel-auth']) && $_COOKIE['rachel-auth'] == "admin") {
        return true;

    # if we've got good user/pass, issue cookie
    } else if (isset($_POST['user']) && isset($_POST['pass'])) {

        $db = getdb();
        $db_user = $db->escapeString($_POST['user']);
        $db_pass = $db->escapeString(md5($_POST['pass']));
        $validuser = $db->querySingle(
            "SELECT * FROM users WHERE username = '$db_user' AND password = '$db_pass'"
        );

        if ($validuser) {
            # we used to let the path be current directory, but then
            # we had cookies getting set that were difficult to unset
            # (if you don't know what directory they were set in, even
            # unsetting at "/" doesn't work) -- so now we set and
            # unset everything at the root
            setcookie("rachel-auth", "admin", 0, "/");
            header(
                "Location: //$_SERVER[HTTP_HOST]"
                . strtok($_SERVER["REQUEST_URI"],'?')
            );
            return true;
        }

    }

    # if we made it here it means they're not authorized
    # -- so give them a chance to log in

    $indexurl = getbaseurl();
    print <<<EOT
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Login</title>
    <style>
        body { background: #ccc; font-family: sans-serif; }
    </style>
  </head>
  <body onload="document.getElementById('user').focus()">
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

#-------------------------------------------
# what kind of RACHEL are we?
#-------------------------------------------
define("RACHELPI_MODBASEDIR", "/var/www/modules");
define("RACHELPLUS_MODBASEDIR", "/media/RACHEL/rachel/modules");
function is_rachelpi() { return is_dir(RACHELPI_MODBASEDIR); }
function is_rachelplus() { return is_dir(RACHELPLUS_MODBASEDIR); }

#-------------------------------------------
# get the filesystem path from the current location
# to the root RACHEL directory, presumably where
# index.php is residing
#-------------------------------------------
function getbasedir() {
    $basedir = dirname(__DIR__);
    $basedir = preg_replace("/\/admin$/", "", $basedir);
    return $basedir;
}

#-------------------------------------------
# get the absolute URL from the current location
# to the root RACHEL directory, presumably where
# index.php is residing
#-------------------------------------------
function getbaseurl() {
    $baseurl = dirname($_SERVER['REQUEST_URI']);
    $baseurl = preg_replace("/\/admin$/", "", $baseurl);
    return $baseurl;
}

#-------------------------------------------
# sometimes we want the module directory
# relative from the current directory instead
# -- this should work through HTTP or command line
#-------------------------------------------
function getrelmodpath() {
    if (isset($_SERVER['REQUEST_URI'])) {
        $me = $_SERVER['REQUEST_URI'];
    } else {
        $me = __FILE__;
    }
    if (basename(dirname($me)) == "admin") {
        return "../modules";
    } else {
        return "modules";
    }
}

function getadmindir() {
    return getbasedir() . "/admin";
}

?>
