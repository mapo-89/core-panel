<?php

declare(strict_types=1);

namespace CorePanel\Contracts;

use CorePanel\Support\Install\CorePanelInstallOptions;
use Illuminate\Console\Command;

interface CorePanelInstallerInterface
{
    public function install(CorePanelInstallOptions $options, Command $command): void;
}
