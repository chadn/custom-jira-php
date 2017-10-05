<?php

include 'JiraApi.php';

class JiraWorklog extends JiraApi
{
    public $req;          // request object, used for output
    public $res;          // result object, used for output
    private $fromEpoch;   // number of seconds since epoch
    private $toEpoch;     // number of seconds since epoch
    private $total;       // array of daily totals
    private $dailyTotal;  // array of daily totals


    /**
     * configuration options that will overwrite and merge with parent JiraApi $config
     *
     * @var array
     */
    private $jwConfig = [
        'skipEmptyWorklogs' => true,  // if true, json output will only include days with worklogs
        'dailyTotalDateFmt' => 'D Y-m-d', // Fri 2017-08-11
        'jsonIssueFields' => ['summary', 'timespent', 'timespentPretty'],
        'displayFields' => ['timespentPretty', 'key','summary']
    ];

    /**
     * Can pass any config options from jwConfig or JiraApi's config
     *  
     * @param  array   $cfg array of options
     * @return JiraApi $this (chainable)
     */
    function __construct($cfg) {
        $this->setConfig($this->jwConfig);
        parent::__construct($cfg);

        if ($this->config['debug']) {
            //var_dump($this->config);
        }
        return $this;
    }


    /**
     * Retrieve all Jira Issues that may have worklogs between dates passed
     *
     * @param  string|int  $fromDateInput look at jira issues after this date
     * @param  string|int  $toDateInput   look at jira issues before this date
     * @return JiraWorklog $this (chainable)
     */
    public function getJiraIssues($fromDateInput, $toDateInput)
    {
        if ("string" === gettype($fromDateInput)) {
            $this->fromEpoch = strtotime($fromDateInput);
        } elseif ("integer" === gettype($fromDateInput)) {
            $this->fromEpoch = $fromDateInput;            
        } else {
            throw new Exception("invalid type for fromDateInput, must be string or integer");
        }

        if ("string" === gettype($toDateInput)) {
            $this->toEpoch = strtotime($toDateInput);
        } elseif ("integer" === gettype($toDateInput)) {
            $this->toEpoch = $toDateInput;            
        } else {
            throw new Exception("invalid type for fromDateInput, must be string or integer");
        }

        $fromDateJQL = date('Y-m-d', $this->fromEpoch); 
        $toDateJQL   = date('Y-m-d', $this->toEpoch); 
        $this->req['jql'] = "worklogDate>=$fromDateJQL AND worklogDate<=$toDateJQL ORDER BY key ASC";
        $this->req['fromDateInput'] = $fromDateInput;
        $this->req['toDateInput']   = $toDateInput;
        $this->req['fromEpoch'] = $this->fromEpoch;
        $this->req['toEpoch']   = $this->toEpoch;

        $apiResponse = $this->apiCall( 'search?maxResults=999&jql=' . urlencode($this->req['jql']), 'GET');

        if (!$apiResponse)  {
            throw new Exception("bad response from API server");
        }
        $this->jiraIssues = json_decode($apiResponse, true);

        $this->prepOutput();

        return $this;
    }



    /**
     * Builds array data for output using most recent worklog request. 
     * 
     * @return JiraWorklog $this (chainable)
     */
    public function prepOutput() {
        if (!$this->jiraIssues)  {
            throw new Exception("must call getJiraIssues() first");
        }

        $this->dailyTotal = [];
        $this->total = ['timespent' => 0];
        //echo var_dump($argv); echo "$fromDate $toDate\n"; exit();

        // get list of jira issue keys and fields for output
        $gflds = $this->getFields($this->jiraIssues, $this->config['jsonIssueFields']);

        foreach ($gflds as $key => $tkt) {

            $secs = $this->getWorklogTotal($key, $this->fromEpoch, $this->toEpoch);
            
            $this->total['timespent'] += $secs;
            $gflds[$key]['timespentPretty'] = $this->roundit($secs, "%5s");
            
            $this->updateWorklogDailyTotal($key);
        }

        $this->total['timespentPretty'] = $this->roundit($this->total['timespent']);

        $this->res = [
            'dateComputed' => date('Y-m-d D g:ia T'),
            'total'        => $this->total,
            'dailyTotal'   => $this->dailyTotal,
            'issues'       => $gflds
        ];
        return $this;
    }


