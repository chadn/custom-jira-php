<?php
namespace Norhaus;

use Norhaus\JiraApi;

class JiraWorklog extends JiraApi
{
    public $req;          // request object, used for output
    public $res;          // result object, used for output
    public $allAuthors;   // object, list of all authors within timeframe, key is authors
    private $fromEpoch;   // number of seconds since epoch
    private $toEpoch;     // number of seconds since epoch
    private $total;       // array of totals
    private $byDate;      // array of daily totals - change to byDate
    private $byAuthor;    // array of daily totals - change to byDate
    private $byWorklogs;  // array of worklog entries
    private $byIssues;    // array of jira issues entries


    /**
     * configuration options that will overwrite and merge with parent JiraApi $config
     *
     * @var array
     */
    private $jwConfig = [
        //'skipEmptyWorklogs' => true,  // if true, json output will only include days with worklogs
        'byDateFmt'        => 'D Y-m-d', // How worklogs are split up. ex: Fri 2017-08-11.  Aggregates based on worklog start time.  Date format can be day, hour, week, month ..
        'outputDateFmt'     => 'Y-m-d D', // used in Total Time logged summary, from DATE to DATE
        'asOfDateFmt'      => 'Y-m-d D g:ia T', // used in Total Time logged summary
        'jsonIssueFields' => [  // fields used for json output, under res->issues
            'summary',          // issue summary, exactly like it is in jira
            'timespentPretty'   // Computed human-friendly short string of hours or minutes.  example: 15m
            ],
        'loggedPerIssueFields' => [ // order of fields used for one line summary under "Total logged per issue"
            'timespentPretty',      // same as in jsonIssueFields
            'key',                  // issue key
            'summary',              // same as in jsonIssueFields, must exist there to work here
            'dailySummary'          // computed. example: (15m 2/4, 2h 2/5, 5.5h 2/7)
            ]
    ];

    /**
     * Can pass any config options from jwConfig or JiraApi's config
     *
     * @param  array   $cfg array of options
     * @return JiraApi $this (chainable)
     */
    public function __construct($cfg=[])
    {
        $this->setConfig($this->jwConfig);
        parent::__construct($cfg);

        if ($this->config['debug']) {
            //var_dump($this->config);
        }
        return $this;
    }


    /**
     * Retrieve all Jira Issues that may have worklogs between dates passed, parse,
     * and store all matching in $worklogs
     *
     * @param  string|int  $fromDateInput look at jira issues after this date
     * @param  string|int  $toDateInput   look at jira issues before this date
     * @param  string      $usernames comma separated list of 1 or more usernames
     * @param  string      $jql       unencoded jql to further refine which issues will be checked
     * @return JiraWorklog $this (chainable)
     */
    public function getJiraIssues($fromDateInput, $toDateInput, $usernames='', $jql='')
    {
        if ("string" === gettype($fromDateInput)) {
            $this->fromEpoch = strtotime($fromDateInput);
        } elseif ("integer" === gettype($fromDateInput)) {
            $this->fromEpoch = $fromDateInput;
        } else {
            throw new \Exception("invalid type for fromDateInput, must be string or integer");
        }

        if ("string" === gettype($toDateInput)) {
            $this->toEpoch = strtotime($toDateInput);
        } elseif ("integer" === gettype($toDateInput)) {
            $this->toEpoch = $toDateInput;
        } else {
            throw new \Exception("invalid type for fromDateInput, must be string or integer");
        }

        if ($usernames) {
            $this->req['usernames'] = explode(',', $usernames);
        } else {
            $this->req['usernames'] = [];
        }
        if ($jql) {
            if (0===preg_match('/^\s*AND/i', $jql)) {
                $jql = " AND ($jql)";
            } else {
                $jql = " ($jql)";
            }
        }

        $this->byWorklogs = [];
        $this->allAuthors = [];
        $fromDateJQL = date('Y-m-d', $this->fromEpoch);
        $toDateJQL   = date('Y-m-d', $this->toEpoch);
        $this->req['jql'] = "worklogDate>=$fromDateJQL AND worklogDate<=$toDateJQL$jql ORDER BY key ASC";
        $this->req['fromDateInput'] = $fromDateInput;
        $this->req['toDateInput']   = $toDateInput;
        $this->req['fromEpoch'] = $this->fromEpoch;
        $this->req['toEpoch']   = $this->toEpoch;

        // Input validated, Now get issue keys from Jira
        // 
        $apiResponse = $this->apiCall('search?maxResults=999&jql=' . urlencode($this->req['jql']), 'GET');

        if (!$apiResponse) {
            throw new \Exception("bad response from API server");
        }
        $this->jiraIssues = json_decode($apiResponse, true);

        // get list of jira issue keys, summary, and other fields for output
        $this->byIssues = $this->getFlattenedIssues($this->jiraIssues, $this->config['jsonIssueFields']);

        // Now get worklogs for each issueKey
        foreach ($this->byIssues as $key => $tkt) {
            $this->parseJiraWorklogs($key, $this->fromEpoch, $this->toEpoch, $this->req['usernames']);
        }
        $this->prepOutput();
        //$this->saveIt('results', $this->res);

        return $this;
    }



