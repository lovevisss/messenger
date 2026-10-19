# 消息提醒平台

Laravel 13 + Vue 的统一消息接口。当前渠道为邮件、MAS 短信、门户站内消息及信息门户待办。每个渠道有独立驱动，后续可注册企业微信或钉钉驱动。

## 启动

1. 复制 `.env.example` 为 `.env`，生成 `APP_KEY`，配置数据库和渠道变量。人员同步默认从主库同机、同账号的 `middata.t_cx_zzqxryxx` 只读查询；如连接不同，设置 `PERSONNEL_DB_HOST`、`PERSONNEL_DB_PORT`、`PERSONNEL_DB_DATABASE`、`PERSONNEL_DB_USERNAME`、`PERSONNEL_DB_PASSWORD`。邮件默认使用 `smtp.exmail.qq.com:465` 的 SSL 连接；把实际邮箱客户端密码写入部署环境的 `MAIL_PASSWORD`，不要写入版本库。
2. 运行 `php artisan migrate`、`php artisan personnel:sync` 完成首次人员同步，再运行 `npm run build`。持续运行 Laravel 服务、`php artisan queue:work --tries=3` 和 Laravel 调度器。调度器每天北京时间 02:00 执行人员同步；可用每分钟运行一次 `php artisan schedule:run` 的系统计划任务，或持续运行 `php artisan schedule:work`。同步失败会保留上次完整快照；首次同步前发送接口返回 `503`。没有队列 worker 时，API 仍会接受请求，但任务不会发送。
3. 注册后台用户后运行 `php artisan notifications:grant-admin 用户邮箱`。管理员登录后可在 Dashboard 选择邮件、短信或信息门户入口，进入 `/notifications` 管理接入系统并保存只显示一次的 API 密钥。后台账号不要求邮箱验证。
4. 可运行 `php artisan notifications:test-email 收件邮箱` 测试 SMTP 提交。命令成功仅表示邮件服务器接受，最终到达需检查收件箱。

短信配置项为 `SMS_URL`、`SMS_EC_NAME`、`SMS_AP_ID`、`SMS_SECRET_KEY`、`SMS_SIGN`、`SMS_ADD_SERIAL`。平台按云 MAS 接口要求使用 `secretKey` 计算 MAC，并将其包含在发往 MAS 的请求体中；业务系统只需使用平台签发的接入密钥。门户配置 `PORTAL_BASE_URL`、`PORTAL_APP_ID`；站内消息还需 `PORTAL_MESSAGE_TYPE_CODE`。优先使用 `PORTAL_CLIENT_ID`、`PORTAL_CLIENT_SECRET` 和 `PORTAL_SCOPE` 自动获取、缓存 POA token，多个 scope 用英文逗号分隔；如使用手动 token，则设置 `PORTAL_POA_TOKEN`。特殊 POA 地址可设置 `PORTAL_TOKEN_URL`。管理后台只显示非敏感配置。

## 调用接口

可直接打开 `/docs/email`、`/docs/sms` 和 `/docs/portal` 查看可分享的 API 接入文档（无需登录）。

所有接口使用 `Authorization: Bearer <接入系统密钥>`，按接入系统限制为每分钟 120 次。POST 提交成功返回 `202` 和任务号；相同系统的相同 `request_id` 返回原任务与 `200`，不重复发送。接口使用 JSON，请求失败返回 Laravel 标准 JSON 错误。

新接入系统的 `request_id` 统一使用 `系统代码:业务场景:业务唯一单号`，例如 `jwc:leave:202610080001`。同一次提醒重试必须复用该编号。`recipients[].id` 填收件人学工号（字符串，保留前导零），响应中的 `recipient_id` 原样返回学工号。平台按学工号从本地每日同步的人员快照查询电子邮箱和移动电话，门户账号默认使用学工号；`email`、`mobile`、`portal_account` 可选，填写后覆盖该渠道的默认地址，但学工号仍须存在于本地人员快照。为兼容已有调用，当前 API 对请求号仍只强制校验非空、最长 120 字符及同一系统内唯一；命名格式由接入文档约定。

