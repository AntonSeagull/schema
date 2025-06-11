<?php

namespace Shm\ShmGQL;


use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use Sentry\State\Scope;
use Shm\Shm;
use Shm\ShmGQL\ShmGQLBlueprints\Auth\ShmGQLAuth;
use Shm\ShmGQL\ShmGQLBlueprints\ShmGQLBlueprintMutation;
use Shm\ShmGQL\ShmGQLBlueprints\ShmGQLBlueprintQuery;
use Shm\ShmGQL\ShmGQLCodeGen\ShmGQLCodeGen;
use Shm\Types\StructureType;

class ShmGQL
{

    public static $init = false;

    /**
     * Инициализация GraphQL-сервера с переданной схемой.
     *
     * @param array{
     *     query?: array<string, array{
     *         type: mixed,
     *         args?: array<string, mixed>,
     *         resolve?: callable
     *     }>,
     *     mutation?: array<string, array{
     *         type: mixed,
     *         args?: array<string, mixed>,
     *         resolve?: callable
     *     }>
     * } $schemaParams Описание схемы GraphQL. 
     * Где `query` и `mutation` — это массивы полей, каждое поле описано:
     *   - `type` — возвращаемый тип (объект ShmType),
     *   - `args` — список аргументов (необязателен),
     *   - `resolve` — функция-резолвер.
     *
     * Пример:
     * [
     *   'query' => [
     *     'hello' => [
     *       'type' => Shm::string(),
     *       'args' =>  Shm::structure([
     *         'name' => Shm::string()->noNullable()
     *       ]).
     *       'resolve' => fn($root, $args, $context, $info) => "Hello {$args['name']}"
     *     ]
     *   ],
     *   'mutation' => [
     *     'setName' => [
     *       'type' => Shm::string(),
     *       'args' =>  Shm::structure([
     *         'name' => Shm::string()->noNullable()
     *       ]),
     *       'resolve' => fn($root, $args, $context, $info) => "Name set to {$args['name']}"
     *     ]
     *   ]
     * ]
     *
     * @return void
     */


    private static function transformSchemaParams(array $field, string $key): array
    {

        $args = [];

        if (isset($field['args'])) {

            $field['args']->editable()->keyIfNot($key . 'Args');

            foreach ($field['args']->items as $argKey => $argType) {
                $args[$argKey] = $argType->editable()->GQLTypeInput();
            }
        }


        return  [
            'type' => $field['type']->keyIfNot($key)->GQLType(),
            'args' => $args == [] ? null : $args,
            'resolve' => function ($root, $args, $context, $info) use ($field) {
                if (isset($field['resolve']) && is_callable($field['resolve'])) {
                    return $field['resolve']($root, $args, $context, $info);
                }
                return null;
            }

        ];
    }


    public static function makeMutation(StructureType $strucutre): ShmGQLBlueprintMutation
    {
        return new ShmGQLBlueprintMutation($strucutre);
    }


    public static function makeQuery(StructureType $strucutre): ShmGQLBlueprintQuery
    {
        return new ShmGQLBlueprintQuery($strucutre);
    }

    private  static function validateSchemaParams(array $schemaParams): void
    {
        if (isset($schemaParams['query'])) {

            if (!is_array($schemaParams['query'])) {
                throw new \InvalidArgumentException("Schema 'query' must be an array.");
            }

            //Проверка что type это BaseType и args это StructureType
            foreach ($schemaParams['query'] as $key => $field) {
                if (!isset($field['type']) || !($field['type'] instanceof \Shm\Types\BaseType)) {
                    throw new \InvalidArgumentException("Schema 'query' field '{$key}' must have a 'type' of BaseType.");
                }
                if (isset($field['args']) && !($field['args'] instanceof StructureType)) {
                    throw new \InvalidArgumentException("Schema 'query' field '{$key}' must have 'args' of StructureType.");
                }
            }
        }

        if (isset($schemaParams['mutation'])) {

            if (!is_array($schemaParams['mutation'])) {
                throw new \InvalidArgumentException("Schema 'mutation' must be an array.");
            }

            //Проверка что type это BaseType и args это StructureType
            foreach ($schemaParams['mutation'] as $key => $field) {
                if (!isset($field['type']) || !($field['type'] instanceof \Shm\Types\BaseType)) {
                    throw new \InvalidArgumentException("Schema 'mutation' field '{$key}' must have a 'type' of BaseType.");
                }
                if (isset($field['args']) && !($field['args'] instanceof StructureType)) {
                    throw new \InvalidArgumentException("Schema 'mutation' field '{$key}' must have 'args' of StructureType.");
                }
            }
        }
    }


