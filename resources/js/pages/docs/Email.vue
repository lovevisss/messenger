<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Clipboard,
    Mail,
    ShieldCheck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { copyText } from '@/lib/copyText';

const props = defineProps<{ apiUrl: string }>();
const copied = ref<string | null>(null);
const copyFeedback = ref('');

const requestBody = `{
  "request_id": "jwc:leave:202610080001",
  "channels": ["email"],
  "title": "请处理请假申请",
  "content": "您有一条新的请假申请，请登录业务系统处理。",
  "recipients": [
    { "id": "20230001" },
    { "id": "20230002" }
  ]
}`;

const curlCommand = computed(
    () =>
        `curl -X POST "${props.apiUrl}" -H "Authorization: Bearer <接入系统密钥>" -H "Accept: application/json" -H "Content-Type: application/json" --data '${requestBody.replace(/\n\s*/g, ' ')}'`,
);

const powershellCommand = computed(
    () =>
        `$body = @'\n${requestBody}\n'@\nInvoke-RestMethod -Method Post -Uri "${props.apiUrl}" -Headers @{ Authorization = "Bearer <接入系统密钥>"; Accept = "application/json" } -ContentType "application/json" -Body $body`,
);

const acceptedResponse = `{
  "id": "7a646e3c-1c2a-4af3-9553-e8c241237530",
  "request_id": "jwc:leave:202610080001",
  "status": "queued",
  "created_at": "2026-10-08T09:30:00.000000Z",
  "deliveries": [
    {
      "recipient_id": "20230001",
      "channel": "email",
      "status": "queued",
      "provider_ref": null,
      "business_task_id": null,
      "attempts": 0,
      "error": null
    },
    {
      "recipient_id": "20230002",
      "channel": "email",
      "status": "queued",
      "provider_ref": null,
      "business_task_id": null,
      "attempts": 0,
      "error": null
    }
  ]
}`;

const validationResponse = `{
  "message": "The recipients.0.id field is required.",
  "errors": {
    "recipients.0.id": ["The recipients.0.id field is required."]
  }
}`;

const sections = [
    { id: 'quick-start', label: '接入步骤' },
    { id: 'send', label: '发送邮件' },
    { id: 'fields', label: '请求字段' },
    { id: 'response', label: '返回与查询' },
    { id: 'errors', label: '错误处理' },
    { id: 'notes', label: '投递说明' },
];

async function copy(value: string, key: string) {
    const success = await copyText(value);
    copied.value = success ? key : null;
    copyFeedback.value = success
        ? '已复制到剪贴板'
        : '复制失败，请选中内容后按 Ctrl+C 手动复制';
    window.setTimeout(() => {
        copied.value = null;
        copyFeedback.value = '';
    }, 3000);
}
</script>

