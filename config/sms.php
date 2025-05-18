<?php

return [
    'smsswitch' => env('SMS_SWITCH'),
    'appid' => env('SMS_APPID'),
    'appkey' => env('SMS_APPKEY'),
    'loginTemplateId' => env('SMS_LOGIN_TEMPLATE'),
    'forgetTemplateId' => env('SMS_FORGET_TEMPLATE'),
    'agentPushTemplateId' => env('SMS_AGENT_PUSH_TEMPLATE'),
    'sign' => env('SMS_SIGN'),
    'day_count_per_phone' => env('SMS_DAY_COUNT_PER_PHONE', 5), // 每个手机号每天最多次数
];
