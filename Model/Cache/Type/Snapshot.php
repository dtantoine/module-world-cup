<?php
/**
 * Antoine World Cup Hub — dedicated cache type for the live snapshot.
 */
declare(strict_types=1);

namespace Antoine\WorldCup\Model\Cache\Type;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

class Snapshot extends TagScope
{
    public const TYPE_IDENTIFIER = 'worldcup_snapshot';
    public const CACHE_TAG = 'WORLDCUP_SNAPSHOT';

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