    /**
     * Fetches worklogs from Jira given $key, returns worklogs within date and matching authors
     *
     * @param  string   $key        issue key
     * @param  integer  $fromEpoch  starting from this date, in seconds since epoch
     * @param  integer  $toEpoch    ending at this date, in seconds since epoch
     * @param  string   $author     if not empty, only count worklogs made by this author
     * @return array    $worklogs   list of worklogs that match times and authors.
     */
    public function parseJiraWorklogs($key, $fromEpoch, $toEpoch, $authors=[])
    {
        $this->dbg("parseJiraWorklogs($key) fromEpoch=$fromEpoch toEpoch=$toEpoch ");

        # get the full worklog for that issue
        //$ret = json_decode(apiCall("issue/$key/worklog"), true);
        $ret = json_decode($this->apiCall("issue/$key/worklog", 'GET'), true);

        foreach ($ret['worklogs'] as $entry) {
            $startedEpoch = strtotime($entry['started']);
            $dbgStr = "worklog {$entry['id']} with started=$startedEpoch ". $entry['started'];

            if ($startedEpoch < $fromEpoch) {
                $this->dbg("Skipping (time, too early) $dbgStr\n");
                continue;           
            }
            if ($startedEpoch > $toEpoch) {
                $this->dbg("Skipping (time, too recent) $dbgStr\n");
                continue;           
            }
            if (!empty($authors)) {
                $match = false;
                foreach ($authors as $author) {
                    if ($author == $entry['author']['name']) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) {
                    $this->dbg("Skipping (authors!={$entry['author']['name']}) $dbgStr\n");
                    continue;
                }
            }
            $this->dbg("Using $dbgStr\n");

            $this->byWorklogs[] = [
                'issueKey'      => $key,
                //'issueId'     => $entry['issueId'],
                'author'        => $entry['author']['name'],
                'worklogId'     => $entry['id'],
                //'comment'     => $entry['comment'],
                'startTime'     => $startedEpoch,
                'timeSpentSecs' => $entry['timeSpentSeconds']
            ];
            $this->allAuthors[$entry['author']['name']] = 1;
        }
    }


