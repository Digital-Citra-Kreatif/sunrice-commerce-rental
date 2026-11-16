<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When a rented unit went out and came back, and how it came back. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_rental_bookings', function (Blueprint $table) {
            $table->timestamp('picked_up_at')->nullable()->after('status');
            $table->timestamp('returned_at')->nullable()->after('picked_up_at');
            $table->string('return_condition', 20)->nullable()->after('returned_at');
            $table->string('return_note', 500)->nullable()->after('return_condition');
        });
    }

    public function down(): void
    {
        Schema::table('commerce_rental_bookings', function (Blueprint $table) {
            $table->dropColumn(['picked_up_at', 'returned_at', 'return_condition', 'return_note']);
        });
    }
};
