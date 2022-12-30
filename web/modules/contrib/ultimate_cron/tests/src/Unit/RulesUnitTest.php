<?php

namespace Drupal\Tests\ultimate_cron\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ultimate_cron\CronRule;
use Drupal\ultimate_cron\Plugin\ultimate_cron\Scheduler\Crontab;

/**
 * Tests Drupal\ultimate_cron\CronRule.
 *
 * @group ultimate_cron
 */
class RulesUnitTest extends UnitTestCase {

  private function getIntervals($rule) {
    $cron = CronRule::factory($rule, $_SERVER['REQUEST_TIME']);
    return $cron->getIntervals();
  }

  private function assertRule($options) {
    // Setup values
    $options['rules'] = is_array($options['rules']) ? $options['rules'] : array($options['rules']);
    $options['catch_up'] = isset($options['catch_up']) ? $options['catch_up'] : 86400 * 365; // @todo Adapting Elysia Cron test cases with a catchup of 1 year

    // Generate result message
    $message = array();
    foreach ($options['rules'] as $rule) {
      $cron = CronRule::factory($rule, strtotime($options['now']));
      $intervals = $cron->getIntervals();
      $parsed_rule = '';
      foreach ($intervals as $key => $value) {
        $parsed_rule .= "$key: " . implode(',', $value) . "\n";
      }
      #$parsed_rule = str_replace(" ", "\n", $cron->rebuildRule($cron->getIntervals()));
      $last_scheduled = $cron->getLastSchedule();
      $message[] = "<span title=\"$parsed_rule\">$rule</span> @ " . date('Y-m-d H:i:s', $last_scheduled);
    }
    $message[] = 'now      @ ' . $options['now'];
    $message[] = 'last-run @ ' . $options['last_run'];
    $message[] = 'catch-up @ ' . $options['catch_up'];
    $message[] = ($options['result'] ? '' : 'not ') . 'expected to run';

    // Do the actual test
    $result = Crontab::shouldRun($options['rules'], strtotime($options['last_run']), strtotime($options['now']), $options['catch_up']);

    return array($options['result'] == $result, implode('<br/>', $message));
  }

  function testIntervals2MinuteRange() {
    $intervals = $this->getIntervals('10-11 12 * * *');
    $this->assertEquals(range(11, 10, -1), $intervals['minutes'], 'Expected minutes to be 10, 11');
    $intervals = $this->getIntervals('0-1 12 * * *');
    $this->assertEquals(range(1, 0, -1), $intervals['minutes'], 'Expected minutes to be 0, 1');
    $intervals = $this->getIntervals('58-59 12 * * *');
    $this->assertEquals(range(59, 58, -1), $intervals['minutes'], 'Expected minutes to be 58, 59');
  }

  function testIntervals2MinuteRangeWithOffset() {
    $intervals = $this->getIntervals('0-1+1 12 * * *');
    $this->assertEquals(range(2, 1, -1), $intervals['minutes'], 'Expected minutes to be 1, 2');
    $intervals = $this->getIntervals('10-11+1 12 * * *');
    $this->assertEquals(range(12, 11, -1), $intervals['minutes'], 'Expected minutes to be 11, 12');
    // Note, this test is testing for correct behaviour when the minutes wrap around
    // Previously, this test would generate 43, 0 due to a bug in expandRange/expandInterval
    $intervals = $this->getIntervals('42-43+1 12 * * *');
    $this->assertEquals(array(44, 43), $intervals['minutes'], 'Expected minutes to be 43, 44');
    // Note, this test is testing for correct behaviour when the minutes wrap around
    $intervals = $this->getIntervals('58-59+1 12 * * *');
    $this->assertEquals(array(59, 0), $intervals['minutes'], 'Expected minutes to be 59, 0');
  }

  function testIntervalsSpecificMinute() {
    $intervals = $this->getIntervals('0 12 * * *');
    $this->assertEquals(array(0), $intervals['minutes'], 'Expected minutes to be 0');
    $intervals = $this->getIntervals('10 12 * * *');
    $this->assertEquals(array(10), $intervals['minutes'], 'Expected minutes to be 10');
    $intervals = $this->getIntervals('59 12 * * *');
    $this->assertEquals(array(59), $intervals['minutes'], 'Expected minutes to be 59');
  }

