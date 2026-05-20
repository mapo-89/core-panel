<?php

declare(strict_types=1);

namespace CorePanel\Support\TableBuilder\Actions;

use CorePanel\Support\TableBuilder\Action;

final class BulkDeleteAction extends Action
{
    public const TYPE = 'bulk-delete';

    protected bool $bulk = true;
}
