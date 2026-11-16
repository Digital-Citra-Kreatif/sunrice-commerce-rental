<?php

declare(strict_types=1);

use Sunrice\CommerceRental\Models\Unit;

beforeEach(function () {
    $this->variant = rentalStore(jakartaUnits: 1);
});

function unitsCsv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'units').'.csv';
    file_put_contents($path, $content);

    return $path;
}

it('creates new units and updates existing ones without moving them', function () {
    $file = unitsCsv("\u{FEFF}code,sku,branch,condition,active,note\nJKT-GM-0001,GM-S,BDG,repair,1,Jahitan lepas\nBDG-GM-0001,GM-S,bdg,,,\nBDG-GM-0002,GM-S,BDG,good,no,\n");

    $this->artisan('rental:import-units', ['file' => $file])->assertSuccessful();

    expect(Unit::query()->count())->toBe(3)
        ->and(Unit::query()->where('code', 'JKT-GM-0001')->first()->only(['branch_id', 'condition', 'note']))
        ->toBe(['branch_id' => branch('JKT')->id, 'condition' => 'repair', 'note' => 'Jahitan lepas'])
        ->and(Unit::query()->where('code', 'BDG-GM-0001')->value('branch_id'))->toBe(branch('BDG')->id)
        ->and(Unit::query()->where('code', 'BDG-GM-0002')->value('active'))->toBeFalse();
});

it('writes nothing when a row is wrong, and says which', function () {
    $file = unitsCsv("code,sku,branch\nBDG-GM-0001,GM-S,BDG\nBDG-GM-0002,NOPE,BDG\nBDG-GM-0001,GM-S,XXX\n");

    $this->artisan('rental:import-units', ['file' => $file])
        ->expectsOutputToContain('Line 3: no variant has SKU "NOPE"')
        ->expectsOutputToContain('Line 4: code BDG-GM-0001 is also on line 2')
        ->assertFailed();
    expect(Unit::query()->count())->toBe(1);

    $this->artisan('rental:import-units', ['file' => $file, '--partial' => true])->assertSuccessful();
    expect(Unit::query()->count())->toBe(2);
});

it('says what a dry run would do', function () {
    $file = unitsCsv("code,sku,branch\nJKT-GM-0001,GM-S,JKT\nJKT-GM-0002,GM-S,JKT\n");

    $this->artisan('rental:import-units', ['file' => $file, '--dry-run' => true])->expectsOutputToContain('Would create 1, would update 1')->assertSuccessful();
    expect(Unit::query()->count())->toBe(1);
});

it('refuses a file without the needed columns', function () {
    $this->artisan('rental:import-units', ['file' => unitsCsv("code,branch\nA,JKT\n")])->expectsOutputToContain('no "sku" column')->assertFailed();
});
