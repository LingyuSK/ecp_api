<?php

namespace App\Common\Models;

class SupplierAccess extends BaseModel {

    protected $table = 'supplier_access';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    protected $casts = [
        'purchaser_id' => 'string',
        'supplier_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

}
