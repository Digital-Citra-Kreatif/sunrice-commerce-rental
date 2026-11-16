<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_rental_branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('whatsapp', 30)->nullable();
            $table->text('address')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // One physical piece: a variant (a dress in one size) at a branch.
        Schema::create('commerce_rental_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variant_id')->index();
            $table->foreignId('branch_id')->constrained('commerce_rental_branches');
            $table->string('code', 40)->unique();
            $table->string('condition', 20)->default('good');
            $table->boolean('active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['variant_id', 'branch_id', 'active']);
        });

        // A unit taken for a date range: by an order, or blocked by staff. The unit is free again
        // on available_from (the day after the end plus the buffer days).
        Schema::create('commerce_rental_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('commerce_rental_units');
            $table->string('kind', 10)->default('order');
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('order_item_id')->nullable()->index();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('available_from');
            $table->unsignedSmallInteger('buffer_days')->default(0);
            $table->string('status', 12)->default('active');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->index(['unit_id', 'status', 'starts_on', 'available_from'], 'commerce_rental_bookings_overlap');
        });

        Schema::create('commerce_rental_buffers', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 10);
            $table->unsignedBigInteger('scope_id');
            $table->unsignedSmallInteger('days');
            $table->timestamps();
            $table->unique(['scope', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_rental_buffers');
        Schema::dropIfExists('commerce_rental_bookings');
        Schema::dropIfExists('commerce_rental_units');
        Schema::dropIfExists('commerce_rental_branches');
    }
};
