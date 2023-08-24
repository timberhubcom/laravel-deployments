<?php

namespace Timberhub\Helpers;

class HTTPRequest {

    /**
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return array
     */
    public static function post(string $url, array $data = [], array $headers = []): array {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return [
            'httpCode' => $httpCode,
            'response' => $response,
        ];
    }

    /**
     * @param string $url
     * @param array $headers
     * @return array
     */
    public static function get(string $url, array $headers = []): array {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return [
            'httpCode' => $httpCode,
            'response' => $response,
        ];
    }
}
