<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { Check, Clipboard } from "@lucide/vue";
import { computed, ref } from "vue";
import { copyText } from "@/lib/copyText";

const props = defineProps<{ apiUrl: string }>();
const copied = ref<string | null>(null);
const messageTemplate = "{{content}}收到了一条消息";

const messageBody = JSON.stringify(
    {
        request_id: "jwc:notice:202610090001",
        channels: ["portal_message"],
        title: "请查看通知",
        content: "您有一条新的业务消息。",
        recipients: [{ id: "20230001" }],
    },
    null,
    2,
);
const taskBody = JSON.stringify(
    {
        request_id: "jwc:approval:202610090001",
        channels: ["portal"],
        title: "请处理待办",
        content: "您有一项业务等待处理。",
        portal: {
            node_id: "review",
            node_name: "审核",
            trans_type_code: "approval",
            trans_type_name: "业务审批",
            initiator: "student001",
            handle_url: "https://business.example.edu.cn/tasks/1",
            view_url: "https://business.example.edu.cn/tasks/1",
        },
        recipients: [
            { id: "20230001", business_task_id: "approval-1-review" },
        ],
    },
    null,
    2,
);
const responseBody = JSON.stringify(
    {
        id: "7a646e3c-1c2a-4af3-9553-e8c241237530",
        request_id: "jwc:notice:202610090001",
        status: "queued",
        created_at: "2026-10-09T09:30:00.000000Z",
        deliveries: [
            {
                recipient_id: "20230001",
                channel: "portal_message",
                status: "queued",
                provider_ref: null,
                business_task_id: null,
                attempts: 0,
                error: null,
            },
        ],
    },
    null,
    2,
);

const queryUrl = computed(() => `${props.apiUrl}/{任务号}`);
const taskUrl = computed(() =>
    props.apiUrl.replace(/\/notifications$/, "/portal-tasks/{业务任务号}"),
);
const examples = computed(() =>
    [
        {
            key: "message",
            title: "门户站内消息",
            description:
                "channels 选择 portal_message。平台向 POA 发送 WEBSITE 消息，将 content 填入模板变量；每位收件人独立投递。",
            body: messageBody,
        },
        {
            key: "task",
            title: "门户待办",
            description:
                "channels 选择 portal。每位收件人须有唯一的 business_task_id；业务系统负责提供办理链接、节点和处理人。",
            body: taskBody,
        },
    ].map((example) => ({
        ...example,
        curl: `curl -X POST "${props.apiUrl}" -H "Authorization: Bearer <接入系统密钥>" -H "Accept: application/json" -H "Content-Type: application/json" --data '${example.body.replace(/\n\s*/g, " ")}'`,
        powershell: `$body = @'\n${example.body}\n'@\nInvoke-RestMethod -Method Post -Uri "${props.apiUrl}" -Headers @{ Authorization = "Bearer <接入系统密钥>"; Accept = "application/json" } -ContentType "application/json" -Body $body`,
    })),
);

const fields = [
    [
        "request_id",
        "必填，最多 120 字符",
        "同一接入系统内唯一；同一次提醒重试时复用。建议采用 系统:场景:业务单号。",
    ],
    ["channels", "必填", '站内消息用 ["portal_message"]，待办用 ["portal"]，也可同时选择。'],
    ["title", "必填，最多 255 字符", "门户消息标题或待办名称。"],
    [
        "content",
        "必填，最多 10000 字符",
        "站内消息填入模板变量 content；待办默认用正文前 200 字生成摘要。",
    ],
    ["recipients", "必填，1–10000 人", "每位收件人独立投递；一次请求中的 id 不得重复。"],
    ["recipients[].id", "必填，最多 120 字符", "收件人学工号，以字符串传入并保留前导零。"],
    ["recipients[].portal_account", "可选", "默认使用学工号；填写后覆盖门户账号。学工号仍须存在于本地每日同步快照。"],
];
const taskFields = [
    [
        "recipients[].business_task_id",
        "必填，最多 120 字符",
        "同一接入系统下全局唯一；后续完成或撤销时用它定位待办。",
    ],
    [
        "recipients[].previous_business_task_id",
        "后续节点可填",
        "上一条已成功创建的业务任务号；平台映射为 POA previousTaskId。",
    ],
    ["portal.node_id / node_name", "必填", "节点标识及显示名称。"],
    ["portal.trans_type_code / trans_type_name", "必填", "事务类型编码及名称。"],
    ["portal.initiator", "可选", "发起人学工号。"],
    ["portal.handle_url / view_url", "可选", "PC 端办理及查看地址；正式业务应提供可访问地址。"],
    ["portal.mobile_handle_url / mobile_view_url", "可选", "移动端办理及查看地址。"],
    ["portal.summary", "可选", "字符串数组，单项最多 255 字符，格式为“字段名|字段值”。"],
];

