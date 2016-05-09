<?php
#-------------------------------------------
# Language: English
# Use underscores in key names so they can be easily interpolated
# in strings (hyphenated keys do not interpolate in strings)
# ALSO: THIS FILE MUST NOT HAVE ANY BLANK LINES OUTSIDE OF
# THE PHP CODE - OR IT WILL BREAK HEADER CONTROL IN ADMIN.PHP
#-------------------------------------------
 
$lang = array();
 
# for index.php
$lang['home'] = 'Home';
$lang['about'] = 'About';
$lang['server_address'] = 'Server Address';
$lang['admin'] = 'Admin';
$lang['stats'] = 'Stats';
$lang['version'] = 'Version';
$lang['months'] = array(
    'Null', 'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
);
$lang['languages'] = 'Languages';

$lang['no_mods_error'] = "<h2>No modules found.</h2>\n"
    . "Please check there are modules in the modules directory,\n"
    . "and that they are not all hidden on the admin page.\n";

# for admin.php
$lang['found_in'] = "Found in";
$lang['hide'] = "hide";
$lang['save_changes'] = "Save Changes";
$lang['saved'] = "Saved";
$lang['not_saved_error'] = "Not Saved - Internal Error";
$lang['logout'] = "logout";
$lang['no_moddir_found'] = "No module directory found.";
$lang['shutdown_system'] = "Shutdown System";
$lang['confirm_shutdown'] = "Are you sure you want to shut down?";
$lang['restart_system'] = "Restart System";
$lang['confirm_restart'] = "Are you sure you want to restart?";
$lang['shutdown_blurb'] = "<p>Shutting down here is safer for the SD/HD than simply unplugging the power.</p>\n"
    . "<p>If you shut down (as opposed to restart), you will need to unplug your system and plug it back in to restart.</p>\n";
$lang['shutdown_ok'] = "The server is shutting down now.";
$lang['restart_ok'] = "The server is restarting down now.";
$lang['shutdown_failed'] = "Unable to shutdown server.";
$lang['restart_failed'] = "Unable to restart server.";
$lang['set_lang'] = "Set Interface Language";
$lang['set_lang_blurb_1'] = "This setting allows you to override browser language settings for all users. By default RACHEL will use whatever language the browser is set to.";
$lang['set_lang_blurb_2'] = "NOTE: this does not change the content language, just the system text, like the top navigation and this page.";
$lang['use_browser'] = "Use Browser Setting (default)";
$lang['force'] = "Force";


?>
