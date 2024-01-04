<?php

namespace UploadThing;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise;
use Illuminate\Http\UploadedFile;
use Psr\Http\Message\ResponseInterface;
use UploadThing\Structs\FilesList;
use UploadThing\Structs\FileUrlList;
use UploadThing\Structs\UploadedData;
use UploadThing\Structs\UploadThingException;
use UploadThing\Structs\UsageInfo;

class UploadThing
{
    public HttpClient $http;

    public function __construct(private string $apiKey)
    {
        $this->http = new HttpClient($this->apiKey);
    }

    /**
     * The function `upload` takes in one or multiple files, along with optional metadata and content
     * disposition, and uploads them to a server using a POST request.
     * 
     * @param UploadedFile|UploadedFile[] $files The `files` parameter can accept either an `UploadedFile` object or an
     * array of `UploadedFile` objects. An `UploadedFile` object represents a file that has been
     * uploaded through a form.
     * @param array $metadata The `metadata` parameter is an optional array that allows you to provide
     * additional information or attributes about the uploaded file(s). This can be useful for
     * categorizing or organizing the files, adding tags, or storing any other relevant data associated
     * with the files. The metadata array can contain key-value pairs where the
     * @param string $contentDisposition The `contentDisposition` parameter is used to specify how the
     * uploaded file should be handled by the server. It can have two possible values:
     * - `inline` - The file should be displayed inline in the browser, if possible.
     * - `attachment` - The file should be downloaded and saved locally.
     * 
     * @return UploadedData[] Uploaded files data
     */
    public function upload(UploadedFile|array $files, array $metadata = [], string $contentDisposition = 'inline')
    {
        $files = is_array($files) ? $files : [$files];

        // request presigned url
        $fileData = array_map(function (UploadedFile $file) {
            return [
                "name" => $file->getClientOriginalName(),
                "size" => $file->getSize(),
                "type" => $file->getMimeType(),
            ];
        }, $files);

        $res = $this->http->request('POST', '/api/uploadFiles', [
            "body" => json_encode([
                "files" => $fileData,
                "metadata" => $metadata,
                "contentDisposition" => $contentDisposition,
            ]),
        ]);

        if ($res->getReasonPhrase() !== 'OK') {
            throw UploadThingException::fromResponse($res);
        }

        $json = json_decode($res->getBody(), true);

        if (isset($json['error']) && !empty($json['error'])) {
            throw UploadThingException::fromResponse($res);
        }

        $uploads = array_map(function ($file, $i) use ($json, $contentDisposition) {
            return $this->uploadFile($file, $json['data'][$i], $contentDisposition);
        }, $files, array_keys($files));

        return $uploads;
    }

    /**
     * The function `deleteFiles` deletes one or more files by sending a POST request to an API
     * endpoint and returns the response as JSON.
     * 
     * @param string|string[] $keys The parameter `keys` can be either a string or an array of strings. It represents the
     * file keys that need to be deleted. If it is a string, it will be converted to an array with a
     * single element. If it is an array, it will be used as is.
     * 
     * @return bool The response from the API endpoint as JSON.
     */
    public function deleteFiles(string|array $keys)
    {
        if (!is_array($keys)) $keys = [$keys];

        $res = $this->requestUT('/api/deleteFile', [
            "fileKeys" => $keys,
        ], 'An unknown error occured while deleting files.');

        return $res['success'];
    }

    /**
     * The function `listFiles` sends a request to the `/api/listFiles` endpoint with optional limit
     * and offset parameters to retrieve a list of files.
     * 
     * @param ?int $limit The "limit" parameter is used to specify the maximum number of files to be returned
     * in the list. It is an optional parameter, so if it is not provided or set to null, there will be
     * no limit on the number of files returned.
     * @param ?int $offset The offset parameter is used to specify the starting point or position in a list
     * of files. It determines the number of files to skip before starting to return the files. For
     * example, if the offset is set to 10, the function will start returning files from the 11th file
     * onwards.
     * 
     * @return FilesList List of files
     */
    public function listFiles(?int $limit = null, ?int $offset = null)
    {
        $data = [];
        if ($limit !== null) $data['limit'] = $limit;
        if ($offset !== null) $data['offset'] = $offset;

        $res = $this->requestUT('/api/listFiles', (object) $data, 'An unknown error occured while listing files.');

        return FilesList::fromArray($res);
    }

    /**
     * The function `renameFiles` sends a request to rename files using the provided updates and
     * returns an error message if an unknown error occurs.
     * 
     * @param array $updates An array of updates to be made to the files. Each update should be an
     * associative array with the following keys:
     * - `fileKey` - The key of the file to be renamed.
     * - `newName` - The new name of the file.
     * 
     * @return bool The response from the API endpoint as JSON.
     */
    public function renameFiles(array $updates)
    {
        $res = $this->requestUT('/api/renameFile', [
            "updates" => $updates,
        ], 'An unknown error occured while renaming files.');

        return $res['success'];
    }

