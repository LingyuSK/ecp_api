<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>【瑞招采】您提交企业“{{$user['supplier_name']}}”认证信息冻结申请已审核通过</title>
  </head>
  <body>
    <p>尊敬的 <strong>{{$user['email']}}</strong>，您好，</p>
    <p>恭喜您，您提交企业“{{$user['supplier_name']}}”认证信息冻结申请已审核通过，请尽快登录系统查看。<a href="{{env('BOSS_URL')}}/front/#/supplierManage/supplierDetail?id={{$supplier_id}}&dataType=info" target="_blank">快速处理</a></p>
    <p></p>
    <p>瑞招采服务平台</p>
  </body>
</html>
