<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    ListTodo,
    LockKeyhole,
    Mail,
    MessageSquareText,
} from '@lucide/vue';
import { dashboard } from '@/routes';

const props = defineProps<{
    canManageNotifications: boolean;
    channelStatus: Record<'email' | 'sms' | 'portal' | 'portal_message', boolean>;
    channelCounts: Partial<Record<'email' | 'sms' | 'portal' | 'portal_message', number>>;
}>();

const channels = [
    {
        key: 'email' as const,
        name: '邮件消息',
        description: '通过 SMTP 向一个或多个收件人发送邮件提醒。',
        icon: Mail,
        number: '01',
        href: '/notifications?channel=email#channel-email',
    },
    {
        key: 'sms' as const,
        name: '短信消息',
        description: '通过云 MAS 批量提交短信，查看每批处理结果。',
        icon: MessageSquareText,
        number: '02',
        href: '/notifications?channel=sms#channel-sms',
    },
    {
        key: 'portal_message' as const,
        name: '门户站内消息',
        description: '通过 POA 消息中心发送门户站内提醒。',
        icon: MessageSquareText,
        number: '03',
        href: '/notifications?channel=portal_message#channel-portal_message',
    },
    {
        key: 'portal' as const,
        name: '信息门户待办',
        description: '向门户创建待办，并管理完成与撤销状态。',
        icon: ListTodo,
        number: '04',
        href: '/notifications?channel=portal#channel-portal',
    },
];

defineOptions({
    layout: { breadcrumbs: [{ title: '消息平台', href: dashboard() }] },
});
</script>

<template>
    <Head title="消息平台" />
    <div class="min-h-full bg-[#f5f7f6] px-5 py-8 text-[#16302b] md:px-10">
        <div class="mx-auto max-w-7xl">
            <header class="border-b border-[#d8e0dc] pb-8">
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-[#287257]"
                >
                    MESSAGE CENTER
                </p>
                <h1
                    class="mt-3 text-3xl font-semibold tracking-tight md:text-4xl"
                >
                    消息提醒平台
                </h1>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-[#64736c]">
                    从邮件、短信和门户渠道进入对应渠道，查看配置与发送记录。业务系统通过统一
                    API 提交提醒。
                </p>
            </header>

            <div
                v-if="!props.canManageNotifications"
                class="mt-7 flex gap-3 rounded-xl border border-[#ead8b4] bg-[#fff8e8] p-4 text-sm text-[#765727]"
            >
                <LockKeyhole class="mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="font-semibold">
                        账号已可登录，消息管理权限尚未开通
                    </p>
                    <p class="mt-1">
                        请在服务器运行
                        <code class="rounded bg-white/70 px-1 py-0.5"
                            >php artisan notifications:grant-admin
                            你的登录邮箱</code
                        >，然后刷新本页。
                    </p>
                </div>
            </div>

            <section class="mt-8" aria-label="消息渠道">
                <div class="mb-4 flex items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">选择消息渠道</h2>
                        <p class="mt-1 text-xs text-[#7b8982]">
                            按邮件、短信、信息门户的建设顺序展示
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <Link
                            href="/docs/email"
                            class="text-sm font-medium text-[#287257] hover:underline"
                            >邮件接入文档</Link
                        >
                        <Link
                            href="/docs/sms"
                            class="text-sm font-medium text-[#287257] hover:underline"
                            >短信接入文档</Link
                        >
                        <Link
                            href="/docs/portal"
                            class="text-sm font-medium text-[#287257] hover:underline"
                            >门户接入文档</Link
                        >
                        <Link
                            v-if="props.canManageNotifications"
                            href="/notifications"
                            class="text-sm font-medium text-[#287257] hover:underline"
                            >查看全部记录</Link
                        >
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <component
                        :is="props.canManageNotifications ? Link : 'div'"
                        v-for="channel in channels"
                        :key="channel.key"
                        :href="
                            props.canManageNotifications
                                ? channel.href
                                : undefined
                        "
                        class="group flex min-h-64 flex-col rounded-2xl border border-[#dce4df] bg-white p-6 shadow-sm transition hover:border-[#a6cdb7] hover:shadow-md"
                    >
                        <div class="flex items-start justify-between">
                            <div
                                class="rounded-xl bg-[#e9f3ef] p-3 text-[#287257]"
                            >
                                <component :is="channel.icon" class="size-6" />
                            </div>
                            <span
                                class="text-xs font-medium text-[#a6b4ac] tabular-nums"
                                >{{ channel.number }} / 03</span
                            >
                        </div>
                        <h3 class="mt-7 text-xl font-semibold">
                            {{ channel.name }}
                        </h3>
                        <p class="mt-2 flex-1 text-sm leading-6 text-[#718077]">
                            {{ channel.description }}
                        </p>
                        <div
                            class="mt-7 flex items-center justify-between border-t border-[#edf1ee] pt-4"
                        >
                            <span
                                :class="
                                    props.channelStatus[channel.key]
                                        ? 'text-[#20764e]'
                                        : 'text-[#906222]'
                                "
                                class="text-xs font-medium"
                            >
                                {{
                                    props.channelStatus[channel.key]
                                        ? '● 已配置'
                                        : '● 待配置'
                                }}
                                <span
                                    v-if="props.canManageNotifications"
                                    class="ml-2 text-[#8a9990]"
                                    >{{
                                        props.channelCounts[channel.key] ?? 0
                                    }}
                                    条投递</span
                                >
                            </span>
                            <span
                                class="flex items-center gap-1 text-sm font-medium text-[#287257]"
                            >
                                {{
                                    props.canManageNotifications
                                        ? '进入渠道'
                                        : '需要管理员权限'
                                }}
                                <ArrowUpRight
                                    v-if="props.canManageNotifications"
                                    class="size-4 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                                />
                            </span>
                        </div>
                    </component>
                </div>
            </section>
            <p class="mt-8 text-sm text-[#718077]">
                更多渠道可在后续接入企业微信和钉钉。
            </p>
        </div>
    </div>
</template>
