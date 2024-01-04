<?php

use Illuminate\Http\UploadedFile;
use UploadThing\Structs\FileListEntry;
use UploadThing\Structs\FilesList;
use UploadThing\Structs\FileUrlList;
use UploadThing\Structs\UploadedData;
use UploadThing\Structs\UsageInfo;
use UploadThing\UploadThing;

global $client, $list;
$client = new UploadThing(getenv('UPLOADTHING_API_KEY'));
$list = [];

function getFile($name, $type) {
  return new UploadedFile(__DIR__ . '/' . $name, $name, $type, null, true);
}

describe('creating', function () {
  test('text file', function () {
    global $client, $list;

    $file = getFile('upload_test.txt', 'text/plain');
    $res = $client->upload($file, [
      'userId' => '1234',
    ]);

    expect($res)->toBeArray();
    expect($res[0])->toBeInstanceOf(UploadedData::class);
    expect($res[0]->name)->toBe('upload_test.txt');

    $content = $client->http->request('GET', $res[0]->url);

    expect(trim($content->getBody()))->toBe(trim($file->get()));

    $list[] = $res[0];
  });

  test('image file', function () {
    global $client, $list;

    $file = getFile('upload_image.jpg', 'image/jpg');
    $res = $client->upload($file);

    expect($res)->toBeArray();
    expect($res[0])->toBeInstanceOf(UploadedData::class);
    expect($res[0]->name)->toBe('upload_image.jpg');

    $list[] = $res[0];
  });

  test('big image', function() {
    global $client, $list;

    $file = getFile('upload_big_image.png', 'image/png');
    $res = $client->upload($file);

    expect($res)->toBeArray();
    expect($res[0])->toBeInstanceOf(UploadedData::class);
    expect($res[0]->name)->toBe('upload_big_image.png');

    $list[] = $res[0];
  });

  test('all', function() {
    global $client, $list;

    $file1 = getFile('upload_test.txt', 'text/plain');
    $file2 = getFile('upload_image.jpg', 'image/jpg');
    $file3 = getFile('upload_big_image.png', 'image/png');

    $res = $client->upload([
      $file1,
      $file2,
      $file3,
    ]);

    expect($res)->toBeArray();
    expect($res)->toHaveLength(3);

    expect($res[0])->toBeInstanceOf(UploadedData::class);
    expect($res[1])->toBeInstanceOf(UploadedData::class);
    expect($res[2])->toBeInstanceOf(UploadedData::class);

    expect($res[0]->name)->toBe('upload_test.txt');
    expect($res[1]->name)->toBe('upload_image.jpg');
    expect($res[2]->name)->toBe('upload_big_image.png');
    
    $list[] = $res[0];
    $list[] = $res[1];
    $list[] = $res[2];
  });
});


describe('reading', function () {
  test('list', function () {
    global $client;

    $res = $client->listFiles();

    expect($res)->toBeInstanceOf(FilesList::class);

    if (count($res->files) > 0) {
      expect($res->files[0])->toBeInstanceOf(FileListEntry::class);
      expect($res->files[0]->id)->toBeString();
      expect($res->files[0]->key)->toBeString();
    }
  });

  test('file urls', function () {
    global $client, $list;

    $data = array_map(function ($list) {
      return $list->key;
    }, $list);

    $res = $client->getFileUrls($data);

    expect($res)->toBeArray();
    expect($res[0])->toBeInstanceOf(FileUrlList::class);
  });
});

describe('updating', function () {
  test('rename', function () {
    global $list, $client;

    $data = array_map(function ($list) {
      $ext = pathinfo($list->key, PATHINFO_EXTENSION);

      return [
        'fileKey' => $list->key,
        'newName' => 'test_rename-' . time() . '.' . $ext,
      ];
    }, $list);

    $res = $client->renameFiles($data);
    expect($res)->toBeBool();
  });
});

describe('deleting', function () {
  test('delete', function () {
    global $list, $client;

    $data = array_map(function ($list) {
      return $list->key;
    }, $list);

    $res = $client->deleteFiles($data);
    expect($res)->toBeBool();
  });
});

test('usage info', function () {
  global $client;

  $res = $client->getUsageInfo();

  expect($res)->toBeInstanceOf(UsageInfo::class);
});
