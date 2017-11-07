<?php
namespace Norhaus\Tests;

use Norhaus\JiraWorklog;
use PHPUnit\Framework\TestCase;

final class JiraWorklogTest extends TestCase
{
    public $jw; // instance of Norhaus\JiraWorklog
    protected static $jw2; // instance of Norhaus\JiraWorklog

    // setUp() and tearDown() template methods are run once for each test method
    // setUpBeforeClass() and tearDownAfterClass() template methods are called before the first test of the test case class is run and after the last test of the test case class is run, respectively.
    public static function setUpBeforeClass()
    {
        //self::$jw2 = new PDO('sqlite::memory:');
    }
    
    public function setUp()
    {
        // Create a mock for the JiraWorklog class, only mock the apiCall() method. 
        // All other methods will retain original function.
        // https://phpunit.de/manual/current/en/test-doubles.html
    
        $cfg = [ 
            //'debug'=>true, // if true, will echo debug info, like worklog entries
            //'debugCache'=>true, // if true, will echo if cache hit
            //'echoTiming'=>true // true: curl call summary
        ];

        $this->jw = $this->getMockBuilder('Norhaus\JiraWorklog')
                         ->setConstructorArgs([$cfg])
                         ->setMethods(['curlWrapper'])
                         ->getMock();

        // use same data we use to test apiCall() to populate the localJiraCache
        $dataProvider = $this->providerApiCallCache();
        foreach ($dataProvider as $val) {
            $cmd = $val[0];
            $expectedResult =  $val[1];
            $this->jw->localJiraCache[ $cmd ] = $expectedResult;
        }
        //var_dump(['setup localJiraCache', count($this->jw->localJiraCache)]);
    }


    /**
     * @param string  $cmd human readable time in minutes (m) or hours (h)
     * @param string  $expectedResult contents of file that should be json decodable  
     * 
     * @dataProvider providerApiCallCache
     */
    public function testApiCallReturnsCache($cmd, $expectedResult)
    {
        $this->assertEquals( $expectedResult, $this->jw->apiCall($cmd) );
    }

    public function providerApiCallCache()
    {
        $cmd = 'search?maxResults=999&jql=worklogDate%3E%3D2017-10-03+AND+worklogDate%3C%3D2017-10-05+ORDER+BY+key+ASC';
        $expectedResult = file_get_contents(__DIR__ . "/data/CN-search.json");
        //$expectedResult = ' .. contents of '. __DIR__ . "/search.json";
        $ret = array(
            [$cmd, $expectedResult]
        );
        foreach (['CN-117','CN-130','CN-133','CN-146'] as $val) {
            $cmd = "issue/$val/worklog";
            $expected = file_get_contents(__DIR__ . "/data/$val-worklog.json");
            $ret[] = [$cmd, $expected];
        }
        return $ret;
    }


    public function testCsvOutput()
    {
        $this->jw->setConfig(['asOfDateFmt' => 'Y-m-d H:i D T']);

        $this->jw->getJiraIssues('2017-10-03', '2017-10-05');

        $strActual = $this->jw->getOutput('csv');
        $strExpected = file_get_contents(__DIR__ . "/data/output1.csv");

        // remove Generated timestamp. Ex: Generated 2017-11-07 13:40 Tue CST using JQL
        $strActual   = preg_replace('/Generated.*using JQL/', 'Generated ... using JQL', $strActual);
        $strExpected = preg_replace('/Generated.*using JQL/', 'Generated ... using JQL', $strExpected);

        $this->assertEquals( 
            $this->jw->jsonPrettyPrint( $strExpected ), 
            $this->jw->jsonPrettyPrint( $strActual )
        );

    }


    /**
     * @param string   $p1from from date time string
     * @param string   $p2to   to date time string
     * @param string   $p3users usernames  
     * @param string   $fn base filename for input data
     * 
     * @dataProvider providerTestJsonOutput
     */
    public function testJsonOutput($p1from, $p2to, $p3users, $fn)
    {
        //$this->jw->getJiraIssues('2017-10-03', '2017-10-05', 'chad,rest-api,jo');
        $this->jw->getJiraIssues($p1from, $p2to, $p3users);

        $jsonActual = $this->jw->getOutput('json');

        $jsonExpected = file_get_contents(__DIR__ . "/data/$fn");

        // remove dateComputed before comparing
        // 
        $jActual = json_decode($jsonActual, true);
        $jExpectd = json_decode($jsonExpected, true);
        unset($jActual['res']['dateComputed']);
        unset($jActual['res']['dateComputedEpoch']);
        unset($jExpectd['res']['dateComputed']);
        unset($jExpectd['res']['dateComputedEpoch']);

        $this->assertEquals( 
            $this->jw->jsonPrettyPrint( $jExpectd ), 
            $this->jw->jsonPrettyPrint( $jActual )
        );

    }

    public function providerTestJsonOutput()
    {
        /* Generated black box tests easily by doing the following:

        php jira-worklog.php -avd -o=csv  >output1.csv
        php jira-worklog.php -avd -o=json  >output1.json
        php jira-worklog.php -avd -o=json -u=chad,jo >output2-chad-jo.json
        php jira-worklog.php -avd -o=json -u=jo,rest-api >output3-jo-rest-api.json
        php jira-worklog.php -avd -o=json -f='2017-10-03T16:08:00-05:00' >output7-f-16-08.json
        php jira-worklog.php -avd -o=json -f='2017-10-03T16:10:00-05:00' >output8-f-16-10.json
        php jira-worklog.php -avd -o=json -f='2017-10-03T16:10:00-05:00' -u=chad >output9-f-16-10-chad.json
        */
        return array(
            array('2017-10-03', '2017-10-05', '', 'output1.json'),
            array('2017-10-03', '2017-10-05', 'chad,jo', 'output2-chad-jo.json'),
            array('2017-10-03', '2017-10-05', 'jo,rest-api', 'output3-jo-rest-api.json'),
            array('2017-10-03T16:08:00-05:00', '2017-10-05', '', 'output7-f-16-08.json'),
            array('2017-10-03T16:10:00-05:00', '2017-10-05', '', 'output8-f-16-10.json'),
            array('2017-10-03T16:10:00-05:00', '2017-10-05', 'chad', 'output9-f-16-10-chad.json')
        
        );
    }

    /**
     * @param string   $expectedResult human readable time in minutes (m) or hours (h)
     * @param integer  $secs number of seconds  
     * @param string   $fmt format for sprintf, defaults to string with no whitespaces
     * 
     * @dataProvider providerTestRoundIt
     */
    public function testRoundIt($expectedResult, $secs, $fmt)
    {
        $jw = new JiraWorklog([]);

        $result = $this->jw->roundit($secs, $fmt);

        $this->assertEquals($expectedResult, $result);

    }
    public function providerTestRoundIt()
    {
        return array(
            array('5m', 300, '%s'),
            array(' 5m ', 300, ' %s '),
            array('15m', 900, '%s'),
            array('1h', 3600, '%s'),
            array('10h', 36000, '%s'),
            array('2.5h', 9000, '%s')
        );
    }

}
