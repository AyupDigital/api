<?php

namespace App\Docs\Operations\Reports;

use App\Docs\Tags\ReportsTag;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Operation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Response;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class DownloadReportOperation extends Operation
{
    /**
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     */
    public static function create(?string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->action(static::ACTION_GET)
            ->tags(ReportsTag::create())
            ->summary('Download a specific report')
            ->description('**Permission:** `Supermin`')
            ->responses(
                Response::ok()->content(
                    MediaType::pdf()->schema(
                        Schema::string()->format(Schema::FORMAT_BINARY)
                    )
                )
            );
    }
}
