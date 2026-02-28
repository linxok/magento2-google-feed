<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

class Type extends TagScope
{
    /**
     * Cache type code unique among all cache types
     */
    const TYPE_IDENTIFIER = 'googlefeed';

    /**
     * Cache tag used to distinguish the cache type from all other cache
     */
    const CACHE_TAG = 'GOOGLEFEED';

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
