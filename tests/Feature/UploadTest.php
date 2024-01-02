<?php

use Illuminate\Http\UploadedFile;
use UploadThing\UploadThing;

global $client;
$client = new UploadThing(getenv('UPLOADTHING_API_KEY'));

describe('uploading', function() {
  test('text file', function() {
    global $client;

    $file = new UploadedFile(__DIR__ . '/upload_test.txt', 'upload_test.txt', 'text/plain', null, true);

    $res = $client->upload($file, [
      'userId' => '1234',
    ]);

    expect($res)->toBeArray();
    expect($res[0])->toBeArray();
    expect($res[0]['name'])->toBe('upload_test.txt');

    $content = $client->http->request('GET', $res[0]['url']);

    expect(trim($content->getBody()))->toBe(trim($file->get()));
  });
});