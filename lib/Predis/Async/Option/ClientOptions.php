<?php

/*
 * This file is part of the Predis\Async package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Async\Option;

use Predis\Option\ClientOptions as BaseClientOptions;
use Predis\Option\ClientPrefix;
use Predis\Option\ClientProfile;

/**
 * Class that manages client options with filtering and conversion.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class ClientOptions extends BaseClientOptions
{
    /**
     * {@inheritdoc}
     */
    protected function getDefaultOptions()
    {
        return array(
            'profile'   => new ClientProfile(),
            'prefix'    => new ClientPrefix(),
            'eventloop' => new ClientEventLoop(),
        );
    }
}
