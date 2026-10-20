<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Case-insensitive "contains" search across a model's searchable columns.
 *
 * Uses whereLike(caseSensitive: false), which compiles to ILIKE on Postgres
 * (plain LIKE is case-sensitive there) and LIKE on SQLite/MySQL.
 */
trait Searchable
{
    /**
     * @return list<string>
     */
    abstract protected function searchableColumns(): array;

    /**
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $query) use ($term) {
            foreach ($this->searchableColumns() as $column) {
                $query->orWhereLike($column, '%'.$term.'%');
            }
        });
    }
}
