<?

# this script spits out a .modules file for whatever
# is installed showing/hidden on the machine currently
# -- this can then be used to make a duplicate machine

require_once("common.php");
if (!authorized()) { exit(); }

$mods = getmods_db();
uasort($mods, 'bypos');

header("content-type: text/plain");

foreach ($mods as $mod) {
    if ($mod['hidden']) {
        echo ".";
    }
    echo $mod['moddir'] . "\n";
}

?>
