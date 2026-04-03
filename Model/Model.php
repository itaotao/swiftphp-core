<?php

namespace SwiftPHP\Core\Model;

use SwiftPHP\Core\Database\ConnectionPool;
use SwiftPHP\Core\Database\Builder\QueryBuilder;
use SwiftPHP\Core\Model\Relation\HasOne;
use SwiftPHP\Core\Model\Relation\HasMany;
use SwiftPHP\Core\Model\Relation\BelongsTo;
use Exception;

class Model
{
    protected $table = '';
    protected $primaryKey = 'id';
    protected $connection = 'mysql';
    protected $fillable = [];
    protected $hidden = [];
    protected $casts = [];
    protected $data = [];
    protected $original = [];
    protected $isNew = true;
    protected $relations = [];
    protected $loadedRelations = [];

    public function __construct(array $data = [])
    {
        if (empty($this->table)) {
            $this->table = $this->getTableName();
        }

        if (!empty($data)) {
            $this->data = $data;
            $this->original = $data;
            $this->isNew = false;
        }
    }

    protected function getTableName(): string
    {
        $class = get_class($this);
        $class = str_replace('App\\Model\\', '', $class);
        $class = str_replace('\\', '_', $class);
        return strtolower($class);
    }

    public function __get(string $name)
    {
        if (isset($this->relations[$name])) {
            if (!isset($this->loadedRelations[$name])) {
                $this->loadedRelations[$name] = $this->relations[$name]->getResults();
            }
            return $this->loadedRelations[$name];
        }
        return $this->data[$name] ?? null;
    }

