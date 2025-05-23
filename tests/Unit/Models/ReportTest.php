<?php

namespace Tests\Unit\Models;

use App\Models\Audit;
use App\Models\Location;
use App\Models\Organisation;
use App\Models\PageFeedback;
use App\Models\Referral;
use App\Models\Report;
use App\Models\ReportType;
use App\Models\Role;
use App\Models\SearchHistory;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Models\UpdateRequest;
use App\Models\User;
use App\Search\ElasticSearch\ElasticsearchQueryBuilder;
use App\Search\ElasticSearch\ServiceQueryBuilder;
use App\Search\SearchCriteriaQuery;
use App\Support\Coordinate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ReportTest extends TestCase
{
    /*
     * Users export.
     */

    public function test_users_export_works_with_super_admin(): void
    {
        // Create a single user.
        $user = User::factory()->create()->makeSuperAdmin();

        // Generate the report.
        $report = Report::generate(ReportType::usersExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'User Reference ID',
            'User First Name',
            'User Last Name',
            'Email address',
            'Highest Permission Level',
            'Organisation/Service Permission Levels',
            'Organisation/Service IDs',
        ], $csv[0]);

        // Assert created user exported.
        $this->assertEquals([
            $user->id,
            $user->first_name,
            $user->last_name,
            $user->email,
            Role::NAME_SUPER_ADMIN,
            '',
            '',
        ], $csv[1]);
    }

    public function test_users_export_works_with_organisation_admin(): void
    {
        // Create an organisation.
        $organisation = Organisation::factory()->create();

        // Create a single user.
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        // Generate the report.
        $report = Report::generate(ReportType::usersExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'User Reference ID',
            'User First Name',
            'User Last Name',
            'Email address',
            'Highest Permission Level',
            'Organisation/Service Permission Levels',
            'Organisation/Service IDs',
        ], $csv[0]);

        // Assert created user exported.
        $this->assertEquals([
            $user->id,
            $user->first_name,
            $user->last_name,
            $user->email,
            Role::NAME_ORGANISATION_ADMIN,
            Role::NAME_ORGANISATION_ADMIN,
            $organisation->id,
        ], $csv[1]);
    }

    public function test_users_export_works_with_service_admin(): void
    {
        // Create a service.
        $service = Service::factory()->create();

        // Create a single user.
        $user = User::factory()->create()->makeServiceAdmin($service);

        // Generate the report.
        $report = Report::generate(ReportType::usersExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'User Reference ID',
            'User First Name',
            'User Last Name',
            'Email address',
            'Highest Permission Level',
            'Organisation/Service Permission Levels',
            'Organisation/Service IDs',
        ], $csv[0]);

        // Assert created user exported.
        $this->assertEquals([
            $user->id,
            $user->first_name,
            $user->last_name,
            $user->email,
            Role::NAME_SERVICE_ADMIN,
            Role::NAME_SERVICE_ADMIN,
            $service->id,
        ], $csv[1]);
    }

    public function test_users_export_works_with_organisation_and_service_admin(): void
    {
        // Create an organisation.
        $organisation = Organisation::factory()->create();

        // Create an organisation admin user.
        $orgAdmin = User::factory()->create()->makeOrganisationAdmin($organisation);

        // Create a service.
        $service = Service::factory()->create();

        // Create a service admin user.
        $serviceAdmin = User::factory()->create()->makeServiceAdmin($service);

        // Create an organisation and service admin
        $orgServiceAdmin = User::factory()->create()->makeOrganisationAdmin($organisation);
        $orgServiceAdmin->makeServiceAdmin($service);

        // Generate the report.
        $report = Report::generate(ReportType::usersExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(4, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'User Reference ID',
            'User First Name',
            'User Last Name',
            'Email address',
            'Highest Permission Level',
            'Organisation/Service Permission Levels',
            'Organisation/Service IDs',
        ], $csv[0]);

        // Assert created user exported.
        $this->assertContains([
            $orgAdmin->id,
            $orgAdmin->first_name,
            $orgAdmin->last_name,
            $orgAdmin->email,
            Role::NAME_ORGANISATION_ADMIN,
            Role::NAME_ORGANISATION_ADMIN,
            $organisation->id,
        ], $csv);

        $this->assertContains([
            $serviceAdmin->id,
            $serviceAdmin->first_name,
            $serviceAdmin->last_name,
            $serviceAdmin->email,
            Role::NAME_SERVICE_ADMIN,
            Role::NAME_SERVICE_ADMIN,
            $service->id,
        ], $csv);

        $this->assertContains([
            $orgServiceAdmin->id,
            $orgServiceAdmin->first_name,
            $orgServiceAdmin->last_name,
            $orgServiceAdmin->email,
            Role::NAME_ORGANISATION_ADMIN,
            implode(',', [Role::NAME_ORGANISATION_ADMIN, Role::NAME_SERVICE_ADMIN]),
            implode(',', [$organisation->id, $service->id]),
        ], $csv);
    }

    /*
     * Services export.
     */

    public function test_services_export_works(): void
    {
        // Create a single service.
        $service = Service::factory()->create();

        // Generate the report.
        $report = Report::generate(ReportType::servicesExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'Organisation',
            'Org Reference ID',
            'Org Email',
            'Org Phone',
            'Service Reference ID',
            'Service Name',
            'Service Web Address',
            'Service Contact Name',
            'Last Updated',
            'Referral Type',
            'Referral Contact',
            'Status',
            'Locations Delivered At',
        ], $csv[0]);

        // Assert created service exported.
        $this->assertEquals([
            $service->organisation->name,
            $service->organisation->id,
            $service->organisation->email,
            $service->organisation->phone,
            $service->id,
            $service->name,
            $service->url,
            $service->contact_name,
            $service->updated_at->format(CarbonImmutable::ISO8601),
            $service->referral_method,
            $service->referral_email ?? '',
            $service->status,
            $service->serviceLocations->map(function (ServiceLocation $serviceLocation) {
                return $serviceLocation->location->full_address;
            })->implode('|'),
        ], $csv[1]);
    }

    /*
     * Organisations export.
     */

    public function test_organisations_export_works(): void
    {
        // Create a single organisation.
        $organisation = Organisation::factory()->create();

        // Create an admin and non-admin user.
        User::factory()->create()->makeSuperAdmin();
        User::factory()->create()->makeGlobalAdmin();
        User::factory()->create()->makeOrganisationAdmin($organisation);

        $headings = [
            'Organisation Reference ID',
            'Organisation Name',
            'Number of Services',
            'Organisation Email',
            'Organisation Phone',
            'Organisation URL',
            'Number of Accounts Attributed',
        ];

        // Generate the report.
        $report = Report::generate(ReportType::organisationsExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals($headings, $csv[0]);

        // Assert created organisation exported.
        $this->assertEquals([
            $organisation->id,
            $organisation->name,
            0,
            $organisation->email,
            $organisation->phone,
            $organisation->url,
            1,
        ], $csv[1]);

        // Create a service
        $service = Service::factory()->create([
            'organisation_id' => $organisation->id,
        ]);

        User::factory()->create()->makeServiceAdmin($service);
        User::factory()->create()->makeServiceWorker($service);
        // Soft deleted organisation admin
        $deletedUser = User::factory()->create()->makeOrganisationAdmin($organisation);
        $deletedUser->delete();
        $this->assertTrue($deletedUser->trashed());

        // Generate the report.
        $report = Report::generate(ReportType::organisationsExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals($headings, $csv[0]);

        // Assert created organisation exported.
        $this->assertEquals([
            $organisation->id,
            $organisation->name,
            1,
            $organisation->email,
            $organisation->phone,
            $organisation->url,
            1,
        ], $csv[1]);
    }

    /*
     * Locations export.
     */

    public function test_locations_export_works(): void
    {
        // Create a single location.
        $location = Location::factory()->create();

        $headings = [
            'Address Line 1',
            'Address Line 2',
            'Address Line 3',
            'City',
            'County',
            'Postcode',
            'Country',
            'Number of Services Delivered at The Location',
        ];

        // Generate the report.
        $report = Report::generate(ReportType::locationsExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals($headings, $csv[0]);

        // Assert created location exported.
        $this->assertEquals([
            $location->address_line_1,
            $location->address_line_2 ?? '',
            $location->address_line_3 ?? '',
            $location->city,
            $location->county,
            $location->postcode,
            $location->country,
            0,
        ], $csv[1]);

        // Create a single service.
        $service = Service::factory()->create();

        ServiceLocation::factory()->create([
            'service_id' => $service->id,
            'location_id' => $location->id,
        ]);

        // Generate the report.
        $report = Report::generate(ReportType::locationsExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals($headings, $csv[0]);

        // Assert created location exported.
        $this->assertEquals([
            $location->address_line_1,
            $location->address_line_2 ?? '',
            $location->address_line_3 ?? '',
            $location->city,
            $location->county,
            $location->postcode,
            $location->country,
            1,
        ], $csv[1]);
    }

    /*
     * Referrals export.
     */

    public function test_referrals_export_works(): void
    {
        // Create a single referral.
        $referral = Referral::factory()->create(['referral_consented_at' => Date::now()]);

        // Generate the report.
        $report = Report::generate(ReportType::referralsExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'Referred to Organisation ID',
            'Referred to Organisation',
            'Referred to Service ID',
            'Referred to Service Name',
            'Date Made',
            'Date Complete',
            'Self/Champion',
            'Refer from organisation',
            'Date Consent Provided',
        ], $csv[0]);

        // Assert created referral exported.
        $this->assertEquals([
            $referral->service->organisation->id,
            $referral->service->organisation->name,
            $referral->service->id,
            $referral->service->name,
            $referral->created_at->format(CarbonImmutable::ISO8601),
            '',
            'Self',
            '',
            $referral->referral_consented_at->format(CarbonImmutable::ISO8601),
        ], $csv[1]);
    }

    public function test_referrals_export_works_when_completed(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();

        // Create a single referral.
        $referral = Referral::factory()->create(['referral_consented_at' => Date::now()]);

        // Update the referral.
        Date::setTestNow(Date::now()->addHour());
        $statusUpdate = $referral->updateStatus($user, Referral::STATUS_COMPLETED);

        // Generate the report.
        $report = Report::generate(ReportType::referralsExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'Referred to Organisation ID',
            'Referred to Organisation',
            'Referred to Service ID',
            'Referred to Service Name',
            'Date Made',
            'Date Complete',
            'Self/Champion',
            'Refer from organisation',
            'Date Consent Provided',
        ], $csv[0]);

        // Assert created referral exported.
        $this->assertEquals([
            $referral->service->organisation->id,
            $referral->service->organisation->name,
            $referral->service->id,
            $referral->service->name,
            $referral->created_at->format(CarbonImmutable::ISO8601),
            $statusUpdate->created_at->format(CarbonImmutable::ISO8601),
            'Self',
            '',
            $referral->referral_consented_at->format(CarbonImmutable::ISO8601),
        ], $csv[1]);
    }

    public function test_referrals_export_works_with_date_range(): void
    {
        // Create an in range referral.
        $referralInRange = Referral::factory()->create([
            'referral_consented_at' => Date::now(),
        ]);

        // Create an out of range referral.
        Referral::factory()->create([
            'referral_consented_at' => Date::now(),
            'created_at' => Date::today()->subMonths(2),
        ]);

        // Generate the report.
        $report = Report::generate(
            ReportType::referralsExport(),
            Date::today()->startOfMonth(),
            Date::today()->endOfMonth()
        );

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'Referred to Organisation ID',
            'Referred to Organisation',
            'Referred to Service ID',
            'Referred to Service Name',
            'Date Made',
            'Date Complete',
            'Self/Champion',
            'Refer from organisation',
            'Date Consent Provided',
        ], $csv[0]);

        // Assert created referral exported.
        $this->assertEquals([
            $referralInRange->service->organisation->id,
            $referralInRange->service->organisation->name,
            $referralInRange->service->id,
            $referralInRange->service->name,
            $referralInRange->created_at->format(CarbonImmutable::ISO8601),
            '',
            'Self',
            '',
            $referralInRange->referral_consented_at->format(CarbonImmutable::ISO8601),
        ], $csv[1]);
    }

    public function test_referrals_export_works_with_organistion_name(): void
    {
        // Create a single referral.
        $referral = Referral::factory()->create([
            'referral_consented_at' => Date::now(),
            'referee_name' => $this->faker->name(),
            'referee_email' => $this->faker->email(),
            'referee_phone' => '07700000000',
            'organisation' => $this->faker->company(),
        ]);

        // Generate the report.
        $report = Report::generate(ReportType::referralsExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'Referred to Organisation ID',
            'Referred to Organisation',
            'Referred to Service ID',
            'Referred to Service Name',
            'Date Made',
            'Date Complete',
            'Self/Champion',
            'Refer from organisation',
            'Date Consent Provided',
        ], $csv[0]);

        // Assert created referral exported.
        $this->assertEquals([
            $referral->service->organisation->id,
            $referral->service->organisation->name,
            $referral->service->id,
            $referral->service->name,
            $referral->created_at->format(CarbonImmutable::ISO8601),
            '',
            'Champion',
            $referral->organisation,
            $referral->referral_consented_at->format(CarbonImmutable::ISO8601),
        ], $csv[1]);
    }

    /*
     * Feedback export.
     */

    public function test_feedback_export_works(): void
    {
        // Create a single feedback.
        $feedback = PageFeedback::factory()->create();

        // Generate the report.
        $report = Report::generate(ReportType::feedbackExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'Date Submitted',
            'Feedback Content',
            'Page URL',
        ], $csv[0]);

        // Assert created feedback exported.
        $this->assertEquals([
            $feedback->created_at->toDateString(),
            $feedback->feedback,
            $feedback->url,
        ], $csv[1]);
    }

    public function test_feedback_export_works_with_date_range(): void
    {
        // Create a single feedback.
        $feedbackWithinRange = PageFeedback::factory()->create();
        PageFeedback::factory()->create(['created_at' => Date::today()->subMonths(2)]);

        // Generate the report.
        $report = Report::generate(
            ReportType::feedbackExport(),
            Date::today()->startOfMonth(),
            Date::today()->endOfMonth()
        );

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'Date Submitted',
            'Feedback Content',
            'Page URL',
        ], $csv[0]);

        // Assert created feedback exported.
        $this->assertEquals([
            $feedbackWithinRange->created_at->toDateString(),
            $feedbackWithinRange->feedback,
            $feedbackWithinRange->url,
        ], $csv[1]);
    }

    /*
     * Audit logs export.
     */

    public function test_audit_logs_export_works(): void
    {
        // Create a single audit log.
        $audit = Audit::factory()->create();

        // Generate the report.
        $report = Report::generate(ReportType::auditLogsExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'Action',
            'Description',
            'User',
            'Date/Time',
            'IP Address',
            'User Agent',
        ], $csv[0]);

        // Assert created audit log exported.
        $this->assertEquals([
            $audit->action,
            $audit->description,
            '',
            $audit->created_at->format(CarbonImmutable::ISO8601),
            $audit->ip_address,
            $audit->user_agent ?? '',
        ], $csv[1]);
    }

    public function test_audit_logs_export_work_with_date_range(): void
    {
        // Create a single audit log.
        $auditWithinRange = Audit::factory()->create();
        Audit::factory()->create(['created_at' => Date::today()->subMonths(2)]);

        // Generate the report.
        $report = Report::generate(
            ReportType::auditLogsExport(),
            Date::today()->startOfMonth(),
            Date::today()->endOfMonth()
        );

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'Action',
            'Description',
            'User',
            'Date/Time',
            'IP Address',
            'User Agent',
        ], $csv[0]);

        // Assert created audit log exported.
        $this->assertEquals([
            $auditWithinRange->action,
            $auditWithinRange->description,
            '',
            $auditWithinRange->created_at->format(CarbonImmutable::ISO8601),
            $auditWithinRange->ip_address,
            $auditWithinRange->user_agent ?? '',
        ], $csv[1]);
    }

    /*
     * Search histories export.
     */

    public function test_search_histories_export_works(): void
    {
        $criteria = new SearchCriteriaQuery;
        $criteria->setQuery('Health and Social');

        $queryBuilder = new ServiceQueryBuilder;
        $esQuery = $queryBuilder->build($criteria);

        // Create a single search history.
        $searchHistory = SearchHistory::create([
            'query' => $esQuery->buildSearchRequest()->toArray(),
            'count' => 1,
        ]);

        // Generate the report.
        $report = Report::generate(ReportType::searchHistoriesExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'Date made',
            'Search Text',
            'Number Services Returned',
            'Coordinates (Latitude,Longitude)',
        ], $csv[0]);

        // Assert created search history exported.
        $this->assertEquals([
            $searchHistory->created_at->toDateString(),
            'health and social',
            1,
            '',
        ], $csv[1]);
    }

    public function test_search_histories_export_works_with_location(): void
    {
        $criteria = new SearchCriteriaQuery;
        $criteria->setQuery('Health and Social');
        $criteria->setOrder(ElasticsearchQueryBuilder::ORDER_DISTANCE);
        $criteria->setLocation(new Coordinate(0, 0));

        $queryBuilder = new ServiceQueryBuilder;
        $esQuery = $queryBuilder->build($criteria);

        // Create a single search history.
        $searchHistory = SearchHistory::create([
            'query' => $esQuery->buildSearchRequest()->toArray(),
            'count' => 1,
        ]);

        // Generate the report.
        $report = Report::generate(ReportType::searchHistoriesExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'Date made',
            'Search Text',
            'Number Services Returned',
            'Coordinates (Latitude,Longitude)',
        ], $csv[0]);

        // Assert created search history exported.
        $this->assertEquals([
            $searchHistory->created_at->toDateString(),
            'health and social',
            1,
            '0,0',
        ], $csv[1]);
    }

    public function test_search_histories_export_works_with_date_range(): void
    {
        $criteria = new SearchCriteriaQuery;
        $criteria->setQuery('Health and Social');

        $queryBuilder = new ServiceQueryBuilder;
        $esQuery = $queryBuilder->build($criteria);

        // Create a single search history.
        $searchHistoryWithinRange = SearchHistory::create([
            'query' => $esQuery->buildSearchRequest()->toArray(),
            'count' => 1,
        ]);
        SearchHistory::create([
            'query' => $esQuery->buildSearchRequest()->toArray(),
            'count' => 1,
            'created_at' => Date::today()->subMonths(2),
        ]);

        // Generate the report.
        $report = Report::generate(
            ReportType::searchHistoriesExport(),
            Date::today()->startOfMonth(),
            Date::today()->endOfMonth()
        );

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'Date made',
            'Search Text',
            'Number Services Returned',
            'Coordinates (Latitude,Longitude)',
        ], $csv[0]);

        // Assert created search history exported.
        $this->assertEquals([
            $searchHistoryWithinRange->created_at->toDateString(),
            'health and social',
            1,
            '',
        ], $csv[1]);
    }

    public function test_search_histories_without_query_are_omitted(): void
    {
        $criteria = new SearchCriteriaQuery;
        $criteria->setCategories(['self-help']);

        $queryBuilder = new ServiceQueryBuilder;
        $esQuery = $queryBuilder->build($criteria);

        // Create a single search history.
        SearchHistory::create([
            'query' => $esQuery->buildSearchRequest()->toArray(),
            'count' => 1,
        ]);

        // Generate the report.
        $report = Report::generate(ReportType::searchHistoriesExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(1, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'Date made',
            'Search Text',
            'Number Services Returned',
            'Coordinates (Latitude,Longitude)',
        ], $csv[0]);
    }

    /*
     * Historic update requests export.
     */

    public function test_historic_update_requests_export_works(): void
    {
        // Create an admin user.
        /** @var \App\Models\User $user */
        $user = User::factory()->create()->makeSuperAdmin();

        // Create an organisation.
        /** @var \App\Models\Organisation $organisation */
        $organisation = Organisation::factory()->create();

        // Create a single update request.
        /** @var \App\Models\UpdateRequest $updateRequest */
        $updateRequest = $organisation->updateRequests()->create([
            'user_id' => $user->id,
            'data' => [
                'name' => 'Test Org Name',
            ],
        ]);

        // Create an actioning user.
        /** @var \App\Models\User $user */
        $actioningUser = User::factory()->create()->makeSuperAdmin();

        // Apply the update request.
        $updateRequest->apply($actioningUser);

        // Reload the update request.
        $updateRequest = UpdateRequest::query()
            ->select('*')
            ->withEntry()
            ->where('id', '=', $updateRequest->id)
            ->firstOrFail();

        // Generate the report.
        $report = Report::generate(ReportType::historicUpdateRequestsExport());

        // Test that the data is correct.
        $csv = csv_to_array($report->file->getContent());

        // Assert correct number of records exported.
        $this->assertEquals(2, count($csv));

        // Assert headings are correct.
        $this->assertEquals([
            'User Submitted',
            'Type',
            'Entry',
            'Date/Time Request Made',
            'Approved/Declined',
            'Date Actioned',
            'Admin who Actioned',
        ], $csv[0]);

        // Assert created search history exported.
        $this->assertEquals([
            $updateRequest->user->full_name,
            $updateRequest->updateable_type,
            $updateRequest->entry,
            $updateRequest->created_at->format(CarbonImmutable::ISO8601),
            $updateRequest->isApproved() ? 'Approved' : 'Declined',
            $updateRequest->isApproved()
            ? $updateRequest->approved_at->format(CarbonImmutable::ISO8601)
            : $updateRequest->deleted_at->format(CarbonImmutable::ISO8601),
            $actioningUser->full_name,
        ], $csv[1]);
    }
}
