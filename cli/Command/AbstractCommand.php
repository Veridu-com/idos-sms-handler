<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace Cli\Command;

use Symfony\Component\Console\Command\Command;

/**
 * Abstract command definition.
 */
abstract class AbstractCommand extends Command {
    /**
     * The application configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Constructor.
     *
     * @param array       $config
     * @param string|null $name
     */
    public function __construct(array $config, string $name = null) {
        parent::__construct($name);

        $this->config    = $config;
    }
}
