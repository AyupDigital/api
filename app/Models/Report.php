<?php

namespace App\Models;

use App\Models\Mutators\ReportMutators;
use App\Models\Relationships\ReportRelationships;
use App\Models\Scopes\ReportScopes;
use Exception;

class Report extends Model
{
    use ReportMutators;
    use ReportRelationships;
    use ReportScopes;

    /**
     * Created a report record and a file record.
     * Then delegates the physical file creation to a `generateReportName` method.
     *
     * @param \App\Models\ReportType $type
     * @return \App\Models\Report
     * @throws \Exception
     */
    public static function generate(ReportType $type): self
    {
        // Create the file record.
        $file = File::create([
            'filename' => 'temp.csv',
            'mime_type' => 'text/csv',
            'is_private' => true,
        ]);

        // Create the report record.
        $report = static::create([
            'report_type_id' => $type->id,
            'file_id' => $file->id,
        ]);

        // Get the name for the report generation method.
        $methodName = 'generate' . ucfirst(camel_case($type->name));

        // Throw exception if the report type does not have a generate method.
        if (!method_exists($report, $methodName)) {
            throw new Exception("The report type [{$type->name}] does not have a corresponding generate method");
        }

        return $report->$methodName();
    }

    /**
     * @return \App\Models\Report
     */
    public function generateCommissionersReport(): self
    {
        // TODO: Add report generation logic here.
        $this->file->upload('This is a dummy report');

        return $this;
    }
}