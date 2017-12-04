<?php

#-------------------------------------------
# Just by including this module you get browser language
# detection and access to the $lang associative array
#-------------------------------------------
# require_once("common.php"); # require ourself? why?
require_once("lang/lang." . getlang() . ".php");

#-------------------------------------------
# To avoid warnings, we specify UTC. Previously the system
# timezone was used, which was Etd/UTC for RACHEL-Pi and
# Asia/Shanghai for the RACHEL-Plus
#-------------------------------------------
date_default_timezone_set('Etc/UCT');

#-------------------------------------------
## returns an associative array of modules from the
## filesystem - does not check the database at all
## includes support for multi-lingual, template.php modules.
##-------------------------------------------
function getmods_fs() {
    global $debug ;
    $absModPath = getAbsModPath();
    if (!is_dir($absModPath)) { return array(); }
    $relModPath = getRelModPath();

    # first we get a list of all the modules from the filesystem
    $fsmods = array();
    $handle = opendir($absModPath);

    while ($moddir = readdir($handle)) {

        if (preg_match("/^\./", $moddir)) continue; // skip hidden files

        if (is_dir("$absModPath/$moddir")) { // look in dirs only
            $fragment = "";
            if (file_exists("$absModPath/$moddir/rachel-index.php")) {
                # new name - less confusing, and
                # will get syntax highlighting in editors
                $fragment = "$absModPath/$moddir/rachel-index.php";
            } else if (file_exists("$absModPath/$moddir/index.htmlf")) {
                # old name - deprecated
                $fragment = "$absModPath/$moddir/index.htmlf";
            }

          // We use the template.php if it exists, otherwise we look at $fragment for html based clues to module info.
          // Note that including template.php instead of going straight to manifest.json allows template to setup multi-lingual
          // and partial translation support without us needing to repeat that code here.
          // TODO: if a standardizations is to exist for $version in the future, make sure it is commented in template.php of new module template.

          if (file_exists("$absModPath/$moddir/template.php")) {               // template module
            $templ = array();
            include "$absModPath/$moddir/template.php";
            if($debug) {error_log("getmods_fs templ-> " . json_encode($templ));}
            if(isset($templ["title"])) {$title = $templ["title"];} else {$title = "no title";}
            if(isset($templ["version"])) {$version = $templ["version"];} else {$version = "v0.0";}
            $hidden = false;
            $templ = array();
          } else {                                                             // non-template module

            if ($fragment) {                            // yes index fragment
                $hidden = false;
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
                $version = "v0.0";
                # XXX this regex should stay in sync with update_version.pl on dev...
                preg_match("/<!--\s*version\s*=\s*(?:\"|')?([^\"'\s]+?)(?:\"|')?\s*-->/", $content, $match);
                if (isset($match[1])) { $version = $match[1]; }

            } else {                                    // no index fragment
                # set values for incomplete module
                $fragment = false;
                $version = "";
                $hidden = true;
                $title = $moddir;
            }
          }                                                                    // end non-template module

        # save info about this module
        $fsmods{ $moddir } = array(
            'dir'      => "$relModPath/$moddir",
            'moddir'   => $moddir,
            'title'    => $title,
            'position' => 0,
            'hidden'   => $hidden,
            'fragment' => $fragment,
            'version'  => $version,
        );
        if($debug) {error_log("getmods_fs fsmods-> " . json_encode($fsmods{$moddir}));}

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
    if (isset($_db)) { return $_db; }

    # not already connected? connect.
    try {
        # this has to work from both cgi & random cli,
        # however it doesn't work if we're faking being
        # a rachelplus or rachelpi (search for "fake-rachel" below)
        $dbfile = getAbsAdminPath() . "/admin.sqlite";
        $_db = new SQLite3($dbfile);
        # File could get created by webserver or cli script
        # and we want both to be able to use it.
        # Also the @ suppresses the error if we're not
        # the owner.
        @chmod($dbfile, 0666);
    } catch (Exception $ex) {
        error_log($ex->getMessage());
        error_log("DB File: $dbfile");
        return null;
    }

    # allow blocking for 10 seconds while other
    # processes are writing to the db
    $_db->busyTimeout(10000);

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
            moddir      VARCHAR(255),
            command     VARCHAR(255),
            pid         INTEGER,
            stdout_tail TEXT,
            stderr_tail TEXT,
            started     INTEGER, -- timestamp
            last_update INTEGER, -- timestamp
            completed   INTEGER, -- timestamp
            dismissed   INTEGER, -- timestamp
            retval      INTEGER,
            files_done  INTEGER,
            data_done   INTEGER,
            data_rate   VARCHAR(255)
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
        # insert default user/pass
        $_db->exec("
            INSERT INTO users (username, password)
            VALUES ('admin', 'd54f4a435aca0ed313c2a7a0b9914d78')
        ");
    }
    $_db->exec("
        CREATE TABLE IF NOT EXISTS prefs (
            pref VARCHAR(255),
            value VARCHAR(255),
            CONSTRAINT pref UNIQUE (pref)
        )
    ");
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

    # if they're running php from the command prompt,
    # that's considered authorized
    if (php_sapi_name() == "cli") {
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

    $indexurl = getAbsBaseUrl();
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
# we have a couple /tmp/ files you can plop down
# to get the admin interface to temporarily show you
# what would come up for different devices
# ...sadly this breaks db access, but we'll have
# to figure that out later
#-------------------------------------------
define("RACHELPI_MODPATH", "/var/www/rachel/modules");
function is_rachelpi() {
    return is_dir(RACHELPI_MODPATH) || file_exists("/tmp/fake-rachelpi");
}
define("RACHELPLUS_MODPATH", "/media/RACHEL/rachel/modules");
function is_rachelplus() {
    return is_dir(RACHELPLUS_MODPATH) || file_exists("/tmp/fake-rachelplus");
}

#-------------------------------------------
# gets the absolute module path on any machine
# this should work from any directory so install
# scripts can call it and find the right place
#-------------------------------------------
function getAbsModPath() {

    if (is_rachelplus()) { return RACHELPLUS_MODPATH; }
    if (is_rachelpi()) { return RACHELPI_MODPATH; }

    # other system (from webroot)
    if (file_exists("./modules")) { return realpath("./modules"); }
    # other (from admin dir)
    if (file_exists("../modules")) { return realpath("../modules"); }

    # unknown
    return false;

}

function getAbsAdminPath() {
    return preg_replace("/modules/", "admin", getAbsModPath());
}

#-------------------------------------------
# Sometimes we want the module directory
# relative from the current directory instead.
# This should work through HTTP or command line
#-------------------------------------------
function getRelModPath() {
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

function getRelAdminPath() {
    return preg_replace("/modules/", "admin", getRelModPath());
}

#-------------------------------------------
# Get the absolute URL from the current location
# to the root RACHEL directory, presumably where
# index.php is residing
#-------------------------------------------
function getAbsBaseUrl() {
    # we avoid just using "/" here because in development
    # our code is sometimes in a subdirectory of the server
    $baseurl = dirname($_SERVER['REQUEST_URI']);
    $baseurl = preg_replace("/\/admin.*/", "/", $baseurl);
    $baseurl = preg_replace("/\/modules.*/", "/", $baseurl); // safe?
    return $baseurl;
}

#-------------------------------------------
# this function updates the database to match the modules that
# are in the filesystem
#-------------------------------------------
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
                #error_log("INSERT into modules (moddir, title, position, hidden) " .
                #    "VALUES ('$db_moddir', '$db_title', '$db_position', '0')");
            }
        }

        # delete anything from the db that wasn't in the fs
        foreach (array_keys($dbmods) as $moddir) {
            if (!isset($fsmods[$moddir])) {
                $db_moddir =   $db->escapeString($moddir);
                $db->exec("DELETE FROM modules WHERE moddir = '$db_moddir'");
                #error_log("DELETE FROM modules WHERE moddir = '$db_moddir'");
            }
        }

        $db->exec("COMMIT");

    }

}

#-------------------------------------------
# Read in a .modules file and return a sorted arrray 
# of the modules that should be installed and an
# associative array of modules that should be hidden.
# So use it like this:
#
#     list($sorted, $hidden) = parseModulesFile($file);
#
# The file format is just a list of module names,
# one per line, in the order you want them to appear.
# Blank lines and lines starting with a "#" are ignored;
# lines starting with a "." are installed but hidden.
#
# We just die on any error: can't read file, malformed file
#-------------------------------------------
function parseModulesFile($file) {

    $fh = fopen($file, "r");
    if (!$fh) {
        error_log("$file could not be opened");
        exit(1);
    }

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
            exit(1);
        }
        # flag hidden items in an associative array
        if (preg_match("/^\./", $line)) {
            $line = preg_replace("/^\./", "", $line);
            $hidden[$line] = 1;
        }
        # put all items in an ordered array, even
        # hidden items, because we will still want to
        # install and sort them
        array_push($sorted, $line);
    }

    return array($sorted, $hidden);

}

