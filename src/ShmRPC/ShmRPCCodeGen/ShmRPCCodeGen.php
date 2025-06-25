<?php

namespace Shm\ShmRPC\ShmRPCCodeGen;

use Shm\ShmCodeGen\ClassGenerator;

class ShmRPCCodeGen
{

    public static array $tsTypes = [];

    public static function html(array $schema)
    {


        ClassGenerator::generateClasses();


        $requests = [];

        $keysGraph = [];
        foreach ($schema  as $key => $field) {

            //     $keysGraph[$key . 'type'] = $field['type']->getKeysGraph();

            //   if (isset($field['args'])) {
            //       $keysGraph[$key . 'args'] = $field['args']->getKeysGraph();
            //  }
            //         exit;

            $requests[$key] = (new ShmRPCRequestCode($field['type'], $field['args'] ?? null, $key, 'query'))->initialize();
        }

        //echo json_encode($keysGraph);
        // exit;



        //Сортируем  ksort(TSType::$tsTypes);
        ksort(TSType::$tsTypes);
        ksort($requests);

        $allTypesKeys = array_keys(TSType::$tsTypes);

        $types = implode("\n", array_values(TSType::$tsTypes));
        $requests =   array_values($requests);



        $requests = [
            "import { RpcResponse, " . implode(',', $allTypesKeys) . " } from './types';",

            'export const rpc = {',
            ...$requests,
            '};'
        ];

        $requests = implode("\n", $requests);




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
