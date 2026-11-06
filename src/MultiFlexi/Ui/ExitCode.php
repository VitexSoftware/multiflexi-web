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
 * Description of ExitCode.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class ExitCode extends \Ease\TWB4\Badge
{
    /**
     * @param null|int    $exitcode    Exit code value (null = pending)
     * @param array       $properties  HTML tag properties
     * @param null|string $description Localized meaning of the exit code (from app_exit_codes);
     *                                 shown as tooltip when set
     */
    public function __construct($exitcode, $properties = [], ?string $description = null)
    {
        if (!isset($properties['title'])) {
            if (null === $exitcode) {
                $properties['title'] = _('Pending');
            } elseif (null !== $description && '' !== $description) {
                $properties['title'] = $description;
            } else {
                $properties['title'] = self::status($exitcode);
            }
        }

        parent::__construct(self::status($exitcode), '&nbsp'.(null === $exitcode ? '⏳' : $exitcode).'&nbsp', $properties);
    }

    /**
     * Exit Code.
     *
     * @param int $exitcode
     *
     * @return string bootstrap color
     */
    public static function status($exitcode)
    {
        switch ($exitcode) {
            case -1:
                $type = 'secondary';

                break;
            case 0:
                if (null === $exitcode) {
                    $type = 'info';
                } else {
                    $type = 'success';
                }

                break;
            case 127:
                $type = 'warning';

                break;

            default:
                $type = 'danger';

                break;
        }

        return $type;
    }
}
