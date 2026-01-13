<?php

namespace Shm\ShmRPC\ShmRPCCodeGen;


use Shm\ShmUtils\Doctor;
use Shm\ShmUtils\ShmInit;
use Shm\ShmUtils\ShmTwig;

class ShmRPCCodeGen
{

    public static array $tsTypes = [];



    public static function html(array $schema, $json = false)
    {







        $requestsData = [];

        $linkRequest = [];

        $keysGraph = [];
        foreach ($schema  as $key => $field) {


            if ($_GET['method'] ?? null) {
                if ($key !== $_GET['method']) {
                    continue;
                }
            }



            $ignore = $field['ignore'] ?? false;
            if ($ignore) {

                continue;
            }

            //     $keysGraph[$key . 'type'] = $field['type']->getKeysGraph();

            //   if (isset($field['args'])) {
            //       $keysGraph[$key . 'args'] = $field['args']->getKeysGraph();
            //  }
            //         exit;

            $requestsData[$key] = (new ShmRPCRequestCode($field['type'], $field['args'] ?? null, $key, $field['formData'] ?? null))->initialize();

            $linkRequest[$key] = "export const " . $key . " = rpc." . $key . ";";
        }

        //echo json_encode($keysGraph);
        // exit;





        //Сортируем  ksort(TSType::$tsTypes);
        ksort(TSType::$tsTypes);
        ksort($requestsData);



        $allTypesKeys = array_keys(TSType::$tsTypes);

        $types = implode("\n", array_values(TSType::$tsTypes));

        $requests =   array_values($requestsData);
        $linkRequest = array_values($linkRequest);


        $requests = [
            "import { rpcClient } from './rpcClient';",
            "import { RpcResponse, " . implode(',', $allTypesKeys) . " } from './types';",

            'export const rpc = {',
            ...$requests,
            '};',
            ...$linkRequest,
        ];

        $requests = implode("\n", $requests);

        $files = [];

        if (($_SERVER['SERVER_NAME'] ?? null) == "localhost") {
            $REQUEST_URI = $_SERVER['REQUEST_URI'];

            //Убарем GET параметры
            $REQUEST_URI = explode('?', $REQUEST_URI)[0];
            //Все / заменяем на _
            $REQUEST_URI = str_replace('/', '_', $REQUEST_URI);


            $dir =  ShmInit::$rootDir . '/schema_history/' . $REQUEST_URI;



            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $dirTypes = $dir . '/types';
            if (!is_dir($dirTypes)) {
                mkdir($dirTypes, 0777, true);
            }

            $dirRequests = $dir . '/requests';
            if (!is_dir($dirRequests)) {
                mkdir($dirRequests, 0777, true);
            }


            foreach (TSType::$tsTypes as $key => $type) {

                file_put_contents($dirTypes . '/' . $key . '.tmp', $type);
            }

            foreach ($requestsData as $key => $request) {
                file_put_contents($dirRequests . '/' . $key . '.tmp', $request);
            }
        }

        $types = "export interface RpcError {
  type: 'UNAUTHORIZED' | 'VALIDATION_ERROR' | 'NOT_FOUND' | 'INTERNAL_ERROR' | 'FORBIDDEN' | 'RATE_LIMITED' | string;
  message: string;
  code?: number;
}

export interface RpcResponse<T = unknown> {
  success: boolean;
  result: T | null;
  error: RpcError | null;
}" . "\n\n" . $types;



        if ($json) {

            echo json_encode([
                'types' => $types,
                'requests' => $requests
            ]);
            exit;
        }


        header('Content-Type: text/html; charset=utf-8');

        // Escape backticks for JavaScript template literals
        $typesEscaped = str_replace('`', '\`', $types);
        $requestsEscaped = str_replace('`', '\`', $requests);

        echo ShmTwig::render('@shm/codegen', [
            'types' => $typesEscaped,
            'requests' => $requestsEscaped,
        ]);

        exit;
    }
}