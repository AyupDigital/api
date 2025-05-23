<?php

namespace App\Http\Controllers\Core\V1\Organisation;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\Logo\ShowRequest;
use App\Models\File;
use App\Models\Organisation;
use App\Models\UpdateRequest;

class LogoController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function __invoke(ShowRequest $request, Organisation $organisation)
    {
        event(EndpointHit::onRead($request, "Viewed logo for organisation [{$organisation->id}]", $organisation));

        // Get the logo file associated.
        $file = $organisation->logoFile;

        // Use the file from an update request instead, if specified.
        if ($request->has('update_request_id')) {
            $logoFileId = UpdateRequest::query()
                ->organisationId($organisation->id)
                ->where('id', '=', $request->update_request_id)
                ->firstOrFail()
                ->data['logo_file_id'];

            /** @var File $file */
            $file = File::findOrFail($logoFileId);
        }

        // Return the file, or placeholder if the file is null.
        return $file?->resizedVersion($request->max_dimension)
            ?? Organisation::placeholderLogo($request->max_dimension);
    }
}
