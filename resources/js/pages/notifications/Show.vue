<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
type Attempt = {
    id: number;
    operation: string;
    status: string;
    error: string | null;
    created_at: string;
};
type Delivery = {
    id: string;
    recipient_id: string;
    channel: string;
    address: string;
    status: string;
    attempts: Attempt[];
    provider_ref: string | null;
    business_task_id: string | null;
    last_error: string | null;
};
const props = defineProps<{
    record: {
        id: string;
        request_id: string;
        title: string;
        content: string;
        status: string;
        created_at: string;
        client_system: { name: string };
        deliveries: Delivery[];
    };
}>();
const channelName: Record<string, string> = {
    email: '邮件',
    sms: '短信',
    portal: '信息门户',
};
</script>

<template>
    <Head :title="record.title" />
    <div class="min-h-full bg-[#f5f7f6] px-5 py-8 text-[#16302b] md:px-10">
        <div class="mx-auto max-w-6xl space-y-6">
            <Link
                href="/notifications"
                class="inline-flex items-center gap-2 text-sm text-[#4a7461] hover:text-[#174d37]"
                ><ArrowLeft class="size-4" />返回消息平台</Link
            >
            <header
                class="rounded-2xl border border-[#dce4df] bg-white p-7 shadow-sm"
            >
                <div
                    class="text-xs font-semibold tracking-widest text-[#3e8f66]"
                >
                    DELIVERY RECORD
                </div>
                <h1 class="mt-3 text-3xl font-semibold">{{ record.title }}</h1>
                <div
                    class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-[#718079]"
                >
                    <span>{{ record.client_system.name }}</span
                    ><span>请求号 {{ record.request_id }}</span
                    ><span>{{ record.created_at }}</span
                    ><span>状态 {{ record.status }}</span>
                </div>
                <p
                    class="mt-6 rounded-xl bg-[#f6f9f7] p-4 text-sm leading-relaxed whitespace-pre-wrap text-[#425a4d]"
                >
                    {{ record.content }}
                </p>
            </header>
            <section
                class="overflow-hidden rounded-2xl border border-[#dce4df] bg-white shadow-sm"
            >
                <div class="border-b border-[#e8eeea] px-6 py-5">
                    <h2 class="text-lg font-semibold">
                        投递明细
                        <span class="ml-2 text-sm font-normal text-[#8a9990]">{{
                            record.deliveries.length
                        }}</span>
                    </h2>
                </div>
                <div class="divide-y divide-[#edf1ee]">
                    <article
                        v-for="delivery in record.deliveries"
                        :key="delivery.id"
                        class="grid gap-3 px-6 py-5 md:grid-cols-[1fr_1fr_auto]"
                    >
                        <div>
                            <div class="text-sm font-medium">
                                {{ delivery.recipient_id }}
                                <span class="ml-2 text-xs text-[#84938a]">{{
                                    channelName[delivery.channel]
                                }}</span>
                            </div>
                            <div class="mt-1 text-xs text-[#819087]">
                                {{ delivery.address }}
                            </div>
                        </div>
                        <div class="text-xs leading-6 text-[#718079]">
                            <div v-if="delivery.business_task_id">
                                业务任务号 {{ delivery.business_task_id }}
                            </div>
                            <div v-if="delivery.provider_ref">
                                上游批次号 {{ delivery.provider_ref }}
                            </div>
                            <div
                                v-if="delivery.last_error"
                                class="text-[#b46150]"
                            >
                                {{ delivery.last_error }}
                            </div>
                            <div
                                v-for="attempt in delivery.attempts"
                                :key="attempt.id"
                            >
                                {{ attempt.operation }} · {{ attempt.status }} ·
                                {{ attempt.created_at }}
                            </div>
                        </div>
                        <div>
                            <span
                                class="inline-block rounded-full bg-[#eaf3ed] px-3 py-1 text-xs font-medium text-[#2b7455]"
                                >{{ delivery.status }}</span
                            >
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>
