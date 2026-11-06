<?php

namespace App\Support;

use InvalidArgumentException;

class ApiDocumentation
{
    /** @return array<string, mixed> */
    public static function for(string $channel, string $apiUrl): array
    {
        $common = [
            'apiUrl' => $apiUrl,
            'queryUrl' => $apiUrl.'/{id}',
            'headers' => [
                'Authorization: Bearer <接入系统密钥>',
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            'errors' => [
                ['401', '接入系统密钥缺失、无效或已停用。'],
                ['404', '任务不存在，或不属于当前接入系统。'],
                ['422', '请求字段不符合要求；响应的 errors 指出字段位置。'],
                ['429', '同一接入系统超过每分钟 120 次请求。'],
                ['503', '本地人员快照尚未同步或无法查询，任务未创建。'],
            ],
            'commonNotes' => [
                '首次提交返回 HTTP 202 和任务 ID；相同接入系统使用同一个 request_id 重试会返回原任务与 HTTP 200，不再发送。',
                '新接入系统的 request_id 建议使用“系统代码:业务场景:业务唯一单号”，总长度最多 120 字符。',
                'recipients[].id 是学工号，须作为字符串传入并保留前导零；查询结果的 recipient_id 原样返回。',
                '平台按学工号查询本地每日同步的人员快照；地址覆盖字段可选，但学工号仍须存在于快照。',
            ],
        ];

        $page = match ($channel) {
            'email' => self::email(),
            'sms' => self::sms(),
            'portal' => self::portal($apiUrl),
            default => throw new InvalidArgumentException('未知文档渠道'),
        };

        if ($channel === 'portal') {
            $page['errors'] = array_merge($common['errors'], [['409', '待办已有冲突的状态操作。']]);
        }

        $page['examples'] = array_map(function (array $example) use ($apiUrl): array {
            $body = json_encode($example['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $oneLine = json_encode($example['payload'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            unset($example['payload']);

            return $example + [
                'body' => $body,
                'curl' => 'curl -X POST "'.$apiUrl.'" -H "Authorization: Bearer <接入系统密钥>" -H "Accept: application/json" -H "Content-Type: application/json" --data '."'".str_replace("'", "'\"'\"'", $oneLine)."'",
                'powershell' => "\$body = @'\n".$body."\n'@\n".'Invoke-RestMethod -Method Post -Uri "'.$apiUrl.'" -Headers @{ Authorization = "Bearer <接入系统密钥>"; Accept = "application/json" } -ContentType "application/json" -Body $body',
            ];
        }, $page['examples']);

        return $page + $common;
    }

    /** @return array<string, mixed> */
    private static function email(): array
    {
        return [
            'title' => '邮件 API 接入文档',
            'intro' => '业务系统使用独立 API 密钥提交邮件任务。平台负责排队、发送和记录每位收件人的处理结果。',
            'examples' => [[
                'title' => '发送邮件',
                'description' => '一次请求的收件人共用邮件主题和纯文本正文。',
                'payload' => [
                    'request_id' => 'jwc:leave:202610080001', 'channels' => ['email'],
                    'title' => '请处理请假申请', 'content' => '您有一条新的请假申请，请登录业务系统处理。',
                    'recipients' => [['id' => '20230001'], ['id' => '20230002']],
                ],
            ]],
            'fields' => [
                ['request_id', '必填，最多 120 字符', '同一接入系统内唯一；重试时沿用原编号。'],
                ['channels', '必填', '发送邮件使用 ["email"]。'],
                ['title', '必填，最多 255 字符', '邮件主题。'],
                ['content', '必填，最多 10000 字符', '纯文本邮件正文；不支持 HTML 和附件。'],
                ['recipients', '必填，1–10000 人', '收件人数组，共用主题和正文。'],
                ['recipients[].id', '必填，最多 120 字符', '学工号，本次请求内唯一。'],
                ['recipients[].email', '可选，有效邮箱地址', '填写后覆盖本地每日同步快照中的 dzyx；不填则按学工号查询。'],
            ],
            'response' => self::response('jwc:leave:202610080001', 'email', 2),
            'validationResponse' => json_encode([
                'message' => 'The recipients.0.id field is required.',
                'errors' => ['recipients.0.id' => ['The recipients.0.id field is required.']],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'notes' => [
                '邮件异步发送；队列 worker 未运行时任务会停留在 queued。',
                'accepted 表示 SMTP 服务器接受邮件，不能证明邮件已进入收件箱。',
                '未收录、缺少邮箱或邮箱格式无效时，该投递记为 failed，原因见 error。',
                '可恢复的连接错误最多尝试三次；服务器接受后、结果记录前中断，重试可能产生重复邮件。',
                'API 密钥只保存在业务系统服务端；密钥轮换后旧密钥立即失效。',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function sms(): array
    {
        return [
            'title' => '短信 API 接入文档',
            'intro' => '业务系统使用独立 API 密钥提交短信任务。平台异步调用云 MAS，记录每位收件人的处理结果。',
            'examples' => [[
                'title' => '发送短信',
                'description' => '业务系统提供包含已审批签名与模板内容的完整短信正文；平台原样转发。',
                'payload' => [
                    'request_id' => 'jwc:notice:202610080001', 'channels' => ['sms'],
                    'title' => '业务提醒', 'content' => '【已审批短信签名】您有一项业务待处理，请登录业务系统查看。',
                    'recipients' => [['id' => '20230001'], ['id' => '20230002']],
                ],
            ]],
            'fields' => [
                ['request_id', '必填，最多 120 字符', '同一接入系统内唯一；重试时沿用原编号。'],
                ['channels', '必填', '发送短信使用 ["sms"]。'],
                ['title', '必填，最多 255 字符', '任务标题；MAS 使用 content 作为短信正文。'],
                ['content', '必填，最多 10000 字符', '所有收件人共用正文；须包含已审批签名并符合 MAS 已审批模板。'],
                ['recipients', '必填，1–10000 人', '平台按每批最多 5000 个号码提交 MAS。'],
                ['recipients[].id', '必填，最多 120 字符', '学工号，本次请求内唯一。'],
                ['recipients[].mobile', '可选，中国大陆手机号', '填写后覆盖快照中的 yddh；不填则按学工号查询。'],
            ],
            'response' => self::response('jwc:notice:202610080001', 'sms', 2),
            'notes' => [
                '队列 worker 未运行时任务停留在 queued。',
                'accepted 仅表示 MAS 已受理，不保证短信最终送达；provider_ref 为 MAS 批次标识（如有）。',
                '未收录、缺少手机号或号码格式无效时，投递记为 failed，原因见 error。',
                '可恢复的连接错误最多尝试三次；MAS 已受理但结果记录前中断，重试可能产生重复短信。',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function portal(string $apiUrl): array
    {
        return [
            'title' => '门户 API 接入文档',
            'intro' => '业务系统可通过统一接口向信息门户发送站内消息或创建待办，平台记录逐收件人、逐渠道结果。',
            'examples' => [
                [
                    'title' => '门户站内消息',
                    'description' => 'channels 选择 portal_message，平台向 POA 发送 WEBSITE 消息。',
                    'payload' => [
                        'request_id' => 'jwc:notice:202610090001', 'channels' => ['portal_message'],
                        'title' => '请查看通知', 'content' => '您有一条新的业务消息。',
                        'recipients' => [['id' => '20230001']],
                    ],
                ],
                [
                    'title' => '门户待办',
                    'description' => 'channels 选择 portal；每位收件人需独立 business_task_id。',
                    'payload' => [
                        'request_id' => 'jwc:approval:202610090001', 'channels' => ['portal'],
                        'title' => '请处理待办', 'content' => '您有一项业务等待处理。',
                        'portal' => [
                            'node_id' => 'review', 'node_name' => '审核',
                            'trans_type_code' => 'approval', 'trans_type_name' => '业务审批',
                            'initiator' => 'student001',
                            'handle_url' => 'https://business.example.edu.cn/tasks/1',
                            'view_url' => 'https://business.example.edu.cn/tasks/1',
                        ],
                        'recipients' => [['id' => '20230001', 'business_task_id' => 'approval-1-review']],
                    ],
                ],
            ],
            'fields' => [
                ['request_id', '必填，最多 120 字符', '同一接入系统内唯一；重试时沿用原编号。'],
                ['channels', '必填', '站内消息用 ["portal_message"]，待办用 ["portal"]；也可同时选择。'],
                ['title', '必填，最多 255 字符', '消息标题或待办名称。'],
                ['content', '必填，最多 10000 字符', '消息模板变量 content；待办默认取前 200 字为摘要。'],
                ['recipients', '必填，1–10000 人', '每位收件人独立投递，id 不得重复。'],
                ['recipients[].id', '必填，最多 120 字符', '学工号，以字符串传入并保留前导零。'],
                ['recipients[].portal_account', '可选', '默认使用学工号；填写后覆盖门户账号。'],
            ],
            'extraFields' => [
                ['recipients[].business_task_id', '待办必填，最多 120 字符', '同一接入系统下全局唯一。'],
                ['recipients[].previous_business_task_id', '后续节点可填', '上一条成功创建的业务任务号。'],
                ['portal.node_id / node_name', '待办必填', '节点标识及名称。'],
                ['portal.trans_type_code / trans_type_name', '待办必填', '事务类型编码及名称。'],
                ['portal.initiator', '可选', '发起人学工号。'],
                ['portal.handle_url / view_url', '可选', 'PC 端办理与查看地址。'],
                ['portal.mobile_handle_url / mobile_view_url', '可选', '移动端办理与查看地址。'],
                ['portal.summary', '可选', '字符串数组，单项最多 255 字符，格式为“字段名|字段值”。'],
            ],
            'response' => self::response('jwc:notice:202610090001', 'portal_message'),
            'messageTemplate' => '{{content}}收到了一条消息',
            'upstream' => [
                ['站内消息', 'POST /apis/messagecenter/v1/poaMessage/messageSend'],
                ['创建待办', 'POST /apis/ttc/v1/transaction/poa/addTransaction'],
                ['办理待办', 'POST /apis/ttc/v1/transaction/poa/taskHandle'],
                ['撤销待办', 'POST /apis/ttc/v1/transaction/poa/taskWithdraw'],
            ],
            'operations' => [
                ['POST', str_replace('/notifications', '/portal-tasks/{业务任务号}/complete', $apiUrl), '{"final":true,"opinion":"已处理"}', '完成待办。final=false 表示后续还有节点。'],
                ['POST', str_replace('/notifications', '/portal-tasks/{业务任务号}/withdraw', $apiUrl), '{"creator":"student001"}', '撤销当前待办并结束事务。'],
            ],
            'notes' => [
                'accepted 表示 POA 已受理，不代表用户已读。站内消息 provider_ref 为 POA sendId。',
                '待办状态还包括 completion_queued、withdrawal_queued、completed、withdrawn。',
                '学工号未收录时，对应投递记为 failed，不调用 POA。',
                '待办状态变更返回 HTTP 202；创建后续节点时填写 previous_business_task_id。',
                'POA 连接错误及部分服务端错误最多重试三次；测试待办不会自动撤销。',
            ],
        ];
    }

    private static function response(string $requestId, string $channel, int $recipientCount = 1): string
    {
        $deliveries = [];

        for ($index = 1; $index <= $recipientCount; $index++) {
            $deliveries[] = [
                'recipient_id' => '2023000'.$index, 'channel' => $channel, 'status' => 'queued',
                'provider_ref' => null, 'business_task_id' => null, 'attempts' => 0, 'error' => null,
            ];
        }

        return json_encode([
            'id' => '7a646e3c-1c2a-4af3-9553-e8c241237530',
            'request_id' => $requestId, 'status' => 'queued',
            'created_at' => '2026-10-08T09:30:00.000000Z',
            'deliveries' => $deliveries,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
