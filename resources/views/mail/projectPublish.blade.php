<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>【瑞招采】【{{$orgName}}】发布【{{$name}}】的招标项目，标书已发布。</title>
    </head>
    <body>
        <p>您好，【{{$orgName}}】发布【{{$name}}】的招标项目，标书已发布，请尽快登录系统完成标书下载。<a href="{{env('BOSS_URL')}}/front/#/supplierTenders/bidDetails?id={{$projectId}}" target="_blank">快速处理</a>。</p>
        <p>瑞招采服务平台</p>
    </body>
</html>
