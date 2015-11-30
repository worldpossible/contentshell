<?php

init();

# try running it with no modules, no db
$html = shell_exec("php index.php");
if (preg_match("/<!DOCTYPE.+No modules found.+<\/html>/s", $html) !== 1) {
    fail("Failed: testing no modules");
} 

# try it with two test modules, no db
rename("test/mod_one", "modules/mod_one") or fail("Couldn't move 'mod_one'");
rename("test/mod_two", "modules/mod_two") or fail("Couldn't move 'mod_two'");
$html = shell_exec("php index.php");
if (preg_match("/<!DOCTYPE.+Test Module 1.+Test Module 2.+<\/html>/s", $html) !== 1) {
    fail("Failed: testing no modules");
} 

echo "All Tests Passed!\n";


cleanup();
exit;

function init() {
    rename("modules", "modules.tmp") or fail("Couldn't move 'modules' directory\n");
    mkdir("modules") or fail("Couldn't create 'modules' directory\n");
    rename("admin.sqlite", "admin.sqlite.tmp") or fail("Couldn't move 'admin.sqlite'\n");
}

function cleanup() {
    if (file_exists("modules.tmp")) {
        if (file_exists("modules/mod_one")) {
            rename("modules/mod_one", "test/mod_one");
        }
        if (file_exists("modules/mod_two")) {
            rename("modules/mod_two", "test/mod_two");
        }
        rmdir("modules");
        rename("modules.tmp", "modules");
    }
    if (file_exists("admin.sqlite.tmp")) {
        if (file_exists("admin.sqlite")) {
            unlink("admin.sqlite");
        }
        rename("admin.sqlite.tmp", "admin.sqlite");
    }
}

function fail($msg) {
    echo "$msg\n";
    cleanup();
    exit;
}

?>
