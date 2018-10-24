<?php

namespace Acquia\LightningExtension\Context;

use Drupal\DrupalExtension\Context\DrupalSubContextBase;
use Webmozart\Assert\Assert;

class TimeElementContext extends DrupalSubContextBase {

  /**
   * Converts an interval to a number of seconds.
   *
   * @param $interval
   *   The interval, in the form DAYS:HOURS:MINUTES:SECONDS.
   *
   * @return int
   *   The interval, represented in seconds.
   */
  protected function toSeconds($interval) {
    list ($days, $hours, $minutes, $seconds) = explode(':', $interval, 4);

    Assert::numeric($days);
    Assert::numeric($seconds);
    Assert::numeric($minutes);
    Assert::numeric($hours);

    $seconds = (int) $seconds;
    $minutes = (int) $minutes;
    $hours = (int) $hours;
    $days = (int) $days;

    return $seconds + ($minutes * 60) + ($hours * 3600) + ($days * 86400);
  }

  /**
   * Converts a time stamp to a formatted time.
   *
   * @param int $ts
   *   The time stamp to convert.
   *
   * @return string
   *   The formatted time, in the form HH:MM:SSA.
   */
  protected function format($ts) {
    return date('h:i:sA', $ts);
  }

  /**
   * Sets a time field to a point in the future.
   *
   * @param string $field
   *   The field to change.
   * @param string $interval
   *   The interval to set, in the form of DAYS:HOURS:MINUTES:SECONDS.
   *
   * @When I set :field to :interval from now
   */
  public function setFutureTime($field, $interval) {
    $value = time() + $this->toSeconds($interval);
    $value = $this->format($value);

    $this->assertSession()->fieldExists($field)->setValue($value);
  }

  /**
   * Sets a time field to a point in the past.
   *
   * @param $field
   *   The field to change.
   * @param $interval
   *   The interval to set, in the form of DAYS:HOURS:MINUTES:SECONDS.
   *
   * @When I set :field to :interval ago
   */
  public function setPastTime($field, $interval) {
    $value = time() - $this->toSeconds($interval);
    $value = $this->format($value);

    $this->assertSession()->fieldExists($field)->setValue($value);
  }

  /**
   * Sets a time field to a certain number of seconds in the future.
   *
   * @param string $field
   *   The field to change.
   * @param int $n
   *   How many seconds in the future to set the time field.
   *
   * @When I set :field to :n second(s) from now
   */
  public function setFutureTimeInSeconds($field, $n) {
    $this->setFutureTime($field, "00:00:00:$n");
  }

  /**
   * Sets a time field to a certain number of seconds in the past.
   *
   * @param string $field
   *   The field to change.
   * @param int $n
   *   How many seconds in the past to set the time field.
   *
   * @When I set :field to :n second(s) ago
   */
  public function setPastTimeInSeconds($field, $n) {
    $this->setPastTime($field, "00:00:00:$n");
  }

  /**
   * Runs cron by invoking the /cron URL.
   *
   * @When I run cron over HTTP
   */
  public function cron() {
    $cron_key = \Drupal::state()->get('system.cron_key');

    $this->visitPath('/cron/' . $cron_key);
  }

  /**
   * Asserts that a number of elements match a CSS selector.
   *
   * @param string $selector
   *   The selector.
   * @param int $n
   *   The number of elements expected to match the selector.
   *
   * @Then exactly :n element(s) should match :selector
   */
  public function matchExactly($selector, $n) {
    $this->assertSession()
      ->elementsCount('css', $selector, $n);
  }

}
