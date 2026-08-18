<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MultiFlexi\Ui;

/**
 * Single canonical way to render a bool-typed MultiFlexi\ConfigField as a
 * Toggle widget. Every form editing a 'bool' field must go through this
 * helper instead of constructing its own Ease\TWB4\Widgets\Toggle, so the
 * checked-state truthiness rule and the toggle's size/label attributes
 * (data-size/data-on/data-off) stay consistent app-wide.
 */
final class BoolFieldWidget
{
    /**
     * @param array<string, string> $extraAttrs merged over the defaults (e.g. ['disabled' => 'disabled'])
     */
    public static function toggle(string $name, ?string $currentValue, array $extraAttrs = []): \Ease\TWB4\Widgets\Toggle
    {
        $attrs = array_merge([
            'data-size' => 'small',
            'data-width' => '70px',
            'data-onstyle' => 'success',
            'data-offstyle' => 'secondary',
        ], $extraAttrs);

        return new \Ease\TWB4\Widgets\Toggle($name, \MultiFlexi\ConfigField::isTruthy($currentValue), 'true', $attrs);
    }
}
