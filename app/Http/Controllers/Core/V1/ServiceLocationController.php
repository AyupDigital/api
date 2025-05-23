<?php

namespace App\Http\Controllers\Core\V1;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceLocation\DestroyRequest;
use App\Http\Requests\ServiceLocation\IndexRequest;
use App\Http\Requests\ServiceLocation\ShowRequest;
use App\Http\Requests\ServiceLocation\StoreRequest;
use App\Http\Requests\ServiceLocation\UpdateRequest;
use App\Http\Resources\ServiceLocationResource;
use App\Http\Responses\ResourceDeleted;
use App\Http\Responses\UpdateRequestReceived;
use App\Models\File;
use App\Models\RegularOpeningHour;
use App\Models\ServiceLocation;
use App\Support\MissingValue;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ServiceLocationController extends Controller
{
    /**
     * ServiceLocationController constructor.
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
        $baseQuery = ServiceLocation::query()
            ->with('regularOpeningHours', 'holidayOpeningHours')
            ->orderBy('name');

        $serviceLocations = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                AllowedFilter::exact('id'),
                AllowedFilter::exact('service_id'),
            ])
            ->allowedIncludes(['location'])
            ->paginate(per_page($request->per_page));

        event(EndpointHit::onRead($request, 'Viewed all service locations'));

        return ServiceLocationResource::collection($serviceLocations);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        return DB::transaction(function () use ($request) {
            // Create the service location.
            $serviceLocation = ServiceLocation::create([
                'service_id' => $request->service_id,
                'location_id' => $request->location_id,
                'name' => $request->name,
                'image_file_id' => $request->image_file_id,
            ]);

            // Attach the regular opening hours.
            foreach ($request->regular_opening_hours as $regularOpeningHour) {
                $serviceLocation->regularOpeningHours()->create([
                    'frequency' => $regularOpeningHour['frequency'],
                    'weekday' => (in_array($regularOpeningHour['frequency'], [RegularOpeningHour::FREQUENCY_WEEKLY, RegularOpeningHour::FREQUENCY_NTH_OCCURRENCE_OF_MONTH]))
                        ? $regularOpeningHour['weekday']
                        : null,
                    'day_of_month' => ($regularOpeningHour['frequency'] === RegularOpeningHour::FREQUENCY_MONTHLY)
                        ? $regularOpeningHour['day_of_month']
                        : null,
                    'occurrence_of_month' => ($regularOpeningHour['frequency'] === RegularOpeningHour::FREQUENCY_NTH_OCCURRENCE_OF_MONTH)
                        ? $regularOpeningHour['occurrence_of_month']
                        : null,
                    'starts_at' => ($regularOpeningHour['frequency'] === RegularOpeningHour::FREQUENCY_FORTNIGHTLY)
                        ? $regularOpeningHour['starts_at']
                        : null,
                    'opens_at' => $regularOpeningHour['opens_at'],
                    'closes_at' => $regularOpeningHour['closes_at'],
                ]);
            }

            // Attach the holiday opening hours.
            foreach ($request->holiday_opening_hours as $holidayOpeningHour) {
                $serviceLocation->holidayOpeningHours()->create([
                    'is_closed' => $holidayOpeningHour['is_closed'],
                    'starts_at' => $holidayOpeningHour['starts_at'],
                    'ends_at' => $holidayOpeningHour['ends_at'],
                    'opens_at' => $holidayOpeningHour['opens_at'],
                    'closes_at' => $holidayOpeningHour['closes_at'],
                ]);
            }

            if ($request->filled('image_file_id')) {
                /** @var File $file */
                $file = File::findOrFail($request->image_file_id)->assigned();

                // Create resized version for common dimensions.
                foreach (config('local.cached_image_dimensions') as $maxDimension) {
                    $file->resizedVersion($maxDimension);
                }
            }

            event(EndpointHit::onCreate($request, "Created service location [{$serviceLocation->id}]", $serviceLocation));

            return new ServiceLocationResource($serviceLocation);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(ShowRequest $request, ServiceLocation $serviceLocation): ServiceLocationResource
    {
        $baseQuery = ServiceLocation::query()
            ->where('id', $serviceLocation->id);

        $serviceLocation = QueryBuilder::for($baseQuery)
            ->allowedIncludes(['location'])
            ->firstOrFail();

        event(EndpointHit::onRead($request, "Viewed service location [{$serviceLocation->id}]", $serviceLocation));

        return new ServiceLocationResource($serviceLocation);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, ServiceLocation $serviceLocation)
    {
        return DB::transaction(function () use ($request, $serviceLocation) {
            // Initialise the data array.
            $data = array_filter_missing([
                'name' => $request->missingValue('name'),
                'regular_opening_hours' => $request->has('regular_opening_hours') ? [] : new MissingValue,
                'holiday_opening_hours' => $request->has('holiday_opening_hours') ? [] : new MissingValue,
                'image_file_id' => $request->missingValue('image_file_id'),
            ]);

            // Loop through each regular opening hour to normalise and then append to the array.
            foreach ($request->input('regular_opening_hours', []) as $regularOpeningHour) {
                $data['regular_opening_hours'][] = array_filter_null([
                    'frequency' => $regularOpeningHour['frequency'],
                    'weekday' => in_array($regularOpeningHour['frequency'], [RegularOpeningHour::FREQUENCY_WEEKLY, RegularOpeningHour::FREQUENCY_NTH_OCCURRENCE_OF_MONTH])
                        ? $regularOpeningHour['weekday']
                        : null,
                    'day_of_month' => ($regularOpeningHour['frequency'] === RegularOpeningHour::FREQUENCY_MONTHLY)
                        ? $regularOpeningHour['day_of_month']
                        : null,
                    'occurrence_of_month' => ($regularOpeningHour['frequency'] === RegularOpeningHour::FREQUENCY_NTH_OCCURRENCE_OF_MONTH)
                        ? $regularOpeningHour['occurrence_of_month']
                        : null,
                    'starts_at' => ($regularOpeningHour['frequency'] === RegularOpeningHour::FREQUENCY_FORTNIGHTLY)
                        ? $regularOpeningHour['starts_at']
                        : null,
                    'opens_at' => $regularOpeningHour['opens_at'],
                    'closes_at' => $regularOpeningHour['closes_at'],
                ]);
            }

            // Loop through each holiday opening hour to normalise and then append to the array.
            foreach ($request->input('holiday_opening_hours', []) as $holidayOpeningHour) {
                $data['holiday_opening_hours'][] = [
                    'is_closed' => $holidayOpeningHour['is_closed'],
                    'starts_at' => $holidayOpeningHour['starts_at'],
                    'ends_at' => $holidayOpeningHour['ends_at'],
                    'opens_at' => $holidayOpeningHour['opens_at'],
                    'closes_at' => $holidayOpeningHour['closes_at'],
                ];
            }

            $updateRequest = $serviceLocation->updateRequests()->create([
                'user_id' => $request->user()->id,
                'data' => $data,
            ]);

            if ($request->filled('image_file_id')) {
                /** @var File $file */
                $file = File::findOrFail($request->image_file_id)->assigned();

                // Create resized version for common dimensions.
                foreach (config('local.cached_image_dimensions') as $maxDimension) {
                    $file->resizedVersion($maxDimension);
                }
            }

            event(EndpointHit::onUpdate($request, "Updated service location [{$serviceLocation->id}]", $serviceLocation));

            return new UpdateRequestReceived($updateRequest);
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyRequest $request, ServiceLocation $serviceLocation)
    {
        return DB::transaction(function () use ($request, $serviceLocation) {
            event(EndpointHit::onDelete($request, "Deleted service location [{$serviceLocation->id}]", $serviceLocation));

            $serviceLocation->delete();

            return new ResourceDeleted('service location');
        });
    }
}
