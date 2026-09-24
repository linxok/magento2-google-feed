<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MyCompany\GoogleFeed\Model\StoreUrlResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StoreUrlResolverTest extends TestCase
{
    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfig;

    /**
     * @var StoreUrlResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->resolver = new StoreUrlResolver($this->storeManager, $this->scopeConfig);
    }

    /**
     * @dataProvider appendStoreCodeDataProvider
     * @param string $baseUrl
     * @param string $storeCode
     * @param string $expected
     */
    public function testAppendStoreCodeOnce($baseUrl, $storeCode, $expected): void
    {
        $this->assertSame($expected, StoreUrlResolver::appendStoreCodeOnce($baseUrl, $storeCode));
    }

    /**
     * @return array
     */
    public function appendStoreCodeDataProvider(): array
    {
        return [
            'plain base url' => ['https://shop.com/', 'en', 'https://shop.com/en/'],
            'code already in path' => ['https://razvertka.com/ua/', 'ua', 'https://razvertka.com/ua/'],
            'existing path' => ['https://shop.com/base/', 'en', 'https://shop.com/base/en/'],
            'subdomain host stays untouched' => ['https://uk.myshop.com/', 'uk', 'https://uk.myshop.com/uk/'],
            'host ending with code is not corrupted' => ['https://myshop.ua/', 'ua', 'https://myshop.ua/ua/'],
            'port preserved' => ['https://shop.com:8080/', 'en', 'https://shop.com:8080/en/'],
            'rewrites disabled keeps index.php' => ['https://shop.com/index.php/', 'en', 'https://shop.com/index.php/en/'],
            'empty store code' => ['https://shop.com/', '', 'https://shop.com/'],
            'invalid base url' => ['not a url', 'en', 'not a url/'],
        ];
    }

    public function testGetStoreBaseUrlAppendsCodeWhenStoreCodeInUrlEnabled(): void
    {
        $store = $this->createStoreMock('en', 1, 'https://shop.com/', true);

        $this->assertSame('https://shop.com/en/', $this->resolver->getStoreBaseUrl($store));
    }

    public function testGetStoreBaseUrlDoesNotInventCodeWhenStoreCodeInUrlDisabled(): void
    {
        $store = $this->createStoreMock('default', 1, 'https://shop.com/', false);

        $this->assertSame('https://shop.com/', $this->resolver->getStoreBaseUrl($store));
    }

    public function testGetStoreBaseUrlDoesNotDuplicateExistingStoreCode(): void
    {
        $store = $this->createStoreMock('ua', 3, 'https://razvertka.com/ua/', true);

        $this->assertSame('https://razvertka.com/ua/', $this->resolver->getStoreBaseUrl($store));
    }

    public function testGetStoreBaseUrlFallsBackToUnsecureBaseUrl(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getCode')->willReturn('en');
        $store->method('getId')->willReturn(1);
        $store->method('isUseStoreInUrl')->willReturn(true);
        $store->method('getBaseUrl')->willReturnMap([
            [UrlInterface::URL_TYPE_DIRECT_LINK, true, ''],
            [UrlInterface::URL_TYPE_DIRECT_LINK, false, 'http://shop.com/'],
        ]);

        $this->assertSame('http://shop.com/en/', $this->resolver->getStoreBaseUrl($store));
    }

    public function testGetFeedUrlIncludesStoreCode(): void
    {
        $store = $this->createStoreMock('ua', 3, 'https://razvertka.com/ua/', true);

        $this->assertSame(
            'https://razvertka.com/ua/googlefeed/feed/index?store=ua',
            $this->resolver->getFeedUrl($store)
        );
    }

    public function testGetImageUrlEncodesPathSegments(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturnMap([
            [UrlInterface::URL_TYPE_MEDIA, null, 'https://shop.com/media/'],
        ]);

        $this->assertSame(
            'https://shop.com/media/catalog/product/f/o/foo%20bar.jpg',
            $this->resolver->getImageUrl('f/o/foo bar.jpg', $store)
        );
    }

    /**
     * @param string $code
     * @param int $id
     * @param string $baseUrl
     * @param bool $useStoreInUrl
     * @return Store|MockObject
     */
    private function createStoreMock($code, $id, $baseUrl, $useStoreInUrl)
    {
        $store = $this->createMock(Store::class);
        $store->method('getCode')->willReturn($code);
        $store->method('getId')->willReturn($id);
        $store->method('isUseStoreInUrl')->willReturn($useStoreInUrl);
        $store->method('getBaseUrl')->willReturnMap([
            [UrlInterface::URL_TYPE_DIRECT_LINK, true, $baseUrl],
            [UrlInterface::URL_TYPE_DIRECT_LINK, false, $baseUrl],
        ]);

        return $store;
    }
}
