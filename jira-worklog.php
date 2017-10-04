<?php

include 'JiraWorklog.php';

// TODO: support cmd-line and http request variables 
// 
// http://php.net/manual/en/function.getopt.php
// Script example.php
// f:   required value
// p::  optional value
// abc  these options do not accept values
//$options = getopt("f:hp:");
//var_dump($options);

// http://php.net/manual/en/reserved.variables.request.php
// $_REQUEST — HTTP Request variables, An associative array that by default contains the contents of $_GET, $_POST and $_COOKIE.



#
# Get timespent report 
# 
$me = __FILE__;
$usage = <<<USAGEOF
php $me <fromDate> [toDate] [jira-key]

jira-key - if exists, adds comment containing worklog summary.  

Examples:

php $me '2017-1' '2017-3'      # all of Q1
php $me '2017-1-1' '2017-1-1'  # just new years day
php $me `date -d "7 days ago" "+%Y-%m-%d"` `date -d "yesterday" "+%Y-%m-%d"` # last 7 days on GNU, Linux
php $me `date -d "last month" "+%Y-%m"` `date -d "last month" "+%Y-%m"`      # last month on GNU, Linux
php $me `date -j -v-7d "+%Y-%m-%d"` `date -j -v-1d "+%Y-%m-%d"`   # last 7 days on OSX, BSD
php $me `date -j -v-1m "+%Y-%m"` `date -j -v-1m "+%Y-%m"` 'CN-95' # last month on OSX, BSD


USAGEOF;
if (!isset($argv[1])) {
    echo $usage;
    exit;
}
$fromDateInput = $argv[1];
@$toDateInput = isset($argv[2]) ? $argv[2] : 'now()';
@$jiraKey = $argv[3];

// Normalize input dates
if (1 === preg_match("/(\d\d\d\d-)(\d\d?)$/", $toDateInput)) {
    // handle special case of month to be date on end of month
    $toDateInput  = date('Y-m-t', strtotime($toDateInput)); 
}

$fromTime = strtotime($fromDateInput);
$toTime   = strtotime($toDateInput) + 60*60*24 -1; // end 1 second before midnight on given date

$cfg = [
    'apiBaseUrl' => 'https://jira.example.org',
    'apiCredentials' => 'rest-api:sweeeetPassword'
];
$jw = new JiraWorklog($cfg);
echo $jw->getJiraIssues($fromTime, $toTime)->outputTxt();

if ($jiraKey) {
    $jw->postComment($jiraKey);
}
