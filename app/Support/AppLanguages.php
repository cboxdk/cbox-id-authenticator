<?php

declare(strict_types=1);

namespace App\Support;

final class AppLanguages
{
    /** Cbox ID's enabled locales, with names shown in their own language. */
    public const NAMES = [
        'en' => 'English', 'da' => 'Dansk', 'de' => 'Deutsch', 'sv' => 'Svenska',
        'nb' => 'Norsk bokmål', 'fr' => 'Français', 'fi' => 'Suomi',
        'es' => 'Español', 'it' => 'Italiano', 'nl' => 'Nederlands',
    ];

    public static function resolve(mixed $locale): string
    {
        if (! is_string($locale)) {
            return 'en';
        }
        $language = strtolower(explode('-', str_replace('_', '-', $locale))[0]);
        $language = $language === 'no' ? 'nb' : $language;

        return array_key_exists($language, self::NAMES) ? $language : 'en';
    }
}
