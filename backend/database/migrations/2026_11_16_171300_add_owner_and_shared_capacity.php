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
        Schema::create('platform_mutexes', function (Blueprint $table) {
            $table->string('name', 32)->primary();
        });
        DB::table('platform_mutexes')->insert([['name' => 'schedule'], ['name' => 'owner']]);
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('owner_slot')->nullable()->unique();
        });
        $owner = DB::table('users')->where('role', 'owner')->orderBy('id')->first();
        if ($owner) {
            DB::table('users')->where('id', $owner->id)->update(['owner_slot' => 1]);
        }
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->string('image_url')->nullable();
        });
        Schema::table('staff', function (Blueprint $table) {
            $table->boolean('is_salon_resource')->default(false);
        });
        $resource = DB::table('staff')->insertGetId([
            'name' => json_encode(['en' => 'Salon booking capacity', 'ar' => 'سعة حجز الصالون']),
            'published' => true, 'is_salon_resource' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (DB::table('services')->whereNull('deleted_at')->pluck('id') as $id) {
            DB::table('service_staff')->insertOrIgnore(['service_id' => $id, 'staff_id' => $resource]);
        }
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['status', 'occupied_start', 'occupied_end'], 'appointments_shared_occupancy');
        });
        foreach ([
            'salon_name' => 'Remi Touch Nail Spa', 'currency' => 'JOD', 'timezone' => 'Asia/Amman',
            'country' => 'JO',
            'booking_enabled' => false, 'require_approval' => false,
        ] as $key => $value) {
            DB::table('site_settings')->insertOrIgnore(['key' => $key, 'value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', fn (Blueprint $table) => $table->dropIndex('appointments_shared_occupancy'));
        Schema::table('staff', fn (Blueprint $table) => $table->dropColumn('is_salon_resource'));
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('image_url'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('owner_slot'));
        Schema::dropIfExists('platform_mutexes');
    }
};
