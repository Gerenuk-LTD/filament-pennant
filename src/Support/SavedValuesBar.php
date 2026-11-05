<?php

namespace Gerenuk\FilamentPennant\Support;

use Illuminate\Support\HtmlString;

/**
 * Uses the panel's colour variables, so it follows the app's theme and dark mode.
 */
class SavedValuesBar
{
    public static function render(int $on, int $off): HtmlString
    {
        $total = $on + $off;

        if ($total === 0) {
            return new HtmlString('<span style="color: var(--gray-500)">' . e(static::translate('none')) . '</span>');
        }

        $share = round($on / $total * 100, 1);

        $summary = e(static::translate('on', $on)) . ' &middot; ' . e(static::translate('off', $off));

        return new HtmlString(<<<HTML
            <div style="display: grid; gap: 0.375rem; min-width: 11rem">
                <div aria-hidden="true" style="height: 0.375rem; overflow: hidden; border-radius: 9999px; background: var(--gray-300)">
                    <div style="width: {$share}%; height: 100%; background: var(--success-500)"></div>
                </div>
                <div style="font-size: 0.75rem; line-height: 1rem">{$summary}</div>
            </div>
            HTML);
    }

    protected static function translate(string $key, int $count = 0): string
    {
        return __("filament-pennant::features.overview.{$key}", ['count' => number_format($count)]);
    }
}
