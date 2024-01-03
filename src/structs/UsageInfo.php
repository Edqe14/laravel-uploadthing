<?php

namespace UploadThing\Structs;

class UsageInfo
{
  public function __construct(
    public int $totalBytes,
    public ?string $totalReadable,
    public int $appTotalBytes,
    public ?string $appTotalReadable,
    public int $filesUploaded,
    public int $limitBytes,
    public ?string $limitReadable
  ) {
  }

  public static function fromArray(array $data)
  {
    return new UsageInfo(
      $data['totalBytes'],
      $data['totalReadable'] ?? null,
      $data['appTotalBytes'],
      $data['appTotalReadable'] ?? null,
      $data['filesUploaded'],
      $data['limitBytes'],
      $data['limitReadable'] ?? null
    );
  }
}
