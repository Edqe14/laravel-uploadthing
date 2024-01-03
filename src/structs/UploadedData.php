<?php

namespace UploadThing\Structs;

class UploadedData
{
  public function __construct(
    public string $key,
    public string $url,
    public string $name,
    public int $size,
  ) {
  }
}