    public function __set(string $name, $value)
    {
        $this->data[$name] = $value;
        $this->isNew = false;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function with(string ...$relations): self
    {
        foreach ($relations as $relation) {
            if (method_exists($this, $relation)) {
                $this->relations[$relation] = $this->$relation();
            }
        }
        return $this;
    }

    public function load(array $relations): self
    {
        foreach ($relations as $relation) {
            if (isset($this->relations[$relation])) {
                $this->loadedRelations[$relation] = $this->relations[$relation]->getResults();
            }
        }
        return $this;
    }

    public function toArray(): array
    {
        $result = $this->data;

        if (!empty($this->hidden)) {
            foreach ($this->hidden as $field) {
                unset($result[$field]);
            }
        }

        foreach ($this->loadedRelations as $name => $relation) {
            if ($relation instanceof Model) {
                $result[$name] = $relation->toArray();
            } elseif (is_array($relation)) {
                $result[$name] = array_map(function ($item) {
                    return $item instanceof Model ? $item->toArray() : $item;
                }, $relation);
            }
        }

        return $result;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    public function save(): bool
    {
        if ($this->isNew) {
            return $this->insert();
        }
        return $this->update();
    }

    public function insert(): bool
    {
        if (empty($this->data)) {
            return false;
        }

        $filtered = $this->filterFillable($this->data);
        if (empty($filtered)) {
            return false;
        }

        $columns = implode(', ', array_keys($filtered));
        $placeholders = implode(', ', array_fill(0, count($filtered), '?'));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $values = array_values($filtered);

        ConnectionPool::init(config('database') ?: []);
        ConnectionPool::query($sql, $values);

        $id = ConnectionPool::lastInsertId();
        if ($id) {
            $this->data[$this->primaryKey] = $id;
            $this->original = $this->data;
            $this->isNew = false;
        }

        return true;
    }

    public function update(): bool
    {
        if ($this->isNew || empty($this->data)) {
            return false;
        }

        if (!isset($this->data[$this->primaryKey])) {
            return false;
        }

        $filtered = $this->filterFillable($this->data);
        if (empty($filtered)) {
            return false;
        }

        $sets = [];
        $values = [];
        foreach ($filtered as $key => $value) {
            if ($key === $this->primaryKey) {
                continue;
            }
            $sets[] = "{$key} = ?";
            $values[] = $value;
        }

        if (empty($sets)) {
            return false;
        }

        $values[] = $this->data[$this->primaryKey];
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE {$this->primaryKey} = ?";

        ConnectionPool::init(config('database') ?: []);
        ConnectionPool::execute($sql, $values);

        $this->original = $this->data;
        return true;
    }

    public function delete(): bool
    {
        if ($this->isNew || !isset($this->data[$this->primaryKey])) {
            return false;
        }

        $id = $this->data[$this->primaryKey];
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";

        ConnectionPool::init(config('database') ?: []);
        ConnectionPool::execute($sql, [$id]);

        $this->data = [];
        $this->original = [];
        return true;
    }

    public function find($id): ?self
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        ConnectionPool::init(config('database') ?: []);
        $result = ConnectionPool::find($sql, [$id]);

        if (!$result) {
            return null;
        }

        $model = new static($result);
        return $model;
    }

    public function select(array $ids = []): array
    {
        if (empty($ids)) {
            $sql = "SELECT * FROM {$this->table}";
            ConnectionPool::init(config('database') ?: []);
            $results = ConnectionPool::select($sql);
        } else {
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));
            $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} IN ({$placeholders})";
            ConnectionPool::init(config('database') ?: []);
            $results = ConnectionPool::select($sql, $ids);
        }

        return array_map(function ($data) {
            return new static($data);
        }, $results);
    }

    public function all(): array
    {
        return $this->select([]);
    }

    public function newQuery(): QueryBuilder
    {
        return QueryBuilder::table($this->table);
    }

    public static function query(): QueryBuilder
    {
        $model = new static();
        return $model->newQuery();
    }

    public function getAttribute(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function hasOne(string $related, ?string $foreignKey = null): HasOne
    {
        $model = new $related();
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $this->primaryKey;

        $relation = new HasOne($this, $model, $foreignKey, $localKey);
        $this->relations['hasOne'] = $relation;
        return $relation;
    }

    public function hasMany(string $related, ?string $foreignKey = null): HasMany
    {
        $model = new $related();
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $this->primaryKey;

        $relation = new HasMany($this, $model, $foreignKey, $localKey);
        $this->relations['hasMany'] = $relation;
        return $relation;
    }

    public function belongsTo(string $related, ?string $foreignKey = null): BelongsTo
    {
        $model = new $related();
        $foreignKey = $foreignKey ?? $model->getPrimaryKey();
        $localKey = $model->getPrimaryKey();

        $relation = new BelongsTo($this, $model, $foreignKey, $localKey);
        $this->relations['belongsTo'] = $relation;
        return $relation;
    }

    protected function getForeignKey(): string
    {
        $className = basename(str_replace('\\', '/', get_class($this)));
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className)) . '_id';
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function where($field, $operator = null, $value = null): self
    {
        return $this;
    }

    public function orderBy($field, $order = 'asc'): self
    {
        return $this;
    }

    public function limit($offset, $length = null): self
    {
        return $this;
    }

    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    public function __call($method, $args)
    {
        if (in_array($method, ['where', 'orderBy', 'limit', 'groupBy', 'having'])) {
            return $this;
        }

        throw new Exception("Method {$method} does not exist");
    }

    public static function __callStatic($method, $args)
    {
        $model = new static();

        if (in_array($method, ['find', 'select', 'all', 'create', 'query'])) {
            return call_user_func_array([$model, $method], $args);
        }

        $tableName = static::convertToTableName(get_called_class());
        $builder = \SwiftPHP\Core\Database\Builder\QueryBuilder::table($tableName);

        if (method_exists($builder, $method)) {
            return call_user_func_array([$builder, $method], $args);
        }

        throw new Exception("Static method {$method} does not exist");
    }

    protected static function convertToTableName(string $className): string
    {
        $className = basename(str_replace('\\', '/', $className));
        $className = preg_replace('/(?<!^)[A-Z]/', '_$0', $className);
        return strtolower($className);
    }
}