  function testRules() {
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '0 12 * * *',
      'last_run' => '2008-01-02 12:00:00',
      'now' => '2008-01-02 12:01:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '0 12 * * *',
      'last_run' => '2008-01-02 12:00:00',
      'now' => '2008-01-02 15:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '0 12 * * *',
      'last_run' => '2008-01-02 12:00:00',
      'now' => '2008-01-03 11:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '0 12 * * *',
      'last_run' => '2008-01-02 12:00:00',
      'now' => '2008-01-03 12:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59 23 * * *',
      'last_run' => '2008-01-02 23:59:00',
      'now' => '2008-01-03 00:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 * * *',
      'last_run' => '2008-01-02 23:59:00',
      'now' => '2008-01-03 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 * * *',
      'last_run' => '2008-01-02 23:59:00',
      'now' => '2008-01-04 00:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 * * *',
      'last_run' => '2008-01-02 23:58:00',
      'now' => '2008-01-02 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 * * *',
      'last_run' => '2008-01-02 23:58:00',
      'now' => '2008-01-03 00:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59 23 * * 0',
      'last_run' => '2008-01-05 23:58:00',
      'now' => '2008-01-05 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59 23 * * 0',
      'last_run' => '2008-01-05 23:58:00',
      'now' => '2008-01-06 00:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 * * 0',
      'last_run' => '2008-01-05 23:58:00',
      'now' => '2008-01-06 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 * * 0',
      'last_run' => '2008-01-05 23:58:00',
      'now' => '2008-01-07 00:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '29,59 23 * * 0',
      'last_run' => '2008-01-05 23:58:00',
      'now' => '2008-01-06 23:29:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '29,59 23 * * 0',
      'last_run' => '2008-01-05 23:58:00',
      'now' => '2008-01-06 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '29,59 23 * * 0',
      'last_run' => '2008-01-05 23:58:00',
      'now' => '2008-01-05 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '29,59 23 * * 0',
      'last_run' => '2008-01-05 23:58:00',
      'now' => '2008-01-06 23:58:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '29,59 23 * * 0',
      'last_run' => '2008-01-05 23:58:00',
      'now' => '2008-01-06 23:28:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '29,59 23 * * 0',
      'last_run' => '2008-01-05 23:28:00',
      'now' => '2008-01-05 23:29:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '29,59 23 * * 0',
      'last_run' => '2008-01-05 23:28:00',
      'now' => '2008-01-05 23:30:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '29,59 23 * * 0',
      'last_run' => '2008-01-05 23:28:00',
      'now' => '2008-01-05 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '29,59 23 * * 0',
      'last_run' => '2008-01-05 23:28:00',
      'now' => '2008-01-06 23:29:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '29,59 23 * * 5',
      'last_run' => '2008-02-22 23:59:00',
      'now' => '2008-02-28 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '29,59 23 * * 5',
      'last_run' => '2008-02-22 23:59:00',
      'now' => '2008-02-29 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '29,59 23 * * 5',
      'last_run' => '2008-02-22 23:59:00',
      'now' => '2008-03-01 00:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59 23 * * 3',
      'last_run' => '2008-12-31 23:59:00',
      'now' => '2009-01-01 00:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59 23 * * 3',
      'last_run' => '2008-12-31 23:59:00',
      'now' => '2009-01-07 00:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 * * 3',
      'last_run' => '2008-12-31 23:59:00',
      'now' => '2009-01-07 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 * 2 5',
      'last_run' => '2008-02-22 23:59:00',
      'now' => '2008-02-29 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 * 2 5',
      'last_run' => '2008-02-22 23:59:00',
      'now' => '2008-03-01 00:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59 23 * 2 5',
      'last_run' => '2008-02-29 23:59:00',
      'now' => '2008-03-07 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59 23 * 2 5',
      'last_run' => '2008-02-29 23:59:00',
      'now' => '2009-02-06 23:58:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 * 2 5',
      'last_run' => '2008-02-29 23:59:00',
      'now' => '2009-02-06 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59 23 */10 * *',
      'last_run' => '2008-01-10 23:58:00',
      'now' => '2008-01-10 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 */10 * *',
      'last_run' => '2008-01-10 23:59:00',
      'now' => '2008-01-11 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 */10 * *',
      'last_run' => '2008-01-10 23:59:00',
      'now' => '2008-01-20 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 1-5,10-15 * *',
      'last_run' => '2008-01-04 23:59:00',
      'now' => '2008-01-05 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 1-5,10-15 * *',
      'last_run' => '2008-01-04 23:59:00',
      'now' => '2008-01-06 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59 23 1-5,10-15 * *',
      'last_run' => '2008-01-05 23:59:00',
      'now' => '2008-01-06 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59 23 1-5,10-15 * *',
      'last_run' => '2008-01-05 23:59:00',
      'now' => '2008-01-10 23:58:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 1-5,10-15 * *',
      'last_run' => '2008-01-05 23:59:00',
      'now' => '2008-01-10 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 1-5,10-15 * *',
      'last_run' => '2008-01-05 23:59:00',
      'now' => '2008-01-16 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 1-5 1 0',
      'last_run' => '2008-01-04 23:59:00',
      'now' => '2008-01-05 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 1-5 1 0',
      'last_run' => '2008-01-05 23:59:00',
      'now' => '2008-01-06 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59 23 1-5 1 0',
      'last_run' => '2008-01-06 23:59:00',
      'now' => '2008-01-07 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59 23 1-5 1 0',
      'last_run' => '2008-01-06 23:59:00',
      'now' => '2008-01-13 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59 23 1-5 1 0',
      'last_run' => '2008-02-04 23:59:00',
      'now' => '2008-02-05 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59 23 1-5 1 0',
      'last_run' => '2008-02-05 23:59:00',
      'now' => '2008-02-10 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59 23 1-5 1 0',
      'last_run' => '2008-02-10 23:59:00',
      'now' => '2008-02-17 23:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '* 0,1,2,3,4,5,6,7,8,18,19,20,21,22,23 * * *',
      'last_run' => '2008-02-10 08:58:00',
      'now' => '2008-02-10 08:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '* 0,1,2,3,4,5,6,7,8,18,19,20,21,22,23 * * *',
      'last_run' => '2008-02-10 08:59:00',
      'now' => '2008-02-10 09:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '* 0,1,2,3,4,5,6,7,8,18,19,20,21,22,23 * * *',
      'last_run' => '2008-02-10 08:59:00',
      'now' => '2008-02-10 17:59:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '* 0,1,2,3,4,5,6,7,8,18,19,20,21,22,23 * * *',
      'last_run' => '2008-02-10 08:59:00',
      'now' => '2008-02-10 18:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '* 0,1,2,3,4,5,6,7,8,18,19,20,21,22,23 * * *',
      'last_run' => '2008-02-10 18:00:00',
      'now' => '2008-02-10 18:01:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '* 0,1,2,3,4,5,6,7,8,18,19,20,21,22,23 * * *',
      'last_run' => '2008-02-10 18:00:00',
      'now' => '2008-02-10 19:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '* 0,1,2,3,4,5,6,7,8,18,19,20,21,22,23 * * *',
      'last_run' => '2008-02-10 18:00:00',
      'now' => '2008-03-10 09:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
  }

