<?php

namespace App\Http\Controllers\Core\V1\Service;

use App\BatchUpload\SpreadsheetParser;
use App\BatchUpload\StoresSpreadsheets;
use App\Http\Controllers\Controller;
use App\Http\Requests\Service\ImportRequest;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCriterion;
use App\Models\UserRole;
use App\Rules\IsOrganisationAdmin;
use App\Rules\MarkdownMaxLength;
use App\Rules\MarkdownMinLength;
use App\Rules\UserHasRole;
use App\Rules\VideoEmbed;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ImportController extends Controller
{
    use StoresSpreadsheets;

    /**
     * Number of rows to import at once.
     */
    const ROW_IMPORT_BATCH_SIZE = 100;

    /**
     * User requesting the import.
     *
     * @var \App\Models\User
     */
    protected $user;

    /**
     * OrganisationController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\Service\ImportRequest $request
     * @throws Illuminate\Validation\ValidationException
     * @return \Illuminate\Http\Response
     */
    public function __invoke(ImportRequest $request)
    {
        $this->user = $request->user('api');

        $this->processSpreadsheet($request->input('spreadsheet'));

        $responseStatus = 201;
        $response = ['imported_row_count' => $this->imported];

        if (count($this->rejected)) {
            $responseStatus = 422;
            $response = ['errors' => ['spreadsheet' => $this->rejected]];
        }

        return response()->json([
            'data' => $response,
        ], $responseStatus);
    }

    /**
     * Validate the spreadsheet rows.
     *
     * @param string $filePath
     * @return array
     */
    public function validateSpreadsheet(string $filePath)
    {
        $spreadsheetParser = new SpreadsheetParser();

        $spreadsheetParser->import(Storage::disk('local')->path($filePath));

        $spreadsheetParser->readHeaders();

        $rejectedRows = $rowIds = [];

        $globalAdminRoleId = Role::globalAdmin()->id;
        $globalAdminRole = new UserRole([
            'user_id' => $this->user->id,
            'role_id' => $globalAdminRoleId,
        ]);
        $superAdminRoleId = Role::superAdmin()->id;
        $superAdminRole = new UserRole([
            'user_id' => $this->user->id,
            'role_id' => $superAdminRoleId,
        ]);

        foreach ($spreadsheetParser->readRows() as $i => $row) {
            $rejectedRow = null;
            /**
             * Cast Boolean rows to boolean value.
             */
            $row['is_free'] = null === ($row['is_free'] ?? null) ?: (bool)$row['is_free'];
            $row['show_referral_disclaimer'] = null === ($row['show_referral_disclaimer'] ?? null) ?: (bool)$row['show_referral_disclaimer'];

            $validator = Validator::make($row, [
                'id' => ['required', 'string', 'uuid', 'unique:services,id'],
                'organisation_id' => [
                    'required',
                    'string',
                    'uuid',
                    'exists:organisations,id',
                    new IsOrganisationAdmin($this->user),
                ],
                'name' => ['required', 'string', 'min:1', 'max:255'],
                'type' => [
                    'required',
                    Rule::in([
                        Service::TYPE_SERVICE,
                        Service::TYPE_ACTIVITY,
                        Service::TYPE_CLUB,
                        Service::TYPE_GROUP,
                    ]),
                ],
                'status' => [
                    'required',
                    Rule::in([
                        Service::STATUS_ACTIVE,
                        Service::STATUS_INACTIVE,
                    ]),
                    new UserHasRole(
                        $this->user,
                        $globalAdminRole,
                        Service::STATUS_INACTIVE
                    ),
                ],
                'intro' => ['required', 'string', 'min:1', 'max:300'],
                'description' => ['required', 'string', new MarkdownMinLength(1), new MarkdownMaxLength(1600)],
                'wait_time' => ['present', 'nullable', Rule::in([
                    Service::WAIT_TIME_ONE_WEEK,
                    Service::WAIT_TIME_TWO_WEEKS,
                    Service::WAIT_TIME_THREE_WEEKS,
                    Service::WAIT_TIME_MONTH,
                    Service::WAIT_TIME_LONGER,
                ])],
                'is_free' => ['required', 'boolean'],
                'fees_text' => ['present', 'nullable', 'string', 'min:1', 'max:255'],
                'fees_url' => ['present', 'nullable', 'url', 'max:255'],
                'testimonial' => ['present', 'nullable', 'string', 'min:1', 'max:255'],
                'video_embed' => ['present', 'nullable', 'url', 'max:255', new VideoEmbed()],
                'url' => ['required', 'url', 'max:255'],
                'contact_name' => ['present', 'nullable', 'string', 'min:1', 'max:255'],
                'contact_phone' => ['present', 'nullable', 'string', 'min:1', 'max:255'],
                'contact_email' => ['present', 'nullable', 'email', 'max:255'],
                'show_referral_disclaimer' => [
                    'required',
                    'boolean',
                    new UserHasRole(
                        $this->user,
                        $superAdminRole,
                        ($row['referral_method'] === Service::REFERRAL_METHOD_NONE) ? false : true
                    ),
                ],
                'referral_method' => [
                    'required',
                    Rule::in([
                        Service::REFERRAL_METHOD_INTERNAL,
                        Service::REFERRAL_METHOD_EXTERNAL,
                        Service::REFERRAL_METHOD_NONE,
                    ]),
                    new UserHasRole(
                        $this->user,
                        $globalAdminRole,
                        Service::REFERRAL_METHOD_NONE
                    ),
                ],
                'referral_button_text' => [
                    'present',
                    'nullable',
                    'string',
                    'min:1',
                    'max:255',
                    new UserHasRole(
                        $this->user,
                        $globalAdminRole,
                        null
                    ),
                ],
                'referral_email' => [
                    'required_if:referral_method,' . Service::REFERRAL_METHOD_INTERNAL,
                    'present',
                    'nullable',
                    'email',
                    'max:255',
                    new UserHasRole(
                        $this->user,
                        $globalAdminRole,
                        null
                    ),
                ],
                'referral_url' => [
                    'required_if:referral_method,' . Service::REFERRAL_METHOD_EXTERNAL,
                    'present',
                    'nullable',
                    'url',
                    'max:255',
                    new UserHasRole(
                        $this->user,
                        $globalAdminRole,
                        null
                    ),
                ],
                'criteria_age_group' => ['present', 'nullable', 'string', 'min:1', 'max:255'],
                'criteria_disability' => ['present', 'nullable', 'string', 'min:1', 'max:255'],
                'criteria_employment' => ['present', 'nullable', 'string', 'min:1', 'max:255'],
                'criteria_gender' => ['present', 'nullable', 'string', 'min:1', 'max:255'],
                'criteria_housing' => ['present', 'nullable', 'string', 'min:1', 'max:255'],
                'criteria_income' => ['present', 'nullable', 'string', 'min:1', 'max:255'],
                'criteria_language' => ['present', 'nullable', 'string', 'min:1', 'max:255'],
                'criteria_other' => ['present', 'nullable', 'string', 'min:1', 'max:255'],
            ]);

            if ($validator->fails()) {
                $row['index'] = $i + 2;
                $rejectedRow = ['row' => $row, 'errors' => $validator->errors()];
            }

            /**
             * Check for duplicate IDs in the spreadsheet.
             */
            if (false !== array_search($row['id'], $rowIds)) {
                $error = ['id' => ['The ID is used elsewhere in the spreadsheet.']];
                if ($rejectedRow) {
                    $rejectedRow['errors']->merge($error);
                } else {
                    $rejectedRow = [
                        'row' => $row,
                        'errors' => new MessageBag($error),
                    ];
                }
            }
            if ($rejectedRow) {
                $rejectedRows[] = $rejectedRow;
            }
            $rowIds[] = $row['id'];
        }

        return $rejectedRows;
    }

    /**
     * Import the uploaded file contents.
     *
     * @param string $filePath
     */
    public function importSpreadsheet(string $filePath)
    {
        $spreadsheetParser = new SpreadsheetParser();

        $spreadsheetParser->import(Storage::disk('local')->path($filePath));

        $spreadsheetParser->readHeaders();

        $importedRows = 0;

        DB::transaction(function () use ($spreadsheetParser, &$importedRows) {
            $serviceAdminRoleId = Role::serviceAdmin()->id;
            $serviceWorkerRoleId = Role::serviceWorker()->id;
            $organisationAdminRoleId = Role::organisationAdmin()->id;

            $serviceRowBatch = $criteriaRowBatch = $userRoleBatch = [];
            $criteriaFields = [
                'age_group',
                'disability',
                'employment',
                'gender',
                'housing',
                'income',
                'language',
                'other',
            ];
            foreach ($spreadsheetParser->readRows() as $serviceRow) {
                /**
                 * Cast Boolean rows to boolean value.
                 */
                $serviceRow['is_free'] = (bool)$serviceRow['is_free'];
                $serviceRow['show_referral_disclaimer'] = (bool)$serviceRow['show_referral_disclaimer'];

                /**
                 * Create the Service Admin roles for each of the service organisation admins.
                 */
                $organisationAdminIds = DB::table((new UserRole())->getTable())->where('role_id', $organisationAdminRoleId)
                    ->where('organisation_id', $serviceRow['organisation_id'])
                    ->pluck('user_id');

                foreach ($organisationAdminIds as $organisationAdminId) {
                    $userRoleBatch[] = [
                        'id' => uuid(),
                        'user_id' => $organisationAdminId,
                        'role_id' => $serviceWorkerRoleId,
                        'service_id' => $serviceRow['id'],
                        'organisation_id' => $serviceRow['organisation_id'],
                        'created_at' => Date::now(),
                        'updated_at' => Date::now(),
                    ];
                    $userRoleBatch[] = [
                        'id' => uuid(),
                        'user_id' => $organisationAdminId,
                        'role_id' => $serviceAdminRoleId,
                        'service_id' => $serviceRow['id'],
                        'organisation_id' => $serviceRow['organisation_id'],
                        'created_at' => Date::now(),
                        'updated_at' => Date::now(),
                    ];
                }

                /**
                 * Check for Criteria fields.
                 * Build a row of passed Criteria fields.
                 * Remove Criteria fields from the Service row.
                 */
                $criteriaRow = [
                    'id' => (string)Str::uuid(),
                    'service_id' => $serviceRow['id'],
                    'created_at' => Date::now(),
                    'updated_at' => Date::now(),
                ];
                foreach ($criteriaFields as $criteriaField) {
                    if (array_key_exists('criteria_' . $criteriaField, $serviceRow)) {
                        $criteriaRow[$criteriaField] = $serviceRow['criteria_' . $criteriaField];
                        unset($serviceRow['criteria_' . $criteriaField]);
                    }
                }

                /**
                 * Add the Criteria row to a batch array.
                 */
                $criteriaRowBatch[] = $criteriaRow;

                /**
                 * Add the meta fields to the Service row.
                 */
                $serviceRow['slug'] = Str::slug(uniqid($serviceRow['name'] . '-'));
                $serviceRow['created_at'] = Date::now();
                $serviceRow['updated_at'] = Date::now();
                $serviceRowBatch[] = $serviceRow;

                /**
                 * If the batch array has reach the import batch size create the insert queries.
                 */
                if (count($serviceRowBatch) === self::ROW_IMPORT_BATCH_SIZE) {
                    DB::table((new Service())->getTable())->insert($serviceRowBatch);
                    DB::table((new ServiceCriterion())->getTable())->insert($criteriaRowBatch);
                    DB::table((new UserRole())->getTable())->insert($userRoleBatch);
                    $importedRows += self::ROW_IMPORT_BATCH_SIZE;
                    $serviceRowBatch = $criteriaRowBatch = $userRoleBatch = [];
                }
            }

            /**
             * If there are a final batch that did not meet the import batch size, create queries for these.
             */
            if (count($serviceRowBatch) && count($serviceRowBatch) !== self::ROW_IMPORT_BATCH_SIZE) {
                DB::table((new Service())->getTable())->insert($serviceRowBatch);
                DB::table((new ServiceCriterion())->getTable())->insert($criteriaRowBatch);
                DB::table((new UserRole())->getTable())->insert($userRoleBatch);
                $importedRows += count($serviceRowBatch);
            }
        }, 5);

        return $importedRows;
    }
}