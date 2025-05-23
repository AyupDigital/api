<?php

namespace App\Http\Controllers\Core\V1;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Location\DestroyRequest;
use App\Http\Requests\Location\IndexRequest;
use App\Http\Requests\Location\ShowRequest;
use App\Http\Requests\Location\StoreRequest;
use App\Http\Requests\Location\UpdateRequest;
use App\Http\Resources\LocationResource;
use App\Http\Responses\ResourceDeleted;
use App\Http\Responses\UpdateRequestReceived;
use App\Models\File;
use App\Models\Location;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LocationController extends Controller
{
    /**
     * LocationController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api')->except('index', 'show');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(IndexRequest $request): AnonymousResourceCollection
    {
        $baseQuery = Location::query();

        $locations = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                AllowedFilter::exact('id'),
                'address_line_1',
                'address_line_2',
                'address_line_3',
                'city',
                'county',
                'postcode',
                'country',
            ])
            ->allowedIncludes(['pendingUpdateRequests'])
            ->allowedSorts([
                'address_line_1',
                'address_line_2',
                'address_line_3',
                'city',
                'county',
                'postcode',
                'country',
            ])
            ->defaultSorts([
                'address_line_1',
                'address_line_2',
                'address_line_3',
                'city',
                'county',
                'postcode',
                'country',
            ])
            ->paginate(per_page($request->per_page));

        event(EndpointHit::onRead($request, 'Viewed all locations'));

        return LocationResource::collection($locations);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        return DB::transaction(function () use ($request) {
            // Create a location instance.
            $location = new Location([
                'address_line_1' => $request->address_line_1,
                'address_line_2' => $request->address_line_2,
                'address_line_3' => $request->address_line_3,
                'city' => $request->city,
                'county' => $request->county,
                'postcode' => $request->postcode,
                'country' => $request->country,
                'accessibility_info' => $request->accessibility_info,
                'has_wheelchair_access' => $request->has_wheelchair_access,
                'has_induction_loop' => $request->has_induction_loop,
                'has_accessible_toilet' => $request->has_accessible_toilet,
                'image_file_id' => $request->image_file_id,
            ]);

            if ($request->filled('image_file_id')) {
                /** @var File $file */
                $file = File::findOrFail($request->image_file_id)->assigned();

                // Create resized version for common dimensions.
                foreach (config('local.cached_image_dimensions') as $maxDimension) {
                    $file->resizedVersion($maxDimension);
                }
            }

            // Persist the record to the database.
            $location->updateCoordinate()->save();

            event(EndpointHit::onCreate($request, "Created location [{$location->id}]", $location));

            return new LocationResource($location);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(ShowRequest $request, Location $location): LocationResource
    {
        $baseQuery = Location::query()
            ->where('id', $location->id);

        $location = QueryBuilder::for($baseQuery)
            ->firstOrFail();

        event(EndpointHit::onRead($request, "Viewed location [{$location->id}]", $location));

        return new LocationResource($location);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, Location $location)
    {
        return DB::transaction(function () use ($request, $location) {
            $updateRequest = $location->updateRequests()->create([
                'user_id' => $request->user()->id,
                'data' => array_filter_missing([
                    'address_line_1' => $request->missingValue('address_line_1'),
                    'address_line_2' => $request->missingValue('address_line_2'),
                    'address_line_3' => $request->missingValue('address_line_3'),
                    'city' => $request->missingValue('city'),
                    'county' => $request->missingValue('county'),
                    'postcode' => $request->missingValue('postcode'),
                    'country' => $request->missingValue('country'),
                    'accessibility_info' => $request->missingValue('accessibility_info'),
                    'has_wheelchair_access' => $request->missingValue('has_wheelchair_access'),
                    'has_induction_loop' => $request->missingValue('has_induction_loop'),
                    'has_accessible_toilet' => $request->missingValue('has_accessible_toilet'),
                    'image_file_id' => $request->missingValue('image_file_id'),
                ]),
            ]);

            if ($request->filled('image_file_id')) {
                /** @var File $file */
                $file = File::findOrFail($request->image_file_id)->assigned();

                // Create resized version for common dimensions.
                foreach (config('local.cached_image_dimensions') as $maxDimension) {
                    $file->resizedVersion($maxDimension);
                }
            }

            event(EndpointHit::onUpdate($request, "Updated location [{$location->id}]", $location));

            return new UpdateRequestReceived($updateRequest);
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyRequest $request, Location $location)
    {
        return DB::transaction(function () use ($request, $location) {
            event(EndpointHit::onDelete($request, "Deleted location [{$location->id}]", $location));

            $location->delete();

            return new ResourceDeleted('location');
        });
    }
}
