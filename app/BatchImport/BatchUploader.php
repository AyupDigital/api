<?php

namespace App\BatchImport;

use App\Models\Collection;
use App\Models\CollectionTaxonomy;
use App\Models\Location;
use App\Models\Organisation;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Models\SocialMedia;
use App\Models\Taxonomy;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BatchUploader
{
    /**
     * @var XlsxReader
     */
    protected $reader;

    /**
     * BatchUploader constructor.
     */
    public function __construct()
    {
        $this->reader = new XlsxReader;
        $this->reader->setReadDataOnly(true);
    }

    /**
     * Validates and then uploads the file.
     *
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws Exception
     */
    public function upload(string $filePath)
    {
        // Load the spreadsheet.
        $spreadsheet = $this->reader->load($filePath);

        // Load each worksheet.
        $organisationsSheet = $spreadsheet->getSheetByName('Organisation');
        $servicesSheet = $spreadsheet->getSheetByName('Service');
        $locationsSheet = $spreadsheet->getSheetByName('Location');
        $serviceLocationsSheet = $spreadsheet->getSheetByName('Service Location');
        $collectionCategoriesSheet = $spreadsheet->getSheetByName('Collection - Category');
        $taxonomyServicesSheet = $spreadsheet->getSheetByName('Taxonomies - Service');
        $taxonomyCategoriesSheet = $spreadsheet->getSheetByName('Taxonomies - Category');

        // Convert the worksheets to associative arrays.
        $organisations = $this->toArray($organisationsSheet);
        $services = $this->toArray($servicesSheet);
        $locations = $this->toArray($locationsSheet);
        $serviceLocations = $this->toArray($serviceLocationsSheet);
        $collections = $this->toArray($collectionCategoriesSheet); // Categories only - not persona.
        $serviceTaxonomies = $this->toArray($taxonomyServicesSheet);
        $collectionTaxonomies = $this->toArray($taxonomyCategoriesSheet);

        // Process.
        try {
            DB::beginTransaction();

            $collections = $this->processCollections($collections);
            $this->processCollectionTaxonomies($collectionTaxonomies, $collections);
            $locations = $this->processLocations($locations);
            $organisations = $this->processOrganisations($organisations);
            $services = $this->processServices($services, $organisations);
            $this->processServiceLocations($serviceLocations, $services, $locations);
            $this->processServiceTaxonomies($serviceTaxonomies, $services);

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    protected function toArray(Worksheet $sheet): array
    {
        $array = $sheet->toArray();
        $headings = array_shift($array);

        $array = array_map(function ($row) use ($headings) {
            $resource = [];

            foreach ($headings as $column => $heading) {
                $resource[$heading] = $row[$column];
            }

            return $resource;
        }, $array);

        return $array;
    }

    protected function processCollections(array $collections): EloquentCollection
    {
        $order = Collection::categories()->orderByDesc('order')->first()->order;

        $collections = new EloquentCollection($collections);
        $collections = $collections->map(function (array $collectionArray) use (&$order): Collection {
            // Increment order.
            $order++;

            // Create a collection instance.
            $collection = Collection::create([
                'type' => Collection::TYPE_CATEGORY,
                'name' => $collectionArray['Category Name'],
                'meta' => [
                    'icon' => 'coffee',
                    'intro' => 'Lorem ipsum',
                ],
                'order' => $order,
            ]);

            // Assign the ID provided by the spreadsheet.
            $collection->_id = $collectionArray['Category ID'];

            return $collection;
        });

        return $collections;
    }

    protected function processCollectionTaxonomies(
        array $collectionTaxonomies,
        EloquentCollection $collections
    ): EloquentCollection {
        $collectionTaxonomies = new EloquentCollection($collectionTaxonomies);
        $collectionTaxonomies = $collectionTaxonomies->map(function (array $collectionTaxonomyArray) use (
            $collections
        ): CollectionTaxonomy {
            // Get the collection ID.
            $collectionId = $collections->first(function (Collection $collection) use ($collectionTaxonomyArray): bool {
                return $collection->_id == $collectionTaxonomyArray['Collection ID'];
            })->id;

            // Create a collection taxonomy instance.
            return CollectionTaxonomy::create([
                'collection_id' => $collectionId,
                'taxonomy_id' => $collectionTaxonomyArray['Taxonomy ID'],
            ]);
        });

        return $collectionTaxonomies;
    }

    protected function processLocations(array $locations): EloquentCollection
    {
        $locations = new EloquentCollection($locations);
        $locations = $locations->map(function (array $locationArray): Location {
            $location = new Location(array_filter([
                'address_line_1' => $locationArray['Address Line 1*'],
                'address_line_2' => $locationArray['Address Line 2'],
                'address_line_3' => $locationArray['Address Line 3'],
                'city' => $locationArray['City*'],
                'county' => $locationArray['County*'],
                'postcode' => $locationArray['Postcode*'],
                'country' => $locationArray['Country*'],
            ]));

            $location->has_wheelchair_access = false;
            $location->has_induction_loop = false;

            // Save the location.
            $location->updateCoordinate()->save();

            // Assign the ID provided by the spreadsheet.
            $location->_id = $locationArray['ID*'];

            return $location;
        });

        return $locations;
    }

    protected function processOrganisations(array $organisations): EloquentCollection
    {
        $organisations = new EloquentCollection($organisations);
        $organisations = $organisations->map(function (array $organisationArray): Organisation {
            $slug = Str::slug($organisationArray['Name*']);
            $iteration = 0;
            do {
                $slug = $iteration > 0 ? $slug.'-'.$iteration : $slug;
                $duplicate = Organisation::query()->where('slug', $slug)->exists();
                $iteration++;
            } while ($duplicate);

            $organisation = Organisation::create([
                'slug' => $slug,
                'name' => $organisationArray['Name*'],
                'description' => $organisationArray['Description*'],
                'url' => $organisationArray['URL*'],
                'email' => $organisationArray['Email*'],
                'phone' => $organisationArray['Phone*'],
            ]);

            $organisation->_id = $organisationArray['ID*'];

            return $organisation;
        });

        return $organisations;
    }

    protected function processServices(array $services, EloquentCollection $organisations): EloquentCollection
    {
        $services = new EloquentCollection($services);
        $services = $services->filter(function ($serviceArray) {
            return $serviceArray['ID*'] &&
                $serviceArray['Organisation ID*'] &&
                $serviceArray['Name*'];
        })->map(function (array $serviceArray) use ($organisations): Service {
            $organisationId = $organisations->first(function (Organisation $organisation) use ($serviceArray): bool {
                return $organisation->_id == $serviceArray['Organisation ID*'];
            })->id;

            $slug = Str::slug($serviceArray['Name*']);
            $iteration = 0;
            do {
                $slug = $iteration > 0 ? $slug.'-'.$iteration : $slug;
                $duplicate = Service::query()->where('slug', $slug)->exists();
                $iteration++;
            } while ($duplicate);

            $isFree = $serviceArray['Is Free*'] == 'yes';

            $isInternal = $serviceArray['Referral Method*'] == 'internal';
            $isExternal = $serviceArray['Referral Method*'] == 'external';
            $isNone = $serviceArray['Referral Method*'] == 'none';

            $service = Service::create([
                'organisation_id' => $organisationId,
                'slug' => $slug,
                'name' => $serviceArray['Name*'],
                'status' => $serviceArray['Status*'] ?: Service::STATUS_ACTIVE,
                'intro' => Str::limit($serviceArray['Intro*'], 250),
                'description' => $serviceArray['Description*'],
                'wait_time' => $this->parseWaitTime($serviceArray['Wait Time']),
                'is_free' => $isFree,
                'fees_text' => ! $isFree ? Str::limit($serviceArray['Fees Text'], 250) : null,
                'fees_url' => ! $isFree ? $serviceArray['Fees URL'] : null,
                'testimonial' => $serviceArray['Testimonial'],
                'video_embed' => $serviceArray['Video Embed'],
                'url' => $serviceArray['URL*'],
                'contact_name' => $serviceArray['Contact Name'],
                'contact_phone' => $serviceArray['Contact Phone'],
                'contact_email' => $serviceArray['Contact Email'],
                'referral_method' => $serviceArray['Referral Method*'] ?: Service::REFERRAL_METHOD_NONE,
                'referral_button_text' => ! $isNone ? 'Make referral' : null,
                'referral_email' => $isInternal ? $serviceArray['Referral Email'] : null,
                'referral_url' => $isExternal ? $serviceArray['Referral URL'] : null,
                'last_modified_at' => Date::now(),
            ]);

            $service->_id = $serviceArray['ID*'];

            $service->social_medias = $this->processSocialMedia($serviceArray, $service);

            return $service;
        });

        return $services;
    }

    protected function parseWaitTime(?string $waitTime): ?string
    {
        switch ($waitTime) {
            case 'Within a week':
                return Service::WAIT_TIME_ONE_WEEK;
            case 'Up to two weeks':
                return Service::WAIT_TIME_TWO_WEEKS;
            case 'Up to three weeks':
                return Service::WAIT_TIME_THREE_WEEKS;
            case 'Up to a month':
                return Service::WAIT_TIME_MONTH;
            case 'Not applicable for this service':
            default:
                return null;
        }
    }

    protected function processSocialMedia(array $serviceArray, Service $service): EloquentCollection
    {
        $socialMedias = new EloquentCollection;

        if ($serviceArray['Social Medias - Twitter']) {
            $socialMedias->push($service->socialMedias()->create([
                'type' => SocialMedia::TYPE_TWITTER,
                'url' => $serviceArray['Social Medias - Twitter'],
            ]));
        }

        if ($serviceArray['Social Medias - Facebook']) {
            $socialMedias->push($service->socialMedias()->create([
                'type' => SocialMedia::TYPE_FACEBOOK,
                'url' => $serviceArray['Social Medias - Facebook'],
            ]));
        }

        if ($serviceArray['Social Medias - Instagram']) {
            $socialMedias->push($service->socialMedias()->create([
                'type' => SocialMedia::TYPE_INSTAGRAM,
                'url' => $serviceArray['Social Medias - Instagram'],
            ]));
        }

        if ($serviceArray['Social Medias - YouTube']) {
            $socialMedias->push($service->socialMedias()->create([
                'type' => SocialMedia::TYPE_YOUTUBE,
                'url' => $serviceArray['Social Medias - YouTube'],
            ]));
        }

        if ($serviceArray['Social Medias - Other']) {
            $socialMedias->push($service->socialMedias()->create([
                'type' => SocialMedia::TYPE_OTHER,
                'url' => $serviceArray['Social Medias - Other'],
            ]));
        }

        return $socialMedias;
    }

    protected function processUsefulInfo(array $serviceArray, Service $service): EloquentCollection
    {
        $usefulInfos = new EloquentCollection;

        if ($serviceArray['Useful Info 1 - Title'] && $serviceArray['Useful Info 1 - Description']) {
            $usefulInfos->push($service->usefulInfos()->create([
                'title' => $serviceArray['Useful Info 1 - Title'],
                'description' => $serviceArray['Useful Info 1 - Description'],
                'order' => 1,
            ]));

            if ($serviceArray['Useful Info 2 - Title'] && $serviceArray['Useful Info 2 - Description']) {
                $usefulInfos->push($service->usefulInfos()->create([
                    'title' => $serviceArray['Useful Info 2 - Title'],
                    'description' => $serviceArray['Useful Info 2 - Description'],
                    'order' => 2,
                ]));
            }
        }

        return $usefulInfos;
    }

    protected function processServiceLocations(
        array $serviceLocations,
        EloquentCollection $services,
        EloquentCollection $locations
    ): EloquentCollection {
        $serviceLocations = new EloquentCollection($serviceLocations);

        $serviceLocations = $serviceLocations->filter(function ($serviceLocationArray) {
            return $serviceLocationArray['Service ID*'] && $serviceLocationArray['Location ID*'];
        })->map(function (array $serviceLocationArray) use ($services, $locations): ServiceLocation {
            $serviceId = $services->first(function (Service $service) use ($serviceLocationArray): bool {
                return $service->_id == $serviceLocationArray['Service ID*'];
            })->id;

            $locationId = $locations->first(function (Location $location) use ($serviceLocationArray): bool {
                return $location->_id == $serviceLocationArray['Location ID*'];
            })->id;

            return ServiceLocation::create([
                'service_id' => $serviceId,
                'location_id' => $locationId,
            ]);
        });

        return $serviceLocations;
    }

    protected function processServiceTaxonomies(
        array $serviceTaxonomies,
        EloquentCollection $services
    ): EloquentCollection {
        $serviceTaxonomies = new EloquentCollection($serviceTaxonomies);

        $taxonomies = $serviceTaxonomies->map(function (array $serviceTaxonomyArray) {
            $taxonomy = Taxonomy::findOrFail($serviceTaxonomyArray['Taxonomy ID']);
            $taxonomy->_service_id = $serviceTaxonomyArray['Service ID'];

            return $taxonomy;
        })->groupBy('_service_id');

        $services->each(function (Service $service) use ($taxonomies) {
            if ($taxonomies->has($service->_id)) {
                $service->syncTaxonomyRelationships($taxonomies[$service->_id]);
            }
        });

        return $services->load('serviceTaxonomies');
    }
}