    /**
     * Takes byWorklog data and copies and totals it for byDate, byIssues, byAuthor
     *
     */
    public function prepOutput()
    {
        if (!$this->jiraIssues) {
            throw new \Exception("must call getJiraIssues() first");
        }

        $this->byDate = [];
        $this->byAuthor = [];
        $this->total = ['timespentSecs' => 0];

        foreach ($this->byWorklogs as $worklog) {
            // 
            // Add worklog info to byDate, byIssues, byAuthor
            // 
            $dateStr = date($this->config['byDateFmt'], $worklog['startTime']);
            if (!array_key_exists($dateStr, $this->byDate)) {
                $this->byDate[$dateStr] = [
                    'totalSecs' => 0, 
                    'authors' => []
                ];
            }
            if (!array_key_exists($worklog['issueKey'], $this->byDate[$dateStr])) {
                $this->byDate[$dateStr][$worklog['issueKey']] = 0;
            }
            if (!array_key_exists($worklog['author'], $this->byDate[$dateStr]['authors'])) {
                $this->byDate[$dateStr]['authors'][$worklog['author']] = ['totalSecs' => 0];
            }
            if (!array_key_exists($worklog['issueKey'], $this->byDate[$dateStr]['authors'][$worklog['author']])) {
                $this->byDate[$dateStr]['authors'][$worklog['author']][$worklog['issueKey']] = 0;
            }
            if (!array_key_exists($worklog['author'], $this->byAuthor)) {
                $this->byAuthor[$worklog['author']] = ['totalSecs' => 0];
            }
            if (!array_key_exists($worklog['issueKey'], $this->byAuthor[$worklog['author']])) {
                $this->byAuthor[$worklog['author']][$worklog['issueKey']] = 0;
            }
            if (!array_key_exists('timeSpentSecs', $this->byIssues[$worklog['issueKey']])) {
                $this->byIssues[$worklog['issueKey']]['timeSpentSecs'] = 0;
            }
            // initializing done, now add time
            $secs = $worklog['timeSpentSecs'];
            $this->total['timespentSecs'] += $secs;
            $this->byDate[$dateStr]['totalSecs'] += $secs;
            $this->byDate[$dateStr][$worklog['issueKey']] += $secs;
            $this->byDate[$dateStr]['authors'][$worklog['author']]['totalSecs'] += $secs;
            $this->byDate[$dateStr]['authors'][$worklog['author']][$worklog['issueKey']] += $secs;
            $this->byAuthor[$worklog['author']]['totalSecs'] += $secs;
            $this->byAuthor[$worklog['author']][$worklog['issueKey']] += $secs;
            $this->byIssues[$worklog['issueKey']]['timeSpentSecs'] += $secs;
        }
        $this->total['timespentPretty'] = $this->roundit($this->total['timespentSecs']);
        foreach ($this->byIssues as $key => $value) {
            if (!array_key_exists('timeSpentSecs', $this->byIssues[$key])) {
                // remove jira issues that matched search but did not have worklogs by authors in timeframe
                unset($this->byIssues[$key]);
                continue;
            }
            $this->byIssues[$key]['timespentPretty'] = $this->roundit($this->byIssues[$key]['timeSpentSecs'], "%5s");
        }

        uksort($this->byDate, array('Norhaus\JiraWorklog', 'mySortByDateStr'));

        $this->res = [
            'dateComputed'      => date($this->config['asOfDateFmt']),
            'dateComputedEpoch' => time(),
            'total'             => $this->total,
            'byDate'            => $this->byDate,
            'byIssues'          => $this->byIssues,
            'byAuthor'          => $this->byAuthor,
            'byWorklogs'        => $this->byWorklogs
        ];
        if ($this->config['debug']) {
            $allAuthors = array_keys($this->allAuthors);
            sort($allAuthors);
            $this->dbg("all worklog authors: " . implode(",", $allAuthors) . "\n");
        }
    }



    /**
     * pretty Print Worklog Daily Total
     *
     * @return string summary of time spent per issue each day, one day per line
     */
    public function prettyPrintWorklogByDate()
    {
        $txtStr = "Daily Worklogs:\n";
        foreach ($this->byDate as $dateStr => $dt) {
            if (0 === $dt['totalSecs']) {
                continue;
            }
            $tmpA = [];
            foreach ($dt as $key => $secs) {
                if (0 == $secs || in_array($key, ['totalSecs','authors'])) {
                    continue;
                }
                $tmpA[] = $this->roundit($secs) . " $key";
            }
            $txtStr .= $this->roundit($dt['totalSecs'], "%4s") . " $dateStr -- ". implode(', ', $tmpA) ."\n";
        }
        return $txtStr;
    }

    /**
     * pretty Print Issue's time spent on each day,
     *
     * @param  string $key  issue key
     * @return array  summary of time spent per issue each day, one day per array value
     */
    public function prettyPrintIssueDaily($key)
    {
        $ret = [];
        foreach ($this->byDate as $dateStr => $dt) {
            if (!array_key_exists($key, $dt)) {
                continue;
            }
            $ret[] = $this->roundit($dt[$key]) . date(' n/j', strtotime($dateStr));
        }
        return $ret;
    }



    /**
     * pretty Print Worklog Issues
     *
     * @return string summary of time spent per Jira issue, one issue per line
     */
    public function prettyPrintWorklogIssues()
    {
        $txtStr = "Total logged per issue:\n";
        foreach ($this->res['byIssues'] as $key => $tkt) {
            $txtFlds = [];
            foreach ($this->config['loggedPerIssueFields'] as $f) {
                if ('dailySummary' == $f) {
                    $txtFlds[] = "(" . implode(', ', $this->prettyPrintIssueDaily($key)) . ")";
                } elseif ('key' == $f) {
                    $txtFlds[] = $key;
                } elseif (array_key_exists($f, $tkt)) {
                    $txtFlds[] = $tkt[$f];
                } else {
                    throw new \Exception("unknown field ($f) in config['loggedPerIssueFields']");
                }
            }
            $txtStr .= implode(' ', $txtFlds) . "\n";
        }
        return $txtStr;
    }


