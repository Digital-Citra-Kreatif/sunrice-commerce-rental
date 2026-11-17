<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Where couriers pick a branch's deliveries up: postal code, map point and a phone for the driver. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_rental_branches', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('whatsapp');
            $table->string('postal_code', 10)->nullable()->after('address');
            $table->decimal('latitude', 10, 7)->nullable()->after('postal_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('commerce_rental_branches', function (Blueprint $table) {
            $table->dropColumn(['phone', 'postal_code', 'latitude', 'longitude']);
        });
    }
};
