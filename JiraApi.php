<?php


class JiraApi
{
    protected $config = [

        // these 2 should move to another file
        'apiBaseUrl' => 'https://jira.example.com',
        'apiCredentials' => 'rest-api:restP@ssword',

        'debug' => false,       // if true, echos detailed debug information
        'echoTiming' => false,  // if true, echos strings that show timing information
        'useLocalJiraCache' => true
    ];

    private $localJiraCache = [];


    /**
     * set configuration options
     * 
     * @param  array   $cfg array of options
     * @return JiraApi $this (chainable)
     */
    function __construct($cfg) {
       return $this->setConfig($cfg);
    }

    /**
     * set configuration options
     * 
     * @param  array   $cfg array of options
     * @return JiraApi $this (chainable)
     */
    public function setConfig($cfg)
    {
        if ("array" === gettype($cfg)) {
            $this->config = array_merge($this->config, $cfg);
        } else {
            throw new Exception("must pass config parameters as array");
        }
        return $this;
    }

    /**
     * makes an API Call to Jira, checking local cache first.
     *
     * @param  string   $command used in URL, ex: issue/CN-2
     * @param  string   $type GET, POST, PUT
     * @param  string|array   $data optional, can be json_encoded string or php array
     * @return array $json data object from API
     */
    public function apiCall($command, $type='GET', $data=null) {

        $cacheKey = $command;
        //$cacheKey = json_encode([$command, $data, $opt]);

        if ('GET'==$type && $this->config['useLocalJiraCache']
            && array_key_exists($cacheKey, $this->localJiraCache))
        {
            //$this->dbg("apiCall() returning cached result for $cacheKey\n");
            //echo $config['apiCall.cache'][$cacheKey] ."\n";
            return $this->localJiraCache[$cacheKey];
        }
        //$this->dbg("apiCall() not returning cached result for $cacheKey\n");

        $url = $this->config['apiBaseUrl'] . '/rest/api/2/' . $command;
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json'
        );
        $start = microtime(true);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $this->config['apiCredentials']);
        if ($data) {
            if (!is_string($data)) {
                $data = json_encode($data);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $result = curl_exec($ch);
        $ch_error = curl_error($ch);
        curl_close($ch);
        $elapsedMs = round(1000*(microtime(true) - $start));

        if ($this->config['echoTiming']) {
            echo "curl: err=$ch_error $elapsedMs" . "ms $type ". strlen($datas) ." ". strlen($result) ." $url\n";
        }
        if ($ch_error) {
            throw new Exception("cURL Error: $ch_error");
        } else {
            if ('GET'==$type && $this->config['useLocalJiraCache']) {
                $this->localJiraCache[$cacheKey] = $result;
                $this->dbg("apiCall() caching result for $cacheKey\n");
            }
            return $result;
        }
    }

    /**
     * retrieves json issue data from Jira API 
     * 
     * @param  string $key Jira issue key, ex: CN-123
     * @return array  json issue data      
     */
    public function getJiraIssueByKey($key) {
        $issue = json_decode(apiCall('issue/'.$key));
        if (!empty($issue->errorMessages) && count($issue->errorMessages) > 0) {
            throw new Exception("Cannot get issue $key: " . $issue->errorMessages[0]);
        }
        return $issue;
    }

    /**
     * Change Jira data to a simple array of requested fields and their values (adapter pattern).
     * Basically a wrapper around getIssueFields()
     *
     * @param  array  $tickets array of isssues or one issue, from Jira API
     * @param  array  $fields array of fields
     * @return array  if $tickets is a single issue, returns simple array where keys are the requested fields,
     *                if $tickets is multiple issues, array has issueKey as keys, value is as if $tickets
     *                is a single issue.
     */
    public function getFields($tickets, $fields) {
        if ("string" == gettype($tickets)) {
            $tkt = json_decode($tickets, true);
        } else if ("object" == gettype($tickets)) {
            $tkt = json_decode(json_encode($tickets), true);
        } else if ("array" == gettype($tickets)) {
            $tkt = $tickets;
        } else {
            $tkt = null;
        }
        $ret = [];
        if (isset($tkt['issues'])) {
            foreach ($tkt['issues'] as $issue) {
                $ret[ $issue['key'] ] = $this->getIssueFields($issue, $fields);
             }
        } else {
            $ret = $this->getIssueFields($tkt, $fields);
        } 
        return $ret;
    }

    /**
     * Change Jira data to a simple array of requested fields and their values (adapter pattern)
     *
     * @param  array  $ticket one issue, from Jira API
     * @param  array  $fields array of fields
     * @return array  if $ticket is a single issue, returns simple array where keys are the requested fields,
     *                if $ticket is multiple issues, array has issueKey as keys, value is as if $ticket
     *                is a single issue.
     */
    public function getIssueFields($ticket, $fields) {
        $ret = [];
        $nf = '<field-not-found>';
        if ("array" != gettype($ticket)) {
            return $ret;
        }
        foreach ($fields as $field) {
            if ($field == 'key') {
                $ret[$field] = array_key_exists($field, $ticket) 
                                ? $ticket[$field] 
                                : $nf;
            } else if (in_array($field, ['resolution','creator','reporter','priority','status'])) {
                $ret[$field] = array_key_exists($field, $ticket['fields']) 
                                ? $ticket['fields'][$field]['name'] 
                                : $nf;
            } else {
                $ret[$field] = array_key_exists($field, $ticket['fields']) 
                                ? $ticket['fields'][$field] 
                                : $nf;
            }
            if (in_array($field, ['timespent','aggregatetimespent','timeoriginalestimate','aggregatetimeestimate'])) {
                // $ret[$field] = ($ret[$field] / (60 * 60)) . "h";
            }
        }
        return $ret;
    }

    /**
     * Does json_encode with JSON_PRETTY_PRINT flag but allows 2-space indentation
     * 
     * @param  array|object  $mixed        php array 
     * @param  boolean       $indent_by_4  if true, indents by 4 spaces. If false, indents by 2
     * @return string                      json encoded string
     */
    public function jsonPrettyPrint($mixed, $indent_by_4=false) {
        $json_indented_by_4 = json_encode($mixed, JSON_PRETTY_PRINT);
        if ($indent_by_4) {
            return $json_indented_by_4;
        } else {
            $json_indented_by_2 = preg_replace('/^(  +?)\\1(?=[^ ])/m', '$1', $json_indented_by_4);
            return $json_indented_by_2;
        }
    }


    public function dbg($string) 
    {
        if ($this->config['debug']) {
            echo $string;
        }
    }

}