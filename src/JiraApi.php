<?php
namespace Norhaus;

class JiraApi
{
    protected $config = [

        // these 2 should move to another file
        'apiBaseUrl' => 'https://jira.example.com',
        'apiCredentials' => 'rest-api:restP@ssword',

        'debug' => false,       // if true, echos detailed debug information
        'debugCache' => false,  // if true, echos whether or not returning from useLocalJiraCache 
        'echoTiming' => false,  // if true, echos strings that show timing information
        'useLocalJiraCache' => true
    ];

    public $localJiraCache = [];


    /**
     * set configuration options
     *
     * @param  array   $cfg array of options
     * @return JiraApi $this (chainable)
     */
    public function __construct($cfg=[])
    {
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
            throw new \Exception("must pass config parameters as array");
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
    public function apiCall($command, $type='GET', $data=null)
    {
        $cacheKey = $command;
        //$cacheKey = json_encode([$command, $data, $opt]);

        if ('GET'==$type && $this->config['useLocalJiraCache']
            && array_key_exists($cacheKey, $this->localJiraCache)) {
            $this->dbg("apiCall() returning cached result, ". 
                strlen($this->localJiraCache[$cacheKey]) ." bytes, for $cacheKey\n",
                'debugCache');
            //echo $config['apiCall.cache'][$cacheKey] ."\n";
            return $this->localJiraCache[$cacheKey];
        }
        $this->dbg("apiCall() not returning cached result for $cacheKey\n", 'debugCache');

        $url = $this->config['apiBaseUrl'] . '/rest/api/2/' . $command;

        $start = microtime(true);

        $ret = $this->curlWrapper($url, $type, $data);

        $elapsedMs = round(1000*(microtime(true) - $start));

        $ch_error = $ret['error'];
        $httpcode = $ret['httpcode'];
        $result   = $ret['result'];

        if ($this->config['echoTiming']) {
            echo "curl: err=$ch_error $elapsedMs" . "ms $httpcode $type ";
            echo strlen($data) ." ". strlen($result) ." $url\n";
        }
        if ($ch_error) {
            throw new \Exception("cURL Error: $ch_error");
        } elseif (403 == $httpcode) {
            $msg = "Common problem is too many failed login attempts.  Verify and reset here:\n";
            $msg .= $this->config['apiBaseUrl'] . "/secure/admin/user/UserBrowser.jspa\n\n";
            throw new \Exception("cURL expected HTTP 200, got $httpcode\n$msg");
        } elseif (300 <= $httpcode) {
            throw new \Exception("cURL expected HTTP 2xx, got $httpcode");
        } else {
            if ('GET'==$type && $this->config['useLocalJiraCache']) {
                $this->localJiraCache[$cacheKey] = $result;
                $this->dbg("apiCall() caching result for $cacheKey\n");
            }
            return $result;
        }
    }


    /**
     * wrapper for curl
     *
     * re-architect this for async API requests - http://blog.programster.org/php-async-curl-requests
     * http://guzzle.readthedocs.io/en/latest/quickstart.html#async-requests
     *         $ret = $this->curlWrapper($url, $type, $data, $headers);
     *         
     * @param  string         $command used in URL, ex: issue/CN-2
     * @param  string         $type GET, POST, PUT
     * @param  string|array   $data optional, can be json_encoded string or php array
     * @param  array          $headers 
     * @return array          
     */
    public function curlWrapper($url, $type, $data=null)
    {
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json'
        );

        $ch = curl_init();
        if ($this->config['debug']) {
            curl_setopt($ch, CURLOPT_VERBOSE, 1);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [
            'error' => $ch_error,
            'httpcode' => $httpcode,
            'result' => $result
        ];
    }

    /**
     * retrieves json issue data from Jira API
     *
     * @param  string $key Jira issue key, ex: CN-123
     * @return array  json issue data
     */
    public function getJiraIssueByKey($key)
    {
        $issue = json_decode(apiCall('issue/'.$key));
        if (!empty($issue->errorMessages) && count($issue->errorMessages) > 0) {
            throw new \Exception("Cannot get issue $key: " . $issue->errorMessages[0]);
        }
        return $issue;
    }

    /**
     * Change Jira data to a flattened array of requested fields and their values (adapter pattern).
     * Basically a wrapper around getIssueFields()
     *
     * @param  array  $tickets array of isssues or one issue, from Jira API
     * @param  array  $fields array of fields
     * @return array  if $tickets is a single issue, returns simple array where keys are the requested fields,
     *                if $tickets is multiple issues, array has issueKey as keys, value is as if $tickets
     *                is a single issue.
     */
    public function getFlattenedIssues($tickets, $fields)
    {
        if ("string" == gettype($tickets)) {
            $tkt = json_decode($tickets, true);
        } elseif ("object" == gettype($tickets)) {
            $tkt = json_decode(json_encode($tickets), true);
        } elseif ("array" == gettype($tickets)) {
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
    public function getIssueFields($ticket, $fields)
    {
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
            } elseif (in_array($field, ['resolution','creator','reporter','priority','status'])) {
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
    public function jsonPrettyPrint($mixed, $indent_by_4=false)
    {
        $json_indented_by_4 = json_encode($mixed, JSON_PRETTY_PRINT);
        if ($indent_by_4) {
            return $json_indented_by_4;
        } else {
            $json_indented_by_2 = preg_replace('/^(  +?)\\1(?=[^ ])/m', '$1', $json_indented_by_4);
            return $json_indented_by_2;
        }
    }

    /**
     * shortcut to validate variable types, throws an Exception if does not match
     *
     * @param  string $type should be one of return strings from gettype()
     * @param  mixed  $x    any variable
     */
    public function requireType($type, $x)
    {
        if ($type != gettype($x)) {
            throw new \Exception(gettype($x) ." was passed, expected $type");
        }
    }


    public function dbg($string, $catg='debug')
    {
        if ($this->config[$catg]) {
            echo $string;
        }
    }
}
