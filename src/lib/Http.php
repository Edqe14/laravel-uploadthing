<?php

namespace UploadThing;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

class HttpClient
{
  const UT_VERSION = "6.1.0";
  const HOST = 'https://uploadthing.com';

  public array $headers;
  public GuzzleClient $client;

  public function __construct(string $apiKey)
  {
    $this->headers = [
      "Content-Type" => "application/json",
      "x-uploadthing-api-key" => $apiKey,
      "x-uploadthing-version" => self::UT_VERSION,
    ];

    $this->client = new GuzzleClient([
      "base_uri" => self::HOST,
    ]);
  }

  /**
   * The function "request" sends an HTTP request using the specified method, path, and options.
   * 
   * @param string $method The method parameter is a string that represents the HTTP method to be used
   * for the request. This can be "GET", "POST", "PUT", "PATCH", "DELETE", etc.
   * @param string $path The "path" parameter is a string that represents the URL path or endpoint that
   * you want to make a request to. It typically includes the domain name and any additional path
   * segments that are required to access the desired resource. For example, if you want to make a
   * request to the "/users" endpoint
   * @param array $options The `options` parameter is an array that can contain additional options for
   * the request. These options can include things like query parameters, request body, authentication
   * credentials, and more. By passing this array as an argument to the `request` method, you can
   * customize the behavior of the request.
   * 
   * @throws GuzzleException
   */
  public function request(string $method, string $path, array $options = [])
  {
    $options["headers"] = $this->headers;
    $options["cache"] = "no-store";

    return $this->client->request($method, $path, $options);
  }

  /**
   * The function generates a URL by concatenating the host and the given path.
   * 
   * @param string $path A string that represents the path or endpoint of a URL. It is
   * used to construct a complete URL by appending it to the host.
   * 
   * @return string the concatenation of the static variable `$host` and the parameter `$path`.
   */
  public static function generateUrl(string $path)
  {
    return self::HOST . $path;
  }
}
