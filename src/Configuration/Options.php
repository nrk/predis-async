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
 * Class that manages client options with filtering and conversion.
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
