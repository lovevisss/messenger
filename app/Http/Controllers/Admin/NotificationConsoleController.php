<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientSystem;
use App\Models\NotificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NotificationConsoleController extends Controller
{
    public function index(Request $request): Response
    {
        $channel = $request->query('channel');
        $channel = in_array($channel, ['email', 'sms', 'portal', 'portal_message'], true) ? $channel : null;
        $search = $request->query('search');
        $search = is_string($search) ? trim(mb_substr($search, 0, 120)) : '';
        $clientId = $request->integer('client');
        $clientId = $clientId > 0 ? $clientId : null;

        $records = NotificationRequest::with('clientSystem')
            ->when($channel, fn ($query) => $query->whereHas('deliveries', fn ($deliveryQuery) => $deliveryQuery->where('channel', $channel)))
            ->when($clientId, fn ($query) => $query->where('client_system_id', $clientId))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search, $channel) {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('request_id', 'like', '%'.$search.'%')
                    ->orWhereHas('deliveries', fn ($deliveryQuery) => $deliveryQuery
                        ->when($channel, fn ($deliveryQuery) => $deliveryQuery->where('channel', $channel))
                        ->where(fn ($deliveryQuery) => $deliveryQuery
                            ->where('recipient_id', 'like', '%'.$search.'%')
                            ->orWhere('address', 'like', '%'.$search.'%')));
            }))
            ->withCount(['deliveries' => fn ($query) => $query->when($channel, fn ($query) => $query->where('channel', $channel))])
            ->latest()->paginate(15)->withQueryString();

        return Inertia::render('notifications/Index', [
            'records' => $records,
            'activeChannel' => $channel,
            'filters' => ['search' => $search, 'client' => $clientId],
            'clients' => ClientSystem::orderBy('name')->get(['id', 'name', 'active', 'created_at']),
            'channelStatus' => [
                'email' => config('mail.default') === 'smtp' && filled(config('mail.mailers.smtp.username')) && filled(config('mail.mailers.smtp.password')),
                'sms' => collect(['ec_name', 'ap_id', 'secret_key', 'sign'])->every(fn ($key) => filled(config('notifications.sms.'.$key))),
                'portal' => filled(config('notifications.portal.base_url')) && filled(config('notifications.portal.app_id'))
                    && (filled(config('notifications.portal.poa_token')) || (filled(config('notifications.portal.client_id')) && filled(config('notifications.portal.client_secret')))),
                'portal_message' => filled(config('notifications.portal.base_url')) && filled(config('notifications.portal.app_id'))
                    && filled(config('notifications.portal.message_type_code'))
                    && (filled(config('notifications.portal.poa_token')) || (filled(config('notifications.portal.client_id')) && filled(config('notifications.portal.client_secret')))),
            ],
            'channelDetails' => [
                'email' => [
                    '服务器' => (string) config('mail.mailers.smtp.host'),
                    '端口' => (string) config('mail.mailers.smtp.port'),
                    '连接方式' => (string) config('mail.mailers.smtp.scheme'),
                    '发件账号' => (string) config('mail.mailers.smtp.username'),
                    '发件地址' => (string) config('mail.from.address'),
                ],
                'sms' => [
                    '接口地址' => (string) config('notifications.sms.url'),
                    '企业名称' => (string) config('notifications.sms.ec_name'),
                    '接口账号' => (string) config('notifications.sms.ap_id'),
                    '签名编码' => (string) config('notifications.sms.sign'),
                ],
                'portal' => [
                    'POA 地址' => (string) config('notifications.portal.base_url'),
                    '门户应用 ID' => (string) config('notifications.portal.app_id'),
                    '令牌来源' => filled(config('notifications.portal.poa_token')) ? '手动令牌' : 'OAuth 客户端凭据',
                ],
                'portal_message' => [
                    'POA 地址' => (string) config('notifications.portal.base_url'),
                    '门户应用 ID' => (string) config('notifications.portal.app_id'),
                    '消息类型编码' => (string) config('notifications.portal.message_type_code'),
                    '发送方式' => 'WEBSITE',
                ],
            ],
            'newKey' => $request->session()->pull('new_client_key'),
        ]);
    }

    public function show(string $notification): Response
    {
        $record = NotificationRequest::with(['clientSystem', 'deliveries.attempts'])->findOrFail($notification);

        return Inertia::render('notifications/Show', ['record' => $record]);
    }

    public function createClient(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:client_systems,name']]);
        $key = Str::random(64);
        ClientSystem::create(['name' => $data['name'], 'key_hash' => hash('sha256', $key)]);

        return back()->with('new_client_key', $key);
    }

    public function rotateClient(ClientSystem $client): RedirectResponse
    {
        $key = Str::random(64);
        $client->update(['key_hash' => hash('sha256', $key)]);

        return back()->with('new_client_key', $key);
    }

    public function toggleClient(ClientSystem $client): RedirectResponse
    {
        $client->update(['active' => ! $client->active]);

        return back();
    }
}
