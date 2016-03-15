# greplog
Script for grepping the CQ5.x and AEM6.x error log files.


```
Usage: php greplog.php -f <log-file-path> [options]
-f: log file path
-l: log level: This filters the log messages by log level.  Possible values are DEBUG, DEBUG-ONLY, INFO, INFO-ONLY, WARN, WARN-ONLY, ERROR.  Default value is ERROR.
-s: search string: Filter log messages by string.
-x: exclude regular expression: Exclude log messages by regular expression. Example value "/Exclude 1|Exclude 2/"
-r: search regular expression: Filter log messages by regular expression. Example value "/Include 1|Include 2/"
-n: show line numbers
-H: show filename
-b: beginning time: This filters log messages by only including messages that come after the time specified.  Example value "01.03.2011 15:09:51".  The format is dd.mm.yyyy hh.mm.ss
-e: end time: This filters log messages by only including messages that come before the time specified.  Example value "01.03.2011 15:09:51". The format is dd.mm.yyyy hh.mm.ss
```

### Example
Search replication.log for all log messages that exclude the terms "reverse" and "publish5"
```
php greplog.php -f replication.log -l info -x "/reverse|publish5/"
```
