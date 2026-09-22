<?php

declare(strict_types=1);

namespace CorePanel\Models\Concerns;

use CorePanel\Models\SocialAccount;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasCorePanelSocialAccounts
{
    /** @return HasMany<SocialAccount, $this> */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /** @return HasOne<SocialAccount, $this> */
    public function microsoftAccount(): HasOne
    {
        return $this->hasOne(SocialAccount::class)->where('provider', 'microsoft');
    }
}
