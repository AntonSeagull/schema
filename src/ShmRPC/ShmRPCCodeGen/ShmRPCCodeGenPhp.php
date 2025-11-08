<?php

namespace Shm\ShmRPC\ShmRPCCodeGen;

use Shm\ShmTypes\BaseType;
use Shm\ShmUtils\Doctor;
use Shm\ShmUtils\ShmInit;

class ShmRPCCodeGenPhp
{


    private static function mapPhpType(string $type): string
    {
        switch ($type) {
            case 'string':
                return 'string';
            case 'int':
                return 'int';
            case 'float':
                return 'float';
            case 'bool':
                return 'bool';
            case 'array':
                return 'array';
            case 'structure':
                return 'object';
            default:
                return 'mixed';
        }
    }

    private static function BaseApiClass()
    {

        //BaseApiClas

        $class = new \Nette\PhpGenerator\ClassType('BaseApiClass');


        $class->addProperty('endpoint')
            ->setStatic()
            ->setVisibility('private')
            ->setType('string')
            ->setValue('http://localhost:8888/' . self::$baseEndpoint);

        $method = $class->addMethod('requestOriginal');

        $method->setVisibility('public');
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


        $method = $class->addMethod('validate');
        $method->setPublic();
        $method->setReturnType('bool');
        $method->setBody('
        return true;
        ');





        $filePath = self::$dirFiles .  'BaseApiClass.php';


        $output = "<?php\n\n"; // заголовок файла
        $output .= "namespace " . self::$globalNamespace . ";\n";

        $output .= $class . "\n\n"; // собираем все классы

        file_put_contents($filePath, $output);
        echo 'BaseApiClass created';
    }

    private static function BaseResponse()
    {
        // BaseErrorResponse class
        $errorClass = new \Nette\PhpGenerator\ClassType('BaseErrorResponse');

        $errorClass->addProperty('type')
            ->setPublic()
            ->setType('string')
            ->setValue('');

        $errorClass->addProperty('message')
            ->setPublic()
            ->setType('string')
            ->setValue('');

        $errorClass->addProperty('code')
            ->setPublic()
            ->setType('?int')
            ->setValue(null);

        // Constructor for BaseErrorResponse
        $errorConstructor = $errorClass->addMethod('__construct');
        $errorConstructor->setPublic();
        $errorConstructor->addParameter('error')->setType('?array')->setDefaultValue(null);
        $errorConstructor->setBody('
        if ($error !== null) {
            $this->type = $error[\'type\'] ?? \'\';
            $this->message = $error[\'message\'] ?? \'\';
            $this->code = $error[\'code\'] ?? null;
        }
        ');

        // BaseResponse class
        $class = new \Nette\PhpGenerator\ClassType('BaseResponse');

        $class->addProperty('success')
            ->setPublic()
            ->setType('bool')
            ->setValue(false);

        $class->addProperty('result')
            ->setPublic()
            ->setType('mixed')
            ->setValue(null);

        $class->addProperty('error')
            ->setPublic()
            ->setType('?BaseErrorResponse')
            ->setValue(null);

        $class->addProperty('executionTime')
            ->setPublic()
            ->setType('int')
            ->setValue(0);

        // Constructor for BaseResponse
        $constructor = $class->addMethod('__construct');
        $constructor->setPublic();
        $constructor->addParameter('response')->setType('?array')->setDefaultValue(null);
        $constructor->setBody('
        $this->success = $response[\'success\'] ?? false;
        $this->result = $response[\'result\'] ?? null;
        $this->error = $response[\'error\'] ? new BaseErrorResponse($response[\'error\']) : null;
        $this->executionTime = $response[\'executionTime\'] ?? 0;
        ');

        // setData method
        $setDataMethod = $class->addMethod('setData');
        $setDataMethod->setPublic();
        $setDataMethod->setReturnType('self');
        $setDataMethod->addParameter('data')->setType('array');
        $setDataMethod->setBody('
        $this->success = $data[\'success\'] ?? false;
        $this->result = $data[\'result\'] ?? null;
        $this->error = $data[\'error\'] ? new BaseErrorResponse($data[\'error\']) : null;
        $this->executionTime = $data[\'executionTime\'] ?? 0;
        return $this;
        ');

        // Save BaseErrorResponse file
        $errorFilePath = self::$dirFiles . 'BaseErrorResponse.php';
        $errorOutput = "<?php\n\n";
        $errorOutput .= "namespace " . self::$globalNamespace . ";\n\n";
        $errorOutput .= $errorClass . "\n";
        file_put_contents($errorFilePath, $errorOutput);
        echo 'BaseErrorResponse created';

        // Save BaseResponse file
        $filePath = self::$dirFiles . 'BaseResponse.php';
        $output = "<?php\n\n";
        $output .= "namespace " . self::$globalNamespace . ";\n\n";
        $output .= $class . "\n";
        file_put_contents($filePath, $output);
        echo 'BaseResponse created';
    }

    private static $requiredFieldsInAllClasses = [];

    private static function addRequiredField(string $fieldKey,  BaseType $field)
    {

        if (isset(self::$requiredFieldsInAllClasses[$fieldKey])) {
            self::$requiredFieldsInAllClasses[$fieldKey]['count']++;
        } else {
            self::$requiredFieldsInAllClasses[$fieldKey] = [
                'field' => $field,
                'count' => 1,
            ];
        }
    }


    public static $ApiClasses = [];

    private static function ApiClass(string $key,  array $field)
    {


        $className = ucfirst(str_replace([' ', '-', '_'], '', $key));

        $class = new \Nette\PhpGenerator\ClassType($className);

        self::$ApiClasses[] = $className;
        //extend BaseApiClass
        $class->setExtends('BaseApiClass');

        $requiredFields = [];
        foreach ($field['args']->items as $argKey => $arg) {
            $param = $class->addProperty($argKey);
            $param->setPrivate();
            $type = $arg->type ?? 'mixed';

            if ($arg->required) {

                self::addRequiredField($argKey, $arg);

                $requiredFields[$argKey] = $arg;
            }


            $param->setType(self::mapPhpType($type));
            if ($arg->default ?? null) {
                $param->setValue($arg->default);
            } else {
                $param->setValue(null);
            }

            $isEnum = false;
            if ($type == 'enum') {
                $isEnum = true;
            }

            $enumValues = [];
            if ($isEnum) {
                $enumValues = array_keys($arg->values ?? []);
            }


            //Функция сеттер
            $setter = $class->addMethod('set' . ucfirst($argKey));

            if ($isEnum) {
                //Добаляем PHPDoc для enum
                $setter->setComment('@param ' . implode('|', $enumValues) . ' $value');
            }


            $setter->setPublic();
            $setter->setReturnType('self');
            $setter->addParameter('value')->setType(self::mapPhpType($type));

            if ($isEnum) {

                $setter->setBody('
                    if(in_array($value, [' . implode(', ', array_map(fn($v) => "'" . $v . "'", $enumValues)) . '])) {
                        $this->' . $argKey . ' = $value;' . "\n" . 'return $this;
                    }
                    throw new \Exception("Invalid value for ' . $argKey . '");
                    ');
            } else {
                $setter->setBody('$this->' . $argKey . ' = $value;' . "\n" . 'return $this;');
            }
            //Функция геттер
            $getter = $class->addMethod('get' . ucfirst($argKey));
            $getter->setPublic();

            $getter->setReturnType('?' . self::mapPhpType($type));
            $getter->setBody('return $this->' . $argKey . ';');
        }

        $constructor = $class->addMethod('__construct');
        $constructor->setPublic();
        $constructor->addParameter('values')->setType('array')->setDefaultValue([]);
        $constructor->setBody('
        if($values){
            foreach($values as $fieldKey => $value){
                $this->$fieldKey = $value;
            }
        }
        ');

        if ($requiredFields) {
            //Функция валидации
            $validate = $class->addMethod('validate');
            $validate->setPublic();
            $validate->setReturnType('bool');

            $body = '';

            foreach ($requiredFields as $requiredKey => $requiredField) {
                $body .= 'if ($this->' . $requiredKey . ' === null) {
                        return false;
                    }' . "\n";
            }
            $body .= 'return true;';


            $validate->setBody($body);
        }

        $requiredKeys = array_keys($requiredFields);
        $method = $class->addMethod('toArray');
        $method->setPublic();
        $method->setReturnType('array');
        $method->setBody('
            return [
                ' . implode(', ', array_map(fn($k) => '"' . $k . '" => $this->' . $k, $requiredKeys)) . '
            ];
            ');

        $method = $class->addMethod('request');
        $method->setPublic();
        $method->setReturnType('array');
        $method->setBody('
          
            if (!$this->validate()) {
                throw new \Exception("Validation failed");
            }
            return $this->requestOriginal("' . $key . '", $this->toArray());
            ');


        $filePath = self::$dirFiles . $className . '.php';


        $output = "<?php\n\n"; // заголовок файла
        $output .= "namespace " . self::$globalNamespace . ";\n";

        $output .= $class . "\n\n"; // собираем все классы

        file_put_contents($filePath, $output);
        echo $className . ' created';
    }

    private static function ApiClient($fields)
    {
        $className = 'ApiClient';
        $class = new \Nette\PhpGenerator\ClassType($className);


        foreach ($fields as $fieldKey => $field) {
            $class->addProperty($fieldKey)->setPrivate()->setType(self::mapPhpType($field->type))->setValue(null);
        }


        if (count($fields) > 0) {


            $constructor  = $class->addMethod('__construct')->setPublic();
            $constructor->addParameter('values')->setType('array')->setNullable(true);
            $constructor->setBody('
        if($values){
            foreach($values as $fieldKey => $value){
                $this->$fieldKey = $value;
            }
        }
        ');
        }


        foreach (self::$ApiClasses as $apiClass) {

            $method = $class->addMethod($apiClass)->setPublic()->setReturnType($apiClass);

            if (count($fields) > 0) {
                $method->setBody('return new ' . $apiClass . '([' . implode(', ', array_map(fn($k) => '"' . $k . '" => $this->' . $k, array_keys($fields))) . ']);');
            } else {
                $method->setBody('return new ' . $apiClass . '();');
            }
        }

        $filePath = self::$dirFiles . $className . '.php';
        $output = "<?php\n\n"; // заголовок файла
        $output .= "namespace " . self::$globalNamespace . ";\n";
        $output .= $class . "\n\n"; // собираем все классы
        file_put_contents($filePath, $output);
        echo $className . ' created';
    }

    private static $baseEndpoint = '';

    private static $dirFiles = '';

    private static $globalNamespace = '';

    public static function php(array $schema)
    {



        $REQUEST_URI = $_SERVER['REQUEST_URI'];

        //Убарем GET параметры
        $REQUEST_URI = explode('?', $REQUEST_URI)[0];
        self::$baseEndpoint = $REQUEST_URI;
        //Все / заменяем на _
        $REQUEST_URI = str_replace('/', '_', $REQUEST_URI);


        self::$dirFiles =  ShmInit::$rootDir . '/_php_codegen/' . $REQUEST_URI . '/';
        if (!is_dir(self::$dirFiles)) {
            mkdir(self::$dirFiles, 0777, true);
        }


        self::$globalNamespace = "CHANGEME";


        self::BaseApiClass();
        self::BaseResponse();



        foreach ($schema  as $key => $field) {

            self::ApiClass($key, $field);
        }

        $mainClassFields = [];

        foreach (self::$requiredFieldsInAllClasses as $fieldKey => $field) {
            if ($field['count'] == count($schema)) {
                $mainClassFields[$fieldKey] = $field['field'];
            }
        }

        self::ApiClient($mainClassFields);

        //  var_dump($mainClassFields);

        exit;
    }
}