#------------------------------------------- 
# Installs sorts, and sets visibility based on a
# .modules file -- must work from the command line
# or the html admin interface
#------------------------------------------- 
function installmods($file, $install_server) {

    list($sorted, $hidden) = parseModulesFile($file);

    if (!$install_server) {
        error_log("Missing install_server argument to installmods() in common.php");
        exit(1);
    }

    # where are we putting the installed modules?
    # (should we use getRelModDir instead? should we
    # replace the code there with this? testing needed
    # under different dirs, http, command line, etc.)
    $destdir = dirname(dirname(__FILE__)) . "/modules/";

    # use rsync -z for remote hosts, not for LAN
    # (the CPU overhead of zip actually slows it down on a fast network)
    $zip = "z";
    if (preg_match("/^[\d\.]+$/", $install_server)) {
        $zip = "";
    }

    try {

        $db = getdb();
        if (!$db) { throw new Exception($db->lastErrorMsg); }

        # this is a performance enhancing command, more than for safety
        $db->exec("BEGIN");

        foreach ($sorted as $moddir) {

            $cmd = "rsync -Pav$zip rsync://$install_server/rachelmods/$moddir $destdir";

            # insert a task into the DB
            $db_cmd    = $db->escapeString($cmd);
            $db_moddir = $db->escapeString($moddir);
            $db->exec("
                INSERT INTO tasks (moddir, command)
                VALUES ('$db_moddir', '$db_cmd')
            ");

        }

        # after all the modules are in the task queue to be installed,
        # we add a call to the sort script -- since this may not run
        # for a while we copy the file to a tmp file for later...

        # get unique name
        $mfile = uniqid("/tmp/sortmods-", true);
        # copy the .modules file to that unique name
        copy($file, $mfile);

        $sortscript = dirname(getAbsModPath()) . "/sortmods.php";
        $db_cmd = $db->escapeString("php $sortscript $mfile");

        # insert a sort task into the DB
        $db->exec("
            INSERT INTO tasks (moddir, command)
            VALUES ('.modules sort', '$db_cmd')
        ");

    } catch (Exception $ex) {
        $db->exec("ROLLBACK");
        error_log($ex);
        exit(1);
    }

    $db->exec("COMMIT");

    # finally, we fire off our clever database updating rsync process
    $script = dirname(__FILE__) . "/do_tasks.php";
    exec("php $script > /dev/null 2>&1 &");

}

#-------------------------------------------
# Call this to sort modules based on a .modules file
#-------------------------------------------
function sortmods($file) {
    list($sorted, $hidden) = parseModulesFile($file);
    _sortmods($sorted, $hidden);
}

#-------------------------------------------
# Internal use - actually does the sorting/hiding work.
# The instructions come from a .modules file
# as parsed by parseModulesFile() -- modules that aren't 
# seen at all are hidden and sorted last, but not removed.
#-------------------------------------------
function _sortmods($sorted, $hidden) {

    # before we sort anything, let's make sure all the modules
    # are actually recorded in the DB

    syncmods_fs2db();

    try {

        $db = getdb();
        if (!$db) { throw new Exception($db->lastErrorMsg); }
        $db->exec("BEGIN");

        # boink everything to the bottom and hide it
        $res = $db->exec("UPDATE modules SET position = '9999', hidden = '1'");
        if (!$res) { throw new Exception($db->lastErrorMsg()); }

        # set the new order and new hidden state
        $db_position = 1;
        foreach ($sorted as $moddir) {
            $db_moddir = $db->escapeString($moddir);
            if (isset($hidden[$moddir])) { $db_is_hidden = 1; } else { $db_is_hidden = 0; }
            $rv = $db->exec("
                UPDATE modules
                   SET position = '$db_position',
                       hidden   = '$db_is_hidden'
                 WHERE moddir = '$db_moddir'
            ");
            if (!$rv) { throw new Exception($db->lastErrorMsg()); }
            ++$db_position;
        }

    } catch (Exception $ex) {
        $db->exec("ROLLBACK");
        error_log($ex);
        exit(1);
    }

    $db->exec("COMMIT");

}

function showip () {
    global $lang;
    #-------------------------------------------
    # this is done as a function to enforce scope on $output
    #-------------------------------------------
    # some notes to prevent future regression:
    # the PHP suggested gethostbyname(gethostname())
    # brings back the unhelpful 127.0.0.1 on RPi systems,
    # as well as slowing down some Windows installations
    # with a DNS lookup. $_SERVER["SERVER_ADDR"] will just
    # display what's in the user's address bar, so also
    # not useful - using ifconfig/ipconfig is the way to go,
    # but requires system-specific tweaking
    #-------------------------------------------
    if (preg_match("/^win/i", PHP_OS)) {
        # under windows it's ipconfig
        # (though we're making windows static-only now)
        $output = shell_exec("ipconfig");
        preg_match("/IPv4 Address.+?: (.+)/", $output, $match);
        if (isset($match[1])) { echo "<b>$lang[server_address]</b>: $match[1]\n"; }
    } else if (preg_match("/^darwin/i", PHP_OS)) {
        # OSX is unix, but it's a little different
        exec("/sbin/ifconfig", $output);
        preg_match("/en0.+?inet (.+?) /", join("", $output), $match);
        if (isset($match[1])) { echo "<b>$lang[server_address]</b>: $match[1]\n"; }
    } else {
        # most likely linux based - so ifconfig should work
        exec("/sbin/ifconfig", $output);
        preg_match("/eth0.+?inet addr:(.+?) /", join("", $output), $match);
        if (isset($match[1])) { echo "<b>LAN</b>: $match[1]\n"; }
        preg_match("/wlan0.+?inet addr:(.+?) /", join("", $output), $match);
        if (isset($match[1])) { echo "<br><b>WIFI</b>: $match[1]\n"; }
    }

}

# restart kiwix so it sees what modules are visible/hidden
function kiwix_restart() {
    exec("sudo bash /root/rachel-scripts/rachelKiwixStart.sh");
}

function show_local_content_link() {
    $db = getdb();
    $rv = $db->querySingle("SELECT 1 FROM prefs WHERE pref = 'show_local_content_link' AND value = '1'");
    return $rv;
}

function run_rsyncd() {
    $db = getdb();
    $rv = $db->querySingle("SELECT 1 FROM prefs WHERE pref = 'run_rsyncd' AND value = '1'");
    return $rv;
}

?>
