<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    ArrowUpRight,
    KeyRound,
    Mail,
    MessageSquareText,
    Plus,
    RadioTower,
    RefreshCw,
    Search,
    ShieldCheck,
} from '@lucide/vue';

type Client = { id: number; name: string; active: boolean; created_at: string };
type RecordItem = {
    id: string;
    request_id: string;
    title: string;
    status: string;
    deliveries_count: number;
    created_at: string;
    client_system: { name: string };
};
type PageLink = { url: string | null; label: string; active: boolean };
const props = defineProps<{
    records: { data: RecordItem[]; links: PageLink[]; total: number };
    clients: Client[];
    channelStatus: Record<'email' | 'sms' | 'portal', boolean>;
    channelDetails: Record<'email' | 'sms' | 'portal', Record<string, string>>;
    newKey?: string | null;
    activeChannel: 'email' | 'sms' | 'portal' | null;
    filters: { search: string; client: number | null };
}>();

const form = useForm({ name: '' });
const searchText = ref(props.filters.search);
const selectedClient = ref(props.filters.client?.toString() ?? '');
const hasFilters = computed(() =>
    Boolean(props.filters.search || props.filters.client),
);
const channels = computed(() => [
    {
        key: 'email' as const,
        name: '邮件',
        detail: 'SMTP · SSL 465',
        icon: Mail,
    },
    {
        key: 'sms' as const,
        name: '短信',
        detail: '云 MAS · 批量提交',
        icon: MessageSquareText,
    },
    {
        key: 'portal' as const,
        name: '信息门户',
        detail: 'POA · 待办事务',
        icon: RadioTower,
    },
]);
function createClient() {
    form.post('/notifications/clients', { onSuccess: () => form.reset() });
}
function applyFilters() {
    const query: Record<string, string> = {};
    if (props.activeChannel) query.channel = props.activeChannel;
    if (searchText.value.trim()) query.search = searchText.value.trim();
    if (selectedClient.value) query.client = selectedClient.value;
    router.get('/notifications', query, {
        replace: true,
        preserveScroll: true,
    });
}
function clearFilters() {
    searchText.value = '';
    selectedClient.value = '';
    applyFilters();
}
function act(id: number, operation: 'rotate' | 'toggle') {
    if (
        operation === 'rotate' &&
        !window.confirm('轮换后旧密钥立即失效。确定继续？')
    )
        return;
    router.post(`/notifications/clients/${id}/${operation}`);
}
function statusLabel(value: string) {
    return (
        (
            {
                queued: '排队中',
                processing: '处理中',
                accepted: '已受理',
                partial: '部分失败',
                failed: '失败',
            } as Record<string, string>
        )[value] ?? value
    );
}
</script>

