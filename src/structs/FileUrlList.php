<?php

namespace UploadThing\Structs;

class FileUrlList
{
  public function __construct(public string $key, public string $url)
  {
  }

  public static function fromArray(array $data)
  {
    return new FileUrlList(
      $data['key'],
      $data['url']
    );
  }
}
