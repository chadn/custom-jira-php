<?php

include 'JiraWorklog.php';

$cfgFile = './jira-config.php';

// Determine if command-line-interface or web interface
$isCli = php_sapi_name() == 'cli'; 

$url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]";
$me = $isCli ? "php " . $argv[0] : $url;
$outfmt = $isCli ? 'txt' : 'html';

$usage = <<<USAGEOF

$me [options]

  -f str  from date string, anything that can be parsed by strtotime(). REQUIRED.
  -t str  to date string, anything that can be parsed by strtotime(). Optional, default is 'today'.
  -o out  output format, specify one of: txt, json, html.  Optional, default is $outfmt.
  -k key  jira issue key, will add comment containing worklog summary.  Optional.
  -c fn   config file, where you store apiCredentials. Optional, default is $cfgFile
  -v      verbose output, including basic timing info with API.
  -d      debug output - warning - this contains a ton of information.
  -h      show this help and exit.

Examples:

USAGEOF;
$usageCLI = <<<USAGE1CLI

$me -f='-7 days'                 # the last 7 days
$me -f='-7 days'  -k='CN-12'     # the last 7 days, and post comment to CN-12
$me -f='2017-1-1' -t='2017-1-1'  # just new years day 2017
$me -f='2017-1'   -t='2017-3'    # Q1 of 2017

USAGE1CLI;
$usageWeb = <<<USAGE2WEB

<a href="$me?f=-7+days"               >$me?f=-7+days</a>                 # the last 7 days
<a href="$me?f=-7+days&k=CN-12"       >$me?f=-7+days&k=CN-12</a>         # the last 7 days, and post comment to CN-12
<a href="$me?f=2017-1-1&t=2017-1-1"   >$me?f=2017-1-1&t=2017-1-1</a>     # just new years day 2017
<a href="$me?f=2017-1&t=2017-3&o=json">$me?f=2017-1&t=2017-3&o=json</a>  # Q1 of 2017, output in JSON

USAGE2WEB;

$usage .= ($isCli ? $usageCLI : $usageWeb) ."\n";
$usage = $isCli ? $usage : "<pre>". $usage . "</pre>";


if ($isCli) {
    // : required,  :: optional
    $options = getopt("f:t::o::k::c::hvd");

} else {
    // allow options from  $_GET, $_POST and $_COOKIE
    $options = &$_REQUEST;
}
if (isset($options[h]) || !array_key_exists('f', $options)) {
    echo $usage;
    exit;
}

$fromDateInput = $options['f'];
$toDateInput   = $options['t'] ?: 'today';  // ternary shorthand
$jiraKey       = $options['k'] ?: '';
$outfmt        = $options['o'] ?: $outfmt;
$cfgFile       = $options['c'] ?: $cfgFile;

$cfg = loadcfg($cfgFile);

if (isset($options[v])) {
  $cfg['echoTiming'] = true;
}
if (isset($options[d])) {
  $cfg['debug'] = true;
}

// Normalize input dates
if (1 === preg_match("/(\d\d\d\d-)(\d\d?)$/", $toDateInput)) {
    // handle special case of month to be date on end of month
    $toDateInput  = date('Y-m-t', strtotime($toDateInput)); 
}

$jw = new JiraWorklog($cfg);
$jw->getJiraIssues($fromDateInput, $toDateInput);
echo $jw->getOutput($outfmt);

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
