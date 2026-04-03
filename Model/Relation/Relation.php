<?php

namespace SwiftPHP\Model\Relation;

use SwiftPHP\Model\Model;
use SwiftPHP\Database\Builder\QueryBuilder;

abstract class Relation
{
    protected Model $parent;
    protected Model $related;
    protected string $foreignKey;
    protected string $localKey;

    public function __construct(Model $parent, Model $related, string $foreignKey, string $localKey)
    {
        $this->parent = $parent;
        $this->related = $related;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    abstract public function getResults(): mixed;

    protected function newQuery(): QueryBuilder
    {
        return QueryBuilder::table($this->related->getTable());
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getLocalKey(): string
    {
        return $this->localKey;
    }
}
