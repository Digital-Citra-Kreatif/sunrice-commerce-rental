<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Units moved between branches, with who moved them and why. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_rental_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('commerce_rental_units')->cascadeOnDelete();
            $table->foreignId('from_branch_id')->constrained('commerce_rental_branches');
            $table->foreignId('to_branch_id')->constrained('commerce_rental_branches');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['unit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_rental_transfers');
    }
};
