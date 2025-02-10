<?php

namespace Modules\Kiosk\App\Models;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    protected $keyIsUuid = true;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(static function ($model) {
            if ($model->keyIsUuid && empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = uuid();
            }
        });
    }
}