    /**
     * pretty Print Worklog Summary
     *
     * @return string summary of total time spent, including start and end time
     */
    public function prettyPrintWorklogSummary()
    {
        $fromDateOutput = date($this->config['outputDateFmt'], $this->fromEpoch);
        $toDateOutput   = date($this->config['outputDateFmt'], $this->toEpoch);

        $txtStr = $this->res['total']['timespentPretty'] . " Total Time logged, ";
        $txtStr .= "from $fromDateOutput to $toDateOutput\n";
        if ($this->req['usernames']) {
            $just1 = 1 === count($this->req['usernames']);
            $txtStr .= "only by ". ($just1 ? 'this user: ' : 'these users: ');
            $txtStr .= implode(', ', $this->req['usernames']) ."\n";
        }
        $txtStr .= " as of ". $this->res['dateComputed'] ."\n";
        return $txtStr;
    }


    /**
     * Returns string formatted as fmt from most recent worklog request.
     *
     * @param  string $fmt output format, one of 'txt', 'html', 'json', 'jira'
     * @return string
     */
    public function getOutput($fmt)
    {
        switch ($fmt) {
            case 'json':  return $this->outputJson();
            case 'html':  return $this->outputHtml();
            case 'jira':  return $this->outputJiraComment();
            case 'txt':   return $this->outputTxt();
            default:      return $this->outputTxt();
        }
    }


    /**
     * Returns string formatted as json from most recent worklog request.
     *
     * @return string
     */
    public function outputJson()
    {
        $ret = [
            'req' => $this->req,
            'res' => $this->res
        ];
        return $this->jsonPrettyPrint($ret);
    }


    /**
     * Returns string formatted as text from most recent worklog request.
     *
     * @return string
     */
    public function outputTxt()
    {
        $output = "\n". $this->prettyPrintWorklogSummary() . "\n";
        $output .= $this->prettyPrintWorklogIssues() . "\n";
        $output .= $this->prettyPrintWorklogByDate() . "\n";
        return $output;
    }

    /**
     * Returns string formatted for Jira comment from most recent worklog request.
     *
     * @return string
     */
    public function outputJiraComment()
    {
        $output = $this->prettyPrintWorklogSummary();
        $output .= "\n{code}\n" . $this->prettyPrintWorklogIssues() . "{code}\n";
        $output .= "\n{code}\n" . $this->prettyPrintWorklogByDate() . "{code}\n";
        return $output;
    }


    /**
     * Returns string formatted for Jira comment from most recent worklog request.
     *
     * @return string
     */
    public function outputHtml()
    {
        $output = "\n<pre>\n". $this->prettyPrintWorklogSummary() . "\n";
        $output .= "\n" . $this->prettyPrintWorklogIssues() . "\n";
        $output .= "\n\n" . $this->prettyPrintWorklogByDate() . "</pre>\n";
        return $output;
    }


    /**
     * adds comment to Jira Issue with output from most recent worklog request.
     *
     * @param  string   $key issue key
     * @return string   $ret json encoded output from api POST.
     */
    public function postComment($key)
    {
        // POST /rest/api/2/issue/{issueIdOrKey}/comment
        // https://docs.atlassian.com/jira/REST/server/#api/2/issue-addComment
        $post = [ 'body' => $this->outputJiraComment() ];
        
        $ret = json_decode($this->apiCall("issue/$key/comment", 'POST', $post), true);

        return $ret;
    }


    /**
     *   Return human readable time (hours, mins) given seconds.  Ex: 7200 -> 2h, 900 -> 15m
     *
     * @param  integer  $x number of seconds
     * @param  string   $fmt format for sprintf, defaults to string with no whitespaces
     * @return string   human readable time in minutes (m) or hours (h)
     */
    public function roundit($x, $fmt='%s')
    {
        $this->requireType('integer', $x);
        $this->requireType('string', $fmt);

        if ($x < 3600) {
            return sprintf($fmt, round($x / 60) . "m");
        }
        return sprintf($fmt, round($x / (60 * 6))/10 . "h");
    }

    public static function mySortByDateStr($a, $b)
    {
        return strtotime($a) - strtotime($b);
    }
}
