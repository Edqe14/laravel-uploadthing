<?php

use Illuminate\Http\UploadedFile;
use UploadThing\Structs\FileList;
use UploadThing\Structs\FileListEntry;
use UploadThing\Structs\UploadedData;
use UploadThing\UploadThing;

global $client;
$client = new UploadThing(getenv('UPLOADTHING_API_KEY'));

describe('creating', function() {
  test('text file', function() {
    global $client;

    $file = new UploadedFile(__DIR__ . '/upload_test.txt', 'upload_test.txt', 'text/plain', null, true);

    $res = $client->upload($file, [
      'userId' => '1234',
    ]);

    expect($res)->toBeArray();
    expect($res[0])->toBeInstanceOf(UploadedData::class);
    expect($res[0]->name)->toBe('upload_test.txt');

    $content = $client->http->request('GET', $res[0]->url);

    expect(trim($content->getBody()))->toBe(trim($file->get()));
  });

  test('image file', function () {
    global $client;

    $file = new UploadedFile(__DIR__ . '/upload_image.jpg', 'upload_image.jpg', 'image/jpg', null, true);

    $res = $client->upload($file);

    expect($res)->toBeArray();
    expect($res[0])->toBeInstanceOf(UploadedData::class);
    expect($res[0]->name)->toBe('upload_image.jpg');
  });
});


describe('reading', function() {
  test('list', function() {
    global $client, $list;
    
    $res = $client->listFiles();

    expect($res)->toBeInstanceOf(FileList::class);

    if (count($res->files) > 0) {
      expect($res->files[0])->toBeInstanceOf(FileListEntry::class);
      expect($res->files[0]->id)->toBeString();
      expect($res->files[0]->key)->toBeString();
    }

    $list = $res->files;
  });
});

describe('updating', function() {
  test('rename', function() {
    global $list, $client;

    $data = array_map(function (FileListEntry $list) {
      $ext = pathinfo($list->key, PATHINFO_EXTENSION);

      return [
        'fileKey' => $list->key,
        'newName' => 'test_rename-' . time() . '.' . $ext,
      ];
    }, $list);

    $res = $client->renameFiles($data);
    expect($res)->toBeArray();
    expect($res['success'])->toBeBool();
  });
});

describe('deleting', function() {
  test('delete', function() {
    global $list, $client;

    $data = array_map(function (FileListEntry $list) {
      return $list->key;
    }, $list);

    $res = $client->deleteFiles($data);
    expect($res)->toBeArray();
    expect($res['success'])->toBeBool();
  });
});