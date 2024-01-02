<?php

use UploadThing\Utils;

describe('utils', function() {
  test('exponential', function() {
    function cb() {
      return 'test';
    }

    global $try;
    $try = 0;
    $cb_one = function() {
      global $try;

      if ($try < 2) {
        $try++;

        return null;
      }


      return 'test';
    };

    expect(Utils::withExponentialBackoff('cb'))->toBe('test');
    expect(Utils::withExponentialBackoff($cb_one))->toBe('test');
  });
});