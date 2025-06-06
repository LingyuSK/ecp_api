<?php

namespace App\Common\Models\Quote;

use App\Common\Models\BaseModel;

class QuoteAttach extends BaseModel {

    protected $table = 'quote_attach';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    protected $casts = [
        'quote_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string'
    ];

}
