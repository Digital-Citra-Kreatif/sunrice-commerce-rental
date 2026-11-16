<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Actions;

use Illuminate\Support\Facades\DB;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\CommerceRental\Admin\UnitResource;
use Sunrice\CommerceRental\Models\Branch;
use Sunrice\CommerceRental\Models\Unit;

/**
 * Units from a CSV with a header row: code, sku, branch (its code), and
 * optionally condition (good, repair, retired), active (1/0, yes/no) and
 * note. A code that exists is updated, but its branch is never changed here
 * (moves go through Move to branch, which keeps the history); a new code is
 * created. Nothing is written when any row is wrong, unless $partial.
 */
class ImportUnits
{
    /**
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function handle(string $path, bool $dryRun = false, bool $partial = false): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ['created' => 0, 'updated' => 0, 'errors' => ["Can't read {$path}."]];
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\u{FEFF}")), (array) fgetcsv($handle, escape: ''));
        foreach (['code', 'sku', 'branch'] as $required) {
            if (! in_array($required, $header, true)) {
                fclose($handle);

                return ['created' => 0, 'updated' => 0, 'errors' => ["The header has no \"{$required}\" column."]];
            }
        }
        $branches = Branch::query()->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper((string) $code) => (int) $id])->all();
        $rows = [];
        $errors = [];
        $seen = [];
        $line = 1;
        while (($cells = fgetcsv($handle, escape: '')) !== false) {
            $line++;
            if ($cells === [null] || array_filter($cells, fn ($c) => trim((string) $c) !== '') === []) {
                continue;
            }
            $row = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
            $code = trim((string) $row['code']);
            $sku = trim((string) $row['sku']);
            $branch = $branches[strtoupper(trim((string) $row['branch']))] ?? null;
            $condition = strtolower(trim((string) ($row['condition'] ?? ''))) ?: 'good';
            $variantId = $sku !== '' ? Variant::query()->where('sku', $sku)->value('id') : null;
            $problem = match (true) {
                $code === '' || mb_strlen($code) > 40 => 'the code is empty or longer than 40 characters',
                isset($seen[$code]) => "code {$code} is also on line {$seen[$code]}",
                $variantId === null => "no variant has SKU \"{$sku}\"",
                $branch === null => 'no branch has code "'.trim((string) $row['branch']).'"',
                ! array_key_exists($condition, UnitResource::CONDITIONS) => "the condition \"{$condition}\" isn't good, repair or retired",
                default => null,
            };
            $seen[$code] ??= $line;
            if ($problem !== null) {
                $errors[] = "Line {$line}: {$problem}.";

                continue;
            }
            $active = strtolower(trim((string) ($row['active'] ?? '')));
            $rows[] = ['code' => $code, 'variant_id' => (int) $variantId, 'branch_id' => $branch, 'condition' => $condition,
                'active' => $active === '' || in_array($active, ['1', 'yes', 'ya', 'true', 'y'], true), 'note' => trim((string) ($row['note'] ?? '')) ?: null];
        }
        fclose($handle);

        if ($dryRun || ($errors !== [] && ! $partial)) {
            $existing = Unit::query()->whereIn('code', array_column($rows, 'code'))->count();

            return ['created' => $dryRun ? count($rows) - $existing : 0, 'updated' => $dryRun ? $existing : 0, 'errors' => $errors];
        }

        $created = 0;
        $updated = 0;
        DB::transaction(function () use ($rows, &$created, &$updated): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                $existing = Unit::query()->whereIn('code', array_column($chunk, 'code'))->get()->keyBy('code');
                foreach ($chunk as $row) {
                    $unit = $existing[$row['code']] ?? null;
                    if ($unit === null) {
                        Unit::query()->create($row);
                        $created++;
                    } else {
                        $unit->fill(array_diff_key($row, ['branch_id' => true, 'code' => true]))->save();
                        $updated++;
                    }
                }
            }
        });

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }
}
