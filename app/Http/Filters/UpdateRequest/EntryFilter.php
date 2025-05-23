<?php

namespace App\Http\Filters\UpdateRequest;

use App\Models\UpdateRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\Filters\Filter;

class EntryFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property): Builder
    {
        $sql = (new UpdateRequest)->getEntrySql();

        // Don't treat comma's as an array separator.
        $value = implode(',', Arr::wrap($value));

        return $query->whereRaw("({$sql}) LIKE ?", "%{$value}%");
    }
}