<template>
    <Head title="消息平台" />
    <div class="min-h-full bg-[#f5f7f6] px-5 py-8 text-[#16302b] md:px-10">
        <div class="mx-auto max-w-7xl space-y-8">
            <header
                class="flex flex-col gap-5 border-b border-[#d8e0dc] pb-7 md:flex-row md:items-end md:justify-between"
            >
                <div>
                    <div
                        class="mb-3 inline-flex items-center gap-2 rounded-full bg-[#dcece6] px-3 py-1 text-xs font-semibold tracking-widest text-[#246b55]"
                    >
                        MESSAGE OPERATIONS
                        <span class="size-1.5 rounded-full bg-[#36a877]"></span>
                    </div>
                    <h1
                        class="text-3xl font-semibold tracking-tight md:text-4xl"
                    >
                        消息平台
                    </h1>
                    <p class="mt-2 text-sm text-[#64736c]">
                        统一查看三个渠道的配置、接入系统与发送结果。
                    </p>
                    <div class="mt-3 flex flex-wrap gap-4">
                        <Link
                            href="/docs/email"
                            class="inline-flex items-center gap-1 text-sm font-medium text-[#287257] hover:underline"
                            >邮件 API 接入文档 <ArrowUpRight class="size-4"
                        /></Link>
                        <Link
                            href="/docs/sms"
                            class="inline-flex items-center gap-1 text-sm font-medium text-[#287257] hover:underline"
                            >短信 API 接入文档 <ArrowUpRight class="size-4"
                        /></Link>
                    </div>
                </div>
                <div
                    class="rounded-xl border border-[#d8e0dc] bg-white px-5 py-3 text-right shadow-sm"
                >
                    <div class="text-xs text-[#7b8982]">当前结果</div>
                    <div class="text-2xl font-semibold tabular-nums">
                        {{ records.total }}
                    </div>
                </div>
            </header>

            <section class="grid gap-4 md:grid-cols-3" aria-label="渠道状态">
                <article
                    v-for="item in channels"
                    :key="item.key"
                    :id="`channel-${item.key}`"
                    class="rounded-2xl border border-[#dce4df] bg-white p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between">
                        <div
                            class="rounded-lg bg-[#e9f3ef] p-2.5 text-[#287257]"
                        >
                            <component :is="item.icon" class="size-5" />
                        </div>
                        <span
                            :class="
                                channelStatus[item.key]
                                    ? 'bg-[#e2f5e9] text-[#20764e]'
                                    : 'bg-[#fff0d9] text-[#906222]'
                            "
                            class="rounded-full px-2.5 py-1 text-xs font-semibold"
                            >{{
                                channelStatus[item.key] ? '已配置' : '待配置'
                            }}</span
                        >
                    </div>
                    <h2 class="mt-5 text-lg font-semibold">{{ item.name }}</h2>
                    <p class="mt-1 text-sm text-[#819089]">{{ item.detail }}</p>
                    <Link
                        :href="`/notifications?channel=${item.key}#channel-${item.key}`"
                        class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-[#287257] hover:underline"
                        >查看{{ item.name }}记录 <ArrowUpRight class="size-3"
                    /></Link>
                </article>
            </section>

            <section
                class="rounded-2xl border border-[#dce4df] bg-white p-6 shadow-sm"
            >
                <div
                    class="mb-5 flex flex-wrap items-end justify-between gap-2"
                >
                    <div>
                        <h2 class="text-lg font-semibold">渠道配置</h2>
                        <p class="mt-1 text-xs text-[#819089]">
                            展示正在使用的非敏感配置，密码和令牌由服务器环境变量管理。
                        </p>
                    </div>
                    <span class="text-xs text-[#8a9990]"
                        >更改环境变量后请重启队列 worker</span
                    >
                </div>
                <div class="grid gap-4 lg:grid-cols-3">
                    <div
                        v-for="item in channels"
                        :key="item.key"
                        class="rounded-xl border border-[#e3ebe5] bg-[#fafcfb] p-4"
                    >
                        <h3 class="mb-3 text-sm font-semibold">
                            {{ item.name }}
                        </h3>
                        <dl class="space-y-2 text-xs">
                            <div
                                v-for="(value, label) in channelDetails[
                                    item.key
                                ]"
                                :key="label"
                                class="flex justify-between gap-3"
                            >
                                <dt class="shrink-0 text-[#8a9990]">
                                    {{ label }}
                                </dt>
                                <dd
                                    class="min-w-0 text-right break-all text-[#415b4c]"
                                >
                                    {{ value || '未设置' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.55fr_1fr]">
                <div
                    class="overflow-hidden rounded-2xl border border-[#dce4df] bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b border-[#e8eeea] px-6 py-5"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">
                                {{
                                    props.activeChannel
                                        ? `${channels.find((channel) => channel.key === props.activeChannel)?.name}发送记录`
                                        : '全部发送记录'
                                }}
                            </h2>
                            <p class="mt-1 text-xs text-[#819089]">
                                按创建时间倒序排列
                            </p>
                        </div>
                        <Link
                            v-if="props.activeChannel"
                            href="/notifications"
                            class="text-xs font-medium text-[#287257] hover:underline"
                            >查看全部</Link
                        >
                        <RefreshCw v-else class="size-4 text-[#82928a]" />
                    </div>
                    <form
                        class="flex flex-col gap-3 border-b border-[#e8eeea] bg-[#fbfcfb] px-6 py-4 sm:flex-row sm:items-center"
                        @submit.prevent="applyFilters"
                    >
                        <div class="relative min-w-0 flex-1">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#87978b]"
                            />
                            <input
                                v-model="searchText"
                                name="search"
                                type="search"
                                maxlength="120"
                                :placeholder="
                                    props.activeChannel === 'email'
                                        ? '搜索主题、请求号、学工号或收件邮箱'
                                        : '搜索主题、请求号或收件人'
                                "
                                aria-label="搜索发送记录"
                                class="w-full rounded-lg border border-[#d6e0d9] bg-white py-2 pr-3 pl-9 text-sm outline-none focus:border-[#3c9670]"
                            />
                        </div>
                        <select
                            v-model="selectedClient"
                            name="client"
                            aria-label="按接入系统筛选"
                            class="rounded-lg border border-[#d6e0d9] bg-white px-3 py-2 text-sm text-[#415b4c] outline-none focus:border-[#3c9670]"
                        >
                            <option value="">全部接入系统</option>
                            <option
                                v-for="client in clients"
                                :key="client.id"
                                :value="client.id.toString()"
                            >
                                {{ client.name }}
                            </option>
                        </select>
                        <button
                            type="submit"
                            class="rounded-lg bg-[#235d49] px-4 py-2 text-sm font-medium text-white hover:bg-[#174735]"
                        >
                            查询
                        </button>
                        <button
                            v-if="hasFilters"
                            type="button"
                            class="text-xs font-medium text-[#648074] hover:underline"
                            @click="clearFilters"
                        >
                            清除
                        </button>
                    </form>
                    <div
                        v-if="records.data.length === 0"
                        class="px-6 py-16 text-center text-sm text-[#8a9790]"
                    >
                        {{
                            hasFilters
                                ? '没有符合条件的发送记录。'
                                : '暂无发送任务。接入系统提交消息后会显示在这里。'
                        }}
                    </div>
                    <div v-else class="divide-y divide-[#edf1ee]">
                        <Link
                            v-for="item in records.data"
                            :key="item.id"
                            :href="`/notifications/${item.id}`"
                            class="group flex items-center justify-between gap-4 px-6 py-4 transition hover:bg-[#f8fbf9]"
                        >
                            <div class="min-w-0">
                                <div class="truncate font-medium">
                                    {{ item.title }}
                                </div>
                                <div
                                    class="mt-1 truncate text-xs text-[#839188]"
                                >
                                    {{ item.client_system.name }} ·
                                    {{ item.request_id }} ·
                                    {{ item.deliveries_count }} 条投递
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                <span
                                    class="rounded-full bg-[#edf3ef] px-2.5 py-1 text-xs text-[#456d57]"
                                    >{{ statusLabel(item.status) }}</span
                                ><ArrowUpRight
                                    class="size-4 text-[#a3b1a9] group-hover:text-[#287257]"
                                />
                            </div>
                        </Link>
                    </div>
                    <nav
                        v-if="records.links.length > 3"
                        class="flex flex-wrap gap-1 border-t border-[#edf1ee] px-6 py-4"
                        aria-label="分页"
                    >
                        <Link
                            v-for="(link, index) in records.links"
                            :key="index"
                            v-html="link.label"
                            :href="link.url || '#'"
                            :class="[
                                link.active
                                    ? 'bg-[#235d49] text-white'
                                    : 'text-[#648074] hover:bg-[#eef5f0]',
                                !link.url
                                    ? 'pointer-events-none opacity-40'
                                    : '',
                            ]"
                            class="rounded-md px-3 py-1.5 text-xs"
                        />
                    </nav>
                </div>

                <div class="space-y-6">
                    <section
                        class="rounded-2xl border border-[#dce4df] bg-white p-6 shadow-sm"
                    >
                        <div class="flex items-center gap-2">
                            <ShieldCheck class="size-5 text-[#287257]" />
                            <h2 class="text-lg font-semibold">接入系统</h2>
                        </div>
                        <p class="mt-1 text-xs text-[#819089]">
                            每个业务系统使用独立密钥调用 API。
                        </p>
                        <div
                            v-if="newKey"
                            class="mt-5 rounded-xl border border-[#c4e6d5] bg-[#f0faf4] p-4"
                        >
                            <div
                                class="flex items-center gap-2 text-sm font-semibold text-[#246b50]"
                            >
                                <KeyRound class="size-4" />新密钥，仅显示一次
                            </div>
                            <code
                                class="mt-2 block text-xs leading-relaxed break-all text-[#285642]"
                                >{{ newKey }}</code
                            >
                            <p class="mt-2 text-xs text-[#688174]">
                                请立即保存到接入系统的安全配置中。
                            </p>
                        </div>
                        <form
                            class="mt-5 flex gap-2"
                            @submit.prevent="createClient"
                        >
                            <input
                                v-model="form.name"
                                aria-label="系统名称"
                                placeholder="输入系统名称"
                                maxlength="120"
                                class="min-w-0 flex-1 rounded-lg border border-[#d6e0d9] bg-white px-3 py-2 text-sm outline-none focus:border-[#3c9670]"
                            />
                            <button
                                :disabled="form.processing"
                                class="inline-flex items-center gap-1 rounded-lg bg-[#235d49] px-3 py-2 text-sm font-medium text-white hover:bg-[#174735]"
                            >
                                <Plus class="size-4" />新增
                            </button>
                        </form>
                        <p
                            v-if="form.errors.name"
                            class="mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.name }}
                        </p>
                        <div class="mt-5 divide-y divide-[#edf1ee]">
                            <div
                                v-for="client in clients"
                                :key="client.id"
                                class="flex items-center justify-between gap-3 py-3"
                            >
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium">
                                        {{ client.name }}
                                    </div>
                                    <div
                                        class="mt-0.5 text-xs"
                                        :class="
                                            client.active
                                                ? 'text-[#399362]'
                                                : 'text-[#a77939]'
                                        "
                                    >
                                        {{
                                            client.active ? '启用中' : '已停用'
                                        }}
                                    </div>
                                </div>
                                <div class="flex shrink-0 gap-1">
                                    <button
                                        class="rounded-md px-2 py-1 text-xs text-[#527263] hover:bg-[#edf4ef]"
                                        @click="act(client.id, 'rotate')"
                                    >
                                        轮换</button
                                    ><button
                                        class="rounded-md px-2 py-1 text-xs text-[#527263] hover:bg-[#edf4ef]"
                                        @click="act(client.id, 'toggle')"
                                    >
                                        {{ client.active ? '停用' : '启用' }}
                                    </button>
                                </div>
                            </div>
                            <div
                                v-if="clients.length === 0"
                                class="py-8 text-center text-xs text-[#91a098]"
                            >
                                尚无接入系统
                            </div>
                        </div>
                    </section>
                    <div
                        class="rounded-xl border border-[#d5e1d9] bg-[#eaf3ed] px-5 py-4 text-xs leading-relaxed text-[#537466]"
                    >
                        渠道凭据由服务器环境变量提供。此页面只显示配置状态，不展示密码或令牌。
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
