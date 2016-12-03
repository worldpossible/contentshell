<?php
require_once("common.php");
if (!authorized()) { exit(); }

#-------------------------------------------
# configuration
#-------------------------------------------
$maxlines = 10000;
if (is_rachelplus()) {
    $alog = "/var/log/httpd/access_log";
} else if (is_rachelpi()) {
    $alog = "/var/log/apache2/access.log";
} else {
    output_and_exit("<h1>Stats Not Supported on this System</h1>");
}

#-------------------------------------------
# check if we can read the log, also handle the
# case where they want the raw log instead of stats
#-------------------------------------------
if (is_readable($alog)) {

    # the error log is in the same place... could be . or _
    $elog = preg_replace("/access(.)log/", "error$1log", $alog);

    if (isset($_GET['dl_alog'])) {
        header("content-type: text/plain");
        readfile($alog);
        exit;
    } else if (isset($_GET['dl_elog'])) {
        if (is_readable($elog)) {
            header("content-type: text/plain");
            readfile($elog);
            exit;
        } else {
            output_and_exit("<h1>Couldn't Read Error Log</h1>");
        }
    } else {
        # down here is the normal case, drawing the stats 
        output_and_exit( draw_stats() );
    }

} else {
    $logdir = preg_replace("/\/[^\/]+$/", "", $alog);
    output_and_exit("
        <h1>Couldn't Read Access Log</h1>
        Try running the following from the command line:
        <pre>chmod 777 $logdir
        chmod 666 $alog
        chmod 666 $elog</pre>
    ");
}

#-------------------------------------------
# draw stats in HTML (returns a string)
#-------------------------------------------
function draw_stats() {

    global $maxlines, $alog, $elog;

    # start timer
    $starttime = microtime(true);

    $out = "";

    # read query string (and display)
    if ($_GET && $_GET['module']) {
        $module = $_GET['module'];
        $out .= "<p>Usage Stats\n";
        $dispmod = preg_replace("/\/modules\//", "", $module);
        
    } else {
        # i don't understand why i wrote this bit:
        if (file_exists("../modules")) {
            $module = "/modules";
        } else {
            $module = "/";
        }
        $out .= "<p>Usage Stats\n";
        $dispmod = "";
    }
    $modmatch = preg_quote($module, "/");


    # read in the log file
    $content = tail($alog, $maxlines);

    # and process
    $nestcount = 0;
    $not_counted = 0;
    $total_pages = 0;
    while (1) {

        ++$nestcount;

        $count = 0;
        $errors = 0; # array();
        $stats = array();
        $start = "";
        foreach(preg_split("/((\r?\n)|(\r\n?))/", $content) as $line) {

            # line count and limiting
            # XXX is this needed if we're using tail()?
            if ($maxlines && $count >= $maxlines) { break; }
            ++$count;

            # we display the date range - [29/Mar/2015:06:25:15 -0700]
            preg_match("/\[(.+?) .+?\]/", $line, $date);
            if ($date) {
                if (!$start) { $start = $date[1]; }
                $end = $date[1];
            }

            # count errors
            preg_match("/\"GET.+?\" (\d\d\d) /", $line, $matches);
            if ($matches && $matches[1] >= 400) {
                ++$errors;
                #inc($errors, $matches[1]);
            }

            # count pages only (not images and support files)
            preg_match("/GET (.+?(\/|\.html?|\.pdf|\.php)) /", $line, $matches);
            if ($matches) {
                $url = $matches[1];
                # cout the subpages
                preg_match("/$modmatch\/([^\/]+)/", $url, $sub);
                if ($sub) {
                    inc($stats, $sub[1]);
                    ++$total_pages;
                } else if (preg_match("/$modmatch\/$/", $url)) {
                    # if there was a hit with this directory as the
                    # trailing component, there was probably a page there
                    # so we count that too
                    inc($stats, "./");
                    ++$total_pages;
                } else {
                    ++$not_counted;
                }
            } else {
                ++$not_counted;
            }

        }

        # auto-descend into directories if there's only one item
        # XXX basically we redo the above over, one dir deeper each time,
        # until we reach a break condition (multiple choices, single page, or too deep,
        # which is pretty darn inefficient
        if (sizeof($stats) == 1) {
            # PHP 5.3 compat - can't index off a function, need a temp var
            $keys = array_keys($stats);
            # but not if the one thing is an html file
            if (preg_match("/(\/|\.html?|\.pdf|\.php)$/", $keys[0])) {
                break; 
            }
            # and not if it's too deep
            if ($nestcount > 5) {
                $out .= "<h1>ERROR descending nested directories</h1>\n";
                break;
            }
            $module .= "/" . $keys[0];
            $modmatch = preg_quote($module, "/");
            $dispmod = preg_replace("/\/modules\//", "", $module);
            $dispmod = preg_replace("/\/+/", "/", $dispmod);
        } else {
            break;
        }

    }

    # date & time formatting (we used to show time, but now we don't)
    $start = preg_replace("/\:.+/", " ", $start, 1);
    $end   = preg_replace("/\:.+/", " ", $end, 1);
    #$start = preg_replace("/\:/", " ", $start, 1);
    #$end   = preg_replace("/\:/", " ", $end, 1);
    #$start = preg_replace("/\:\d\d$/", "", $start, 1);
    #$end   = preg_replace("/\:\d\d$/", "", $end, 1);
    $start = preg_replace("/\//", " ", $start);
    $end   = preg_replace("/\//", " ", $end);
    $out .= "<b>$start</b> through <b>$end</b></p>\n";

    # tell the user the path they're in
    if ($dispmod) {
        $out .= "<h3 style='margin-bottom: 0;'>Looking In: $dispmod</h3>\n";
        $out .= "<a href='stats.php' style='font-size: small;'>&larr; back to all modules</a>";
    } else {
        $out .= "<h3 style='margin-bottom: 0;'>Looking At: all modules</h3>\n";
    }

    # stats display
    arsort($stats);
    $out .= "<table class=\"stats\">\n";
    $out .= "<tr><th>Hits</th><th>Content</th></tr>\n";
    foreach ($stats as $mod => $hits) {
        # html pages are links to the content
        if (preg_match("/(\/|\.html?|\.pdf|\.php)$/", $mod)) {
            $url = "$module/$mod";
            $out .= "<tr><td>$hits</td><td>$mod ";
            $out .= "<small>(<a href=\"$url\" target=\"_blank\">view</a>)</small></td></tr>\n";
        # directories link to a drill-down
        } else {
            $url = "stats.php?module=" . urlencode("$module/$mod");
            $out .= "<tr><td>$hits</td>";
            $out .= "<td><a href=\"$url\">$mod</a></td></tr>\n";
        }
    }
    $out .= "</table>\n";

    # timer readout
    $time = microtime(true) - $starttime;
    $out .= sprintf(
        "<p><b>$count lines analyzed in %.2f seconds.</b><br>\n", $time
    );
    $out .= "
        <span style='font-size: small;'>
        $total_pages content pages seen<br>
        $not_counted items not counted (images, css, js, admin, etc)<br>
        <!-- $errors errors -->
        </span></p>
    ";

    # download log links
    $out .= ('
        <ul>
        <li><a href="stats.php?dl_alog=1">Download Raw Access Log</a>
        <li><a href="stats.php?dl_elog=1">Download Raw Error Log</a>
        </ul>
    ');

    return $out;

}

#-------------------------------------------
# smart count incrementer XXX move to common.php
#-------------------------------------------
function inc(&$array, $key) {
    if (isset($array[$key])) {
        ++$array[$key];
    } else {
        $array[$key] = 1;
    }
}

#-------------------------------------------
# smart file tail (grabbed online)
#-------------------------------------------
function tail($filename, $lines = 10, $buffer = 4096) {

    # Open the file
    $f = fopen($filename, "rb");

    # Jump to last character
    fseek($f, -1, SEEK_END);

    # Read it and adjust line number if necessary
    # (Otherwise the result would be wrong if file
    # doesn't end with a blank line)
    if(fread($f, 1) != "\n") $lines -= 1;

    # Start reading
    $contents = '';
    $chunk = '';

    # While we would like more
    while(ftell($f) > 0 && $lines >= 0) {
        # Figure out how far back we should jump
        $seek = min(ftell($f), $buffer);

        # Do the jump (backwards, relative to where we are)
        fseek($f, -$seek, SEEK_CUR);

        # Read a chunk and prepend it to our contents
        $contents = ($chunk = fread($f, $seek)).$contents;

        # Jump back to where we started reading
        fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);

        # Decrease our line counter
        $lines -= substr_count($chunk, "\n");
    }

    # While we have too many lines
    # (Because of buffer size we might have read too many)
    while($lines++ < 0) {
        # Find first newline and remove all text before that
        $contents = substr($contents, strpos($contents, "\n") + 1);
    }

    # Close file and return
    fclose($f); 
    return $contents; 

}

$page_title = $lang['stats'];
$page_script = "";
$page_nav = "stats";

function output_and_exit($output) {
    # a bit sloppy, this...
    global $lang;
    $page_title = $lang['stats'];
    $page_nav = "stats";
    $page_script = "";
    include "head.php";
    echo $output;
    include "foot.php";
    exit;
}

?>
