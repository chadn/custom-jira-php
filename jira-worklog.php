<?php
require_once 'src/JiraApi.php';
require_once 'src/JiraWorklog.php';

//use Norhaus\JiraApi;
use Norhaus\JiraWorklog;

$cfgFile = './jira-config.php';

// Determine if command-line-interface or web interface
$isCli = php_sapi_name() == 'cli'; 

if ($isCli) {
  $me = "php " . $argv[0];
} else {
  $me = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]";
}
$outfmt = $isCli ? 'txt' : 'html';

$usage = <<<USAGEOF

$me [options]

  -f str  from date string, anything that can be parsed by strtotime(). REQUIRED.
  -t str  to date string, anything that can be parsed by strtotime(). Optional, default is 'now'.
  -u usrs filter worklogs to only include ones matching jira users. Comma separate usernames in usrs.
  -o out  output format, specify one of: txt, json, csv, html.  Optional, default is $outfmt.
  -k key  jira issue key, will add comment containing worklog summary.  Optional.
  -c fn   config file, where you store apiCredentials. Optional, default is $cfgFile
  -j jql  encoded jql to further limit results (beyond dates and users). Should not have "Order by".
  -w      instead of giving daily totals, give totals per week.
  -v      verbose output, including basic timing info with API, written to STDERR.
  -d      debug output - warning - this contains a ton of information, written to STDERR.
  -h      show this help and exit.

Examples:

USAGEOF;
$usageCLI = <<<USAGE1CLI

$me -f='-7 days'                  # summarize worklogs over the last 7 days
$me -f='-7 days' -u=chad,jo       # the last 7 days, only users chad and jo
$me -f='-7 days' -k=CN-12         # the last 7 days, and post comment to CN-12
$me -f='-7 days' -o=json          # the last 7 days, output in json 
$me -f='-7 days' -o=csv           # the last 7 days, output in csv (open with excel) 
$me -f=2017-1-1  -j=labels%3Dfun  # last 7 days, jql: labels=fun
$me -f=2017-1-1  -t=2017-1-1      # just new years day 2017
$me -f=2017-1    -t=2017-3        # Q1 of 2017
$me -f=2017-1    -t=2017-3  -w    # Q1 of 2017, weekly byDate summary

USAGE1CLI;
$usageWeb = <<<USAGE2WEB

<a href="$me?f=-7+days"            >$me?f=-7+days</a>                # summarize worklogs over the last 7 days
<a href="$me?f=-7+days&u=chad,jo"  >$me?f=-7+days&u=chad,jo</a>      # the last 7 days, only users chad and jo
<a href="$me?f=-7+days&k=CN-12"    >$me?f=-7+days&k=CN-12</a>        # the last 7 days, and post comment to CN-12
<a href="$me?f=-7+days&o=json"     >$me?f=-7+days&o=json</a>         # the last 7 days, output in json
<a href="$me?f=-7+days&o=csv"      >$me?f=-7+days&o=csv</a>          # the last 7 days, output in csv (open with excel) 
<a href="$me?f=-7+days&j=labels%3Dfun">$me?f=-7+days&j=labels%3Dfun</a> # last 7 days, jql: labels=fun
<a href="$me?f=2017-1-1&t=2017-1-1">$me?f=2017-1-1&t=2017-1-1</a>    # just new years day 2017
<a href="$me?f=2017-1&t=2017-3"    >$me?f=2017-1&t=2017-3</a>        # Q1 of 2017
<a href="$me?f=2017-1&t=2017-3&w=1">$me?f=2017-1&t=2017-3&w=1</a>    # Q1 of 2017, weekly byDate summary

USAGE2WEB;

$usage .= ($isCli ? $usageCLI : $usageWeb) ."\n";
$usage = $isCli ? $usage : "<pre>". $usage . "</pre>";


if ($isCli) {
    // : required
    // :: optional
    $options = getopt("ac::df:hj::k::o::t::u::vw");

} else {
    // allow options from  $_GET, $_POST and $_COOKIE
    $options = &$_REQUEST;
}
if (@isset($options[h]) || (!(array_key_exists('f', $options) || @isset($options[a])))) {
    echo $usage;
    exit;
}

