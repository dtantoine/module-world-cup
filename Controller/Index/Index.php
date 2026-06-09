<?php
/**
 * Antoine World Cup Hub — public stats page (/world-cup). Renders the snapshot
 * server-side; the JS app takes over for live polling.
 */
declare(strict_types=1);

namespace Antoine\WorldCup\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

class Index implements HttpGetActionInterface
{
    /**
     * Constructor.
     *
     * @param ResultFactory $resultFactory
     */
    public function __construct(private readonly ResultFactory $resultFactory)
    {
    }

    /**
     * Render the public World Cup stats page.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
    }
}
