<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Illuminate\Support\Str;
use Sunrice\Commerce\Settings\SettingsSection;
use Sunrice\Commerce\Settings\StoreSettings;

/**
 * Settings → Store → Rentals (store settings under `rental`), each with a
 * default from config/commerce-rental.php.
 */
class RentalSettings
{
    public const DEPOSIT_NONE = 'none';

    public const DEPOSIT_PERCENT = 'percent';

    public const DEPOSIT_FIXED = 'fixed';

    public function __construct(protected StoreSettings $settings) {}

    public function get(string $key): mixed
    {
        return $this->settings->get("rental.{$key}", config("commerce-rental.{$key}"));
    }

    public function int(string $key): int
    {
        return max(0, (int) $this->get($key));
    }

    /**
     * The rental packages customers can choose (Settings → Rentals → Packages), by key.
     *
     * @return array<string, array{key: string, name: string, days: int, fee: int}>
     */
    public function packages(): array
    {
        $out = [];
        foreach ((array) $this->get('packages') as $row) {
            if (! is_array($row) || trim((string) ($row['name'] ?? '')) === '' || (int) ($row['days'] ?? 0) < 1) {
                continue;
            }
            $name = is_array($row['name']) ? (string) ($row['name'][app()->getLocale()] ?? reset($row['name']) ?: '') : (string) $row['name'];
            $key = Str::slug((string) (is_array($row['name']) ? reset($row['name']) : $row['name'])) ?: 'package';
            $out[$key] = ['key' => $key, 'name' => $name, 'days' => (int) $row['days'], 'fee' => max(0, (int) ($row['fee'] ?? 0))];
        }

        return $out;
    }

    /** @return array{key: string, name: string, days: int, fee: int}|null */
    public function package(?string $key): ?array
    {
        return $key === null ? null : ($this->packages()[$key] ?? null);
    }

    public static function section(): SettingsSection
    {
        return new SettingsSection('rental', 'Rentals', fn () => self::fields(), feature: 'rental', permission: 'commerce.settings.checkout', position: 35);
    }

    /** @return array<int, array<string, mixed>> */
    protected static function fields(): array
    {
        $deposit = fn (string $mode) => ['field' => 'deposit_mode', 'operator' => 'equals', 'value' => $mode];

        return [
            ['handle' => 'buffer_days', 'section' => 'Bookings', 'width' => 50, 'type' => 'number', 'label' => 'Rest days after a return',
                'instructions' => 'For cleaning and checks, before the unit can go out again. Categories and products can set their own.', 'config' => ['min' => 0, 'max' => 30], 'validation' => ['integer']],
            ['handle' => 'min_days', 'section' => 'Bookings', 'width' => 50, 'type' => 'number', 'label' => 'Shortest rental (days)', 'config' => ['min' => 1, 'max' => 60], 'validation' => ['integer', 'min:1']],
            ['handle' => 'max_days', 'section' => 'Bookings', 'width' => 50, 'type' => 'number', 'label' => 'Longest rental (days)', 'config' => ['min' => 1, 'max' => 365], 'validation' => ['integer', 'min:1']],
            ['handle' => 'min_days_ahead', 'section' => 'Bookings', 'width' => 50, 'type' => 'number', 'label' => 'Book at least (days ahead)', 'instructions' => '0: the same day.', 'config' => ['min' => 0, 'max' => 60], 'validation' => ['integer']],
            ['handle' => 'max_days_ahead', 'section' => 'Bookings', 'width' => 50, 'type' => 'number', 'label' => 'Book at most (days ahead)', 'instructions' => '0: no limit.', 'config' => ['min' => 0, 'max' => 3650], 'validation' => ['integer', 'min:0']],

            ['handle' => 'pricing', 'section' => 'Pricing', 'type' => 'select', 'label' => 'A product\'s price is', 'config' => ['options' => [
                ['value' => 'package', 'label' => 'For the first days (below); each day more costs extra'],
                ['value' => 'daily', 'label' => 'Per day (price × days)'],
            ]]],
            ['handle' => 'base_days', 'section' => 'Pricing', 'width' => 50, 'type' => 'number', 'label' => 'Days the price covers',
                'condition' => ['field' => 'pricing', 'operator' => 'equals', 'value' => 'package'], 'config' => ['min' => 1, 'max' => 60], 'validation' => ['integer', 'min:1']],
            ['handle' => 'extra_day_fee', 'section' => 'Pricing', 'width' => 50, 'type' => 'commerce_money', 'label' => 'Each day more',
                'condition' => ['field' => 'pricing', 'operator' => 'equals', 'value' => 'package']],
            ['handle' => 'packages', 'section' => 'Pricing', 'type' => 'repeater', 'label' => 'Packages',
                'instructions' => 'Fixed-length rentals customers can choose instead, with a fee on top of the price (Out of town: 7 days, +50.000).',
                'config' => ['max' => 10, 'fields' => [
                    ['handle' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'config' => ['max' => 60]],
                    ['handle' => 'days', 'width' => 50, 'type' => 'number', 'label' => 'Days', 'required' => true, 'config' => ['min' => 1, 'max' => 60], 'validation' => ['integer', 'min:1']],
                    ['handle' => 'fee', 'width' => 50, 'type' => 'commerce_money', 'label' => 'Fee on top of the price'],
                ]]],

            ['handle' => 'pickup_reminder_days', 'section' => 'Reminders', 'width' => 50, 'type' => 'number', 'label' => 'Remind of the pickup (days before)',
                'instructions' => 'By email. 0: no reminder.', 'config' => ['min' => 0, 'max' => 14], 'validation' => ['integer']],
            ['handle' => 'return_reminder_days', 'section' => 'Reminders', 'width' => 50, 'type' => 'number', 'label' => 'Remind of the return (days before)',
                'instructions' => 'By email. 0: no reminder.', 'config' => ['min' => 0, 'max' => 14], 'validation' => ['integer']],

            ['handle' => 'deposit_mode', 'section' => 'Deposit', 'type' => 'select', 'label' => 'Deposit at checkout', 'config' => ['options' => [
                ['value' => self::DEPOSIT_NONE, 'label' => 'None: pay in full'],
                ['value' => self::DEPOSIT_PERCENT, 'label' => 'A percentage of the order'],
                ['value' => self::DEPOSIT_FIXED, 'label' => 'A fixed amount per dress'],
            ]]],
            ['handle' => 'deposit_percent', 'section' => 'Deposit', 'width' => 50, 'type' => 'number', 'label' => 'Deposit (%)', 'condition' => $deposit(self::DEPOSIT_PERCENT),
                'config' => ['min' => 1, 'max' => 99], 'validation' => ['integer', 'min:1', 'max:99']],
            ['handle' => 'deposit_fixed', 'section' => 'Deposit', 'width' => 50, 'type' => 'commerce_money', 'label' => 'Deposit per dress', 'condition' => $deposit(self::DEPOSIT_FIXED)],
            ['handle' => 'balance_days_before', 'section' => 'Deposit', 'width' => 50, 'type' => 'number', 'label' => 'Balance due (days before pickup)',
                'instructions' => 'Closer to the pickup day than this, the order is paid in full. The reminder is in Checkout settings.', 'config' => ['min' => 0, 'max' => 60], 'validation' => ['integer']],
        ];
    }
}