    /**
     * Returns total seconds worked between fromEpoch toEpoch by examining all worklogs 
     * 
     * @param  string   $key issue key
     * @param  integer  $fromEpoch 
     * @param  integer  $toEpoch 
     * @return integer  $totalSecs 
     */
    public function getWorklogTotal($key, $fromEpoch, $toEpoch) {
        $dbg = $this->config['debug'];
        $totalSecs = 0;

        # get the full worklog for that issue
        //$ret = json_decode(apiCall("issue/$key/worklog"), true);
        $ret = json_decode($this->apiCall("issue/$key/worklog",'GET'), true);

        $this->dbg("getWorklogTotal($key) fromEpoch=$fromEpoch toEpoch=$toEpoch ");

        foreach ($ret['worklogs'] as $entry) {
            $startedEpoch = strtotime($entry['started']);

            if ($startedEpoch >= $fromEpoch && $startedEpoch <= $toEpoch) {
                //$periodLog[$key][] = $entry['timeSpentSeconds'] . " " . $entry['started'];
                $totalSecs += $entry['timeSpentSeconds'];
                //$this->dbg(var_export($entry, true));

                $this->dbg("Using ");

            } else {
                $this->dbg("Skipping ");
            }
            $this->dbg("worklog {$entry['id']} with started=$startedEpoch, " . $entry['started'] . "\n");
        }
        return $totalSecs;
    }

    /**
     * updates $this->dailyTotal with worklog data
     * 
     * @param  string $key Jira issue key
     */
    public function updateWorklogDailyTotal($key) {

        // normalize $fromEpoch to be start of day - do not support partial day checks, 
        // since many people create worklogs without paying attention to correct time
        $fromEpoch2 = strtotime(date('Y-m-d', $this->fromEpoch)); 

        // for each day between fromEpoch and toEpoch, get daily total
        for ($curTime = $fromEpoch2; $curTime <= $this->toEpoch; $curTime += 60*60*24) {

            $secs = $this->getWorklogTotal($key, $curTime, $curTime + 60*60*24);

            if (0 === $secs && $this->config['skipEmptyWorklogs']) continue; 

            $dateStr = date($this->config['dailyTotalDateFmt'], $curTime);
            
            if (!array_key_exists($dateStr, $this->dailyTotal)) {
                $this->dailyTotal[$dateStr] = ['totalSecs' => 0];
                //echo " ---- initialized dailyTotal $dateStr\n";
            } 
            $this->dailyTotal[$dateStr]['totalSecs'] += $secs;
            $this->dailyTotal[$dateStr][$key] += $secs;
        }
    }

    /**
     * pretty Print Worklog Daily Total 
     * 
     * @return string summary of time spent per issue each day, one day per line
     */
    public function prettyPrintWorklogDailyTotal() {

        $txtStr = "Daily Worklogs:\n";
        foreach ($this->dailyTotal as $dateStr => $dt) {
            if (0 === $dt['totalSecs']) continue;
            $tmpA = [];
            foreach ($dt as $key => $secs) {
                if ('totalSecs' == $key || 0 == $secs) continue;
                $tmpA[] = $this->roundit($secs) . " $key";
            }
            $txtStr .= $this->roundit($dt['totalSecs'], "%4s") . " $dateStr -- ". implode(', ', $tmpA) ."\n";
        }
        return $txtStr;
    }

    /**
     * pretty Print Worklog Issues 
     * 
     * @return string summary of time spent per Jira issue, one issue per line
     */
    public function prettyPrintWorklogIssues() {

        $txtStr = "Total logged per issue:\n";
        foreach ($this->res['issues'] as $key => $tkt) {
            foreach ($this->config['displayFields'] as $f) {
                $txtStr .= ('key' == $f ? $key : $tkt[$f]) ." ";
            }
            $txtStr .= "\n";
        }
        return $txtStr;
    }

