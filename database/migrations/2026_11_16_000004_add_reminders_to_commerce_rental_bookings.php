<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When the customer was reminded to pick the unit up and to bring it back (each once). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_rental_bookings', function (Blueprint $table) {
            $table->timestamp('pickup_reminded_at')->nullable()->after('return_note');
            $table->timestamp('return_reminded_at')->nullable()->after('pickup_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('commerce_rental_bookings', function (Blueprint $table) {
            $table->dropColumn(['pickup_reminded_at', 'return_reminded_at']);
        });
    }
};
