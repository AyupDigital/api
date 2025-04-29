<?php

namespace App\Http\Controllers\Core\V1\Service;

use App\Actions\SetStaleServicesToInactiveAction;
use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Service\DisableStale\UpdateRequest;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DisableStaleController extends Controller
{
    /**
     * DisableStaleController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(UpdateRequest $request, SetStaleServicesToInactiveAction $setStaleServicesToInactiveAction)
    {
        $setStaleServicesToInactiveAction->handle(CarbonImmutable::parse($request->last_modified_at));

        event(EndpointHit::onUpdate($request, "Disabled stale services from [{$request->last_modified_at}]"));

        return response()->json([
            'message' => 'Stale services have been disabled.',
        ]);
    }
}