<template>
    <Head title="邮件 API 接入文档" />
    <div class="min-h-screen bg-[#f6f7f3] font-sans text-[#192b29]">
        <p
            v-if="copyFeedback"
            role="status"
            class="fixed right-5 bottom-5 z-50 rounded-lg bg-[#194f3e] px-4 py-3 text-sm font-medium text-white shadow-lg"
        >
            {{ copyFeedback }}
        </p>
        <header class="border-b border-[#dbe3dc] bg-[#f6f7f3]/95">
            <div
                class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 md:px-8"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-9 items-center justify-center rounded-lg bg-[#194f3e] text-white"
                    >
                        <Mail class="size-5" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold tracking-tight">
                            消息提醒平台
                        </p>
                        <p class="text-[11px] tracking-[0.16em] text-[#73867b]">
                            INTEGRATION DOCS
                        </p>
                    </div>
                </div>
                <Link
                    href="/dashboard"
                    class="inline-flex items-center gap-2 text-sm font-medium text-[#386b55] hover:underline"
                    ><ArrowLeft class="size-4" />返回平台</Link
                >
            </div>
        </header>

        <div
            class="mx-auto grid max-w-7xl gap-10 px-5 py-10 md:px-8 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-16"
        >
            <aside class="lg:sticky lg:top-8 lg:self-start">
                <p
                    class="mb-4 text-[11px] font-bold tracking-[0.18em] text-[#819086]"
                >
                    渠道文档
                </p>
                <div
                    class="rounded-xl border border-[#d8e2da] bg-white p-1.5 shadow-sm"
                >
                    <a
                        href="#top"
                        class="flex items-center justify-between rounded-lg bg-[#e7f1e9] px-3 py-2.5 text-sm font-semibold text-[#205d43]"
                        ><span>01　邮件</span><ArrowRight class="size-4"
                    /></a>
                    <Link
                        href="/docs/sms"
                        class="block rounded-lg px-3 py-2.5 text-sm text-[#6e7f73] hover:bg-[#edf4ed] hover:text-[#205d43]"
                        >02　短信</Link
                    >
                    <Link
                        href="/docs/portal"
                        class="block rounded-lg px-3 py-2.5 text-sm text-[#6e7f73] hover:bg-[#edf4ed] hover:text-[#205d43]"
                        >03　信息门户</Link
                    >
                </div>
                <nav
                    class="mt-7 hidden space-y-1 border-l border-[#d8e2da] pl-3 lg:block"
                    aria-label="本页目录"
                >
                    <a
                        v-for="section in sections"
                        :key="section.id"
                        :href="`#${section.id}`"
                        class="block py-1.5 text-sm text-[#6e7f73] transition hover:text-[#205d43]"
                        >{{ section.label }}</a
                    >
                </nav>
            </aside>

            <main id="top" class="min-w-0 pb-24">
                <div class="max-w-3xl">
                    <div
                        class="mb-5 inline-flex items-center gap-2 rounded-full border border-[#c9dfcf] bg-[#eaf4ec] px-3 py-1 text-xs font-semibold text-[#206145]"
                    >
                        <span class="size-1.5 rounded-full bg-[#31875d]"></span>
                        API v1 · 邮件渠道
                    </div>
                    <h1
                        class="text-4xl font-semibold tracking-tight md:text-5xl"
                    >
                        邮件 API 接入文档
                    </h1>
                    <p
                        class="mt-5 max-w-2xl text-base leading-8 text-[#66766b]"
                    >
                        业务系统使用独立 API
                        密钥提交邮件任务。本平台负责排队、发送和记录每个收件人的处理结果。
                    </p>
                    <div
                        class="mt-8 flex flex-wrap gap-2 text-xs font-medium text-[#49715c]"
                    >
                        <span class="rounded-md bg-[#e8eee8] px-2.5 py-1.5"
                            >HTTP + JSON</span
                        ><span class="rounded-md bg-[#e8eee8] px-2.5 py-1.5"
                            >Bearer 鉴权</span
                        ><span class="rounded-md bg-[#e8eee8] px-2.5 py-1.5"
                            >异步投递</span
                        ><span class="rounded-md bg-[#e8eee8] px-2.5 py-1.5"
                            >多人发送</span
                        >
                    </div>
                </div>

                <section
                    id="quick-start"
                    class="mt-16 scroll-mt-8 border-t border-[#dbe3dc] pt-9"
                >
                    <p
                        class="text-xs font-bold tracking-[0.18em] text-[#528166]"
                    >
                        01 / PREPARE
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold">接入步骤</h2>
                    <ol class="mt-6 grid gap-3 md:grid-cols-3">
                        <li
                            class="rounded-xl border border-[#dbe3dc] bg-white p-5"
                        >
                            <span class="text-xs font-semibold text-[#478569]"
                                >STEP 01</span
                            >
                            <h3 class="mt-3 font-semibold">领取密钥</h3>
                            <p class="mt-2 text-sm leading-6 text-[#68796d]">
                                管理员在“消息平台 →
                                接入系统”创建业务系统，将只显示一次的 API
                                密钥安全交给对接人。
                            </p>
                        </li>
                        <li
                            class="rounded-xl border border-[#dbe3dc] bg-white p-5"
                        >
                            <span class="text-xs font-semibold text-[#478569]"
                                >STEP 02</span
                            >
                            <h3 class="mt-3 font-semibold">提交任务</h3>
                            <p class="mt-2 text-sm leading-6 text-[#68796d]">
                                在请求头使用 Bearer 密钥，以 JSON
                                提交主题、正文和收件人列表。
                            </p>
                        </li>
                        <li
                            class="rounded-xl border border-[#dbe3dc] bg-white p-5"
                        >
                            <span class="text-xs font-semibold text-[#478569]"
                                >STEP 03</span
                            >
                            <h3 class="mt-3 font-semibold">查询结果</h3>
                            <p class="mt-2 text-sm leading-6 text-[#68796d]">
                                保存返回的任务
                                ID，再调用查询接口查看每位收件人的投递状态。
                            </p>
                        </li>
                    </ol>
                    <div
                        class="mt-5 flex items-start gap-3 rounded-lg border border-[#d2e4d7] bg-[#ebf5ed] p-4 text-sm leading-6 text-[#345c45]"
                    >
                        <ShieldCheck class="mt-0.5 size-5 shrink-0" /><span
                            >对接方只使用平台签发的 API 密钥。SMTP
                            发件账号和密码由平台服务器管理，无需提供给业务系统。</span
                        >
                    </div>
                </section>

                <section
                    id="send"
                    class="mt-16 scroll-mt-8 border-t border-[#dbe3dc] pt-9"
                >
                    <p
                        class="text-xs font-bold tracking-[0.18em] text-[#528166]"
                    >
                        02 / SEND
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold">发送邮件</h2>
                    <div
                        class="mt-6 flex flex-wrap items-center gap-3 rounded-lg border border-[#dbe3dc] bg-white px-4 py-3 font-mono text-sm"
                    >
                        <span
                            class="rounded bg-[#dff2e5] px-2 py-1 font-bold text-[#216347]"
                            >POST</span
                        ><code class="min-w-0 break-all">{{ apiUrl }}</code
                        ><button
                            class="ml-auto text-[#527d61] hover:text-[#194f3e]"
                            aria-label="复制接口地址"
                            @click="copy(apiUrl, 'url')"
                        >
                            <Check
                                v-if="copied === 'url'"
                                class="size-4"
                            /><Clipboard v-else class="size-4" />
                        </button>
                    </div>
                    <div
                        class="mt-5 overflow-x-auto rounded-xl border border-[#263c35] bg-[#152722] text-[#e7f1e9] shadow-lg shadow-[#183326]/10"
                    >
                        <div
                            class="flex items-center justify-between border-b border-white/10 px-5 py-3 text-xs text-[#9eb8a7]"
                        >
                            <span>请求体 · JSON</span
                            ><button
                                class="flex items-center gap-1.5 hover:text-white"
                                @click="copy(requestBody, 'request')"
                            >
                                <Check
                                    v-if="copied === 'request'"
                                    class="size-3.5"
                                /><Clipboard v-else class="size-3.5" />{{
                                    copied === 'request' ? '已复制' : '复制'
                                }}
                            </button>
                        </div>
                        <pre
                            class="overflow-x-auto p-5 text-[13px] leading-7"
                        ><code>{{ requestBody }}</code></pre>
                    </div>
                    <details
                        class="mt-4 rounded-lg border border-[#dbe3dc] bg-white p-4"
                    >
                        <summary
                            class="cursor-pointer text-sm font-semibold text-[#315d45]"
                        >
                            查看 cURL 示例（Bash）
                        </summary>
                        <div
                            class="mt-4 overflow-x-auto rounded-md bg-[#edf2ed] p-4"
                        >
                            <code class="text-xs leading-6 break-all">{{
                                curlCommand
                            }}</code>
                        </div>
                        <p class="mt-3 text-xs text-[#7b887d]">
                            将示例密钥和学工号替换为实际值。
                        </p>
                    </details>
                    <details
                        class="mt-3 rounded-lg border border-[#dbe3dc] bg-white p-4"
                    >
                        <summary
                            class="cursor-pointer text-sm font-semibold text-[#315d45]"
                        >
                            查看 PowerShell 示例（Windows）
                        </summary>
                        <pre
                            class="mt-4 overflow-x-auto rounded-md bg-[#edf2ed] p-4 text-xs leading-6"
                        ><code>{{ powershellCommand }}</code></pre>
                    </details>
                </section>

                <section
                    id="fields"
                    class="mt-16 scroll-mt-8 border-t border-[#dbe3dc] pt-9"
                >
                    <p
                        class="text-xs font-bold tracking-[0.18em] text-[#528166]"
                    >
                        03 / PAYLOAD
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold">请求字段</h2>
                    <div
                        class="mt-6 overflow-x-auto rounded-xl border border-[#dbe3dc] bg-white"
                    >
                        <table class="w-full min-w-[640px] text-left text-sm">
                            <thead class="bg-[#eaf0e9] text-xs text-[#55705b]">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">
                                        字段
                                    </th>
                                    <th class="px-4 py-3 font-semibold">
                                        要求
                                    </th>
                                    <th class="px-4 py-3 font-semibold">
                                        说明
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-[#edf1ed] text-[#586b5d]"
                            >
                                <tr>
                                    <td
                                        class="px-4 py-3 font-mono text-[#1d3f30]"
                                    >
                                        request_id
                                    </td>
                                    <td class="px-4 py-3">
                                        必填 · 最多 120 字符
                                    </td>
                                    <td class="px-4 py-3">
                                        按下方统一编号格式生成；同一系统内重复提交返回原任务。
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        class="px-4 py-3 font-mono text-[#1d3f30]"
                                    >
                                        channels
                                    </td>
                                    <td class="px-4 py-3">必填</td>
                                    <td class="px-4 py-3">
                                        邮件固定为 <code>["email"]</code>。
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        class="px-4 py-3 font-mono text-[#1d3f30]"
                                    >
                                        title
                                    </td>
                                    <td class="px-4 py-3">
                                        必填 · 最多 255 字符
                                    </td>
                                    <td class="px-4 py-3">邮件主题。</td>
                                </tr>
                                <tr>
                                    <td
                                        class="px-4 py-3 font-mono text-[#1d3f30]"
                                    >
                                        content
                                    </td>
                                    <td class="px-4 py-3">
                                        必填 · 最多 10000 字符
                                    </td>
                                    <td class="px-4 py-3">
                                        纯文本邮件正文；当前不支持 HTML 和附件。
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        class="px-4 py-3 font-mono text-[#1d3f30]"
                                    >
                                        recipients
                                    </td>
                                    <td class="px-4 py-3">必填 · 1–10000 人</td>
                                    <td class="px-4 py-3">
                                        收件人数组，共用主题和正文。
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        class="px-4 py-3 font-mono text-[#1d3f30]"
                                    >
                                        recipients[].id
                                    </td>
                                    <td class="px-4 py-3">
                                        必填 · 最多 120 字符
                                    </td>
                                    <td class="px-4 py-3">
                                        收件人的学工号，作为字符串传入并保留前导零；在本次请求内唯一。
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        class="px-4 py-3 font-mono text-[#1d3f30]"
                                    >
                                        recipients[].email
                                    </td>
                                    <td class="px-4 py-3">
                                        可选 · 有效邮箱地址
                                    </td>
                                    <td class="px-4 py-3">
                                        填写后覆盖本地每日同步快照中的 dzyx，最多 255 字符；不填则按学工号查询。
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        class="mt-5 rounded-xl border border-[#cbded1] bg-[#edf5ee] p-5 text-sm text-[#365b43]"
                    >
                        <h3 class="font-semibold">编号约定</h3>
                        <p class="mt-2 leading-7">
                            新接入系统请统一采用
                            <code class="rounded bg-white px-1.5 py-0.5"
                                >系统代码:业务场景:业务唯一单号</code
                            >
                            作为 <code>request_id</code>。例如
                            <code class="rounded bg-white px-1.5 py-0.5"
                                >jwc:leave:202610080001</code
                            >，其中 <code>jwc</code> 是系统代码，<code
                                >leave</code
                            >
                            是业务场景，最后一段是该次提醒的唯一业务单号。
                        </p>
                        <p class="mt-2 leading-7">
                            系统代码和业务场景使用小写英文字母、数字或下划线；业务单号使用英文字母、数字、下划线或短横线。总长度不超过
                            120
                            字符。每次新的提醒生成新编号；网络超时或未收到提交响应而重试时沿用原编号，不能重新生成时间戳或流水号。
                        </p>
                        <p class="mt-2 leading-7">
                            这是新接入系统的统一命名约定。为兼容已有调用，当前服务端尚未按该格式拒绝旧请求号；仍强制校验非空、长度及同一系统内的唯一性。
                        </p>
                        <p class="mt-2 leading-7">
                            <code>recipients[].id</code>
                            固定填写收件人学工号；查询结果中的
                            <code>recipient_id</code>
                            会原样返回这个学工号，方便业务系统对应投递结果。
                        </p>
                        <p class="mt-2 leading-7">
                            平台按学工号查询本地每日同步的电子邮箱。可选的
                            <code>recipients[].email</code> 会覆盖该邮箱；学工号仍须存在于本地快照。
                            未收录、缺少邮箱或邮箱格式无效时，该邮件投递记为
                            <code>failed</code>，原因见 <code>error</code>。
                        </p>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-[#718074]">
                        请求头必须包含
                        <code class="rounded bg-[#e9eee8] px-1.5 py-0.5"
                            >Authorization: Bearer &lt;接入系统密钥&gt;</code
                        >、<code class="rounded bg-[#e9eee8] px-1.5 py-0.5"
                            >Accept: application/json</code
                        >
                        和
                        <code class="rounded bg-[#e9eee8] px-1.5 py-0.5"
                            >Content-Type: application/json</code
                        >。
                    </p>
                </section>

                <section
                    id="response"
                    class="mt-16 scroll-mt-8 border-t border-[#dbe3dc] pt-9"
                >
                    <p
                        class="text-xs font-bold tracking-[0.18em] text-[#528166]"
                    >
                        04 / RESULT
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold">返回与查询</h2>
                    <p class="mt-4 text-sm leading-7 text-[#66766b]">
                        首次提交返回
                        <strong class="text-[#205d43]">202 Accepted</strong
                        >。这是已入队，不代表已发到收件箱。请保存返回的
                        <code>id</code>，之后按任务号查询。
                    </p>
                    <div
                        class="mt-5 overflow-x-auto rounded-xl border border-[#263c35] bg-[#152722] text-[#e7f1e9]"
                    >
                        <div
                            class="border-b border-white/10 px-5 py-3 text-xs text-[#9eb8a7]"
                        >
                            首次提交响应 · 示例
                        </div>
                        <pre
                            class="overflow-x-auto p-5 text-[13px] leading-7"
                        ><code>{{ acceptedResponse }}</code></pre>
                    </div>
                    <div
                        class="mt-5 flex flex-wrap items-center gap-3 rounded-lg border border-[#dbe3dc] bg-white px-4 py-3 font-mono text-sm"
                    >
                        <span
                            class="rounded bg-[#e8eaf8] px-2 py-1 font-bold text-[#435394]"
                            >GET</span
                        ><code class="min-w-0 break-all"
                            >{{ apiUrl }}/&#123;id&#125;</code
                        >
                    </div>
                    <p class="mt-4 text-sm leading-7 text-[#66766b]">
                        查询接口使用同一 Bearer
                        密钥，仅能查看当前接入系统自己的任务。重复提交相同
                        <code>request_id</code> 返回
                        <strong>200 OK</strong>
                        和原任务，不会重新发送；即使内容改变也仍返回原任务。
                    </p>
                    <div class="mt-5 grid gap-2 sm:grid-cols-2">
                        <div
                            class="rounded-lg border border-[#dbe3dc] bg-white p-4 text-sm"
                        >
                            <strong>任务状态</strong>
                            <p class="mt-2 leading-6 text-[#66766b]">
                                <code>queued</code> 排队中 ·
                                <code>processing</code> 处理中 ·
                                <code>accepted</code> 全部提交成功 ·
                                <code>partial</code> 部分失败 ·
                                <code>failed</code> 全部失败
                            </p>
                        </div>
                        <div
                            class="rounded-lg border border-[#dbe3dc] bg-white p-4 text-sm"
                        >
                            <strong>单人投递状态</strong>
                            <p class="mt-2 leading-6 text-[#66766b]">
                                <code>queued</code> 待处理 ·
                                <code>submitted</code> 处理中 ·
                                <code>accepted</code> SMTP 已接受 ·
                                <code>failed</code> 失败
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    id="errors"
                    class="mt-16 scroll-mt-8 border-t border-[#dbe3dc] pt-9"
                >
                    <p
                        class="text-xs font-bold tracking-[0.18em] text-[#528166]"
                    >
                        05 / ERRORS
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold">错误处理</h2>
                    <div
                        class="mt-6 overflow-x-auto rounded-xl border border-[#dbe3dc] bg-white"
                    >
                        <table class="w-full min-w-[540px] text-left text-sm">
                            <tbody class="divide-y divide-[#edf1ed]">
                                <tr>
                                    <td
                                        class="px-4 py-3 font-mono font-semibold"
                                    >
                                        401
                                    </td>
                                    <td class="px-4 py-3">
                                        密钥缺失、无效或已停用
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        class="px-4 py-3 font-mono font-semibold"
                                    >
                                        422
                                    </td>
                                    <td class="px-4 py-3">
                                        参数不符合要求；响应中的
                                        <code>errors</code> 标明字段
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        class="px-4 py-3 font-mono font-semibold"
                                    >
                                        404
                                    </td>
                                    <td class="px-4 py-3">
                                        任务不存在，或不属于当前接入系统
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        class="px-4 py-3 font-mono font-semibold"
                                    >
                                        429
                                    </td>
                                    <td class="px-4 py-3">
                                        同一接入系统超过每分钟 120 次请求
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold">503</td>
                                    <td class="px-4 py-3">本地人员快照尚未同步或无法查询，任务未创建</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-4 text-sm leading-7 text-[#66766b]">
                        网络超时或服务错误后，可以用相同
                        <code>request_id</code> 重试提交，并根据返回的任务 ID
                        查询结果。
                    </p>
                    <details
                        class="mt-4 rounded-lg border border-[#dbe3dc] bg-white p-4"
                    >
                        <summary
                            class="cursor-pointer text-sm font-semibold text-[#315d45]"
                        >
                            查看参数错误响应示例
                        </summary>
                        <pre
                            class="mt-4 overflow-x-auto rounded-md bg-[#edf2ed] p-4 text-xs leading-6"
                        ><code>{{ validationResponse }}</code></pre>
                    </details>
                </section>

                <section
                    id="notes"
                    class="mt-16 scroll-mt-8 border-t border-[#dbe3dc] pt-9"
                >
                    <p
                        class="text-xs font-bold tracking-[0.18em] text-[#528166]"
                    >
                        06 / DELIVERY
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold">投递说明</h2>
                    <ul class="mt-5 space-y-3 text-sm leading-7 text-[#66766b]">
                        <li>
                            邮件异步发送。平台需要运行队列
                            worker；未运行时任务会停留在 <code>queued</code>。
                        </li>
                        <li>
                            <code>accepted</code> 表示 SMTP
                            服务器接受邮件，无法证明邮件已进入收件箱。收件人还需检查垃圾邮件及邮箱规则。
                        </li>
                        <li>
                            可恢复的连接错误最多尝试三次。若邮件服务器已接受，但队列在记录结果前中断，重试可能产生重复邮件。
                        </li>
                        <li>
                            请将 API
                            密钥只保存在业务系统服务端。密钥轮换后旧密钥立即失效。
                        </li>
                    </ul>
                </section>

                <footer
                    class="mt-16 border-t border-[#dbe3dc] pt-6 text-xs text-[#89968c]"
                >
                    消息提醒平台 · 邮件接入文档 · API v1
                </footer>
            </main>
        </div>
    </div>
</template>
