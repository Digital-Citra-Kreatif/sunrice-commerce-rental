<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Illuminate\Support\Str;

/**
 * What rental locations are called: config commerce-rental.location_term
 * (a word, or one per language), else "location" in the language's words.
 * Strings take it as :location (or :Location, capitalised).
 */
final class Wording
{
    /** The word in the current language (or $locale's), lowercase: "location", "cabang". */
    public static function location(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $term = config('commerce-rental.location_term');
        if (is_array($term)) {
            $term = $term[$locale] ?? $term[config('app.fallback_locale', 'en')] ?? null;
        }

        return is_string($term) && trim($term) !== '' ? trim($term) : (string) __('commerce-rental::rental.term', [], $locale);
    }

    /** For the rental screens (in English): "Location", "Branch". */
    public static function label(): string
    {
        return Str::ucfirst(self::location('en'));
    }

    /** "Locations", "Branches". */
    public static function pluralLabel(): string
    {
        return Str::plural(self::label());
    }

    /**
     * A rental string with the word filled in.
     *
     * @param  array<string, mixed>  $replace
     */
    public static function trans(string $key, array $replace = []): string
    {
        return (string) __($key, ['location' => self::location(), ...$replace]);
    }

    /** @param  array<string, mixed>  $replace */
    public static function choice(string $key, int $count, array $replace = []): string
    {
        return trans_choice($key, $count, ['location' => self::location(), ...$replace]);
    }
}
