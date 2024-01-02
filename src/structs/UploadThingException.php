<?php

namespace UploadThing\Structs;

use Psr\Http\Message\ResponseInterface;

class UploadThingException extends \Exception
{
  const ERROR_CODES = [
    // Generic
    "BAD_REQUEST" => 400,
    "NOT_FOUND" => 404,
    "FORBIDDEN" => 403,
    "INTERNAL_SERVER_ERROR" => 500,
    "INTERNAL_CLIENT_ERROR" => 500,

    // S3 specific
    "TOO_LARGE" => 413,
    "TOO_SMALL" => 400,
    "TOO_MANY_FILES" => 400,
    "KEY_TOO_LONG" => 400,

    // UploadThing specific
    "URL_GENERATION_FAILED" => 500,
    "UPLOAD_FAILED" => 500,
    "MISSING_ENV" => 500,
    "FILE_LIMIT_EXCEEDED" => 500,
  ];

  public function __construct($message, $code = 0, \Throwable $previous = null)
  {
    $message = "[UT] " . $message;

    parent::__construct($message, $code, $previous);
  }

  public static function fromResponse(ResponseInterface $response)
  {
    $jsonOrNull = json_decode($response->getBody(), true);

    if ($jsonOrNull === null) {
      return new self($response->getReasonPhrase(), self::getErrorCodeFromStatus($response->getStatusCode()));
    }

    $message = $jsonOrNull["message"] ?? $jsonOrNull["error"] ?? $response->getReasonPhrase();

    return new self($message, self::getErrorCodeFromStatus($response->getStatusCode()));
  }

  public static function getErrorCodeFromStatus(int $status)
  {
    $errorCode = array_search($status, self::ERROR_CODES);

    if ($errorCode === false) {
      return "INTERNAL_SERVER_ERROR";
    }

    return $errorCode;
  }
}
