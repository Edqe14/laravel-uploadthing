<?php

namespace UploadThing;

class Utils {
  public static $host = 'https://uploadthing.com';

  /**
   * The function generates a URL by concatenating the host and the given path.
   * 
   * @param string $path A string that represents the path or endpoint of a URL. It is
   * used to construct a complete URL by appending it to the host.
   * 
   * @return string the concatenation of the static variable `$host` and the parameter `$path`.
   */
  public static function generateUrl(string $path) {
    return self::$host . $path;
  }

  /**
   * The function `withExponentialBackoff` retries a given callback function with an increasing delay
   * between retries, up to a maximum number of retries and maximum delay time.
   * 
   * NOTE: Please return a non-empty value from the callback function
   * or the function will continue to retry until it reaches the maximum number of retries.
   * 
   * @param callable $cb The `cb` parameter is a callable function or method that will be executed
   * within the `withExponentialBackoff` function. It is the code that you want to retry with
   * exponential backoff.
   * @param int $maxRetries The `maxRetries` parameter specifies the maximum number of times the
   * callback function will be retried before giving up.
   * @param int $maxMs The `maxMs` parameter represents the maximum backoff time in milliseconds. It
   * determines the maximum amount of time to wait between retries. In the provided code, the `maxMs`
   * value is set to `64 * 1000`, which means the maximum backoff time is 64 seconds.
   * @param int $baseMs The baseMs parameter represents the initial delay in milliseconds before the
   * first retry attempt.
   * 
   * @return the result of the callback function.
   */
  public static function withExponentialBackoff(
    callable $cb,
    int $maxRetries = 5,
    int $maxMs = 64 * 1000,
    int $baseMs = 250
  ) {
    $tries = 0;
    $result = null;
    $backoffMs = $baseMs;

    while ($tries < $maxRetries) {
      $result = $cb();
      if (!empty($result)) return $result;

      $tries++;
      $backoffMs = min($maxMs, $backoffMs * 2);

      sleep($backoffMs / 1000);
    }

    return $result;
  }
}