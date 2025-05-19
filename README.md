星易采
项目介绍
星易采，是全新推出的一款轻量级、高性能、前后端分离的采购管理系统，后端源码完全开源，包含供应商管理、采购询价、采购竞价、采购招标等功能。

技术特点
前后端完全分离 (互不依赖 开发效率高)
采用PHP8.2+
lumen（轻量级PHP开发框架）
Composer一键引入三方扩展
简约高效的编码风格
安装教程
教程地址：https://doc.ruizhaocai.com/#/deploy/

环境要求
CentOS 7.0+ 、Ubuntu 20+
Nginx 1.10+
PHP 8.2
MySQL 5.7+
页面演示
输入图片说明 输入图片说明

系统演示
采购商管理后台地址 https://demo1.lingyusk.com/front

供应商后台管理地址 https://demo1.lingyusk.com/front

采购商前台网站地址 https://demo1.lingyusk.com/front

采购商账号：admin 密码：ecp@2024

供应商账号：gys@erui.com 密码：ecp@2024

定时任务
用于自动处理招标采购系统自动开标以及报名截至等时间相关状态的更新

php artisan schedule:run

消息队列
用于邮件等的处理

php artisan queue:listen

消息通信
用于竞价信息同步

php artisan workman start|stop|restart

安全&缺陷
如果您碰到安装和使用问题可以加群联系管理员，将操作流程和截图详细发出来，我们看到后会给出解决方案。

如果有BUG或者安全问题，我们会第一时间修复。
