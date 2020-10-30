<?php


namespace AsemAlalami\LaravelAdvancedFilter\Operators;


use AsemAlalami\LaravelAdvancedFilter\Exceptions\UnsupportedOperatorException;
use AsemAlalami\LaravelAdvancedFilter\Fields\Field;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;

class In extends Operator
{
    /**
     * @inheritDoc
     */
    public function apply(Builder $builder, Field $field, $value, string $conjunction = 'and'): Builder
    {
        if (empty($value)) {
            return $builder;
        }

        if ($field->getDatatype() == 'date') {
            return $this->applyOnDate($builder, $field, $value, $conjunction);
        }

        // to use in NotIn operator
        $notIn = $this->getSqlOperator() != 'IN';

        return $builder->whereIn($field->getColumn(), $this->getSqlValue($value), $conjunction, $notIn);
    }

    /**
     * @inheritDoc
     */
    public function getSqlOperator(): string
    {
        return 'IN';
    }

    protected function getSqlValue($value)
    {
        return Arr::wrap($value);
    }

    public function applyOnCustom(Builder $builder, Field $field, $value, string $conjunction = 'and'): Builder
    {
        if (empty($value)) {
            return $builder;
        }

        $value = implode(",", $this->getSqlValue($value));

        $sql = "{$field->getColumn()} {$this->getSqlOperator()} ({$value})";

        return $builder->whereRaw($sql, [], $conjunction);
    }

    public function applyOnCount(Builder $builder, Field $field, $value, string $conjunction = 'and'): Builder
    {
        throw new UnsupportedOperatorException($this->name, 'count');
    }

    private function applyOnDate(Builder $builder, Field $field, $value, string $conjunction = 'and')
    {
        // cast to date string
        $values = array_map(function ($v) use ($field) {
            return $field->getCastedValue($v)->toDateString();
        }, $this->getSqlValue($value));

        if ($builder->getConnection()->getName() == 'sqlite') {
            return $this->applyDateOnSQLite($builder, $field->getColumn(), $values, $conjunction);
        }

        $builder->getQuery()->wheres[] = [
            'boolean' => $conjunction,
            'type' => 'date',
            'column' => $field->getColumn(),
            'operator' => $this->getSqlOperator(),
            'value' => new Expression("('" . implode("', '", $values) . "')"),
        ];

        return $builder;
    }

    private function applyDateOnSQLite(Builder $builder, string $column, $values, string $conjunction = 'and')
    {
        $bind = implode(',', array_fill(0, count($values), '?'));

        $sql = "strftime('%Y-%m-%d', `{$column}`) {$this->getSqlOperator()} ({$bind})";

        return $builder->whereRaw($sql, $values, $conjunction);
    }
}
