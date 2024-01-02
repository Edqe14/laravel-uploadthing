<?php

namespace UploadThing;

use Illuminate\Http\UploadedFile;

class UploadThing
{
    public HttpClient $http;

    public function __construct(private string $apiKey)
    {
        $this->http = new HttpClient($this->apiKey);
    }

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

        if ($json['error'] ?? false) {
            throw UploadThingException::fromResponse($res);
        }

        $uploads = array_map(function($file, $i) use ($json, $contentDisposition) {
            return $this->uploadFile($file, $json['data'][$i], $contentDisposition);
        }, $files, array_keys($files));

        return $uploads;
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

        return [
            "key" => $key,
            "url" => $fileUrl,
            "name" => $file->getClientOriginalName(),
            "size" => $file->getSize(),
        ];
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