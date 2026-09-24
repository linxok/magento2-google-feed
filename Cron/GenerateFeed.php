<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\FlagManager;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MyCompany\GoogleFeed\Model\FeedFileManager;
use MyCompany\GoogleFeed\Model\FeedGenerator;
use Psr\Log\LoggerInterface;

class GenerateFeed
{
    const LAST_RUN_FLAG = 'mycompany_googlefeed_last_run';

    /**
     * @var FeedGenerator
     */
    protected $feedGenerator;

    /**
     * @var FeedFileManager
     */
    protected $feedFileManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var FlagManager
     */
    protected $flagManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param FeedGenerator $feedGenerator
     * @param FeedFileManager $feedFileManager
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param FlagManager $flagManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        FeedGenerator $feedGenerator,
        FeedFileManager $feedFileManager,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        FlagManager $flagManager,
        LoggerInterface $logger
    ) {
        $this->feedGenerator = $feedGenerator;
        $this->feedFileManager = $feedFileManager;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->flagManager = $flagManager;
        $this->logger = $logger;
    }

    /**
     * Execute cron job. The job runs frequently and checks the configured schedule itself.
     *
     * @return void
     */
    public function execute()
    {
        if (!$this->scopeConfig->getValue('googlefeed/cron/enabled')) {
            return;
        }

        $lastRun = (int)$this->flagManager->getFlagData(self::LAST_RUN_FLAG);
        if (!$this->isDue($lastRun)) {
            return;
        }

        // Mark the run before generation so a failing feed is not retried every few minutes.
        $this->flagManager->saveFlag(self::LAST_RUN_FLAG, time());

        try {
            $storeIds = $this->feedFileManager->getStoreIdsForGeneration();
            $generatedCount = 0;

            foreach ($storeIds as $storeId) {
                try {
                    $store = $this->storeManager->getStore($storeId);

                    if (!$this->scopeConfig->isSetFlag(
                        'googlefeed/general/enabled',
                        ScopeInterface::SCOPE_STORE,
                        $storeId
                    )) {
                        continue;
                    }

                    $currentStoreId = (int)$this->storeManager->getStore()->getId();
                    $this->storeManager->setCurrentStore($storeId);

                    try {
                        $feedContent = $this->feedGenerator->generateFeed();
                        $finalPath = $this->feedFileManager->getStoreSpecificPath($store);
                        $this->feedFileManager->saveFeed($feedContent, $finalPath);
                        $generatedCount++;

                        $this->logger->info(sprintf(
                            'Google Feed generated for store "%s" (%s): %s',
                            $store->getName(),
                            $store->getCode(),
                            $finalPath
                        ));
                    } finally {
                        $this->storeManager->setCurrentStore($currentStoreId);
                    }
                } catch (\Exception $e) {
                    $this->logger->error(sprintf(
                        'Error generating Google Feed for store ID %d: %s',
                        $storeId,
                        $e->getMessage()
                    ));
                }
            }

            if ($generatedCount > 0) {
                $this->logger->info(sprintf('Google Feed cron completed: %d feed(s) generated', $generatedCount));
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in Google Feed cron execution: ' . $e->getMessage());
        }
    }

    /**
     * Check whether the configured schedule is due.
     *
     * @param int $lastRun Unix timestamp of the last run
     * @return bool
     */
    protected function isDue($lastRun)
    {
        $frequency = (string)$this->scopeConfig->getValue('googlefeed/cron/frequency');
        $frequency = $frequency !== '' ? $frequency : 'daily';

        $hour = 2;
        $minute = 0;
        $configuredTime = (string)$this->scopeConfig->getValue('googlefeed/cron/time');
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $configuredTime, $matches)) {
            $hour = min(23, (int)$matches[1]);
            $minute = min(59, (int)$matches[2]);
        }

        $now = new \DateTimeImmutable('now');
        $lastRun = (int)$lastRun;

        if ($frequency === 'weekly') {
            return $lastRun <= $now->getTimestamp() - 7 * 86400;
        }

        switch ($frequency) {
            case 'hourly':
                $candidate = $now->setTime((int)$now->format('G'), $minute, 0);
                if ($candidate > $now) {
                    $candidate = $candidate->modify('-1 hour');
                }
                break;
            case 'twice_daily':
            case 'every_6_hours':
                $blockHours = $frequency === 'twice_daily' ? 12 : 6;
                $blockHour = intdiv((int)$now->format('G'), $blockHours) * $blockHours;
                $candidate = $now->setTime($blockHour, $minute, 0);
                if ($candidate > $now) {
                    $candidate = $candidate->modify('-' . $blockHours . ' hours');
                }
                break;
            case 'daily':
            default:
                $candidate = $now->setTime($hour, $minute, 0);
                if ($candidate > $now) {
                    $candidate = $candidate->modify('-1 day');
                }
                break;
        }

        return $lastRun < $candidate->getTimestamp();
    }
}
