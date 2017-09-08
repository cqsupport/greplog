#!/usr/bin/php
<?php
$major_errors        = array();
$major_error_matches = array(
    "IOException: No space left on",
    "Too many open files",
    "Failed to read bundle: ",
    "IOException: File not found: ",
    "DataStoreException: Record not found",
    "DataStoreException: Could not length of dataIdentifier",
    "NoClassDefFoundError",
    "NoSuchMethod",
    "ClassNotFoundException",
    "Error occurred while obtaining InputStream for blobId",
    "OutOfMemoryError",
    " was already added in revision"
);
$narrative_matches   = array(
    "com\.day\.crx\.persistence\.tar\.OptimizeThread Scheduled optimization started at" => "Tar optimization started",
    "com\.day\.crx\.persistence\.tar\.index\.IndexSet Merging index files for" => "Tar Index Merging started",
    "\[FelixShutdown\] org\.apache\.felix\.framework BundleEvent STOPPING" => "AEM/CQ initializing shutdown",
    "\[FelixStartLevel\] org\.apache\.sling\.commons\.logservice BundleEvent STOPPING" => "AEM/CQ completed shutdown",
    "\[FelixStartLevel\] org\.apache\.sling\.installer\.core BundleEvent RESOLVED|resources registered with OsgiInstaller" => "AEM/CQ initializing startup",
    
    "JcrPackageDefinitionImpl unwrapping package" => "Installing package",
    "JcrPackageImpl Creating snapshot for" => "Creating package snapshot",
    "ZipVaultPackage Extracting " => "Package install completed",
    "org\.apache\.sling\.audit\.osgi\.installer Installed configuration" => "OSGi Configuration installed"
);
$narrative           = array();

function help()
{
    echo "Usage: php greplog.php -f <log-file-path> [options]
-f: log file path
-l: log level: This filters the log messages by log level.  Possible values are DEBUG, DEBUG-ONLY, INFO, INFO-ONLY, WARN, WARN-ONLY, ERROR.  Default value is ERROR.
-s: search string: Filter log messages by string.
-x: exclude regular expression: Exclude log messages by regular expression. Example value \"/Exclude 1|Exclude 2/\"
-r: search regular expression: Filter log messages by regular expression. Example value \"/Include 1|Include 2/\"
-n: show line numbers
-H: show filename
-b: beginning time: This filters log messages by only including messages that come after the time specified.  Example value \"01.03.2011 15:09:51\".  The format is dd.mm.yyyy hh.mm.ss
-e: end time: This filters log messages by only including messages that come before the time specified.  Example value \"01.03.2011 15:09:51\". The format is dd.mm.yyyy hh.mm.ss
\n";
    exit(0);
}

function get_level_regex($level)
{
    $levelstr = "ERROR";
    $level    = strtolower($level);
    if ($level == "trace") {
        $levelstr = "(TRACE|DEBUG|INFO|WARN|ERROR)";
    } else if ($level == "debug") {
        $levelstr = "(DEBUG|INFO|WARN|ERROR)";
    } else if ($level == "debug-only") {
        $levelstr = "DEBUG";
    } else if ($level == "info") {
        $levelstr = "(INFO|WARN|ERROR)";
    } else if ($level == "info-only") {
        $levelstr = "INFO";
    } else if ($level == "warn") {
        $levelstr = "(WARN|ERROR)";
    } else if ($level == "warn-only") {
        $levelstr = "WARN";
    }
    return $levelstr;
}
function checkMajorErrors($linenum, $logline, $line, $filename)
{
    global $major_errors, $major_error_matches;
    foreach ($major_error_matches as $needle) {
        if (!array_key_exists($needle, $major_errors) && strstr($line, $needle)) {
            $major_errors[$needle] = array(
                $filename,
                $linenum,
                $logline,
                $line
            );
            unset($major_error_matches[$needle]);
        }
    }
}

function checkNarrative($linenum, $logline, $line, $filename)
{
    global $narrative, $narrative_matches;
    foreach ($narrative_matches as $needle => $message) {
        if (preg_match("/" . $needle . "/", $logline)) {
            if ($logline == $line) {
                array_push($narrative, array(
                    $message,
                    $filename,
                    $linenum,
                    $logline
                ));
            } else {
                array_push($narrative, array(
                    $message,
                    $filename,
                    $linenum,
                    $logline,
                    $line
                ));
            }
        }
    }
}

