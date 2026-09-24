<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MyCompany\GoogleFeed\Test\Unit\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;
use MyCompany\GoogleFeed\Model\FeedGenerator;
use MyCompany\GoogleFeed\Model\GoogleCategoryStorage;
use MyCompany\GoogleFeed\Model\StoreUrlResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FeedGeneratorTest extends TestCase
{
    /**
     * @var FeedGenerator
     */
    private $generator;

    protected function setUp(): void
    {
        $this->generator = new FeedGenerator(
            $this->createMock(CollectionFactory::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(PriceCurrencyInterface::class),
            $this->createMock(StockRegistryInterface::class),
            $this->createMock(CategoryRepositoryInterface::class),
            $this->createMock(GoogleCategoryStorage::class),
            $this->createMock(StoreUrlResolver::class),
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * @dataProvider conditionProvider
     * @param mixed $input
     * @param string|null $expected
     */
    public function testMapConditionValue($input, $expected): void
    {
        $this->assertSame($expected, $this->invoke('mapConditionValue', [$input]));
    }

    /**
     * @return array
     */
    public function conditionProvider(): array
    {
        return [
            'default new' => ['New', 'new'],
            'used' => ['used', 'used'],
            'refurbished' => ['Refurbished', 'refurbished'],
            'localized used' => ['Вживаний', 'used'],
            'empty' => ['', null],
            'unknown' => ['something-else', null],
        ];
    }

    /**
     * @dataProvider genderProvider
     * @param mixed $input
     * @param string|null $expected
     */
    public function testMapGenderValue($input, $expected): void
    {
        $this->assertSame($expected, $this->invoke('mapGenderValue', [$input]));
    }

    /**
     * @return array
     */
    public function genderProvider(): array
    {
        return [
            'male' => ['Male', 'male'],
            'female is not male' => ['Female', 'female'],
            'women is not men' => ['Women', 'female'],
            'unisex' => ['unisex', 'unisex'],
            'localized male' => ['Чоловічий', 'male'],
            'empty' => ['', null],
            'unknown' => ['n/a', null],
        ];
    }

    /**
     * @dataProvider ageGroupProvider
     * @param mixed $input
     * @param string|null $expected
     */
    public function testMapAgeGroupValue($input, $expected): void
    {
        $this->assertSame($expected, $this->invoke('mapAgeGroupValue', [$input]));
    }

    /**
     * @return array
     */
    public function ageGroupProvider(): array
    {
        return [
            'adult' => ['Adult', 'adult'],
            'kids' => ['Kids', 'kids'],
            'toddler' => ['Toddler', 'toddler'],
            'infant' => ['Infant', 'infant'],
            'newborn' => ['Newborn', 'newborn'],
            'empty' => ['', null],
            'unknown' => ['senior', null],
        ];
    }

    /**
     * @param string $method
     * @param array $args
     * @return mixed
     */
    private function invoke(string $method, array $args)
    {
        $reflection = new \ReflectionMethod($this->generator, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->generator, $args);
    }
}
