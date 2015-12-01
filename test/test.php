<?php

# rough check to make sure we're in the right place
if (!file_exists("test")) {
    echo "Run the test script from the contentshell directory as:\n";
    echo "\tphp test/test.php\n";
    exit;
}

echo "Starting tests...\n";

init();

testmsg("Testing index.php with no modules, no db");
$html = shell_exec("php index.php");
passfail(preg_match("/<!DOCTYPE.+No modules found.+<\/html>/s", $html) == 1);

testmsg("Testing index.php with two modules, no db");
rename("test/mod_one", "modules/mod_one") or error("Couldn't move 'mod_one'");
rename("test/mod_two", "modules/mod_two") or error("Couldn't move 'mod_two'");
$html = shell_exec("php index.php");
passfail(preg_match("/<!DOCTYPE.+Test Module 1.+Test Module 2.+<\/html>/s", $html) == 1);

testmsg("Testing index.php created db");
passfail(file_exists("admin.sqlite"));
unlink("admin.sqlite") or error("Couldn't remove test db file");

testmsg("Testing admin.php with two modules, no db");
$html = shell_exec("PHP_AUTH_USER=root PHP_AUTH_PW=rachel php admin.php");
passfail(preg_match("/<!DOCTYPE.+mod_one - Test Module 1.+new.+mod_two - Test Module 2.+new.+<\/html>/s", $html) == 1);

testmsg("Testing admin.php created db");
passfail(file_exists("admin.sqlite"));

testmsg("Testing admin.php saving module orders");
$html = shell_exec(
    "PHP_AUTH_USER=root PHP_AUTH_PW=rachel " .
    "php -e -r '\$_GET[\"moddirs\"] = \"mod_two,mod_one\"; " .
    "include \"admin.php\";'"
);
passfail($html == "");

testmsg("Testing admin.php reading saved module order");
$html = shell_exec("PHP_AUTH_USER=root PHP_AUTH_PW=rachel php admin.php");
passfail(preg_match("/<!DOCTYPE.+mod_two - Test Module 2.+mod_one - Test Module 1.+<\/html>/s", $html) == 1);

testmsg("Testing index.php reading saved module order");
$html = shell_exec("php index.php");
passfail(preg_match("/<!DOCTYPE.+Test Module 2.+Test Module 1.+<\/html>/s", $html) == 1);

testmsg("Testing admin.php hiding both modules");
$html = shell_exec(
    "PHP_AUTH_USER=root PHP_AUTH_PW=rachel " .
    "php -e -r '\$_GET[\"moddirs\"] = \"mod_one,mod_two\"; " .
    "\$_GET[\"hidden\"] = \"mod_one,mod_two\"; " .
    "include \"admin.php\";'"
);
passfail($html == "");

testmsg("Testing index.php reading all modules hidden");
$html = shell_exec("php index.php");
passfail(preg_match("/<!DOCTYPE.+No modules found.+<\/html>/s", $html) == 1);

if ($fail == 0) {
    echo "All Tests Passed!\n";
} else {
    echo "$pass test passed, $fail tests failed\n";
}

cleanup();

function init() {
    global $fail, $pass, $error;
    $fail = 0; $pass = 0; $error = 0;
    rename("modules", "modules.tmp") or error("Couldn't move 'modules' directory\n");
    mkdir("modules") or error("Couldn't create 'modules' directory\n");
    rename("admin.sqlite", "admin.sqlite.tmp") or error("Couldn't move 'admin.sqlite'\n");
    # if something went wrong already, bail
    if ($error) { cleanup(); }
}

# we move stuff around before testing, so we try to move it back after
function cleanup() {

    global $error;

    echo "Cleaning up...\n";

    if (file_exists("modules.tmp")) {
        if (file_exists("modules/mod_one")) {
            rename("modules/mod_one", "test/mod_one") or error("Couldn't remove 'modules/mod_one'");
        }
        if (file_exists("modules/mod_two")) {
            rename("modules/mod_two", "test/mod_two") or error("Couldn't remove 'modules/mod_two'");
        }
        rmdir("modules") or error("Couldn't remove test 'modules'");
        rename("modules.tmp", "modules") or error("Couldn't put 'modules.tmp' back");
    }
    if (file_exists("admin.sqlite.tmp")) {
        if (file_exists("admin.sqlite")) {
            unlink("admin.sqlite") or error("Couldn't put 'modules.tmp' back");
        }
        rename("admin.sqlite.tmp", "admin.sqlite") or error("Couldn't put 'modules.tmp' back");
    }

    if ($error) {
        echo "There were $error internal errors during testing.\n";
        echo "See messages to determine if cleanup is required.\n";
    }

    exit;
}

function passfail($bool) {
    global $pass, $fail;
    if ($bool) {
        ++$pass;
        echo " PASS\n";
    } else {
        ++$fail;
        echo " FAIL\n";
    }
}

# this means there was an internal error
# during testing, not that the test failed
function error($msg) {
    global $error;
    ++$error;
    echo "$msg\n";
}

function testmsg($msg) {
    echo str_pad($msg, 60, ".");
}

?>