function search_in_file($filename, $level, $search, $excludesearch, $regex, $show_linenum, $show_filename, $start_time, $end_time, $lines_before, $lines_after)
{
    //Open the file
    $fp         = fopen($filename, 'r');
    $linenum    = 0;
    $logmsgline = 0;
    $stacklines = 0;
    $canread    = (($line = fgets($fp)) !== false);
    $linenum++;
    $is_match = true;
    while ($dontread || $canread) {
        $strlogmsg = "";
        $logline   = "";
        $sub       = substr($line, 0, 300);
        checkNarrative($linenum, $line, $sub, $filename);
        //if(!preg_match("/^(?:[a-zA-Z0-9.\-_]+:)?(?:\d+:)?(\d\d\.\d\d\.\d\d\d\d \d\d:\d\d:\d\d(\.\d\d\d)?) \*" . get_level_regex($level) . "\*/", $sub, $matches)) echo "No match " . $sub;
        $logmsgline = $linenum;
        if (preg_match("/^(?:[a-zA-Z0-9.\-_]+:)?(?:\d+:)?(\d\d\.\d\d\.\d\d\d\d \d\d:\d\d:\d\d(\.\d\d\d)?) \*" . get_level_regex($level) . "\*/", $sub, $matches)) {
            $time       = strtotime($matches[1]);
            $strlogmsg  = $line;
            $logline    = $line;
            checkMajorErrors($linenum, $logline, $line, $filename);
            $line = fgets($fp);
            $linenum++;
            $dontread = true;
            $sub = substr($line, 0, 300);
            checkNarrative($linenum, $logline, $line, $filename);
            while ($line !== false && !preg_match("/^(?:[a-zA-Z0-9.\-_]+:)?(?:\d+:)?\d\d\.\d\d\.\d\d\d\d \d\d:\d\d:\d\d(?:\.\d\d\d)? \*/", $sub)) {
                checkMajorErrors($linenum, $logline, $sub, $filename);
                if (strlen($line) > 5000) {
                    //skip
                    $strlogmsg .= "...<line too long>\n";
                } else {
                    $strlogmsg .= $line;
                }
                $line = fgets($fp);
                $linenum++;
                $sub = substr($line, 0, 300);
                checkNarrative($linenum, $logline, $sub, $filename);
            }
            //since we read the next line while looking for the stack trace, set a dontread flag
            // to tell the loop not to read to the next line.
            $is_match = true;
            if ($search && !strstr($strlogmsg, $search)) {
                $is_match = false;
            }
            if ($regex && !preg_match($regex, $strlogmsg)) {
                $is_match = false;
            }
            if ($excludesearch && preg_match($excludesearch, $strlogmsg)) {
                $is_match = false;
            }
            if (($start_time && $time < $start_time) || ($end_time && $time > $end_time)) {
                $is_match = false;
            }
            if ($is_match) {
                echo (($show_linenum) ? $logmsgline . ":" : "") . (($show_filename) ? $filename . ":" : "") . "$strlogmsg";
            }
        }
        if (!$dontread) {
            $canread = (($line = fgets($fp)) !== false);
            $linenum++;
        }
        $dontread = false;
    }
    fclose($fp);
}

date_default_timezone_set('America/Los_Angeles');
$options = getopt("f:h:l:s:x:r:b:e:A:B:nH");
$file    = $options["f"];

if ($file == null) {
    echo "-f log file path parameter must be defined\n";
    help();
}
//print_r($options);

//echo "Searching file: $file...\n";
if ($options["b"]) {
    $start_time = strtotime($options["b"]); //example format "01.03.2011 15:09:51"
    //echo "Beginning time: " . $options["b"] . "\n";
}
if ($options["e"]) {
    $end_time = strtotime($options["e"]); //"03.03.2011 03:46:19"
    //echo "Ending time: " . $options["e"] . "\n";
}
foreach (glob($file) as $filepath) {
    search_in_file($filepath, $options["l"], $options["s"], $options["x"], $options["r"], array_key_exists("n", $options), array_key_exists("H", $options), $start_time, $end_time, $options["A"], $options["B"]);
}
if (!empty($major_errors)) {
    print "\n\n MAJOR ERROR REPORT (note that this report only shows the first instance of the error):";
    print_r($major_errors);
}
if (!empty($narrative)) {
    print "\n\n LOG NARRATIVE:";
    print_r($narrative);
}
?>
