<?php

namespace Shm\ShmRPC\ShmRPCCodeGen;


use Shm\ShmUtils\Doctor;
use Shm\ShmUtils\ShmInit;

class ShmRPCCodeGen
{

    public static array $tsTypes = [];

    public static function php(array $schema)
    {



        $REQUEST_URI = $_SERVER['REQUEST_URI'];

        //Убарем GET параметры
        $REQUEST_URI = explode('?', $REQUEST_URI)[0];
        $baseEndpoint = $REQUEST_URI;
        //Все / заменяем на _
        $REQUEST_URI = str_replace('/', '_', $REQUEST_URI);


        $dir =  ShmInit::$rootDir . '/_php_codegen/' . $REQUEST_URI . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }




        $classFullName = "ApiService";

        $class = new \Nette\PhpGenerator\ClassType($classFullName);




        $class->addProperty('endpoint')
            ->setStatic()
            ->setVisibility('private')
            ->setType('string')
            ->setValue('http://localhost:8888/' . $baseEndpoint);

        $method = $class->addMethod('request');
        $method->setStatic();
        $method->setVisibility('private');
        $method->setReturnType('array');
        $method->addParameter('method')->setType('string');
        $method->addParameter('params')->setType('array');
        $method->setBody('
        $client = new \GuzzleHttp\Client();
        $headers = [
            \'Content-Type\' => \'application/json\'
        ];
        $body = json_encode([
            \'method\' => $method,
            \'params\' => $params,
        ]);
        $request = new \GuzzleHttp\Psr7\Request(\'POST\', self::$endpoint, $headers, $body);
        $res = $client->sendAsync($request)->wait();
        $responseBody = $res->getBody();
        $response = json_decode($responseBody, true);
        return $response;
        ');


        foreach ($schema  as $key => $field) {


            $method = $class->addMethod($key);
            $method->setStatic();

            $paramsKeys = array_keys($field['args']->items ?? []);

            $method->setBody('
            return self::request(\'' . $key . '\', [' .  implode(', ', array_map(fn($k) => '"' . $k . '" => $' . $k, $paramsKeys)) . ']);
            ');
            $method->setReturnType('array');
            if ($field['args'] ?? null) {

                foreach ($field['args']->items as $argKey => $arg) {
                    $param = $method->addParameter($argKey);
                    $param->setType('mixed');
                    if ($arg->default ?? null) {
                        $param->setDefaultValue($arg->default);
                    }
                    if ($arg->nullable ?? null) {
                        $param->setNullable();
                    }
                }
            }
            if ($field['formData'] ?? null) {
                $method->addParameter('formData')->setType('array')->setNullable();
            }
        }


        $filePath = $dir . $classFullName . '.php';


        $output = "<?php\n\n"; // заголовок файла
        $output .= "use GuzzleHttp\Client;;\n";
        $output .= "use GuzzleHttp\Psr7\Request;\n";
        $output .= "use GuzzleHttp\Psr7\Utils;\n";
        $output .= "use GuzzleHttp\Exception\RequestException;\n\n";
        $output .= $class . "\n\n"; // собираем все классы

        file_put_contents($filePath, $output);

        exit;
    }

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


        echo '<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeGen</title>
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/default.min.css">
    <script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.6.1/toastify.js"
        integrity="sha512-MnKz2SbnWiXJ/e0lSfSzjaz9JjJXQNb2iykcZkEY2WOzgJIWVqJBFIIPidlCjak0iTH2bt2u1fHQ4pvKvBYy6Q=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.6.1/toastify.css"
        integrity="sha512-VSD3lcSci0foeRFRHWdYX4FaLvec89irh5+QAGc00j5AOdow2r5MFPhoPEYBUQdyarXwbzyJEO7Iko7+PnPuBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>

    <div>
        <div class="row">
            <div class="col" style="position:relative; max-width: 50vw;">

                <pre style=" height: 100vh; overflow:scroll;"><div class="code-types">
                    
</div></pre>
                <button type="button" class="btn btn-primary copy-types"
                    style="position:absolute; top:10px; right: 50px;">Copy</button>
            </div>
            <div class="col" style="position:relative; max-width: 50vw;">
                <button type="button" class="btn btn-primary copy-requests"
                    style="position:absolute; top:10px; right: 50px;">Copy</button>
                <pre style="height: 100vh; overflow:scroll;"><div class="code-requests">
                 
                    </div></pre>
            </div>

        </div>
    </div>



</body>

<script>
    document.addEventListener(\'DOMContentLoaded\', (event) => {


    document.getElementsByClassName("code-types")[0].innerHTML =  hljs.highlight(
  `
  ' . str_replace('`', '\`', $types) . '`,
  { language: "typescript" }
).value;
    

  document.getElementsByClassName("code-requests")[0].innerHTML =  hljs.highlight(
  `' . str_replace('`', '\`', $requests) . '`,
  { language: "typescript" }
).value;
        });


    function fallbackCopyTextToClipboard(text) {
        var textArea = document.createElement("textarea");
        textArea.value = text;

        // Avoid scrolling to bottom
        textArea.style.top = "0";
        textArea.style.left = "0";
        textArea.style.position = "fixed";

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            var successful = document.execCommand(\'copy\');
            var msg = successful ? \'successful\' : \'unsuccessful\';
            console.log(\'Fallback: Copying text command was \' + msg);
        } catch (err) {
            console.error(\'Fallback: Oops, unable to copy\', err);
        }

        document.body.removeChild(textArea);
    }

    function copyTextToClipboard(text) {
        if (!navigator.clipboard) {
            fallbackCopyTextToClipboard(text);
            return;
        }
        navigator.clipboard.writeText(text).then(function() {
            console.log(\'Async: Copying to clipboard was successful!\');
        }, function(err) {
            console.error(\'Async: Could not copy text: \', err);
        });
    }

    var copyTypes = document.querySelector(\'.copy-types\'),
        copyRequesrs = document.querySelector(\'.copy-requests\');

    copyTypes.addEventListener(\'click\', function(event) {
        copyTextToClipboard(document.getElementsByClassName("code-types")[0].innerText);
        Toastify({
            text: "Скопировано в буфер обмена",
            className: "info",
            position: "center"
        }).showToast();
    });


    copyRequesrs.addEventListener(\'click\', function(event) {
        copyTextToClipboard(document.getElementsByClassName("code-requests")[0].innerText);
        Toastify({
            text: "Скопировано в буфер обмена",
            className: "info",
            position: "center"

        }).showToast();
    });
</script>

</html>
';

        exit;
    }
}