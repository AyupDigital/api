<?php

namespace App\Docs\Operations\Settings;

use App\Docs\Schemas\ResourceSchema;
use App\Docs\Schemas\Setting\SettingSchema;
use App\Docs\Schemas\Setting\UpdateSettingSchema;
use App\Docs\Tags\SettingsTag;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Operation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\RequestBody;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Response;

class UpdateSettingOperation extends Operation
{
    /**
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     */
    public static function create(?string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->action(static::ACTION_PUT)
            ->tags(SettingsTag::create())
            ->summary('Update the settings')
            ->description(
                <<<'EOT'
**Permission:** `Super Admin`

---

Settings are all returned at once instead of being paginated.
EOT
            )
            ->requestBody(
                RequestBody::create()
                    ->required()
                    ->content(
                        MediaType::json()->schema(UpdateSettingSchema::create())
                    )
            )
            ->responses(
                Response::ok()->content(
                    MediaType::json()->schema(
                        ResourceSchema::create(null, SettingSchema::create())
                    )
                )
            );
    }
}
