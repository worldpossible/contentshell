<?php

#-------------------------------------------
# configuration
#-------------------------------------------
$maxlines = 10000;
$rpi_logpath   = "/var/log/apache2/access.log";
$uwamp_logpath = "../bin/apache/logs/access.log";
# XXX rachel plus log path?

#-------------------------------------------
# determine correct path
#-------------------------------------------
if (is_readable($rpi_logpath)) {
    $alog = $rpi_logpath;
} else if (is_readable($uwamp_logpath)) {
    $alog = $uwamp_logpath;
}

#-------------------------------------------
# get page content
#-------------------------------------------
if (isset($alog)) { # if we can read the access log

    $elog = preg_replace("/access.log/", "error.log", $alog);

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
            $output = "<h1>Couldn't Read Error Log</h1>";
        }
    } else {
        # down here is the normal case, drawing the stats 
        $output = draw_stats();
    }

} else {
    $output = "<h1>Couldn't Read Access Log</h1>\n";
    $output .= "Try running the following from the command line:\n";
    $output .= "<pre>chmod 777 /var/log/apache2\n";
    $output .= "chmod 666 /var/log/apache2/access.log\n";
    $output .= "chmod 666 /var/log/apache2/error.log\n</pre>";
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
        $out .= "<h1><a href=\"stats.php\">RACHEL Stats</a></h1>\n";
        $dispmod = preg_replace("/\/modules\//", "", $module);
    } else {
        if (file_exists("./modules")) {
            $module = "/modules";
        } else {
            $module = "/";
        }
        $out .= "<h1>RACHEL Stats</h1>\n";
        $dispmod = "";
    }
    $modmatch = preg_quote($module, "/");


    # read in the log file
    $content = tail($alog, $maxlines);
    
    $nestcount = 0;
    while (1) {

        ++$nestcount;

        $count = 0;
        $errors = array();
        $stats = array();
        $start = "";
        foreach(preg_split("/((\r?\n)|(\r\n?))/", $content) as $line){

            # line count and limiting
            if ($maxlines && $count >= $maxlines) { break; }
            ++$count;

            # dates - [29/Mar/2015:06:25:15 -0700]
            preg_match("/\[(.+?) .+?\]/", $line, $date);
            if ($date) {
                if (!$start) {
                    $start = $date[1];
                }
                $end = $date[1];
            }

            # count errors
            preg_match("/\"GET.+?\" (\d\d\d) /", $line, $matches);
            if ($matches && $matches[1] >= 400) {
                inc($errors, $matches[1]);
            }

            # break out html only
            preg_match("/GET (.+?\.(html?|pdf|php)?) /", $line, $matches);
            if ($matches) {
                $url = $matches[1];
                preg_match("/$modmatch\/([^\/]+)/", $url, $sub);
                if ($sub) {
                    inc($stats, $sub[1]);
                }
            } else {
                inc($stats, "not counted (.jpg, .js, support files, etc.)");
            }

        }
        
        # auto-descend into directories if there's only one item
        if (sizeof($stats) == 1) {
            # but not if the one thing is an html file
            if (preg_match("/\.(html?|pdf|php)?/", array_keys($stats)[0])) {
                break; 
            }
            if (preg_match("/^not counted/", array_keys($stats)[0])) {
                break; 
            }
            # and not if it's too deep
            if ($nestcount > 5) {
                $out .= "<h1>ERROR descending nested directories</h1>\n";
                break;
            }
            $module .= "/" . array_keys($stats)[0];
            $modmatch = preg_quote($module, "/");
            $dispmod = preg_replace("/\/modules\//", "", $module);
            $dispmod = preg_replace("/\/+/", "/", $dispmod);
        } else {
            break;
        }

    }

    # tell the user the path they're in
    if ($dispmod) {
        $out .= "<h2>$dispmod</h2>\n";
    }

    # date & time formatting
    $start = preg_replace("/\:/", " ", $start, 1);
    $end   = preg_replace("/\:/", " ", $end, 1);
    $start = preg_replace("/\:\d\d$/", "", $start, 1);
    $end   = preg_replace("/\:\d\d$/", "", $end, 1);
    $start = preg_replace("/\//", " ", $start);
    $end   = preg_replace("/\//", " ", $end);
    $out .= "<b>$start</b> <small>through</small> <b>$end</b>\n";

    # stats display
    arsort($stats);
    $out .= "<table>\n";
    $out .= "<tr><th>Hits</th><th>Content</th></tr>\n";
    foreach ($stats as $mod => $hits) {
        # html pages are links to the content
        if (preg_match("/\.(html?|pdf|php)?$/", $mod)) {
            $url = "$module/$mod";
            $out .= "<tr><td>$hits</td><td>$mod ";
            $out .= "<small>(<a href=\"$url\">view</a>)</small></td></tr>\n";
        } else if (preg_match("/^not counted/", $mod)) {
            $out .= "<tr><td>$hits</td><td>$mod</td></tr>\n";
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
        "<p><b>$count lines analyzed in %.2f seconds.</b></p>\n", $time
    );

    # download log links
    $out .= (
        "<ul>\n" .
        "<li><a href=\"stats.php?dl_alog=1\">Download Access Log</a>" .
        "<li><a href=\"stats.php?dl_elog=1\">Download Error Log</a>" .
        "</ul>\n"
    );

    return $out;

}

#-------------------------------------------
# smart count incrementer
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

?>
<!DOCTYPE html">
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>RACHEL - STATS</title>
    <style>
        body { background-color: #ffc; font-family: sans-serif; }
        h1 { margin-bottom: 10px; }
        table { border-collapse: collapse; margin-top: 20px;}
        td { border: 1px solid #cc9; padding: 5px; }
    </style>
</head>
<body>

<?php echo $output ?>


</body>
</html>

