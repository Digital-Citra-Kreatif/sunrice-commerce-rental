<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rental branches become the store's locations (sunrice/commerce-multi-locations):
 * each branch is the location with its code (made, as a pickup point, when
 * there is none, keeping the branch's id when it is free), units and
 * transfers point at locations, and the branches table goes. Carts and
 * orders whose rental lines name a branch whose id changed are rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        $map = [];
        foreach (DB::table('commerce_rental_branches')->orderBy('id')->get() as $branch) {
            $existing = DB::table('commerce_locations')->where('code', $branch->code)->value('id');
            if ($existing !== null) {
                $map[(int) $branch->id] = (int) $existing;

                continue;
            }
            $row = [
                'code' => $branch->code, 'name' => $branch->name, 'phone' => $branch->phone ?? null, 'whatsapp' => $branch->whatsapp,
                'address' => $branch->address, 'postal_code' => $branch->postal_code ?? null,
                'latitude' => $branch->latitude ?? null, 'longitude' => $branch->longitude ?? null,
                'ships_from' => true, 'pickup' => true, 'active' => $branch->active, 'sort_order' => $branch->sort_order,
                'created_at' => $branch->created_at, 'updated_at' => now(),
            ];
            if (! DB::table('commerce_locations')->where('id', $branch->id)->exists()) {
                DB::table('commerce_locations')->insert(['id' => $branch->id, ...$row]);
                $map[(int) $branch->id] = (int) $branch->id;
            } else {
                $map[(int) $branch->id] = (int) DB::table('commerce_locations')->insertGetId($row);
            }
        }
        // PostgreSQL: the ids inserted by hand don't move the sequence.
        if (DB::getDriverName() === 'pgsql' && $map !== []) {
            DB::statement("SELECT setval(pg_get_serial_sequence('commerce_locations', 'id'), (SELECT MAX(id) FROM commerce_locations))");
        }

        Schema::table('commerce_rental_units', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('variant_id')->constrained('commerce_locations');
        });
        Schema::table('commerce_rental_transfers', function (Blueprint $table) {
            $table->foreignId('from_location_id')->nullable()->after('unit_id')->constrained('commerce_locations');
            $table->foreignId('to_location_id')->nullable()->after('from_location_id')->constrained('commerce_locations');
        });
        foreach ($map as $branch => $location) {
            DB::table('commerce_rental_units')->where('branch_id', $branch)->update(['location_id' => $location]);
            DB::table('commerce_rental_transfers')->where('from_branch_id', $branch)->update(['from_location_id' => $location]);
            DB::table('commerce_rental_transfers')->where('to_branch_id', $branch)->update(['to_location_id' => $location]);
        }

        Schema::table('commerce_rental_units', function (Blueprint $table) {
            $table->dropIndex(['variant_id', 'branch_id', 'active']);
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
            $table->index(['variant_id', 'location_id', 'active']);
        });
        Schema::table('commerce_rental_transfers', function (Blueprint $table) {
            $table->dropForeign(['from_branch_id']);
            $table->dropForeign(['to_branch_id']);
            $table->dropColumn(['from_branch_id', 'to_branch_id']);
        });
        Schema::dropIfExists('commerce_rental_branches');

        $this->rewriteLines($map);
    }

    public function down(): void
    {
        Schema::create('commerce_rental_branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('whatsapp', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        // Every location becomes a branch again (which were branches isn't kept).
        foreach (DB::table('commerce_locations')->orderBy('id')->get() as $location) {
            DB::table('commerce_rental_branches')->insert([
                'id' => $location->id, 'code' => $location->code, 'name' => $location->name, 'whatsapp' => $location->whatsapp, 'phone' => $location->phone,
                'address' => $location->address, 'postal_code' => $location->postal_code, 'latitude' => $location->latitude, 'longitude' => $location->longitude,
                'active' => $location->active, 'sort_order' => $location->sort_order, 'created_at' => $location->created_at, 'updated_at' => $location->updated_at,
            ]);
        }
        Schema::table('commerce_rental_units', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('variant_id')->constrained('commerce_rental_branches');
        });
        Schema::table('commerce_rental_transfers', function (Blueprint $table) {
            $table->foreignId('from_branch_id')->nullable()->after('unit_id')->constrained('commerce_rental_branches');
            $table->foreignId('to_branch_id')->nullable()->after('from_branch_id')->constrained('commerce_rental_branches');
        });
        DB::table('commerce_rental_units')->update(['branch_id' => DB::raw('location_id')]);
        DB::table('commerce_rental_transfers')->update(['from_branch_id' => DB::raw('from_location_id'), 'to_branch_id' => DB::raw('to_location_id')]);
        Schema::table('commerce_rental_units', function (Blueprint $table) {
            $table->dropIndex(['variant_id', 'location_id', 'active']);
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
            $table->index(['variant_id', 'branch_id', 'active']);
        });
        Schema::table('commerce_rental_transfers', function (Blueprint $table) {
            $table->dropForeign(['from_location_id']);
            $table->dropForeign(['to_location_id']);
            $table->dropColumn(['from_location_id', 'to_location_id']);
        });
    }

    /**
     * Rental lines keep their place as meta rental.branch: point the ones
     * whose branch got another id at their location (rental.location).
     *
     * @param  array<int, int>  $map
     */
    protected function rewriteLines(array $map): void
    {
        $changed = array_filter($map, fn (int $location, int $branch) => $location !== $branch, ARRAY_FILTER_USE_BOTH);
        if ($changed === []) {
            return;
        }
        foreach (['commerce_cart_items', 'commerce_order_items'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'meta')) {
                continue;
            }
            DB::table($table)->where('meta', 'like', '%"rental"%')->orderBy('id')->chunkById(500, function ($rows) use ($table, $changed): void {
                foreach ($rows as $row) {
                    $meta = json_decode((string) $row->meta, true);
                    $branch = (int) ($meta['rental']['branch'] ?? 0);
                    if (is_array($meta) && isset($changed[$branch])) {
                        unset($meta['rental']['branch']);
                        $meta['rental']['location'] = $changed[$branch];
                        DB::table($table)->where('id', $row->id)->update(['meta' => json_encode($meta)]);
                    }
                }
            });
        }
    }
};
