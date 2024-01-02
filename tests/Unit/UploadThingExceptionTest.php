<?php

use UploadThing\Structs\UploadThingException;

test('get code from status code', function() {
  expect(UploadThingException::getErrorCodeFromStatus(403))->toBe('FORBIDDEN');
  expect(UploadThingException::getErrorCodeFromStatus(404))->toBe('NOT_FOUND');
  expect(UploadThingException::getErrorCodeFromStatus(413))->toBe('TOO_LARGE');
});