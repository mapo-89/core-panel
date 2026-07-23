<?php

declare(strict_types=1);

namespace CorePanel\Support\Auth;

use Illuminate\Support\Collection;

/**
 * @extends Collection<int, array{id:string, ip_address:string|null, user_agent:string|null, last_active:int, is_current:bool}>
 */
final class BrowserSessionCollection extends Collection {}
