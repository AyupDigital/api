<?php

namespace App\Http\Controllers\Core\V1\Location;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Location\Image\ShowRequest;
use App\Models\File;
use App\Models\Location;
use App\Models\UpdateRequest;

class ImageController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function __invoke(ShowRequest $request, Location $location)
    {
        event(EndpointHit::onRead($request, "Viewed image for location [{$location->id}]", $location));

        // Get the image file associated.
        $file = $location->imageFile;

        // Use the file from an update request instead, if specified.
        if ($request->has('update_request_id')) {
            $imageFileId = UpdateRequest::query()
                ->locationId($location->id)
                ->where('id', '=', $request->update_request_id)
                ->firstOrFail()
                ->data['image_file_id'];

            /** @var File $file */
            $file = File::findOrFail($imageFileId);
        }

        // Return the file, or placeholder if the file is null.
        return $file?->resizedVersion($request->max_dimension)
            ?? Location::placeholderImage($request->max_dimension);
    }
}
