<?php
namespace Cabanga\CoioteTurbo\Core;

use Illuminate\Http\Request as LaravelRequest;
use Swoole\Http\Request as SwooleRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
//use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\InputBag as ParameterBag;
class RequestBridge
{
    /**
     * Converts a Swoole request to a Laravel request.
     * @param \Swoole\Http\Request $swooleRequest
     * @return \Illuminate\Http\Request
     */
//    public static function convert(SwooleRequest $swooleRequest): LaravelRequest
//    {
//        $server = [];
//        foreach ($swooleRequest->server ?? [] as $key => $value) {
//            $server[strtoupper($key)] = $value;
//        }
//
//        foreach ($swooleRequest->header ?? [] as $key => $value) {
//            $server['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
//        }
//
//        // Create the initial Laravel request.
//        $laravelRequest = LaravelRequest::create(
//            $swooleRequest->server['request_uri'] ?? '/',
//            $swooleRequest->server['request_method'] ?? 'GET',
//            $swooleRequest->get ?? [], // Use GET here initially for the query string.
//            $swooleRequest->cookie ?? [],
//            self::convertSwooleFiles($swooleRequest->files ?? []), // Use the robust file converter.
//            $server,
//            $swooleRequest->rawContent() ?? null
//        );
//
//        // For non-GET requests, the request body becomes the source of input data.
//        // Laravel's Request class is smart enough to handle JSON decoding automatically
//        // if the content-type header is set correctly. For other types like form-urlencoded
//        // in PUT/PATCH requests, we may need to manually populate the 'request' bag.
//        if ($laravelRequest->method() !== 'GET') {
//            // For standard form posts, use the $swooleRequest->post property.
//            if (!empty($swooleRequest->post)) {
//                $laravelRequest->request = new ParameterBag($swooleRequest->post);
//            }
//            // If it's not a JSON request and has raw content, it might be a PUT/PATCH form.
//            elseif (!$laravelRequest->isJson() && $laravelRequest->getContent()) {
//                $data = [];
//                parse_str($laravelRequest->getContent(), $data);
//                $laravelRequest->request = new ParameterBag($data);
//            }
//        }
//
//        return $laravelRequest;
//    }


    public static function convert(SwooleRequest $swooleRequest): LaravelRequest
    {
        $server = [];
        foreach ($swooleRequest->server ?? [] as $key => $value) {
            $server[strtoupper($key)] = $value;
        }

        // Intelligently map headers to the server array. THIS IS A KEY FIX.
        // This logic correctly handles special CGI headers like Content-Type and Content-Length,
        // which solves the isJson() issue and improves overall compatibility.
        foreach ($swooleRequest->header ?? [] as $key => $value) {
            $key = strtoupper(str_replace('-', '_', $key));

            if ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $server[$key] = $value;
            } else {
                $server['HTTP_' . $key] = $value;
            }
        }

        // Create the initial Laravel Request object.
        $laravelRequest = LaravelRequest::create(
            $swooleRequest->server['request_uri'] ?? '/',
            $swooleRequest->server['request_method'] ?? 'GET',
            $swooleRequest->get ?? [],
            $swooleRequest->cookie ?? [],
            self::convertSwooleFiles($swooleRequest->files ?? []), // We assume convertSwooleFiles exists
            $server,
            $swooleRequest->rawContent() ?? null
        );

        // Populate the 'request' bag for POST, PUT, PATCH, DELETE methods.
        // Laravel's Request object automatically decodes JSON from raw content,
        // so we only need to handle urlencoded form data manually.
        if ($laravelRequest->method() !== 'GET' && !$laravelRequest->isJson()) {
            $data = [];
            if ($laravelRequest->isMethod('POST')) {
                // For standard POST requests, the data is in the 'post' property.
                $data = $swooleRequest->post ?? [];
            } else {
                // For PUT, PATCH, DELETE requests, data must be parsed from the raw body.
                parse_str($swooleRequest->rawContent() ?? '', $data);
            }

            // Use the correct InputBag class to avoid TypeErrors.
            $laravelRequest->request = new ParameterBag($data);
        }

        return $laravelRequest;
    }


    /**
     * Converts the Swoole file array into an array of UploadedFile objects.
     * @param array $swooleFiles
     * @return array
     */
    protected static function convertSwooleFiles(array $swooleFiles): array
    {
        $laravelFiles = [];
        foreach ($swooleFiles as $key => $file) {
            if (isset($file['tmp_name'])) {
                $laravelFiles[$key] = new UploadedFile(
                    $file['tmp_name'],
                    $file['name'] ?? 'unknown',
                    $file['type'] ?? null,
                    $file['error'] ?? UPLOAD_ERR_OK,
                    true // Mark as a test file, which prevents move_uploaded_file issues.
                );
            }
        }
        return $laravelFiles;
    }
}