<?php

namespace Shm\ShmGQL\ShmGQLCodeGen;

use Shm\ShmUtils\ShmUtils;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;

class ShmGQLRequestCode
{



    private BaseType $type;
    private BaseType | null $args;
    private string $key;
    private string $requestType;

    public function __construct(
        BaseType $type,
        BaseType | null $args,
        string $key,
        string $requestType = 'query'
    ) {
        $this->type = $type;
        $this->args = $args;
        $this->key = $key;
        $this->requestType = $requestType;
    }


    private function  functionName(): string
    {

        if ($this->requestType === 'mutation') {
            return 'm' . ShmUtils::onlyLetters($this->key);
        }

        if ($this->requestType === 'query') {
            return 'q' . ShmUtils::onlyLetters($this->key);
        }

        return 'errorFunctionName';
    }

    private function paramsForFunction(): string
    {
        if (isset($this->args)) {

            return 'params: ' . $this->args->keyIfNot($this->key)->tsInputType()->getTsTypeName();
        }
        return "";
    }


    private function gqlParameters(): string
    {
        if (isset($this->args)) {

            $parameters = [];
            foreach ($this->args->items as $key => $item) {
                $parameters[] = '$' . $key . ': ' .  $item->tsInputType()->getTsTypeName();
            }

            return '(' . implode(', ', $parameters) . ')';
        }
        return "";
    }

    private function gqlParametersValues(): string
    {
        if (isset($this->args)) {

            $parameters = [];
            foreach ($this->args->items as $key => $item) {
                $parameters[] = $key . ': $' . $key;
            }

            return '(' . implode(', ', $parameters) . ')';
        }
        return "";
    }



    private function variablesParamsInGQL(): string
    {
        if (isset($this->args)) {

            return ',variables: params';
        };
        return "";
    }



    public function initialize(): string
    {

        $appoloMethod = $this->requestType === 'query' ? 'query' : 'mutate';





        $fullRequest =  $this->type->tsGQLFullRequest();



        return  "
        export const  {$this->functionName()} = ({$this->paramsForFunction()}) => {
            return new Promise<{$this->type->tsType()->getTsTypeName()} | null>((resolve, reject) => {
            apolloClient.{$appoloMethod}({
             {$this->requestType}: gql`
            {$this->requestType} {$this->key}{$this->gqlParameters()} {
            {$this->key}{$this->gqlParametersValues()}{$fullRequest}
            }
            `{$this->variablesParamsInGQL()}
            }).then((json) => {
                resolve(json.data.{$this->key});
              })
              .catch(() => {
                reject();
              });
            });
            };
            ";
    }
}