$fromDateInput = @$options['f'];
$toDateInput   = @$options['t'] ?: 'now';  // ternary shorthand
$jiraKey       = @$options['k'] ?: '';
$usernames     = @$options['u'] ?: '';
$outfmt        = @$options['o'] ?: $outfmt;
$cfgFile       = @$options['c'] ?: $cfgFile;
$jql           = @urldecode($options['j']) ?: '';

$cfg = loadcfg($cfgFile);

if (@isset($options[v])) {
  $cfg['echoTiming'] = true;
}
if (@isset($options[d])) {
  $cfg['debug'] = true;
  $cfg['debugCache'] = true;
}
if (@isset($options[w])) {
  $cfg['byDateFmt'] = 'o \W\e\e\k W';
}


// Normalize input dates
if (1 === preg_match("/(\d\d\d\d-)(\d\d?)$/", $toDateInput)) {
    // handle special case of month to be date on end of month
    $toDateInput  = date('Y-m-t', strtotime($toDateInput)); 
}

if ('csv' === $outfmt) {
  $cfg['asOfDateFmt'] = 'Y-m-d H:i D T';
}

$jw = new JiraWorklog($cfg);

if (@isset($options['a'])) {
    // Simple way to test using cached JSON from Jira
    // Can change authors and output type from cmd-line. 
    // Cannot change jql at all (must be blank), must match cmd in providerApiCallCache()
    // Cannot change from, to dates - but can change time, ex: -f='2017-10-03T22:00:00-05:00'
    $dataProvider = providerApiCallCache();
    foreach ($dataProvider as $val) {
        $cmd = $val[0];
        $expectedResult =  $val[1];
        $jw->localJiraCache[ $cmd ] = $expectedResult;
    }
    $jql = '';
    if ('2017-10-03' != date('Y-m-d', strtotime($fromDateInput))) {
      $fromDateInput = '2017-10-03';
    }
    if ('2017-10-05' != date('Y-m-d', strtotime($toDateInput))) {
      $toDateInput = '2017-10-05';
    }
    fwrite(STDERR, "\nDETECTED -a USING CACHED JSON\n\n");
}
$jw->getJiraIssues($fromDateInput, $toDateInput, $usernames, $jql);
$outputStr = $jw->getOutput($outfmt);

if (!$isCli && 'json' === $outfmt) {
    header('Content-Type: application/json');
}
if (!$isCli && 'csv' === $outfmt) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=worklogs-'.date('U').'.csv');
    header("Content-Length: ". strlen($outputStr));
}
echo $outputStr;
if ($jiraKey) {
    $jw->postComment($jiraKey);
}



function loadcfg($cfgFile) {
  global $usage;

  if (!is_readable($cfgFile)) {
      $cfgFile = getPathToScript() . $cfgFile;
      if (!is_readable($cfgFile)) {
          echo "\nERROR: Can not read config file: $cfgFile\n\n";
          echo $usage;
          exit;
      }
  }
  $cfg = include $cfgFile;
  return $cfg;
}


function getPathToScript() {
    $path = $_SERVER["PHP_SELF"]; 
    if ('/' != substr($path, 0, 1)) {
        # relative path, add pwd
        $path = $_SERVER['PWD'] ."/". $path;
    }
    $path = preg_replace('/[^\/]+$/', '', $path);
    return $path;
}


function providerApiCallCache()
{
    $cmd = 'search?maxResults=999&jql=worklogDate%3E%3D2017-10-03+AND+worklogDate%3C%3D2017-10-05+ORDER+BY+key+ASC';
    $expectedResult = file_get_contents(__DIR__ . "/tests/data/CN-search.json");
    //$expectedResult = ' .. contents of '. __DIR__ . "/search.json";
    $ret = array(
        [$cmd, $expectedResult]
    );
    foreach (['CN-117','CN-130','CN-133','CN-146'] as $val) {
        $cmd = "issue/$val/worklog";
        $expected = file_get_contents(__DIR__ . "/tests/data/$val-worklog.json");
        $ret[] = [$cmd, $expected];
    }
    return $ret;
}

