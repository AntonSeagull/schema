<?php

namespace Shm\ShmRPC\ShmRPCUtils;

use Shm\Shm;
use Shm\ShmDB\mDB;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\RedisCache;
use Shm\ShmUtils\Response;
use Shm\ShmUtils\ShmUtils;

class ShmRPCContext
{

    public $method;

    public $cache;

    public $type;

    public $args;

    public $request;

    public $params;

    public $resolve = null;

    public $context = null;

    public $onlyDisplayRelations = false;


    private   function xor_encrypt(string $text, string $key): string
    {
        $keyLen = strlen($key);
        $output = '';

        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            $output .= $text[$i] ^ $key[$i % $keyLen];
        }

        return base64_encode($output);
    }

    private   function xor_decrypt(string $encodedText, string $key): string
    {
        $text = base64_decode($encodedText);
        $keyLen = strlen($key);
        $output = '';

        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            $output .= $text[$i] ^ $key[$i % $keyLen];
        }

        return $output;
    }


    public function __construct(string $method, mixed $schemaMethod, $request)
    {

        if (is_object($schemaMethod) && method_exists($schemaMethod, 'make')) {
            $schemaMethod = $schemaMethod->make();
        }



        $this->request = $request;

        $this->method = $method;
        $this->cache = $schemaMethod['cache'] ?? null;
        $this->type = $schemaMethod['type'] ?? null;
        $this->args = $schemaMethod['args'] ?? null;
        $this->onlyDisplayRelations = $schemaMethod['onlyDisplayRelations'] ?? false;

        $this->resolve = $schemaMethod['resolve'] ?? null;


        $this->type->updateKeys($this->method);


        if (isset($this->args) && $this->args !== null) {


            if (is_array($this->args) && !($this->args instanceof StructureType)) {


                $this->args = Shm::structure($this->args);
            }


            $this->args->editable()->staticBaseTypeName('Args' . ShmUtils::onlyLetters($this->method));
        }



        $this->context = $request['context'] ?? null;

        $params = $request['params'] ?? [];
        if ($this->context && is_string($params)) {

            $params = $this->xor_decrypt($params, $this->context);

            try {
                $params = \json_decode($params, true);
            } catch (\Exception $e) {
                Response::validation("Ошибка выполнения запроса");
            }
        }
        $this->params = $params;
    }


    public function isCached(): bool
    {
        $cache = isset($this->request['cache']) ? $this->request['cache'] : true;
        return  $cache  && ($this->cache ?? 0) > 0;
    }

    public function cachedKey(): string
    {
        return $this->method . json_encode($this->params);
    }

    public function cachedResponse()
    {

        $cache = RedisCache::get($this->cachedKey());
        if ($cache) {
            if ($cache !== null) {

                $result = json_decode($cache, true);



                if ($this->context) {
                    $result = json_encode($result);
                    $result = $this->xor_encrypt($result, $this->context);
                }

                Response::cache();
                $result = mDB::replaceObjectIdsToString($result);
                Response::success($result);
            }
        }
    }

    public function callMethod()
    {




        $params = $this->params;




        Response::startTraceTiming("executeMethod");
        if (!is_callable($this->resolve)) {
            Response::validation("Method is not callable.");
        }
        $result = call_user_func($this->resolve, $this, $params);
        Response::endTraceTiming("executeMethod");


        Response::startTraceTiming("normalize");





        $result = $this->type->normalize($result, false);




        $result = $this->type->toOutput($result);




        $result = $this->type->removeOtherItems($result);




        $result = $this->type->normalizePrivate($result);

        Response::endTraceTiming("normalize");



        $onlyDisplayRelations = $this->onlyDisplayRelations;



        if ($result) {





            if ($this->type instanceof StructureType || $this->type instanceof \Shm\ShmTypes\ArrayOfType) {
                $this->type->expand();



                Response::startTraceTiming("externalData");
                $result = $this->type->externalData($result, $onlyDisplayRelations);
                Response::endTraceTiming("externalData");
            }
        }

        if ($result)
            $result = mDB::replaceObjectIdsToString($result);

        $end = microtime(true);





        if ($result && ($schemaMethod['cache'] ?? 0) > 0) {
            RedisCache::set($this->cachedKey(), json_encode($result), $this->cache);
        }



        if ($this->context) {
            $result = json_encode($result);
            $result = $this->xor_encrypt($result, $this->context);
        }




        Response::success($result);
    }


    public function setType(BaseType $type)
    {
        $this->type = $type;
        $this->type->updateKeys("type");
    }

    public function getType(): BaseType
    {
        return $this->type;
    }
}
