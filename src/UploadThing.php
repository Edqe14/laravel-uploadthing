<?php

namespace UploadThing;

use Illuminate\Http\UploadedFile;
use UploadThing\Structs\UploadedData;
use UploadThing\Structs\UploadThingException;

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
     * @param UploadedFile $files The `files` parameter can accept either an `UploadedFile` object or an
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
     * @return array<int,UploadedData> Uploaded files data
     */
    public function upload(UploadedFile|array $files, array $metadata = [], string $contentDisposition = 'inline') 
    {
        $files = is_array($files) ? $files : [$files];

        // request presigned url
        $fileData = array_map(function(UploadedFile $file) {
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

        $uploads = array_map(function($file, $i) use ($json, $contentDisposition) {
            return $this->uploadFile($file, $json['data'][$i], $contentDisposition);
        }, $files, array_keys($files));

        return $uploads;
    }

    /**
     * The function `deleteFiles` deletes one or more files by sending a POST request to an API
     * endpoint and returns the response as JSON.
     * 
     * @param string $keys The parameter `keys` can be either a string or an array of strings. It represents the
     * file keys that need to be deleted. If it is a string, it will be converted to an array with a
     * single element. If it is an array, it will be used as is.
     */
    public function deleteFiles(string|array $keys) {
        if (!is_array($keys)) $keys = [$keys];

        $res = $this->http->request('POST', '/api/deleteFile', [
            "body" => json_encode([
                "fileKeys" => $keys,
            ]),
        ]);

        $json = json_decode($res->getBody(), true);

        if ($res->getReasonPhrase() !== 'OK' || (isset($json['error']) && !empty($json['error']))) {
            throw new UploadThingException($json['error'] ?? 'An unknown error occured while deleting files.', 'INTERNAL_SERVER_ERROR');
        }

        return $json;
    }

    private function uploadFile(UploadedFile $file, array $data, string $contentDisposition = 'inline') {
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

        $data = $file->get();
        $etags = array_map(function($url, $i) use ($key, $file, $chunkSize, $data, $contentDisposition) {
            $offset = $chunkSize * $i;
            $end = min($offset + $chunkSize, $file->getSize());
            $chunk = substr($data, $offset, $end);

            $etag = $this->uploadPart($url, $chunk, $key, $file->getMimeType(), $contentDisposition, $file->getClientOriginalName());

            return [
                "tag" => $etag,
                "partNumber" => $i + 1,
            ];
        }, $presignedUrls, array_keys($presignedUrls));

        $this->http->request('POST', '/api/completeMultipart', [
            "body" => json_encode([
                "fileKey" => $key,
                "uploadId" => $uploadId,
                "etags" => $etags,
            ]),
        ]);

        $this->pollForFileData("/api/pollUpload/".$key);

        return new UploadedData($key, $fileUrl, $file->getClientOriginalName(), $file->getSize());
    }

    private function pollForFileData(string $url) {
        return Utils::withExponentialBackoff(function() use ($url) {
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

    private function uploadPart(string $url, string $data, string $key, string $type, string $contentDisposition, string $fileName, int $retry = 0, int $maxRetries = 5) {
        $res = $this->http->request('PUT', $url, [
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

        if ($res->getReasonPhrase() === 'OK') {
            $etag = $res->getHeaderLine('Etag');
            if (empty($etag)) {
                throw new UploadThingException("Missing Etag header from uploaded part", 'UPLOAD_FAILED');
            }

            return preg_replace("/\"/", "", $etag);
        }

        if ($retry < $maxRetries) {
            $delay = 2 ** $retry;

            sleep($delay);

            return $this->uploadPart($url, $data, $type, $contentDisposition, $fileName, $retry + 1);
        }

        $this->http->request('POST', '/api/failureCallback', [
            "body" => json_encode([
                "fileKey" => $key,
            ])
        ]);

        throw new UploadThingException("Failed to upload file to storage provider", 'UPLOAD_FAILED');
    }
}