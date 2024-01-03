<?php

namespace UploadThing\Structs;

class FileListEntry
{
  public function __construct(
    public string $id,
    public string $key,
  ) {
  }
}
