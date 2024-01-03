<?php

namespace UploadThing\Structs;

class FilesList
{
  public function __construct(
    public array $files,
    public bool $hasMore,
  ) {
    $this->files = array_map(function (array $data) {
      return new FileListEntry($data['id'], $data['key']);
    }, $files);
  }

  public static function fromArray(array $data)
  {
    return new FilesList(
      $data['files'],
      $data['hasMore'],
    );
  }
}
