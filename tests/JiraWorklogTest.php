<?php
namespace Norhaus\Tests;

use Norhaus\JiraWorklog;
use PHPUnit\Framework\TestCase;

final class JiraWorklogTest extends TestCase
{
    public function testTrueIsTrue()
    {
        $foo = true;
        $this->assertTrue($foo);
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

        $result = $jw->roundit($secs, $fmt);

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