    /**
     * The getFileUrls function takes in a string or an array of file keys and returns the file URLs.
     * 
     * @param string|string[] keys The parameter "keys" can be either a string or an array. It represents the
     * file keys for which you want to retrieve the file URLs. If it is a string, it will be converted
     * to an array with a single element.
     * 
     * @return FileUrlList[] the file URLs for the given keys.
     */
    public function getFileUrls(string|array $keys)
    {
        if (!is_array($keys)) $keys = [$keys];

        $res = $this->requestUT('/api/getFileUrl', [
            "fileKeys" => $keys,
        ], 'An unknown error occured while getting file URLs.');

        return array_map(function ($file) {
            return FileUrlList::fromArray($file);
        }, $res['data']);
    }

    /**
     * The function retrieves usage information from an API and returns it as a UsageInfo object.
     * 
     * @return UsageInfo an instance of the UsageInfo class, which is created from the response received from the
     * requestUT method.
     */
    public function getUsageInfo()
    {
        $res = $this->requestUT('/api/getUsageInfo', [], 'An unknown error occured while getting usage info.');

        return UsageInfo::fromArray($res);
    }

    /**
     * @throws UploadThingException
     */
    private function requestUT(string $path, mixed $data = [], $fallbackErr = "An unknown error occured.")
    {
        $res = $this->http->request('POST', $path, [
            "body" => json_encode($data),
        ]);

        $json = json_decode($res->getBody(), true);

        if ($res->getReasonPhrase() !== 'OK' || (isset($json['error']) && !empty($json['error']))) {
            throw new UploadThingException($json['error'] ?? $fallbackErr, 'INTERNAL_SERVER_ERROR');
        }

        return $json;
    }

    private function uploadFile(UploadedFile $file, array $data, string $contentDisposition = 'inline')
    {
        [
            'presignedUrls' => $presignedUrls,
            'key' => $key,
            'fileUrl' => $fileUrl,
            'uploadId' => $uploadId,
            'chunkSize' => $chunkSize
        ] = $data;

        if (empty($presignedUrls) || !is_array($presignedUrls)) {
            throw new UploadThingException('Failed to generate presigned URL', 'URL_GENERATION_FAILED');
        }

        $handle = fopen($file->getPathname(), 'r');
        if (!$handle) {
            throw new UploadThingException('Failed to open file', 'FILE_OPEN_FAILED');
        }

        $etags = array_map(function ($url, $i) use ($handle, $chunkSize, $key, $file, $contentDisposition) {
            $data = fread($handle, $chunkSize);

            if ($data === false) {
                fclose($handle);
                throw new UploadThingException('Failed to read file', 'FILE_READ_FAILED');
            }

            return $this->uploadPart($url, $data, $key, $file->getMimeType(), $contentDisposition, $file->getClientOriginalName())
                ->then(function ($etag) use ($i) {
                    return [
                        "tag" => $etag,
                        "partNumber" => $i + 1,
                    ];
                });
        }, $presignedUrls, array_keys($presignedUrls));

        fclose($handle);
        unset($handle);

        $etags = Promise\Utils::unwrap($etags);

        $this->http->request('POST', '/api/completeMultipart', [
            "body" => json_encode([
                "fileKey" => $key,
                "uploadId" => $uploadId,
                "etags" => $etags,
            ]),
        ]);

        $this->pollForFileData("/api/pollUpload/" . $key);

        return new UploadedData($key, $fileUrl, $file->getClientOriginalName(), $file->getSize());
    }

    private function pollForFileData(string $url)
    {
        return Utils::withExponentialBackoff(function () use ($url) {
            $res = $this->http->request('GET', $url);
            $maybeJson = json_decode($res->getBody(), true);

            if ($maybeJson === null) {
                return null;
            }

            if ($maybeJson['status'] !== 'done') {
                return null;
            }

            return true;
        });
    }

    private function uploadPart(string $url, string $data, string $key, string $type, string $contentDisposition, string $fileName, int $retry = 0, int $maxRetries = 5)
    {
        $resPromise = $this->http->requestAsync('PUT', $url, [
            "body" => $data,
            "headers" => [
                "Content-Type" => $type,
                "Content-Disposition" => join('; ', [
                    $contentDisposition,
                    'filename="' . urlencode($fileName) . '"',
                    "filename*=UTF-8''" . urlencode($fileName),
                ]),
            ],
        ]);

        return $resPromise->then(
            function (ResponseInterface $res) {
                if ($res->getReasonPhrase() === 'OK') {
                    $etag = $res->getHeaderLine('Etag');
                    if (empty($etag)) {
                        throw new UploadThingException("Missing Etag header from uploaded part", 'UPLOAD_FAILED');
                    }

                    return preg_replace("/\"/", "", $etag);
                }
            },
            function () use ($retry, $maxRetries, $url, $data, $type, $contentDisposition, $fileName, $key) {
                if ($retry < $maxRetries) {
                    $delay = 2 ** $retry;

                    sleep($delay);

                    return $this->uploadPart($url, $data, $type, $contentDisposition, $fileName, $retry + 1);
                }

                $this->http->requestAsync('POST', '/api/failureCallback', [
                    "body" => json_encode([
                        "fileKey" => $key,
                    ])
                ]);

                throw new UploadThingException("Failed to upload file to storage provider", 'UPLOAD_FAILED');
            }
        );
    }
}
