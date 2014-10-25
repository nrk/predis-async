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

use Predis\Configuration\Options as BaseOptions;
use Predis\Configuration\PrefixOption;
use Predis\Configuration\ProfileOption;

/**
 * Manages Predis options with filtering, conversion and lazy initialization of
 * values using a mini-DI container approach.
 *
 * @property-read mixed eventloop Event loop instance.
 * @property-read bool  phpiredis Use phpiredis (only when available).
 * @property-read mixed prefix    Key prefixing strategy using the given prefix.
 * @property-read mixed profile   Server profile.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class Options extends BaseOptions
{
    /**
     * {@inheritdoc}
     */
    protected function getHandlers()
    {
        return array(
            'profile'   => new ProfileOption(),
            'prefix'    => new PrefixOption(),
            'eventloop' => new EventLoopOption(),
            'phpiredis' => new PhpiredisOption(),
        );
    }
}
