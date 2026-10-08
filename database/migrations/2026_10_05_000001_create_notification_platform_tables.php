<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));

        Schema::create('client_systems', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('key_hash', 64)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('client_system_id')->constrained()->cascadeOnDelete();
            $table->string('request_id');
            $table->string('title');
            $table->longText('content');
            $table->json('channels');
            $table->json('portal')->nullable();
            $table->string('status')->default('queued');
            $table->timestamps();
            $table->unique(['client_system_id', 'request_id']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('notification_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_system_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_id');
            $table->string('channel', 32);
            $table->string('address');
            $table->json('metadata')->nullable();
            $table->string('status')->default('queued');
            $table->string('provider_ref')->nullable();
            $table->string('business_task_id')->nullable();
            $table->string('portal_task_id')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['notification_request_id', 'recipient_id', 'channel'], 'delivery_recipient_channel_unique');
            $table->unique(['client_system_id', 'business_task_id'], 'delivery_business_task_unique');
            $table->index(['channel', 'status']);
        });

        Schema::create('delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('notification_delivery_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 24)->default('send');
            $table->string('status', 24);
            $table->string('provider_ref')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_attempts');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_requests');
        Schema::dropIfExists('client_systems');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
