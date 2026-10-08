<?php

namespace Tests\Feature;

use App\Jobs\ChangePortalTask;
use App\Jobs\SendNotification;
use App\Models\ClientSystem;
use App\Models\NotificationDelivery;
use App\Models\NotificationRequest;
use App\Models\User;
use App\Services\Channels\SmsDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationPlatformTest extends TestCase
{
    private function client(): string
    {
        $key = 'test-client-secret';
        ClientSystem::create(['name' => '业务系统', 'key_hash' => hash('sha256', $key)]);

        return $key;
    }

    public function test_email_integration_document_is_public_and_uses_the_current_api_url(): void
    {
        $this->get('/docs/email')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('docs/Email')
                ->where('apiUrl', url('/api/v1/notifications')));
    }

    public function test_sms_integration_document_is_public_and_uses_the_current_api_url(): void
    {
        $this->get('/docs/sms')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('docs/Sms')
                ->where('apiUrl', url('/api/v1/notifications')));
    }

    public function test_email_submission_is_idempotent_and_dispatches_to_every_recipient(): void
    {
        Queue::fake();
        Mail::fake();
        $key = $this->client();
        $body = [
            'request_id' => 'request-1', 'channels' => ['email'], 'title' => '测试提醒', 'content' => '请查看业务事项',
            'recipients' => [
                ['id' => 'a', 'email' => 'a@example.com'],
                ['id' => 'b', 'email' => 'b@example.com'],
            ],
        ];

        $first = $this->withToken($key)->postJson('/api/v1/notifications', $body)->assertAccepted();
        $id = $first->json('id');
        $this->withToken($key)->postJson('/api/v1/notifications', $body)->assertOk()->assertJsonPath('id', $id);
        $this->assertSame(1, NotificationRequest::count());
        $this->assertSame(2, NotificationDelivery::count());
        Queue::assertPushed(SendNotification::class, 1);

        app()->call([new SendNotification($id), 'handle']);
        $this->withToken($key)->getJson('/api/v1/notifications/'.$id)->assertOk()->assertJsonPath('status', 'accepted');
        $this->assertSame(2, NotificationDelivery::where('status', 'accepted')->count());
    }

    public function test_client_isolation_and_validation(): void
    {
        Queue::fake();
        $key = $this->client();
        $body = ['request_id' => 'x', 'channels' => ['email'], 'title' => '提醒', 'content' => '内容',
            'recipients' => [['id' => 'a', 'email' => 'a@example.com']]];
        $this->postJson('/api/v1/notifications', $body)->assertUnauthorized();
        $this->withToken($key)->postJson('/api/v1/notifications', array_replace($body, ['recipients' => [['id' => 'a']]]))->assertUnprocessable();
        $id = $this->withToken($key)->postJson('/api/v1/notifications', $body)->assertAccepted()->json('id');
        ClientSystem::create(['name' => '其他系统', 'key_hash' => hash('sha256', 'another-key')]);
        $this->withToken('another-key')->getJson('/api/v1/notifications/'.$id)->assertNotFound();
    }

    public function test_sms_payload_uses_base64_and_mac_without_secret_in_body(): void
    {
        Queue::fake();
        $key = $this->client();
        config()->set('notifications.sms', [
            'url' => 'https://sms.example.test/send', 'ec_name' => '学校', 'ap_id' => 'api',
            'secret_key' => 'private-secret', 'sign' => 'signature', 'add_serial' => '',
        ]);
        Http::fake(['sms.example.test/*' => Http::response(['rspcod' => 'success', 'success' => true, 'msgGroup' => 'batch-1'])]);
        $id = $this->withToken($key)->postJson('/api/v1/notifications', [
            'request_id' => 'sms-1', 'channels' => ['sms'], 'title' => '提醒', 'content' => '测试短信',
            'recipients' => [['id' => 'a', 'mobile' => '13800138000'], ['id' => 'b', 'mobile' => '13800138001']],
        ])->assertAccepted()->json('id');

        app()->call([new SendNotification($id), 'handle']);
        Http::assertSent(function ($request) {
            $payload = json_decode(base64_decode($request->body()), true);

            return $payload['mobiles'] === '13800138000,13800138001'
                && ! isset($payload['secretKey'])
                && $payload['mac'] === md5('学校'.'api'.'private-secret'.$payload['mobiles'].'测试短信'.'signature');
        });
        $this->assertSame(2, NotificationDelivery::where('provider_ref', 'batch-1')->count());
    }

    public function test_large_sms_batch_is_split_at_provider_limit(): void
    {
        config()->set('notifications.sms', [
            'url' => 'https://sms.example.test/send', 'ec_name' => '学校', 'ap_id' => 'api',
            'secret_key' => 'private-secret', 'sign' => 'signature', 'add_serial' => '',
        ]);
        Http::fake(['sms.example.test/*' => Http::response(['rspcod' => 'success', 'msgGroup' => 'batch'])]);
        $deliveries = collect(range(1, 5001))->map(function ($number) {
            $delivery = new NotificationDelivery(['address' => sprintf('138%08d', $number)]);
            $delivery->id = (string) $number;

            return $delivery;
        });
        $notification = new NotificationRequest(['title' => '提醒', 'content' => '测试短信']);
        $results = app(SmsDriver::class)->send($deliveries, $notification);

        Http::assertSentCount(2);
        $this->assertCount(5001, $results);
    }

    public function test_email_queue_jobs_are_bounded_in_size(): void
    {
        Queue::fake();
        $key = $this->client();
        $recipients = collect(range(1, 11))->map(fn ($number) => [
            'id' => 'person-'.$number, 'email' => 'person'.$number.'@example.com',
        ])->all();
        $this->withToken($key)->postJson('/api/v1/notifications', [
            'request_id' => 'email-batch', 'channels' => ['email'], 'title' => '提醒', 'content' => '内容',
            'recipients' => $recipients,
        ])->assertAccepted();

        Queue::assertPushed(SendNotification::class, 4);
    }

    public function test_portal_create_and_complete_use_task_mapping(): void
    {
        Queue::fake();
        $key = $this->client();
        config()->set('notifications.portal', ['base_url' => 'https://portal.example.test', 'poa_token' => 'test-token', 'app_id' => 'app-1']);
        Http::fake(['portal.example.test/*' => Http::response(['code' => 0, 'data' => ['errorIds' => []]])]);
        $id = $this->withToken($key)->postJson('/api/v1/notifications', [
            'request_id' => 'portal-1', 'channels' => ['portal'], 'title' => '审核事项', 'content' => '请办理',
            'portal' => ['node_id' => 'node-1', 'node_name' => '审核', 'trans_type_code' => 'type-1', 'trans_type_name' => '提醒'],
            'recipients' => [['id' => 'a', 'portal_account' => 'teacher001', 'business_task_id' => 'business-1']],
        ])->assertAccepted()->json('id');
        app()->call([new SendNotification($id), 'handle']);
        $delivery = NotificationDelivery::firstOrFail();
        $this->assertSame('accepted', $delivery->fresh()->status);
        $this->assertNotNull($delivery->portal_task_id);
        Http::assertSent(function ($request) use ($delivery) {
            $body = json_decode($request->body(), true);

            return str_ends_with($request->url(), '/addTransaction')
                && $body[0]['taskId'] === $delivery->portal_task_id
                && $body[0]['handlers'] === 'teacher001'
                && $request->hasHeader('Authorization', 'test-token');
        });

        $this->withToken($key)->postJson('/api/v1/portal-tasks/business-1/complete', ['final' => true])->assertAccepted();
        Queue::assertPushed(ChangePortalTask::class);
        app()->call([new ChangePortalTask($delivery->id, 'complete', ['final' => true]), 'handle']);
        $this->assertSame('completed', $delivery->fresh()->status);
    }

    public function test_one_channel_failure_does_not_hide_other_channel_success(): void
    {
        Queue::fake();
        Mail::fake();
        $key = $this->client();
        $id = $this->withToken($key)->postJson('/api/v1/notifications', [
            'request_id' => 'mixed-1', 'channels' => ['email', 'sms'], 'title' => '提醒', 'content' => '内容',
            'recipients' => [['id' => 'a', 'email' => 'a@example.com', 'mobile' => '13800138000']],
        ])->assertAccepted()->json('id');

        app()->call([new SendNotification($id), 'handle']);
        $this->withToken($key)->getJson('/api/v1/notifications/'.$id)->assertJsonPath('status', 'partial');
        $this->assertSame('accepted', NotificationDelivery::where('channel', 'email')->firstOrFail()->status);
        $this->assertSame('failed', NotificationDelivery::where('channel', 'sms')->firstOrFail()->status);
    }

    public function test_portal_withdrawal_is_idempotent_and_conflicts_with_completion(): void
    {
        Queue::fake();
        $key = $this->client();
        config()->set('notifications.portal', ['base_url' => 'https://portal.example.test', 'poa_token' => 'test-token', 'app_id' => 'app-1']);
        Http::fake(['portal.example.test/*' => Http::response(['code' => 0, 'data' => ['errorIds' => []]])]);
        $id = $this->withToken($key)->postJson('/api/v1/notifications', [
            'request_id' => 'portal-2', 'channels' => ['portal'], 'title' => '审核事项', 'content' => '请办理',
            'portal' => ['node_id' => 'node-1', 'node_name' => '审核', 'trans_type_code' => 'type-1', 'trans_type_name' => '提醒'],
            'recipients' => [['id' => 'a', 'portal_account' => 'teacher001', 'business_task_id' => 'business-2']],
        ])->assertAccepted()->json('id');
        app()->call([new SendNotification($id), 'handle']);
        $delivery = NotificationDelivery::firstOrFail();

        $this->withToken($key)->postJson('/api/v1/portal-tasks/business-2/withdraw', ['creator' => 'student001'])->assertAccepted();
        $this->withToken($key)->postJson('/api/v1/portal-tasks/business-2/withdraw', ['creator' => 'student001'])->assertAccepted();
        Queue::assertPushed(ChangePortalTask::class, 1);
        $this->withToken($key)->postJson('/api/v1/portal-tasks/business-2/complete', ['final' => true])->assertConflict();
        app()->call([new ChangePortalTask($delivery->id, 'withdraw', ['creator' => 'student001']), 'handle']);
        $this->assertSame('withdrawn', $delivery->fresh()->status);
    }

    public function test_portal_can_obtain_and_cache_oauth_client_token(): void
    {
        Queue::fake();
        $key = $this->client();
        config()->set('notifications.portal', [
            'base_url' => 'https://portal.example.test', 'poa_token' => null,
            'token_url' => null, 'client_id' => 'client-id', 'client_secret' => 'client-secret',
            'scope' => 'ttc:v1:writeTransaction', 'app_id' => 'app-1',
        ]);
        Http::fake([
            'portal.example.test/oauth2/token' => Http::response(['access_token' => 'short-lived-token', 'expires_in' => 3600]),
            'portal.example.test/apis/*' => Http::response(['code' => 0, 'data' => ['errorIds' => []]]),
        ]);
        $id = $this->withToken($key)->postJson('/api/v1/notifications', [
            'request_id' => 'portal-oauth', 'channels' => ['portal'], 'title' => '审核事项', 'content' => '请办理',
            'portal' => ['node_id' => 'node-1', 'node_name' => '审核', 'trans_type_code' => 'type-1', 'trans_type_name' => '提醒'],
            'recipients' => [
                ['id' => 'a', 'portal_account' => 'teacher001', 'business_task_id' => 'oauth-1'],
                ['id' => 'b', 'portal_account' => 'teacher002', 'business_task_id' => 'oauth-2'],
            ],
        ])->assertAccepted()->json('id');
        app()->call([new SendNotification($id), 'handle']);
        Http::assertSentCount(3);
        $this->assertSame(2, NotificationDelivery::where('status', 'accepted')->count());
    }

    public function test_console_requires_admin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/notifications')->assertForbidden();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user)->get('/notifications')->assertOk()->assertInertia(fn (Assert $page) => $page->component('notifications/Index'));
    }

    public function test_admin_can_create_client_with_a_one_time_key(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $response = $this->actingAs($user)->post('/notifications/clients', ['name' => '教务系统'])->assertRedirect();
        $key = $response->baseResponse->getSession()->get('new_client_key');
        $this->assertIsString($key);
        $this->assertSame(hash('sha256', $key), ClientSystem::firstOrFail()->key_hash);
        $this->assertNotSame($key, ClientSystem::firstOrFail()->key_hash);
    }

    public function test_channel_entrance_filters_console_records(): void
    {
        $user = User::factory()->unverified()->create();
        $user->forceFill(['is_admin' => true])->save();
        $client = ClientSystem::create(['name' => '业务系统', 'key_hash' => hash('sha256', 'filter-test-key')]);

        foreach (['email', 'sms'] as $channel) {
            $record = NotificationRequest::create([
                'client_system_id' => $client->id,
                'request_id' => 'filter-'.$channel,
                'title' => $channel.' reminder',
                'content' => 'test',
                'channels' => [$channel],
            ]);
            NotificationDelivery::create([
                'notification_request_id' => $record->id,
                'client_system_id' => $client->id,
                'recipient_id' => 'recipient-1',
                'channel' => $channel,
                'address' => $channel === 'email' ? 'test@example.com' : '13800000000',
            ]);
        }

        $this->actingAs($user)->get('/notifications?channel=email')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('notifications/Index')
                ->where('activeChannel', 'email')
                ->where('records.total', 1)
                ->where('records.data.0.request_id', 'filter-email'));
    }

    public function test_email_records_can_be_searched_and_filtered_by_client_system(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $firstClient = ClientSystem::create(['name' => '教务系统', 'key_hash' => hash('sha256', 'teaching-key')]);
        $secondClient = ClientSystem::create(['name' => '人事系统', 'key_hash' => hash('sha256', 'hr-key')]);

        foreach ([
            [$firstClient, 'jwc:leave:0001', '请假提醒', '20230001', 'teacher1@example.edu.cn'],
            [$secondClient, 'hr:hire:0002', '入职提醒', '20230002', 'teacher2@example.edu.cn'],
        ] as [$client, $requestId, $title, $recipientId, $address]) {
            $record = NotificationRequest::create([
                'client_system_id' => $client->id,
                'request_id' => $requestId,
                'title' => $title,
                'content' => '请处理',
                'channels' => ['email'],
            ]);
            NotificationDelivery::create([
                'notification_request_id' => $record->id,
                'client_system_id' => $client->id,
                'recipient_id' => $recipientId,
                'channel' => 'email',
                'address' => $address,
            ]);
        }

        $this->actingAs($user)->get('/notifications?channel=email&search=20230001&client='.$firstClient->id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('notifications/Index')
                ->where('filters.search', '20230001')
                ->where('filters.client', $firstClient->id)
                ->where('records.total', 1)
                ->where('records.data.0.request_id', 'jwc:leave:0001'));

        $this->actingAs($user)->get('/notifications?channel=email&search=teacher2@example.edu.cn&client='.$firstClient->id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('records.total', 0));

        $this->actingAs($user)->get('/notifications?channel=email&search=teacher2@example.edu.cn&client='.$secondClient->id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('records.total', 1)
                ->where('records.data.0.request_id', 'hr:hire:0002'));

        $this->actingAs($user)->get('/notifications?channel=email&search=请假')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('records.total', 1)
                ->where('records.data.0.request_id', 'jwc:leave:0001'));

        $this->actingAs($user)->get('/notifications?channel=email&search=hr:hire')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('records.total', 1)
                ->where('records.data.0.client_system.name', '人事系统'));
    }

    public function test_unverified_user_can_open_dashboard_and_admin_can_use_channel_entrances(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('canManageNotifications', false));

        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard')
                ->where('canManageNotifications', true)
                ->has('channelStatus.email')
                ->has('channelStatus.sms')
                ->has('channelStatus.portal'));
        $this->actingAs($user)->get('/notifications')->assertOk();
    }
}
