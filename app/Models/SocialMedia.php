<?php

namespace App\Models;

use App\Models\Mutators\SocialMediaMutators;
use App\Models\Relationships\SocialMediaRelationships;
use App\Models\Scopes\SocialMediaScopes;

class SocialMedia extends Model
{
    use SocialMediaMutators;
    use SocialMediaRelationships;
    use SocialMediaScopes;

    const TYPE_FACEBOOK = 'facebook';

    const TYPE_INSTAGRAM = 'instagram';

    const TYPE_OTHER = 'other';

    const TYPE_TIKTOK = 'tiktok';

    const TYPE_TWITTER = 'twitter';

    const TYPE_SNAPCHAT = 'snapchat';

    const TYPE_YOUTUBE = 'youtube';

    const TYPE_LINKEDIN = 'linkedin';

    const TYPE_WHATSAPP = 'whatsapp';

    const TYPE_NEXTDOOR = 'nextdoor';

    const TYPE_BLUESKY = 'bluesky';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'social_medias';
}
