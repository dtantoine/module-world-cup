<?php
/**
 * Antoine World Cup Hub — public JSON snapshot endpoint (/world-cup/data).
 * Serves the cached snapshot; no upstream I/O in the request path.
 */
declare(strict_types=1);

namespace Antoine\WorldCup\Controller\Data;

use Antoine\WorldCup\Model\Snapshot\Repository;
use Antoine\WorldCup\Model\Upstream\Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

class Index implements HttpGetActionInterface
{
    /**
     * Constructor.
     *
     * @param JsonFactory $jsonFactory
     * @param Repository $repository
     * @param Config $config
     */
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly Repository $repository,
        private readonly Config $config
    ) {
    }

    /**
     * Serve the cached snapshot as JSON; returns {"enabled":false} when disabled.
     *
     * The response is marked no-store so neither Varnish/FPC nor the browser caches
     * it — the page HTML is full-page-cached, and the frontend relies on this
     * endpoint for fresh live data, so it must never be served stale.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store, max-age=0', true);
        if (!$this->config->isEnabled()) {
            return $result->setData(['enabled' => false]);
        }
        return $result->setData($this->repository->get());
    }
}
