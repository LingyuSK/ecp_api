<?php

namespace App\Common\Models;

class AccessTpl extends BaseModel {



    protected $table = 'access_tpl';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    //JSON 隐藏
    protected $hidden = [
        'deleted_flag',
    ];
    protected $casts = [
        'supplier_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

}
