<?php

namespace Shm\ShmTypes;

use GraphQL\Type\Definition\Type;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;

class MixedType extends BaseType
{
    public string $type = 'mixed';



    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {
        if ($addDefaultValues &&  $value === null && $this->defaultIsSet) {
            return $this->default;
        }
        return  $value;
    }




    public function tsType(): TSType
    {
        $TSType = new TSType("Any", "any");


        return $TSType;
    }
}
