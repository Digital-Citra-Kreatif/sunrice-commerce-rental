<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

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
            ['handle' => 'max_days_ahead', 'section' => 'Bookings', 'width' => 50, 'type' => 'number', 'label' => 'Book at most (days ahead)', 'config' => ['min' => 1, 'max' => 730], 'validation' => ['integer', 'min:1']],

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
