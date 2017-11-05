<?php
namespace Norhaus\Tests;

use PHPUnit\Framework\TestCase;

final class JiraApiTest extends TestCase
{
    protected $ja; // instance of Norhaus\JiraApi

    // setUp() and tearDown() template methods are run once for each test method
    // setUpBeforeClass() and tearDownAfterClass() template methods are called before the first test of the test case class is run and after the last test of the test case class is run, respectively.

    public function setUp()
    {
        // Create a mock for the JiraApi class, only mock the apiCall() method. 
        // All other methods will retain original function.
        // https://phpunit.de/manual/current/en/test-doubles.html
        
        $cfg = [ 
            'debug'=>true, 
            'echoTiming'=>true 
        ];
        $this->ja = $this->getMockBuilder('Norhaus\JiraApi')
                         ->setConstructorArgs([$cfg])
                         ->setMethods(['curlWrapper'])
                         ->getMock();
    }

    public function testApiCallReturnsCurlWrapper()
    {
        $cmd = 'issue/CN-175/worklog';
        $url = 'https://jira.example.com/rest/api/2/'. $cmd;

        $curlReturn = [
            'error' => '',
            'httpcode' => 200,
            'result' => 'chad'
        ];

        // Set up the expectation for the apiCall() method
        // to be called any amount of times with the string 'something'
        // as its parameter.
        $this->ja->expects($this->any())
                 ->method('curlWrapper')
                 ->with( $this->equalTo($url), 'GET' )
                 ->will($this->returnValue($curlReturn));

        $result = $this->ja->apiCall($cmd);

        $this->assertEquals( $result, 'chad' );
    }
    
    public function testTrueIsTrue()
    {
        $foo = true;
        $this->assertTrue($foo);
    }

}

