<?php

namespace SwiftPHP\Core\Model\Relation;

use SwiftPHP\Core\Model\Model;

class HasMany extends Relation
{
    public function getResults(): array
    {
        $foreignValue = $this->parent->getAttribute($this->localKey);
        if ($foreignValue === null) {
            return [];
        }

        return $this->newQuery()
            ->where($this->foreignKey, $foreignValue)
            ->get();
    }
}
