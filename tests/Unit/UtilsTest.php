<?php

use UploadThing\Utils;

describe('utils', function() {
  test('generate url', function() {
    expect(Utils::generateUrl('/test'))->toBe('https://uploadthing.com/test');
    expect(Utils::generateUrl('/test/123'))->toBe('https://uploadthing.com/test/123');
  });

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