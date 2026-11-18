<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('appointment_status_events', 'event')) {
            Schema::table('appointment_status_events', function (Blueprint $table): void {
                $table->string('event')->default('status');
                $table->json('details')->nullable();
            });
        }
        if (! DB::table('service_categories')->exists()) {
            foreach (['Nails' => 'الأظافر', 'Makeup' => 'المكياج', 'Lash Extensions' => 'وصلات الرموش', 'Hair Services' => 'خدمات الشعر'] as $en => $ar) {
                DB::table('service_categories')->insert(['name' => json_encode(compact('en', 'ar')), 'published' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointment_status_events', fn (Blueprint $table) => $table->dropColumn(['event', 'details']));
    }
};
