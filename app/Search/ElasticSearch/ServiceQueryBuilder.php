<?php

namespace App\Search\ElasticSearch;

use App\Contracts\QueryBuilder;
use App\Models\Collection;
use App\Models\Service;
use App\Models\Taxonomy;
use App\Search\SearchCriteriaQuery;
use App\Support\Coordinate;
use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class ServiceQueryBuilder extends ElasticsearchQueryBuilder implements QueryBuilder
{
    public function __construct()
    {
        $this->esQuery = [
            'function_score' => [
                'query' => [
                    'bool' => [
                        'must' => [],
                        'should' => [],
                        'filter' => [],
                    ],
                ],
                'functions' => [
                    [
                        'script_score' => [
                            'script' => [
                                'source' => "doc['score'].size() == 0 ? 1 : ((doc['score'].value + 1) * 0.1) + 1",
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->mustPath = 'function_score.query.bool.must';
        $this->shouldPath = 'function_score.query.bool.should';
        $this->filterPath = 'function_score.query.bool.filter';
    }

    public function build(SearchCriteriaQuery $query, ?int $page = null, ?int $perPage = null): SearchRequestBuilder
    {
        $page = page($page);
        $perPage = per_page($perPage);

        $this->applyStatus(Service::STATUS_ACTIVE);

        if ($query->hasQuery()) {
            $this->applyQuery($query->getQuery());
        }

        if ($query->hasCategories()) {
            $this->applyCategories($query->getCategories());
        }

        if ($query->hasPersonas()) {
            $this->applyPersonas($query->getPersonas());
        }

        if ($query->hasWaitTime()) {
            $this->applyWaitTime($query->getWaitTime());
        }

        if ($query->hasIsFree()) {
            $this->applyIsFree($query->getIsFree());
        }

        if ($query->hasEligibilities()) {
            $this->applyEligibilities($query->getEligibilities());
        }

        if ($query->hasPolygon()) {
            $this->applyPolygon($query->getPolygon());
        }

        if ($query->hasLocation()) {
            $this->applyLocation($query->getLocation(), $query->getDistance());
        }

        $searchQuery = Service::searchQuery($this->esQuery)
            ->size($perPage)
            ->from(($page - 1) * $perPage);

        if ($query->hasOrder()) {
            $searchQuery->sortRaw($this->applyOrder($query));
        }

        return $searchQuery;
    }

    protected function applyStatus(string $status): void
    {
        $this->addFilter('status', $status);
    }

    protected function applyQuery(string $query): void
    {
        $this->addMatch('name', $query, $this->shouldPath, 3);
        $this->addMatch('name', $query, $this->shouldPath, 4, 'AUTO', 'AND');
        $this->addMatchPhrase('name', $query, $this->shouldPath, 6);
        $this->addMatch('organisation_name', $query, $this->shouldPath, 3);
        $this->addMatch('organisation_name', $query, $this->shouldPath, 4, 'AUTO', 'AND');
        $this->addMatchPhrase('organisation_name', $query, $this->shouldPath, 6);
        $this->addMatch('intro', $query, $this->shouldPath);
        $this->addMatch('intro', $query, $this->shouldPath, 2, 'AUTO', 'AND');
        $this->addMatchPhrase('intro', $query, $this->shouldPath, 3);
        $this->addMatch('description', $query, $this->shouldPath);
        $this->addMatch('description', $query, $this->shouldPath, 1.5, 'AUTO', 'AND');
        $this->addMatchPhrase('description', $query, $this->shouldPath, 2);
        $this->addMatch('taxonomy_categories', $query, $this->shouldPath);
        $this->addMatch('taxonomy_categories', $query, $this->shouldPath, 2, 'AUTO', 'AND');
        $this->addMatchPhrase('taxonomy_categories', $query, $this->shouldPath, 3);

        $this->addMinimumShouldMatch();
    }

    protected function applyCategories(array $categorySlugs): void
    {
        $categoryNames = Collection::query()
            ->whereIn('slug', $categorySlugs)
            ->with('children')
            ->get()
            ->flatMap(function ($collection) {
                return $collection->children->pluck('slug')->prepend($collection->slug);
            })
            ->unique()
            ->all();

        // Add each category name to the should clause
        // foreach ($categoryNames as $categoryName) {
        //  $this->addShould('collection_categories', $categoryName);
        // }
        $this->addFilter('collection_categories', $categoryNames);

        // Set minimum_should_match to 1 to ensure at least one should clause matches
        // $this->addMinimumShouldMatch(1);
    }

    protected function applyPersonas(array $personaSlugs): void
    {
        $personaNames = Collection::query()
            ->whereIn('slug', $personaSlugs)
            ->pluck('name')
            ->all();

        $this->addFilter('collection_personas', $personaNames);
    }

    protected function applyWaitTime(string $waitTime): void
    {
        if (! Service::waitTimeIsValid($waitTime)) {
            throw new InvalidArgumentException("The wait time [$waitTime] is not valid");
        }

        $criteria = [];

        switch ($waitTime) {
            case Service::WAIT_TIME_ONE_WEEK:
                $criteria[] = Service::WAIT_TIME_ONE_WEEK;
                break;
            case Service::WAIT_TIME_TWO_WEEKS:
                $criteria[] = Service::WAIT_TIME_ONE_WEEK;
                $criteria[] = Service::WAIT_TIME_TWO_WEEKS;
                break;
            case Service::WAIT_TIME_THREE_WEEKS:
                $criteria[] = Service::WAIT_TIME_ONE_WEEK;
                $criteria[] = Service::WAIT_TIME_TWO_WEEKS;
                $criteria[] = Service::WAIT_TIME_THREE_WEEKS;
                break;
            case Service::WAIT_TIME_MONTH:
                $criteria[] = Service::WAIT_TIME_ONE_WEEK;
                $criteria[] = Service::WAIT_TIME_TWO_WEEKS;
                $criteria[] = Service::WAIT_TIME_THREE_WEEKS;
                $criteria[] = Service::WAIT_TIME_MONTH;
                break;
            case Service::WAIT_TIME_LONGER:
                $criteria[] = Service::WAIT_TIME_ONE_WEEK;
                $criteria[] = Service::WAIT_TIME_TWO_WEEKS;
                $criteria[] = Service::WAIT_TIME_THREE_WEEKS;
                $criteria[] = Service::WAIT_TIME_MONTH;
                $criteria[] = Service::WAIT_TIME_LONGER;
                break;
        }

        $this->addFilter('wait_time', $criteria);
    }

    protected function applyIsFree(bool $isFree): void
    {
        $this->addFilter('is_free', $isFree);
    }

    protected function applyEligibilities(array $eligibilityNames): void
    {
        $eligibilities = Taxonomy::whereIn('name', $eligibilityNames)->get();
        $eligibilityIds = $eligibilities->pluck('id')->all();

        // Iterate over the children of the root Service Eligibility taxonomy as 'types'
        foreach (Taxonomy::serviceEligibility()->children as $serviceEligibilityType) {
            // If the eligibilities are descendants of the Service Eligibility type
            if ($serviceEligibilityTypeOptionIds = $serviceEligibilityType->filterDescendants($eligibilityIds)) {
                // Get the eligibility names
                $serviceEligibilityTypeNames = $eligibilities->filter(
                    function ($eligibility) use ($serviceEligibilityTypeOptionIds) {
                        return in_array($eligibility->id, $serviceEligibilityTypeOptionIds);
                    }
                )->pluck('name')->all();

                // Create the Service Eligibility type name
                $serviceEligibilityTypeAllName = $serviceEligibilityType->name.' All';

                // Filter by service eligibility names and type name
                $this->addFilter('service_eligibilities.keyword', array_merge($serviceEligibilityTypeNames, [$serviceEligibilityTypeAllName]));

                $this->addMinimumShouldMatch();

                // Add terms for each name which will add to score
                foreach ($serviceEligibilityTypeNames as $serviceEligibilityTypeName) {
                    $this->addTerm('service_eligibilities.keyword', $serviceEligibilityTypeName, $this->shouldPath);
                }

                // Add a match for the type name which will not add to score
                $this->addMatch('service_eligibilities', $serviceEligibilityTypeAllName, $this->shouldPath, 0);
            }
        }
    }

    protected function applyPolygon(array $coordinates): void
    {
        $matches = Arr::get($this->esQuery, $this->mustPath);
        $matches[] = [
            'nested' => [
                'path' => 'service_locations',
                'query' => [
                    'geo_polygon' => [
                        'service_locations.location' => [
                            'points' => $coordinates,
                        ],
                    ],
                ],
            ],
        ];
        Arr::set($this->esQuery, $this->mustPath, $matches);
    }

    protected function applyLocation(Coordinate $coordinate, ?int $distance): void
    {
        // Add filter for listings within a search distance miles radius, or national.
        $matches = Arr::get($this->esQuery, $this->mustPath);
        $matches[] = [
            'nested' => [
                'path' => 'service_locations',
                'query' => [
                    'geo_distance' => [
                        'distance' => $distance ? $distance.'mi' : config('local.search_distance').'mi',
                        'service_locations.location' => $coordinate->toArray(),
                    ],
                ],
            ],
        ];
        Arr::set($this->esQuery, $this->mustPath, $matches);

        // Apply scoring for favouring results closer to the coordinate.
        $this->esQuery['function_score']['functions'][] = [
            'gauss' => [
                'service_locations.location' => [
                    'origin' => $coordinate->toArray(),
                    'scale' => '1mi',
                ],
            ],
        ];
    }

    protected function applyOrder(SearchCriteriaQuery $query): array
    {
        if ($query->getOrder() === static::ORDER_DISTANCE) {
            return [
                [
                    '_geo_distance' => [
                        'service_locations.location' => $query->getLocation()->toArray(),
                        'nested_path' => 'service_locations',
                    ],
                ],
            ];
        }

        return ['_score'];
    }

    protected function addShould(string $field, $value): void
    {
        $should = Arr::get($this->esQuery, $this->shouldPath);
        $should[] = [
            'term' => [
                $field => $value,
            ],
        ];
        Arr::set($this->esQuery, $this->shouldPath, $should);
    }

    protected function addMinimumShouldMatch(int $count = 1): void
    {
        $bool = Arr::get($this->esQuery, 'function_score.query.bool');
        $bool['minimum_should_match'] = $count;
        Arr::set($this->esQuery, 'function_score.query.bool', $bool);
    }
}