async function copy(value: string, key: string) {
    copied.value = (await copyText(value)) ? key : null;
    window.setTimeout(() => {
        copied.value = null;
    }, 3000);
}
</script>

<template>
    <Head title="门户 API 接入文档" />
    <main class="min-h-screen bg-[#f6f7f3] px-5 py-8 text-[#192b29] md:px-8">
        <div class="mx-auto max-w-6xl space-y-8">
            <nav class="flex flex-wrap gap-5 border-b border-[#dbe3dc] pb-5 text-sm text-[#386b55]">
                <Link href="/dashboard" class="hover:underline">返回平台</Link
                ><Link href="/docs/email" class="hover:underline">邮件文档</Link
                ><Link href="/docs/sms" class="hover:underline">短信文档</Link>
            </nav>
            <header class="max-w-4xl">
                <p class="text-xs font-semibold tracking-[0.16em] text-[#4d876a]">
                    INTEGRATION DOCS · API V1
                </p>
                <h1 class="mt-3 text-3xl font-semibold md:text-4xl">门户 API 接入文档</h1>
                <p class="mt-4 text-sm leading-7 text-[#5c7166]">
                    统一接口支持门户站内消息和待办。业务系统使用本平台签发的接入密钥；POA
                    凭据由平台服务器管理，调用方无需获取 POA token。
                </p>
            </header>

            <section class="grid gap-4 md:grid-cols-3" aria-label="接口概览">
                <div class="rounded-xl border border-[#dbe3dc] bg-white p-5">
                    <p class="text-xs text-[#718277]">创建消息或待办</p>
                    <code class="mt-2 block break-all text-sm font-semibold"
                        >POST {{ apiUrl }}</code
                    >
                </div>
                <div class="rounded-xl border border-[#dbe3dc] bg-white p-5">
                    <p class="text-xs text-[#718277]">查询投递状态</p>
                    <code class="mt-2 block break-all text-sm font-semibold"
                        >GET {{ queryUrl }}</code
                    >
                </div>
                <div class="rounded-xl border border-[#dbe3dc] bg-white p-5">
                    <p class="text-xs text-[#718277]">完成或撤销待办</p>
                    <code class="mt-2 block break-all text-sm font-semibold"
                        >POST {{ taskUrl }}/complete|withdraw</code
                    >
                </div>
            </section>

            <section class="rounded-2xl border border-[#dbe3dc] bg-white p-6 md:p-8">
                <h2 class="text-xl font-semibold">门户上游接口映射</h2>
                <p class="mt-2 text-sm leading-7 text-[#4b6255]">
                    以下由消息平台服务器调用，业务系统只需使用上方统一 API。
                </p>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[650px] text-left text-sm">
                        <thead class="border-b border-[#dbe3dc] text-[#678071]">
                            <tr>
                                <th class="py-3 pr-4">业务动作</th>
                                <th class="py-3">POA 路径</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#edf1ec]">
                            <tr>
                                <td class="py-3 pr-4">站内消息</td>
                                <td class="py-3 font-mono text-xs">
                                    POST /apis/messagecenter/v1/poaMessage/messageSend
                                </td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4">创建待办</td>
                                <td class="py-3 font-mono text-xs">
                                    POST /apis/ttc/v1/transaction/poa/addTransaction
                                </td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4">办理待办</td>
                                <td class="py-3 font-mono text-xs">
                                    POST /apis/ttc/v1/transaction/poa/taskHandle
                                </td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4">撤销待办</td>
                                <td class="py-3 font-mono text-xs">
                                    POST /apis/ttc/v1/transaction/poa/taskWithdraw
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-sm leading-7 text-[#4b6255]">
                    站内消息固定发送方式为 <code>WEBSITE</code>，模板变量为
                    <code>content</code>；平台保存 POA 返回的
                    <code>sendId</code>。待办使用业务任务号映射 POA 任务号，支持后续节点关联。
                </p>
            </section>

            <section class="rounded-2xl border border-[#dbe3dc] bg-white p-6 md:p-8">
                <h2 class="text-xl font-semibold">接入准备</h2>
                <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm leading-7 text-[#4b6255]">
                    <li>
                        向平台管理员取得接入系统密钥，统一使用
                        <code>Authorization: Bearer &lt;接入系统密钥&gt;</code>，并发送 JSON、声明
                        <code>Accept: application/json</code>。
                    </li>
                    <li>
                        选择 <code>portal_message</code> 发送站内消息，选择
                        <code>portal</code> 创建待办；同一请求可同时选择两者。
                    </li>
                    <li>
                        保存提交响应中的任务号，并用查询接口查看逐收件人结果。队列 worker
                        必须运行，任务才会实际发出。
                    </li>
                </ol>
            </section>

            <section
                v-for="example in examples"
                :key="example.key"
                class="rounded-2xl border border-[#dbe3dc] bg-white p-6 md:p-8"
            >
                <h2 class="text-xl font-semibold">{{ example.title }}</h2>
                <p class="mt-2 text-sm leading-7 text-[#4b6255]">{{ example.description }}</p>
                <p v-if="example.key === 'message'" class="mt-2 text-sm text-[#4b6255]">
                    当前消息类型模板使用 <code>{{ messageTemplate }}</code
                    >，因此 <code>content</code> 为模板中的可变部分。
                </p>
                <div class="mt-5 flex items-center justify-between">
                    <h3 class="font-semibold">请求 JSON</h3>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 text-sm text-[#287257]"
                        @click="copy(example.body, `${example.key}-json`)"
                    >
                        <Check v-if="copied === `${example.key}-json`" class="size-4" /><Clipboard
                            v-else
                            class="size-4"
                        />复制
                    </button>
                </div>
                <pre
                    class="mt-2 overflow-x-auto rounded-xl bg-[#173329] p-4 text-sm leading-6 text-white"
                ><code>{{ example.body }}</code></pre>
                <div class="mt-5 grid gap-5 lg:grid-cols-2">
                    <div>
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold">Bash / curl</h3>
                            <button
                                type="button"
                                class="text-sm text-[#287257]"
                                @click="copy(example.curl, `${example.key}-curl`)"
                            >
                                复制
                            </button>
                        </div>
                        <pre
                            class="mt-2 overflow-x-auto rounded-xl bg-[#eef3ef] p-4 text-xs leading-6"
                        ><code>{{ example.curl }}</code></pre>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold">PowerShell</h3>
                            <button
                                type="button"
                                class="text-sm text-[#287257]"
                                @click="copy(example.powershell, `${example.key}-ps`)"
                            >
                                复制
                            </button>
                        </div>
                        <pre
                            class="mt-2 overflow-x-auto rounded-xl bg-[#eef3ef] p-4 text-xs leading-6"
                        ><code>{{ example.powershell }}</code></pre>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-[#dbe3dc] bg-white p-6 md:p-8">
                <h2 class="text-xl font-semibold">请求字段</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[680px] text-left text-sm">
                        <thead class="border-b border-[#dbe3dc] text-[#678071]">
                            <tr>
                                <th class="py-3 pr-4">字段</th>
                                <th class="py-3 pr-4">要求</th>
                                <th class="py-3">说明</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="field in fields"
                                :key="field[0]"
                                class="border-b border-[#edf1ec]"
                            >
                                <td class="py-3 pr-4 font-mono text-xs">{{ field[0] }}</td>
                                <td class="py-3 pr-4">{{ field[1] }}</td>
                                <td class="py-3 text-[#52675a]">{{ field[2] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <h3 class="mt-7 font-semibold">待办附加字段</h3>
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full min-w-[680px] text-left text-sm">
                        <tbody>
                            <tr
                                v-for="field in taskFields"
                                :key="field[0]"
                                class="border-b border-[#edf1ec]"
                            >
                                <td class="py-3 pr-4 font-mono text-xs">{{ field[0] }}</td>
                                <td class="py-3 pr-4">{{ field[1] }}</td>
                                <td class="py-3 text-[#52675a]">{{ field[2] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-[#dbe3dc] bg-white p-6 md:p-8">
                <h2 class="text-xl font-semibold">返回与查询</h2>
                <p class="mt-3 text-sm leading-7 text-[#4b6255]">
                    首次提交返回 HTTP <code>202</code>，表示已入队；同一接入系统复用
                    <code>request_id</code> 返回原任务和 HTTP <code>200</code>，不会再次发送。
                </p>
                <pre
                    class="mt-3 overflow-x-auto rounded-xl bg-[#173329] p-4 text-sm leading-6 text-white"
                ><code>{{ responseBody }}</code></pre>
                <p class="mt-4 text-sm leading-7 text-[#4b6255]">
                    使用 <code>GET {{ queryUrl }}</code> 查询。<code>deliveries[].status</code> 包括
                    <code>queued</code
                    >、<code>submitted</code>、<code>accepted</code>、<code>failed</code>；待办还有
                    <code>completion_queued</code
                    >、<code>withdrawal_queued</code>、<code>completed</code>、<code>withdrawn</code>。<code
                        >accepted</code
                    >
                    表示 POA 已受理，不代表用户已读。站内消息的 <code>provider_ref</code> 是 POA
                    <code>sendId</code>，失败原因在 <code>error</code>；请求整体可为
                    <code>processing</code>、<code>accepted</code>、<code>partial</code> 或
                    <code>failed</code>。
                    学工号未收录时，对应门户投递直接记为 <code>failed</code>，不会调用 POA。
                </p>
            </section>

            <section class="rounded-2xl border border-[#dbe3dc] bg-white p-6 md:p-8">
                <h2 class="text-xl font-semibold">完成、撤销与后续节点</h2>
                <p class="mt-3 text-sm leading-7 text-[#4b6255]">
                    仅待办支持状态变更。路径中的业务任务号是创建时的
                    <code>business_task_id</code>，需使用同一接入系统密钥。
                </p>
                <ul class="mt-3 space-y-3 text-sm leading-7 text-[#4b6255]">
                    <li>
                        <code>POST {{ taskUrl }}/complete</code>：请求体
                        <code>{"final":true,"opinion":"已处理"}</code>。<code>final=true</code>
                        结束事务；<code>final=false</code>
                        记录办理情况，后续待办由业务系统另行创建。
                    </li>
                    <li>
                        <code>POST {{ taskUrl }}/withdraw</code>：请求体
                        <code>{"creator":"student001"}</code>，撤销当前待办并结束事务。
                    </li>
                    <li>
                        创建后续节点时，在新收件人中填写上一条成功创建的
                        <code>previous_business_task_id</code>。
                    </li>
                </ul>
                <p class="mt-3 text-sm text-[#4b6255]">
                    状态变更返回 HTTP 202 和当前状态；可继续查询最终结果。
                </p>
            </section>

            <section class="rounded-2xl border border-[#dbe3dc] bg-white p-6 md:p-8">
                <h2 class="text-xl font-semibold">错误处理与联调</h2>
                <div class="mt-3 grid gap-4 text-sm leading-7 text-[#4b6255] md:grid-cols-2">
                    <p>
                        <code>401</code>：接入系统密钥无效。<br /><code>404</code
                        >：任务不存在或不属于该系统。<br /><code>409</code
                        >：待办已有冲突的状态操作。
                    </p>
                    <p>
                        <code>422</code>：字段缺失、格式不符或业务任务号重复，响应
                        <code>errors</code> 指出字段位置。<br /><code>429</code>：超过每分钟 120
                        次的限流。<br /><code>503</code>：本地人员快照尚未同步或无法查询，任务未创建。
                    </p>
                </div>
                <p class="mt-4 text-sm leading-7 text-[#4b6255]">
                    POA
                    连接错误及部分服务端错误由队列重试，最多尝试三次；最终结果以查询接口为准。联调前确认服务器可访问
                    POA、队列 worker 正在运行、办理链接能从门户打开。测试账号可用
                    <code>20150301</code>；每次测试更换 <code>request_id</code> 和待办
                    <code>business_task_id</code>。测试待办不会自动撤销。
                </p>
            </section>
        </div>
    </main>
</template>