### 发送邮件

`POST /api/v1/notifications`

```json
{
    "request_id": "jwc:leave:202610080001",
    "channels": ["email"],
    "title": "请处理请假申请",
    "content": "您有一条新的请假申请。",
    "recipients": [
        { "id": "20230001" },
        { "id": "20230002" }
    ]
}
```

选择 `sms` 时，平台使用本地快照中的 `yddh`；邮件使用 `dzyx`。一次请求的短信正文相同，MAS 单批最多 5000 个号码，平台自动拆批。单次请求最多 10000 名收件人。邮件按小批队列处理，门户消息和待办每位收件人独立处理。学工号未收录、所选渠道缺少地址或地址格式无效时，该渠道记为 `failed`，其他可用渠道继续发送；本地快照尚未完成首次同步或无法查询时返回 `503`，不创建任务。

短信 `content` 由业务系统提供，平台原样转发；业务系统应使用云 MAS 已审批的短信签名和模板内容。

### 发送门户站内消息

`channels` 选择 `portal_message`，门户账号默认使用收件人学工号。平台固定发送方式为 `WEBSITE`，把 `content` 映射到消息类型模板的 `content` 参数。消息类型需在门户消息中台配置好相应模板。

```json
{
    "request_id": "jwc:notice:202610090001",
    "channels": ["portal_message"],
    "title": "请查看通知",
    "content": "您有一条新的业务消息。",
    "recipients": [{ "id": "20150301" }]
}
```

### 创建门户待办

```json
{
    "request_id": "jwc:approval:202610080002",
    "channels": ["portal", "email"],
    "title": "请审核申请",
    "content": "有一项申请等待审核。",
    "portal": {
        "node_id": "counselor-review",
        "node_name": "辅导员审核",
        "trans_type_code": "leave",
        "trans_type_name": "请假申请",
        "initiator": "student001",
        "handle_url": "https://business.example.edu.cn/leave/001"
    },
    "recipients": [
        {
            "id": "20230001",
            "business_task_id": "leave-001-review-1"
        }
    ]
}
```

每位门户收件人有独立的 `business_task_id`，在同一接入系统下全局唯一。后续节点由业务系统再次调用发送接口创建，新任务的收件人增加 `previous_business_task_id`，平台会映射到上一门户任务。业务系统负责决定流程和处理人。

门户真实联调前，请确认服务器能访问 `PORTAL_BASE_URL`，队列 worker 正在运行，`APP_URL` 是测试人能打开的实际地址。用 `/docs/portal` 的两个示例分别向 `20150301` 提交消息和待办；每次测试更换 `request_id`，待办还需更换 `business_task_id`。待办测试可把 `portal.handle_url` 和 `portal.view_url` 设为实际服务的 `/dashboard`。通过查询接口确认逐渠道 `accepted`，并在门户账号中检查显示结果。测试待办不会自动撤销。

### 查询与变更

- `GET /api/v1/notifications/{任务号}`：查询逐收件人、逐渠道状态。`accepted` 对邮件表示 SMTP 提交成功，对短信表示 MAS 已受理，对门户表示待办创建成功；短信不保证最终送达。
- `POST /api/v1/portal-tasks/{业务任务号}/complete`，请求体 `{"final":true,"opinion":"已处理"}`：完成待办。`final=false` 表示后续还有节点，由业务系统另行创建。
- `POST /api/v1/portal-tasks/{业务任务号}/withdraw`，请求体 `{"creator":"student001"}`：撤销并结束当前事务。

投递状态包括 `queued`、`submitted`、`accepted`、`failed`；待办还包括 `completion_queued`、`withdrawal_queued`、`completed`、`withdrawn`。可恢复的连接错误最多重试三次。邮件与短信上游没有通用幂等机制，如果服务进程在上游接受后、记录结果前异常退出，重试仍有重复投递的可能。

## 扩展渠道

新渠道实现 `App\Services\Channels\ChannelDriver`，在 `config/notifications.php` 的 `drivers` 中注册，并为 API 收件人增加相应的地址字段与校验。已有邮件、短信和门户驱动无需修改。
