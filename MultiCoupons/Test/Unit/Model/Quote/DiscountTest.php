<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\SalesRule\Test\Unit\Model\Quote;

use Magento\Bundle\Model\Plugin\QuoteItem;
use \Sd\MultiCoupons\Model\Quote\Discount as SdQuoteDiscount;
use \Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use \Magento\Store\Model\StoreManager as StoreManagerModel;
use \Magento\SalesRule\Model\Validator as ValidatorModel;
use \Magento\Framework\Event\Manager as EventManager;
use \Magento\Framework\Pricing\PriceCurrencyInterface as PriceCurrencyInterface;
use \Magento\Quote\Model\Quote\Address as QuoteAddressModel;
use \Magento\Quote\Api\Data\ShippingInterface as ShippingInterface;
use \Magento\Quote\Api\Data\ShippingAssignmentInterface as ShippingAssignmentInterface;
use \Magento\Quote\Model\Quote\Item as QuoteItemModel;
use \Magento\Quote\Model\Quote as QuoteModel;
use \Magento\Store\Model\Store as StoreModel;
use \Magento\SalesRule\Model\Quote\Discount as QuoteDiscount;

/**
 * Class DiscountTest
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DiscountTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SdQuoteDiscount
     */
    protected $discount;

    /**
     * @var ObjectManagerHelper
     */
    protected $objectManager;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $storeManagerMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $validatorMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $eventManagerMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $shippingAssignmentMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $addressMock;

    protected function setUp()
    {
        $this->objectManager = new ObjectManagerHelper($this);
        $this->storeManagerMock = $this->createMock(StoreManagerModel::class);
        $this->validatorMock = $this->getMockBuilder(ValidatorModel::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'canApplyRules',
                    'reset',
                    'init',
                    'initTotals',
                    'sortItemsByPriority',
                    'setSkipActionsValidation',
                    'process',
                    'processShippingAmount',
                    'canApplyDiscount',
                    '__wakeup',
                ]
            )
            ->getMock();
        $this->eventManagerMock = $this->createMock(EventManager::class);
        $priceCurrencyMock = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrencyMock->expects($this->any())
            ->method('round')
            ->will($this->returnCallback(
                function ($argument) {
                    return round($argument, 2);
                }
            ));

        $this->addressMock = $this->createPartialMock(
            QuoteAddressModel::class,
            ['getQuote', 'getAllItems', 'getShippingAmount', '__wakeup', 'getCustomAttributesCodes']
        );
        $this->addressMock->expects($this->any())
            ->method('getCustomAttributesCodes')
            ->willReturn([]);

        $shipping = $this->createMock(ShippingInterface::class);
        $shipping->expects($this->any())->method('getAddress')->willReturn($this->addressMock);

        $this->shippingAssignmentMock = $this->createMock(ShippingAssignmentInterface::class);
        $this->shippingAssignmentMock->expects($this->any())->method('getShipping')->willReturn($shipping);

        /** @var SdQuoteDiscount $discount */
        $this->discount = $this->objectManager->getObject(
            SdQuoteDiscount::class,
            [
                'storeManager' => $this->storeManagerMock,
                'validator' => $this->validatorMock,
                'eventManager' => $this->eventManagerMock,
                'priceCurrency' => $priceCurrencyMock,
            ]
        );
    }

    /**
     * @return array
     */
    public function collectItemHasChildrenDataProvider()
    {
        $data = [
            // 3 items, each $100, testing that discount are distributed to item correctly
            [
                'child_item_data' => [
                    'item1' => [
                        'base_row_total' => 0,
                    ]
                ],
                'parent_item_data' => [
                    'discount_amount' => 20,
                    'base_discount_amount' => 10,
                    'original_discount_amount' => 40,
                    'base_original_discount_amount' => 20,
                    'base_row_total' => 0,
                ],
                'expected_child_item_data' => [
                    'item1' => [
                        'discount_amount' => 0,
                        'base_discount_amount' => 0,
                        'original_discount_amount' => 0,
                        'base_original_discount_amount' => 0,
                    ]
                ],
            ],
            [
                // 3 items, each $100, testing that discount are distributed to item correctly
                'child_item_data' => [
                    'item1' => [
                        'base_row_total' => 100,
                    ],
                    'item2' => [
                        'base_row_total' => 100,
                    ],
                    'item3' => [
                        'base_row_total' => 100,
                    ],
                ],
                'parent_item_data' => [
                    'discount_amount' => 20,
                    'base_discount_amount' => 10,
                    'original_discount_amount' => 40,
                    'base_original_discount_amount' => 20,
                    'base_row_total' => 300,
                ],
                'expected_child_item_data' => [
                    'item1' => [
                        'discount_amount' => 6.67,
                        'base_discount_amount' => 3.33,
                        'original_discount_amount' => 13.33,
                        'base_original_discount_amount' => 6.67,
                    ],
                    'item2' => [
                        'discount_amount' => 6.66,
                        'base_discount_amount' => 3.34,
                        'original_discount_amount' => 13.34,
                        'base_original_discount_amount' => 6.66,
                    ],
                    'item3' => [
                        'discount_amount' => 6.67,
                        'base_discount_amount' => 3.33,
                        'original_discount_amount' => 13.33,
                        'base_original_discount_amount' => 6.67,
                    ],
                ],
            ],
        ];
        return $data;
    }

    /**
     * @covers \Sd\MultiCoupons\Model\Quote\Discount::collect
     */
    public function testCollectItemNoDiscount()
    {
        $itemNoDiscount = $this->createPartialMock(
            QuoteItemModel::class,
            ['getNoDiscount', '__wakeup']
        );
        $itemNoDiscount->expects($this->once())
            ->method('getNoDiscount')
            ->willReturn(true);

        $quoteMock = $this->createMock(QuoteModel::class);
        $this->addressMock->expects($this->any())
            ->method('getQuote')
            ->willReturn($quoteMock);

        $this->validatorMock->expects($this->once())
            ->method('sortItemsByPriority')
            ->with([$itemNoDiscount], $this->addressMock)
            ->willReturnArgument(0);

        $quoteMock->expects($this->at(0))
            ->method('getStoreId')
            ->willReturn('1');

        $storeMock = $this->createPartialMock(StoreModel::class, ['__wakeup']);
        $this->storeManagerMock->expects($this->any())
            ->method('getStore')
            ->willReturn($storeMock);

        $this->shippingAssignmentMock->expects($this->any())
            ->method('getItems')
            ->willReturn([$itemNoDiscount]);

        $totalMock = $this->createMock(QuoteAddressModel\Total::class);

        $this->assertInstanceOf(
            SdQuoteDiscount::class,
            $this->discount->collect($quoteMock, $this->shippingAssignmentMock, $totalMock)
        );
    }

    /**
     * @covers \Sd\MultiCoupons\Model\Quote\Discount::collect
     */
    public function testCollectItemNoItems()
    {
        $this->shippingAssignmentMock->expects($this->any())
            ->method('getItems')
            ->willReturn([]);

        $totalMock = $this->createMock(QuoteAddressModel\Total::class);
        $quoteMock = $this->createMock(QuoteModel::class);

        $this->assertInstanceOf(
            SdQuoteDiscount::class,
            $this->discount->collect($quoteMock, $this->shippingAssignmentMock, $totalMock)
        );
    }

    /**
     * @covers \Sd\MultiCoupons\Model\Quote\Discount::collect
     */
    public function testCollectItemHasParent()
    {
        $itemWithParentId = $this->createPartialMock(
            QuoteItemModel::class,
            ['getNoDiscount', 'getParentItem', '__wakeup']
        );
        $itemWithParentId->expects($this->once())
            ->method('getNoDiscount')
            ->willReturn(false);
        $itemWithParentId->expects($this->once())
            ->method('getParentItem')
            ->willReturn(true);

        $this->validatorMock->expects($this->any())
            ->method('canApplyDiscount')
            ->willReturn(true);
        $this->validatorMock->expects($this->any())
            ->method('sortItemsByPriority')
            ->with([$itemWithParentId], $this->addressMock)
            ->willReturnArgument(0);

        $storeMock = $this->createPartialMock(
            StoreModel::class,
            ['getStore', '__wakeup']
        );
        $this->storeManagerMock->expects($this->any())
            ->method('getStore')
            ->willReturn($storeMock);

        $quoteMock = $this->createMock(QuoteModel::class);
        $this->addressMock->expects($this->any())
            ->method('getQuote')
            ->willReturn($quoteMock);
        $this->addressMock->expects($this->any())
            ->method('getShippingAmount')
            ->willReturn(true);

        $this->shippingAssignmentMock->expects($this->any())
            ->method('getItems')
            ->willReturn([$itemWithParentId]);

        $totalMock = $this->createMock(QuoteAddressModel\Total::class);

        $this->assertInstanceOf(
            SdQuoteDiscount::class,
            $this->discount->collect($quoteMock, $this->shippingAssignmentMock, $totalMock)
        );
    }

    /**
     * @covers \Sd\MultiCoupons\Model\Quote\Discount::collect
     * @param QuoteItemModel $childItemData
     * @param QuoteItemModel $parentData
     * @param array $expectedChildData
     * @dataProvider collectItemHasChildrenDataProvider
     */
    public function testCollectItemHasChildren($childItemData, $parentData, $expectedChildData)
    {
        $childItems = [];
        foreach ($childItemData as $itemId => $itemData) {
            $item = $this->objectManager->getObject(QuoteItemModel::class)->setData($itemData);
            $childItems[$itemId] = $item;
        }

        $itemWithChildren = $this->getMockBuilder(QuoteItemModel::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getNoDiscount',
                    'getParentItem',
                    'getHasChildren',
                    'isChildrenCalculated',
                    'getChildren',
                    '__wakeup',
                ]
            )
            ->getMock();

        $itemWithChildren->expects($this->once())
            ->method('getNoDiscount')
            ->willReturn(false);
        $itemWithChildren->expects($this->once())
            ->method('getParentItem')
            ->willReturn(false);
        $itemWithChildren->expects($this->once())
            ->method('getHasChildren')
            ->willReturn(true);
        $itemWithChildren->expects($this->once())
            ->method('isChildrenCalculated')
            ->willReturn(true);
        $itemWithChildren->expects($this->any())
            ->method('getChildren')
            ->willReturn($childItems);

        foreach ($parentData as $key => $value) {
            $itemWithChildren->setData($key, $value);
        }

        $this->validatorMock->expects($this->any())
            ->method('canApplyDiscount')
            ->willReturn(true);
        $this->validatorMock->expects($this->once())
            ->method('sortItemsByPriority')
            ->with([$itemWithChildren], $this->addressMock)
            ->willReturnArgument(0);
        $this->validatorMock->expects($this->any())
            ->method('canApplyRules')
            ->willReturn(true);

        $storeMock = $this->getMockBuilder(StoreModel::class)
            ->disableOriginalConstructor()
            ->setMethods(['getStore', '__wakeup'])
            ->getMock();
        $this->storeManagerMock->expects($this->any())
            ->method('getStore')
            ->willReturn($storeMock);

        $quoteMock = $this->getMockBuilder(QuoteModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->addressMock->expects($this->any())
            ->method('getQuote')
            ->willReturn($quoteMock);
        $this->addressMock->expects($this->any())
            ->method('getShippingAmount')
            ->willReturn(true);

        $this->shippingAssignmentMock->expects($this->any())
            ->method('getItems')
            ->willReturn([$itemWithChildren]);
        $totalMock = $this->createMock(QuoteAddressModel\Total::class);

        $this->assertInstanceOf(
            QuoteDiscount::class,
            $this->discount->collect($quoteMock, $this->shippingAssignmentMock, $totalMock)
        );

        foreach ($expectedChildData as $itemId => $expectedItemData) {
            $childItem = $childItems[$itemId];
            foreach ($expectedItemData as $key => $value) {
                $this->assertEquals($value, $childItem->getData($key), 'Incorrect value for ' . $key);
            }
        }
    }

    /**
     * @covers \Sd\MultiCoupons\Model\Quote\Discount::collect
     */
    public function testCollectItemHasNoChildren()
    {
        $itemWithChildren = $this->getMockBuilder(QuoteItemModel::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getNoDiscount',
                    'getParentItem',
                    'getHasChildren',
                    'isChildrenCalculated',
                    'getChildren',
                    '__wakeup',
                ]
            )
            ->getMock();
        $itemWithChildren->expects($this->once())
            ->method('getNoDiscount')
            ->willReturn(false);
        $itemWithChildren->expects($this->once())
            ->method('getParentItem')
            ->willReturn(false);
        $itemWithChildren->expects($this->once())
            ->method('getHasChildren')
            ->willReturn(false);

        $this->validatorMock->expects($this->any())
            ->method('canApplyDiscount')
            ->willReturn(true);
        $this->validatorMock->expects($this->once())
            ->method('sortItemsByPriority')
            ->with([$itemWithChildren], $this->addressMock)
            ->willReturnArgument(0);

        $storeMock = $this->getMockBuilder(StoreModel::class)
            ->disableOriginalConstructor()
            ->setMethods(['getStore', '__wakeup'])
            ->getMock();
        $this->storeManagerMock->expects($this->any())
            ->method('getStore')
            ->willReturn($storeMock);

        $quoteMock = $this->getMockBuilder(QuoteModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->addressMock->expects($this->any())
            ->method('getQuote')
            ->willReturn($quoteMock);
        $this->addressMock->expects($this->any())
            ->method('getShippingAmount')
            ->willReturn(true);

        $this->shippingAssignmentMock->expects($this->any())
            ->method('getItems')
            ->willReturn([$itemWithChildren]);

        $totalMock = $this->createMock(QuoteAddressModel\Total::class);

        $this->assertInstanceOf(
            QuoteDiscount::class,
            $this->discount->collect($quoteMock, $this->shippingAssignmentMock, $totalMock)
        );
    }
}
