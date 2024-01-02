<?php

namespace UploadThing\Structs;

class FileList {
  public function __construct(
    public array $files,
    public bool $hasMore,
  ) {
    $this->files = array_map(function (array $data) {
      return new FileListEntry($data['id'], $data['key']);
    }, $files);
  }
}