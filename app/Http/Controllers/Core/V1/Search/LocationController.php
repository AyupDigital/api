<?php

namespace App\Http\Controllers\Core\V1\Search;

use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LocationController
{
    public function __invoke(Request $request)
    {
        // TODO: Move this to a Request class if it grows.
        $request->validate([
            'query' => 'string|required',
            'page' => 'integer|nullable',
            'per_page' => 'integer|nullable',
        ]);

        $searchQuery = $request->input('query');

        $locations = Location::query()
            ->where(function (Builder $query) use ($searchQuery) {
                $query->where('address_line_1', 'like', '%'.$searchQuery.'%')
                    ->orWhere('address_line_2', 'like', '%'.$searchQuery.'%')
                    ->orWhere('city', 'like', '%'.$searchQuery.'%')
                    ->orWhere('postcode', 'like', '%'.$searchQuery.'%')
                    ->orWhere('country', 'like', '%'.$searchQuery.'%');
            })->orderBy('address_line_1', 'asc')->paginate(
                $request->input('per_page', 25),
                ['*'],
                'page',
                $request->input('page', 1)
            );
        
        return LocationResource::collection($locations);
    }
}