    public static function init(array $schemaParams)
    {


        self::validateSchemaParams($schemaParams);

        self::$init = true;

        $start = microtime(true);


        $body = file_get_contents('php://input');
        $request = \json_decode($body, true);

        $query = $request['query'] ?? null;
        $variables = $request['variables'] ?? null;

        $operationType = null;
        $operationName = null;

        try {
            $parsedQuery = \GraphQL\Language\Parser::parse($query);
            foreach ($parsedQuery->definitions as $definition) {
                if (isset($definition->operation)) {
                    $operationType = $definition->operation; // query или mutation
                }
                if (isset($definition->selectionSet->selections[0]->name->value)) {
                    $operationName = $definition->selectionSet->selections[0]->name->value;
                }
            }
        } catch (\Exception $e) {
        }



        if (($_SERVER['SERVER_NAME'] ?? null) != "localhost") {
            $rule = new DisableIntrospection(DisableIntrospection::ENABLED);
            DocumentValidator::addRule($rule);
        }





        $SchemaData = [];

        if (isset($schemaParams['query'])) {


            $queryFields = [];

            foreach ($schemaParams['query'] as $key => $field) {
                $queryFields[$key] = self::transformSchemaParams($field, $key);
            }

            $SchemaData['query'] = new ObjectType([
                'name' => 'Query',
                "fields" => $queryFields,
            ]);
        } else {
            $SchemaData['query'] = null;
        }

        if (isset($schemaParams['mutation'])) {

            $mutationFields = [];

            foreach ($schemaParams['mutation'] as $key => $field) {
                $mutationFields[$key] = self::transformSchemaParams($field, $key);
            }



            $SchemaData['mutation'] =
                new ObjectType([
                    'name' => 'Mutation',
                    "fields" => $mutationFields,
                ]);
        } else {
            $SchemaData['mutation'] = null;
        }



        //If GET request, we can return the schema
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            ShmGQLCodeGen::html($schemaParams);
        };

        $schema = new Schema($SchemaData);



        $rootResolver = [];

        $myErrorFormatter = function (Error $error) use ($query, $variables) {


            $_error = FormattedError::createFromException($error);

            \Sentry\captureException($error);
            \Sentry\configureScope(function (Scope $scope) use ($query, $variables) {
                $scope->setExtra('query', $query);
                $scope->setExtra('variables', $variables);
            });


            return $_error;
        };

        $myErrorHandler = function (array $errors, callable $formatter) {

            return array_map($formatter, $errors);
        };



        $debug = ($_SERVER['SERVER_NAME'] ?? null) === 'localhost'
            ? DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE
            : DebugFlag::NONE;


        $result = GraphQL::executeQuery($schema, $query, $rootResolver, null, $variables, null, null)
            ->setErrorFormatter($myErrorFormatter)
            ->setErrorsHandler($myErrorHandler)->toArray($debug);




        $end = microtime(true);
        $duration = round(($end - $start) * 1000);




        // Логгируем в Sentry, если медленно
        $threshold = 3000; // ms
        if ($duration > $threshold) {
            \Sentry\configureScope(function (Scope $scope) use ($query, $variables, $operationType, $operationName, $duration) {
                $scope->setExtra('query', $query);
                $scope->setExtra('variables', $variables);
                $scope->setExtra('duration_ms', $duration);
                $scope->setExtra('operation_type', $operationType);
                $scope->setExtra('operation_name', $operationName);
                $scope->setExtra('SERVER', $_SERVER);
                $scope->setTag('slow_query', 'true');
            });

            $msg = "⚠️ Slow GraphQL {$operationType} `{$operationName}` took {$duration}ms";
            \Sentry\captureMessage($msg, \Sentry\Severity::warning());
        }




        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit(0);
    }

    public static function auth(StructureType ...$authCollections): ShmGQLAuth
    {
        return (new ShmGQLAuth(...$authCollections));
    }
}