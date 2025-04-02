<?php

namespace App\Http\Controllers\Core\V1;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Filters\UpdateRequest\EntryFilter;
use App\Http\Filters\UpdateRequest\TypeFilter;
use App\Http\Requests\UpdateRequest\DestroyRequest;
use App\Http\Requests\UpdateRequest\IndexRequest;
use App\Http\Requests\UpdateRequest\ShowRequest;
use App\Http\Resources\UpdateRequestResource;
use App\Http\Responses\ResourceDeleted;
use App\Models\Location;
use App\Models\Organisation;
use App\Models\OrganisationEvent;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Models\UpdateRequest;
use Exception;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UpdateRequestController extends Controller
{
    /**
     * UpdateRequestController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(IndexRequest $request): AnonymousResourceCollection
    {
        $baseQuery = UpdateRequest::query()
            ->select('*')
            ->withEntry()
            ->pending();

        $updateRequests = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                AllowedFilter::exact('id'),
                AllowedFilter::scope('service_id'),
                AllowedFilter::scope('service_location_id'),
                AllowedFilter::scope('location_id'),
                AllowedFilter::scope('organisation_id'),
                AllowedFilter::custom('entry', new EntryFilter()),
                AllowedFilter::custom('type', new TypeFilter()),
            ])
            ->allowedIncludes(['user'])
            ->allowedSorts([
                'entry',
                'created_at',
            ])
            ->defaultSort('-created_at')
            ->paginate(per_page($request->per_page));

        event(EndpointHit::onRead($request, 'Viewed all update requests'));

        return UpdateRequestResource::collection($updateRequests);
    }

    /**
     * Display the specified resource.
     */
    public function show(ShowRequest $request, UpdateRequest $updateRequest): UpdateRequestResource
    {
        $baseQuery = UpdateRequest::query()
            ->select('*')
            ->withEntry()
            ->where('id', $updateRequest->id);

        $updateRequest = QueryBuilder::for($baseQuery)
            ->firstOrFail();

        $canView = false;
        try {
            $updateRequest->load('updatable');
            $updatable = $request->updateable;
        } catch (Exception $e) {
            if ($request->user()->isGlobalAdmin()) {
                event(EndpointHit::onRead($request, "Viewed update request [{$updateRequest->id}]", $updateRequest));

            return new UpdateRequestResource($updateRequest);
            }
        }

        if ($updatable instanceof Service) {
            $canView = $request->user()->isServiceAdmin($updatable);
        }

        if ($updatable instanceof ServiceLocation) {
            $updateRequest->updatable->load('service');
            $canView = $request->user()->isServiceAdmin($updateRequest->updatable->service);
        }

        if ($updatable instanceof Organisation) {
            $canView = $request->user()->isOrganisationAdmin();
        }

        if ($updatable instanceof Location) {
            $canView = $request->user()->isServiceAdmin();
        }

        if ($updatable instanceof OrganisationEvent) {
            $updatable->load('organisation');
            $canView = $request->user()->isOrganisationAdmin($updateRequest->updatable->organisation);
        }

        if ($updatable instanceof Page) {
            $canView = $request->user()->isContentAdmin();
        }

        if (!$canView) {
            return abort(401);
        }

        event(EndpointHit::onRead($request, "Viewed update request [{$updateRequest->id}]", $updateRequest));

        return new UpdateRequestResource($updateRequest);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyRequest $request, UpdateRequest $updateRequest)
    {
        return DB::transaction(function () use ($request, $updateRequest) {
            $updateRequest->update([
                'rejection_message' => $request->input('message'),
            ]);
            event(EndpointHit::onDelete($request, "Deleted update request [{$updateRequest->id}]", $updateRequest));

            $updateRequest->delete($request->user('api'));

            return new ResourceDeleted('update request');
        });
    }
}