  function testRules1MinuteRange() {
    // Test a 1 minute range
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '10-10 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:09:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '10-10 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:10:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '10-10 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:11:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
  }

  function testRules2MinuteRange() {
    // Test a 1 minute range
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '10-11 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:09:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '10-11 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:10:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '10-11 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:11:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '10-11 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:12:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
  }

  function testRules2MinuteRangeWithOffset() {
    // Test a 1 minute range
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '10-11+1 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:10:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '10-11+1 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:11:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '10-11+1 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:12:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '10-11+1 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:13:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
  }

  function testRules5MinuteRange() {
    // Test a 5 minute range
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '10-15 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:09:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '10-15 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:10:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '10-15 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:11:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '10-15 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:12:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '10-15 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:13:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '10-15 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:14:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '10-15 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:15:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    // This should not run, as it last ran one minute ago
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '10-15 12 * * *',
      'last_run' => '2008-01-03 12:15:00',
      'now' => '2008-01-03 12:16:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    // This should run, as catch_up defaults to 1 year and it last ran 16 minutes ago.
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '10-15 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:16:00'
    ));
    $this->assertTrue($result[0], $result[1]);
  }

  function testRules5MinuteStep() {
    // Test a 5 minute step
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '*/5 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:01:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '*/5 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:02:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '*/5 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:03:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '*/5 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:04:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '*/5 12 * * *',
      'last_run' => '2008-01-03 12:00:00',
      'now' => '2008-01-03 12:05:00'
    ));
    $this->assertTrue($result[0], $result[1]);
  }

  function testRulesExtended() {
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '0 0 * jan,oct *',
      'last_run' => '2008-01-31 00:00:00',
      'now' => '2008-03-10 09:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '0 0 * jan,oct *',
      'last_run' => '2008-01-31 00:00:00',
      'now' => '2008-10-01 00:00:00'
    ));
    $this->assertTrue($result[0], $result[1]);
  }

  function testRulesMinuteWithOffset() {
    // Test a 1 minute range
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '10+1 12 * * *',
      'last_run' => '2008-01-01 12:00:00',
      'now' => '2008-01-03 12:10:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '10+1 12 * * *',
      'last_run' => '2008-01-01 12:00:00',
      'now' => '2008-01-03 12:11:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '10+1 12 * * *',
      'last_run' => '2008-01-01 12:00:00',
      'now' => '2008-01-03 12:12:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59+1 12 * * *',
      'last_run' => '2008-01-01 12:00:00',
      'now' => '2008-01-03 12:59:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => True,
      'rules' => '59+1 12 * * *',
      'last_run' => '2008-01-01 12:00:00',
      'now' => '2008-01-03 12:00:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59+1 12 * * *',
      'last_run' => '2008-01-01 12:00:00',
      'now' => '2008-01-03 13:00:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
    $result = $this->assertRule(array(
      'result' => FALSE,
      'rules' => '59+1 12 * * *',
      'last_run' => '2008-01-01 12:00:00',
      'now' => '2008-01-03 13:01:00',
      'catch_up' => 1
    ));
    $this->assertTrue($result[0], $result[1]);
  }
}

