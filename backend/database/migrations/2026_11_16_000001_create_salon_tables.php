<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('role')->default('manager'));
        Schema::create('site_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('value')->nullable();
            $t->timestamps();
        });
        Schema::create('service_categories', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('published')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('staff', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->boolean('published')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained('service_categories');
            $t->json('name');
            $t->json('description');
            $t->unsignedBigInteger('price_minor')->nullable();
            $t->unsignedInteger('duration_minutes')->nullable();
            $t->unsignedInteger('buffer_before')->default(0);
            $t->unsignedInteger('buffer_after')->default(0);
            $t->boolean('published')->default(false);
            $t->boolean('bookable')->default(false);
            $t->string('image_url')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('service_staff', function (Blueprint $t) {
            $t->foreignId('service_id')->constrained();
            $t->foreignId('staff_id')->constrained('staff');
            $t->primary(['service_id', 'staff_id']);
        });
        Schema::create('working_hours', function (Blueprint $t) {
            $t->id();
            $t->foreignId('staff_id')->constrained('staff');
            $t->unsignedTinyInteger('weekday');
            $t->time('starts_at');
            $t->time('ends_at');
            $t->timestamps();
            $t->index(['staff_id', 'weekday']);
        });
        Schema::create('schedule_blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('staff_id')->nullable()->constrained('staff');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('kind')->default('block');
            $t->string('reason')->nullable();
            $t->timestamps();
            $t->index(['staff_id', 'starts_at', 'ends_at']);
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('email')->index();
            $t->string('phone', 30)->nullable();
            $t->boolean('marketing_consent')->default(false);
            $t->timestamps();
        });
        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 32)->unique();
            $t->foreignId('customer_id')->constrained();
            $t->foreignId('service_id')->constrained();
            $t->foreignId('staff_id')->constrained('staff');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->dateTime('occupied_start');
            $t->dateTime('occupied_end');
            $t->json('service_name');
            $t->unsignedBigInteger('price_minor')->nullable();
            $t->string('currency', 3);
            $t->unsignedInteger('duration_minutes');
            $t->json('policy_snapshot');
            $t->string('status')->default('confirmed');
            $t->string('locale', 2);
            $t->string('source')->default('website');
            $t->text('notes')->nullable();
            $t->text('internal_notes')->nullable();
            $t->timestamps();
            $t->index(['staff_id', 'occupied_start', 'occupied_end']);
            $t->index(['status', 'starts_at']);
        });
        Schema::create('appointment_status_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained();
            $t->string('status');
            $t->foreignId('user_id')->nullable()->constrained();
            $t->timestamps();
        });
        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->json('title');
            $t->json('description');
            $t->json('syllabus')->nullable();
            $t->boolean('published')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('course_offerings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained();
            $t->unsignedInteger('capacity');
            $t->unsignedBigInteger('price_minor')->nullable();
            $t->dateTime('registration_opens_at');
            $t->dateTime('registration_closes_at');
            $t->unsignedInteger('cancellation_hours')->default(24);
            $t->boolean('published')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('course_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('offering_id')->constrained('course_offerings');
            $t->foreignId('staff_id')->nullable()->constrained('staff');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->timestamps();
            $t->index(['staff_id', 'starts_at', 'ends_at']);
        });
        Schema::create('course_registrations', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 32)->unique();
            $t->foreignId('offering_id')->constrained('course_offerings');
            $t->foreignId('customer_id')->constrained();
            $t->json('course_title');
            $t->unsignedBigInteger('price_minor')->nullable();
            $t->string('currency', 3);
            $t->json('policy_snapshot');
            $t->string('status')->default('confirmed');
            $t->string('locale', 2);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['offering_id', 'status']);
        });
        Schema::create('guest_management_tokens', function (Blueprint $t) {
            $t->id();
            $t->string('token_hash', 64)->unique();
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->dateTime('expires_at');
            $t->dateTime('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['subject_type', 'subject_id']);
        });
        Schema::create('idempotency_records', function (Blueprint $t) {
            $t->id();
            $t->string('scope', 64);
            $t->string('key', 80);
            $t->string('fingerprint', 64);
            $t->text('response')->nullable();
            $t->timestamps();
            $t->unique(['scope', 'key']);
        });
        Schema::create('media_assets', function (Blueprint $t) {
            $t->id();
            $t->string('path');
            $t->string('mime');
            $t->unsignedBigInteger('size');
            $t->foreignId('user_id')->constrained();
            $t->timestamps();
        });
        Schema::create('gallery_items', function (Blueprint $t) {
            $t->id();
            $t->string('image_url');
            $t->json('alt');
            $t->json('caption')->nullable();
            $t->foreignId('category_id')->nullable()->constrained('service_categories');
            $t->boolean('published')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('content_blocks', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('value');
            $t->boolean('published')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('contact_messages', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('email');
            $t->text('message');
            $t->string('locale', 2);
            $t->string('delivery_status')->default('stored');
            $t->timestamps();
        });
        Schema::create('notification_outbox', function (Blueprint $t) {
            $t->id();
            $t->string('event_key')->unique();
            $t->string('recipient');
            $t->string('locale', 2);
            $t->string('subject');
            $t->text('body');
            $t->string('status')->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->dateTime('sent_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('changes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'notification_outbox', 'contact_messages', 'content_blocks', 'gallery_items', 'media_assets', 'idempotency_records', 'guest_management_tokens', 'course_registrations', 'course_sessions', 'course_offerings', 'courses', 'appointment_status_events', 'appointments', 'customers', 'schedule_blocks', 'working_hours', 'service_staff', 'services', 'staff', 'service_categories', 'site_settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
    }
};
