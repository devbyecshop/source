<?php

namespace App\Models\v2;

use App\Models\BaseModel;

class PayLog extends BaseModel
{
    protected $connection = 'shop';

    protected $table      = 'pay_log';
    
    public $timestamps = false;
}