    /**
     * pretty Print Worklog Summary 
     * 
     * @return string summary of total time spent, including start and end time
     */
    public function prettyPrintWorklogSummary() {
        $fromDateOutput = date('Y-m-d D', $this->fromEpoch);  
        $toDateOutput   = date('Y-m-d D', $this->toEpoch); 

        $txtStr = $this->res['total']['timespentPretty'] . " Total Time logged, ";
        $txtStr .= "$fromDateOutput - $toDateOutput\n as of ". $this->res['dateComputed'] ."\n";
        return $txtStr;
    }

    /**
     * Returns string formatted as fmt from most recent worklog request. 
     * 
     * @param  string $fmt output format, one of 'txt', 'html', 'json', 'jira'
     * @return string  
     */
    public function getOutput($fmt) {
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
    public function outputJson() {
        header('Content-Type: application/json');
        $ret = [
            'req' => $this->req,
            'res' => $this->res
        ];
        return $this->jsonPrettyPrint( $ret );
    }

    /**
     * Returns string formatted as text from most recent worklog request. 
     * 
     * @return string  
     */
    public function outputTxt() {
        $output = "\n". $this->prettyPrintWorklogSummary() . "\n";
        $output .= $this->prettyPrintWorklogIssues() . "\n";
        $output .= $this->prettyPrintWorklogDailyTotal() . "\n";
        return $output;
    }

    /**
     * Returns string formatted for Jira comment from most recent worklog request. 
     * 
     * @return string  
     */
    public function outputJiraComment() {
        $output = $this->prettyPrintWorklogSummary();
        $output .= "\n{code}\n" . $this->prettyPrintWorklogIssues() . "{code}\n";
        $output .= "\n{code}\n" . $this->prettyPrintWorklogDailyTotal() . "{code}\n";
        return $output;
    }

    /**
     * Returns string formatted for Jira comment from most recent worklog request. 
     * 
     * @return string  
     */
    public function outputHtml() {
        $output = "\n<pre>\n". $this->prettyPrintWorklogSummary() . "\n";
        $output .= "\n" . $this->prettyPrintWorklogIssues() . "\n";
        $output .= "\n\n" . $this->prettyPrintWorklogDailyTotal() . "</pre>\n";
        return $output;
    }

    /**
     * adds comment to Jira Issue with output from most recent worklog request. 
     * 
     * @param  string   $key issue key
     * @return string   $ret json encoded output from api POST.
     */
    public function postComment($key) {
        // POST /rest/api/2/issue/{issueIdOrKey}/comment
        // https://docs.atlassian.com/jira/REST/server/#api/2/issue-addComment
        // tried but looks like cannot edit date of comment
        // curl -v -u 'rest-api:api4r3sT@norha' -H "Content-Type: application/json" -X POST --data '{"body":"test body"}'  http://192.168.1.1:8082/rest/api/2/issue/TC2-25/comment | php ~/bin/pretty-print-json.php

        $post = [ 'body' => $this->outputJiraComment() ];
        //$post['body'] = "^Comment orig date: ". date('r', strtotime($a->date)) ."^\n\n". $post['body'];
        //$post['updated'] = $post['created'] = strtotime($a->date); // "2016-12-16T00:08:54.072Z"; 
        
        $ret = json_decode($this->apiCall("issue/$key/comment", 'POST', $post), true);

        return $ret;
    }


    /**
     *   Return human readable time (hours, mins) given seconds.  Ex: 7200 -> 2h, 900 -> 15m 
     *   
     * @param  integer  $x number of seconds  
     * @param  string   $fmt issue key
     * @return string    
     */
    public function roundit($x, $fmt='%s') {
        if ($x < 3600) {
            return sprintf($fmt, round($x / 60) . "m");
        }
        return sprintf($fmt, round($x / (60 * 6))/10 . "h");
    }

}