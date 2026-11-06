<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Check, Clipboard, MessageSquareText } from '@lucide/vue';
import { computed, ref } from 'vue';
import { copyText } from '@/lib/copyText';
import type { ApiDocumentation } from '@/types/api-documentation';

const props = defineProps<{ apiUrl: string; doc: ApiDocumentation }>();
const copied = ref<string | null>(null);
const copyFeedback = ref('');
const requestBody = computed(() => props.doc.examples[0].body);
const curlCommand = computed(() => props.doc.examples[0].curl);
const powershellCommand = computed(() => props.doc.examples[0].powershell);
const responseBody = computed(() => props.doc.response);
const fields = computed(() => props.doc.fields.map(([name, requirement, description]) => ({ name, requirement, description })));

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
    <Head title="短信 API 接入文档" />
    <div class="min-h-screen bg-[#f6f7f3] font-sans text-[#192b29]">
        <p
            v-if="copyFeedback"
            role="status"
            class="fixed right-5 bottom-5 z-50 rounded-lg bg-[#194f3e] px-4 py-3 text-sm font-medium text-white shadow-lg"
        >
            {{ copyFeedback }}
        </p>
        <header class="border-b border-[#dbe3dc] bg-white">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4 md:px-8"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-9 items-center justify-center rounded-lg bg-[#194f3e] text-white"
                        ><MessageSquareText class="size-5"
                    /></span>
                    <span class="text-sm font-semibold"
                        >消息提醒平台 · API v1</span
                    >
                </div>
                <nav class="flex gap-5 text-sm text-[#386b55]">
                    <Link href="/docs/email" class="hover:underline"
                        >邮件文档</Link
                    >
                    <Link href="/docs/portal" class="hover:underline"
                        >门户文档</Link
                    >
                    <Link href="/dashboard" class="hover:underline"
                        >返回平台</Link
                    >
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-5 py-12 md:px-8">
            <div class="max-w-3xl">
                <span
                    class="rounded-full border border-[#c9dfcf] bg-[#eaf4ec] px-3 py-1 text-xs font-semibold text-[#206145]"
                    >API v1 · 短信渠道</span
                >
                <h1
                    class="mt-5 text-4xl font-semibold tracking-tight md:text-5xl"
                >
                    短信 API 接入文档
                </h1>
                <p class="mt-5 text-base leading-8 text-[#66766b]">
                    {{ doc.intro }}
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="/docs/sms/download/pdf" class="rounded-lg bg-[#194f3e] px-4 py-2 text-sm font-semibold text-white hover:bg-[#276449]">下载 PDF</a>
                    <a href="/docs/sms/download/html" class="rounded-lg border border-[#a9c7b2] bg-white px-4 py-2 text-sm font-semibold text-[#205d43] hover:bg-[#edf5ee]">下载离线 HTML</a>
                </div>
            </div>

            <section class="mt-12 grid gap-3 md:grid-cols-3">
                <div
                    v-for="(step, index) in [
                        '从管理员领取接入系统密钥',
                        '提交 JSON 短信任务并保存任务 ID',
                        '按任务 ID 查询每位收件人状态',
                    ]"
                    :key="step"
                    class="rounded-xl border border-[#dbe3dc] bg-white p-5"
                >
                    <span class="text-xs font-semibold text-[#478569]"
                        >STEP 0{{ index + 1 }}</span
                    >
                    <p class="mt-3 text-sm leading-6">{{ step }}</p>
                </div>
            </section>

            <section id="send" class="mt-14 border-t border-[#dbe3dc] pt-9">
                <h2 class="text-2xl font-semibold">发送短信</h2>
                <div
                    class="mt-5 flex items-center gap-3 rounded-lg border border-[#dbe3dc] bg-white px-4 py-3 font-mono text-sm"
                >
                    <span
                        class="rounded bg-[#dff2e5] px-2 py-1 font-bold text-[#216347]"
                        >POST</span
                    >
                    <code class="min-w-0 break-all">{{ apiUrl }}</code>
                    <button
                        class="ml-auto text-[#527d61]"
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
                    class="mt-5 overflow-x-auto rounded-xl bg-[#152722] text-[#e7f1e9]"
                >
                    <div
                        class="flex justify-between border-b border-white/10 px-5 py-3 text-xs text-[#9eb8a7]"
                    >
                        <span>请求体 · JSON</span
                        ><button @click="copy(requestBody, 'body')">
                            {{ copied === 'body' ? '已复制' : '复制' }}
                        </button>
                    </div>
                    <pre
                        class="p-5 text-[13px] leading-7"
                    ><code>{{ requestBody }}</code></pre>
                </div>
                <details
                    class="mt-4 rounded-lg border border-[#dbe3dc] bg-white p-4"
                >
                    <summary
                        class="cursor-pointer text-sm font-semibold text-[#315d45]"
                    >
                        cURL 示例（Bash）
                    </summary>
                    <pre
                        class="mt-4 overflow-x-auto rounded-md bg-[#edf2ed] p-4 text-xs leading-6"
                    ><code>{{ curlCommand }}</code></pre>
                </details>
                <details
                    class="mt-3 rounded-lg border border-[#dbe3dc] bg-white p-4"
                >
                    <summary
                        class="cursor-pointer text-sm font-semibold text-[#315d45]"
                    >
                        PowerShell 示例（Windows）
                    </summary>
                    <pre
                        class="mt-4 overflow-x-auto rounded-md bg-[#edf2ed] p-4 text-xs leading-6"
                    ><code>{{ powershellCommand }}</code></pre>
                </details>
                <p class="mt-4 text-sm leading-7 text-[#66766b]">
                    将示例密钥、手机号、短信签名和正文替换为实际已审批的内容。请求头需包含
                    <code>Authorization: Bearer &lt;接入系统密钥&gt;</code
                    >、<code>Accept: application/json</code> 和
                    <code>Content-Type: application/json</code>。
                </p>
            </section>

            <section id="fields" class="mt-14 border-t border-[#dbe3dc] pt-9">
                <h2 class="text-2xl font-semibold">请求字段</h2>
                <div
                    class="mt-5 overflow-x-auto rounded-xl border border-[#dbe3dc] bg-white"
                >
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="bg-[#eaf0e9] text-[#55705b]">
                            <tr>
                                <th class="px-4 py-3">字段</th>
                                <th class="px-4 py-3">要求</th>
                                <th class="px-4 py-3">说明</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#edf1ed]">
                            <tr v-for="field in fields" :key="field.name">
                                <td class="px-4 py-3 font-mono text-[#1d3f30]">
                                    {{ field.name }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ field.requirement }}
                                </td>
                                <td class="px-4 py-3 text-[#586b5d]">
                                    {{ field.description }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="mt-5 rounded-xl border border-[#cbded1] bg-[#edf5ee] p-5 text-sm leading-7 text-[#365b43]"
                >
                    <strong>编号约定</strong>
                    <p class="mt-2">
                        新接入系统的 <code>request_id</code> 使用
                        <code>系统代码:业务场景:业务唯一单号</code>，例如
                        <code>jwc:notice:202610080001</code
                        >。系统代码和业务场景使用小写英文字母、数字或下划线；业务单号可另含短横线。当前服务端只强制校验非空、长度和同一系统内唯一。
                    </p>
                    <p class="mt-2">
                        <code>recipients[].id</code> 填学工号；响应中的
                        <code>recipient_id</code> 原样返回，便于对应投递结果。
                    </p>
                    <p class="mt-2">
                        平台按学工号查询本地每日同步的移动电话。可选的
                        <code>recipients[].mobile</code> 会覆盖该号码；学工号仍须在本地快照中。
                        未收录、缺少号码或号码格式无效时，该短信投递记为
                        <code>failed</code>，原因见 <code>error</code>。
                    </p>
                </div>
            </section>

            <section id="response" class="mt-14 border-t border-[#dbe3dc] pt-9">
                <h2 class="text-2xl font-semibold">返回与查询</h2>
                <p class="mt-4 text-sm leading-7 text-[#66766b]">
                    首次提交返回
                    <strong>202 Accepted</strong>，表示任务已入队。重复提交相同
                    <code>request_id</code> 返回原任务与
                    <strong>200 OK</strong
                    >，不重复发送；即使请求内容变化也返回原任务。
                </p>
                <div
                    class="mt-5 overflow-x-auto rounded-xl bg-[#152722] text-[#e7f1e9]"
                >
                    <div
                        class="border-b border-white/10 px-5 py-3 text-xs text-[#9eb8a7]"
                    >
                        首次提交响应 · 示例
                    </div>
                    <pre
                        class="p-5 text-[13px] leading-7"
                    ><code>{{ responseBody }}</code></pre>
                </div>
                <div
                    class="mt-5 rounded-lg border border-[#dbe3dc] bg-white px-4 py-3 font-mono text-sm"
                >
                    <span
                        class="mr-3 rounded bg-[#e8eaf8] px-2 py-1 font-bold text-[#435394]"
                        >GET</span
                    ><code class="break-all">{{ apiUrl }}/&#123;id&#125;</code>
                </div>
                <p class="mt-4 text-sm leading-7 text-[#66766b]">
                    查询使用同一 Bearer 密钥，只能查看本接入系统任务。任务状态为
                    <code>queued</code
                    >、<code>processing</code>、<code>accepted</code>、<code
                        >partial</code
                    >
                    或 <code>failed</code>；逐人短信投递状态为
                    <code>queued</code>、<code>submitted</code>、<code
                        >accepted</code
                    >
                    或 <code>failed</code>。<code>provider_ref</code> 为 MAS
                    返回的批次标识（如有）。
                </p>
            </section>

            <section id="errors" class="mt-14 border-t border-[#dbe3dc] pt-9">
                <h2 class="text-2xl font-semibold">错误处理与投递说明</h2>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="error in doc.errors"
                        :key="error[0]"
                        class="rounded-lg border border-[#dbe3dc] bg-white p-4 text-sm"
                    >
                        {{ error[0] }}：{{ error[1] }}
                    </div>
                </div>
                <ul
                    class="mt-5 list-disc space-y-2 pl-5 text-sm leading-7 text-[#66766b]"
                >
                    <li v-for="note in doc.notes" :key="note">{{ note }}</li>
                </ul>
            </section>
        </main>
    </div>
</template>
