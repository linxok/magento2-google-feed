<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MyCompany\GoogleFeed\Model\FeedFileManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FeedFileManagerTest extends TestCase
{
    /**
     * @var Filesystem|MockObject
     */
    private $filesystem;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfig;

    /**
     * @var FeedFileManager
     */
    private $manager;

    protected function setUp(): void
    {
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->manager = new FeedFileManager($this->filesystem, $this->storeManager, $this->scopeConfig);
    }

    public function testGetStoreSpecificPathUsesConfiguredBasePath(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(function ($path) {
            if ($path === FeedFileManager::XML_PATH_FILE_PATH) {
                return 'googlefeed/feed.xml';
            }
            if ($path === 'general/locale/code') {
                return 'uk_UA';
            }

            return null;
        });

        $store = $this->createStoreMock('ua', 'Ukr', 3);

        $this->assertSame('googlefeed/feed_ukr_ua_uk.xml', $this->manager->getStoreSpecificPath($store));
    }

    public function testGetStoreSpecificPathNeutralizesPathTraversal(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(function ($path) {
            if ($path === FeedFileManager::XML_PATH_FILE_PATH) {
                return '../../etc/feed.xml';
            }
            if ($path === 'general/locale/code') {
                return 'en_US';
            }

            return null;
        });

        $store = $this->createStoreMock('en', 'Eng', 1);

        $this->assertSame('etc/feed_eng_en_en.xml', $this->manager->getStoreSpecificPath($store));
    }

    public function testGetStoreIdsFromConfiguration(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(FeedFileManager::XML_PATH_STORE_IDS)
            ->willReturn('1, 3');
        $this->storeManager->expects($this->never())->method('getStores');

        $this->assertSame([1, 3], $this->manager->getStoreIdsForGeneration());
    }

    public function testGetStoreIdsFallsBackToActiveStores(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(FeedFileManager::XML_PATH_STORE_IDS)
            ->willReturn('');

        $activeStore = $this->createMock(Store::class);
        $activeStore->method('getIsActive')->willReturn(true);
        $activeStore->method('getId')->willReturn(1);

        $inactiveStore = $this->createMock(Store::class);
        $inactiveStore->method('getIsActive')->willReturn(false);
        $inactiveStore->method('getId')->willReturn(2);

        $this->storeManager->method('getStores')->willReturn([$activeStore, $inactiveStore]);

        $this->assertSame([1], $this->manager->getStoreIdsForGeneration());
    }

    /**
     * @param string $code
     * @param string $name
     * @param int $id
     * @return Store|MockObject
     */
    private function createStoreMock($code, $name, $id)
    {
        $store = $this->createMock(Store::class);
        $store->method('getCode')->willReturn($code);
        $store->method('getName')->willReturn($name);
        $store->method('getId')->willReturn($id);

        return $store;
    }
}
