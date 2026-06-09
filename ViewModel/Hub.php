<?php
/**
 * Antoine World Cup Hub — exposes the snapshot + enabled flag to the template.
 */
declare(strict_types=1);

namespace Antoine\WorldCup\ViewModel;

use Antoine\WorldCup\Model\Snapshot\Repository;
use Antoine\WorldCup\Model\Upstream\Config;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Hub implements ArgumentInterface
{
    /**
     * Constructor.
     *
     * @param Repository $repository
     * @param Config     $config
     */
    public function __construct(
        private readonly Repository $repository,
        private readonly Config $config
    ) {
    }

    /**
     * Whether the upstream integration is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Return the current snapshot array (empty when disabled).
     *
     * @return array<string,mixed>
     */
    public function getSnapshot(): array
    {
        return $this->config->isEnabled() ? $this->repository->get() : [];
    }

    /**
     * Return the snapshot as a safely-escaped JSON string for inline use.
     *
     * @return string
     */
    public function getSnapshotJson(): string
    {
        return json_encode($this->getSnapshot(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
    }

    /**
     * Return the URL of the JSON data endpoint.
     *
     * @return string
     */
    public function getDataUrl(): string
    {
        return '/world-cup/data';
    }

    /**
     * Return the frontend live-poll interval in milliseconds.
     *
     * @return int
     */
    public function getPollIntervalMs(): int
    {
        return $this->config->getPollInterval() * 1000;
    }
}
