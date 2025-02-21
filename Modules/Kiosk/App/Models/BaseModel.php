<?php

namespace Modules\Kiosk\App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
  use HasUuids;
  protected $keyIsUuid = true;
}

