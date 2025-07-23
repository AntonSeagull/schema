<?php

namespace Shm\ShmRPC\ShmRPCClient;

use Exception;

class ShmRPCClient
{
    private  string $endpoint = '';

    private  string $token = '';
    private  string $apikey = '';

    public  function endpoint(string $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    public  function token(string $token): void
    {
        $this->token = $token;
    }

    public  function apiKey(string $apikey): void
    {
        $this->apikey = $apikey;
    }




    public  function call(string $method, array $params = []): mixed
    {


        $request = [
            'method' => $method,
            'params' =>  $params,
            'token' => $this->token,
            'apikey' => $this->apikey,
        ];

        $options = [
            CURLOPT_URL => $this->endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request),
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new Exception("cURL error: $err");
        }

        $json = json_decode($response, true);


        if (isset($json['error'])) {
            throw new Exception($json['error']['message'] ?? 'Unknown error');
        }

        return $json['result'] ?? null;
    }

    public  function callFormData(string $method, array $formData): mixed
    {

        $formData['method'] = $method;


        foreach ($formData as $key => $value) {
            if (is_array($value) && isset($value['tmp_name'], $value['name'])) {
                $formData[$key] = new \CURLFile($value['tmp_name'], $value['type'], $value['name']);
            } elseif (is_array($value) || is_object($value)) {
                $formData[$key] = json_encode($value);
            }
        }

        if ($this->token) {
            $formData['token'] = $this->token;
        }
        if ($this->apikey) {
            $formData['apikey'] = $this->apikey;
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $formData,
            CURLOPT_HTTPHEADER => [],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new \Exception("cURL error: $err");
        }

        $json = json_decode($response, true);


        if (isset($json['error'])) {


            throw new \Exception($json['error']['message'] ?? 'Unknown error');
        }

        return $json['result'] ?? null;
    }
}
