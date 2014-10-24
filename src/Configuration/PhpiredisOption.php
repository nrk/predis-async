<?php

/*
 * This file is part of the Predis\Async package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Async\Configuration;

use Predis\Configuration\OptionInterface;
use Predis\Configuration\OptionsInterface;

/**
 * Option class that handles the creation of the event loop.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class PhpiredisOption implements OptionInterface
{
    /**
     * {@inheritdoc}
     */
    public function filter(OptionsInterface $options, $value)
    {
        if (!$value) {
            return false;
        }

        if (!is_object($value) && $asbool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) {
            return $asbool;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefault(OptionsInterface $options)
    {
        return function_exists('phpiredis_reader_create');
    }
}
