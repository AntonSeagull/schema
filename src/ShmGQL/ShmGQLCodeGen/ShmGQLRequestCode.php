<?php

namespace Shm\ShmGQL\ShmGQLCodeGen;

use Shm\GQLUtils\Utils;
use Shm\Types\BaseType;

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
            return 'm' . Utils::onlyLetters($this->key);
        }

        if ($this->requestType === 'query') {
            return 'q' . Utils::onlyLetters($this->key);
        }

        return 'errorFunctionName';
    }

    private function paramsForFunction(): string
    {
        if (isset($this->args)) {
            return 'params: ' . $this->args->keyIfNot($this->key . 'Args')->tsTypeName();
        }
        return "";
    }


    private function gqlParameters(): string
    {
        if (isset($this->args)) {

            $parameters = [];
            foreach ($this->args->items as $key => $item) {
                $parameters[] = '$' . $key . ': ' .  $item->tsTypeName();
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





    public function initialize(): string
    {

        return  "
        export const  {$this->functionName()} = ({$this->paramsForFunction()}) => {
            return new Promise<{$this->type->tsTypeName()} | null>((resolve, reject) => {
            apolloClient.{$this->requestType}({
            query: gql`
            query {$this->key}{$this->gqlParameters()} {
            {$this->key}{$this->gqlParametersValues()}{$this->type->tsGQLFullRequest()}
            }
            `,
              variables: params
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