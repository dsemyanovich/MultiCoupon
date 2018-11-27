<?php

namespace Sd\MultiCoupons\Model\Quote;

use Magento\Quote\Api\Data\ShippingAssignmentInterface as ShippingAssignmentInterface;
use Magento\Quote\Model\Quote as QuoteModel;
use Magento\Quote\Model\Quote\Address\Total as QuoteAddressTotal;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal as QuoteAddressAbstractTotal;

class Discount extends \Magento\SalesRule\Model\Quote\Discount
{
    const SALES_QUOTE_ADDRESS_DISCOUNT_ITEM_ARRAY_KEY = 'item';

    /**
     * @var null|\Magento\Store\Api\Data\StoreInterface
     */
    protected $store;

    /**
     * @var null|\Magento\Quote\Api\Data\AddressInterface
     */
    protected $address;

    /**
     * Collect address discount amount considering multi coupons
     *
     * @param QuoteModel $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param QuoteAddressTotal $total
     * @return $this
     */
    public function collect(
        QuoteModel $quote,
        ShippingAssignmentInterface $shippingAssignment,
        QuoteAddressTotal $total
    ): Discount {
        QuoteAddressAbstractTotal::collect($quote, $shippingAssignment, $total);

        $this->store = $this->storeManager->getStore($quote->getStoreId());
        $this->address = $shippingAssignment->getShipping()->getAddress();
        $this->calculator->reset($this->address);

        $items = $shippingAssignment->getItems();

        if (empty($items)) {
            return $this;
        }

        $couponsList = explode(',', $quote->getCouponCode());
        $couponsList = is_array($couponsList) ? $couponsList: [$couponsList];
        $items = $this->calculator->sortItemsByPriority($items, $this->address);

        foreach ($couponsList as $couponCode) {
            $this->applyCoupon($couponCode, $items, $quote, $total);
            $this->calculateTotal($total);
        }

        return $this;
    }

    /**
     * @param QuoteAddressTotal $total
     */
    private function calculateTotal(QuoteAddressTotal $total)
    {
        /* Process shipping amount discount */
        $this->address->setShippingDiscountAmount(0);
        $this->address->setBaseShippingDiscountAmount(0);
        if ($this->address->getShippingAmount()) {
            $this->calculator->processShippingAmount($this->address);
            $total->addTotalAmount(
                $this->getCode(),
                -$this->address->getShippingDiscountAmount()
            );
            $total->addBaseTotalAmount(
                $this->getCode(),
                -$this->address->getBaseShippingDiscountAmount()
            );
            $total->setShippingDiscountAmount(
                $this->address->getShippingDiscountAmount()
            );
            $total->setBaseShippingDiscountAmount(
                $this->address->getBaseShippingDiscountAmount()
            );
        }

        $this->calculator->prepareDescription($this->address);
        $total->setDiscountDescription(
            $this->address->getDiscountDescription()
        );
        $total->setSubtotalWithDiscount(
            $total->getSubtotal() + $total->getDiscountAmount()
        );
        $total->setBaseSubtotalWithDiscount(
            $total->getBaseSubtotal() + $total->getBaseDiscountAmount()
        );
    }

    /**
     * @param string $couponCode
     * @param array $items
     * @param QuoteModel $quote
     * @param QuoteAddressTotal $total
     */
    private function applyCoupon(
        string $couponCode,
        array $items,
        QuoteModel $quote,
        QuoteAddressTotal $total
    ) {
        $this->calculator->init(
            $this->store->getWebsiteId(),
            $quote->getCustomerGroupId(),
            $couponCode
        );

        $this->calculator->initTotals($items, $this->address);

        $eventArgs = [
            'website_id' => $this->store->getWebsiteId(),
            'customer_group_id' => $quote->getCustomerGroupId(),
            'coupon_code' => $couponCode,
        ];

        $this->address->setDiscountDescription();

        /** @var QuoteModel\Item $item */
        foreach ($items as $item) {
            if ($item->getNoDiscount() || !$this->calculator->canApplyDiscount($item)) {
                $this->resetDiscountPerItem($item);
                continue;
            }

            if ($item->getParentItem()) {
                continue;
            }

            $eventArgs['item'] = $item;
            $this->eventManager->dispatch(
                'sales_quote_address_discount_item',
                $eventArgs
            );

            if ($item->getHasChildren() && $item->isChildrenCalculated()) {
                $this->calculateDiscountPerItem($item, $total);
            } else {
                $this->calculator->process($item);
                $this->aggregateItemDiscount($item, $total);
            }
        }
    }

    /**
     * @param QuoteModel\Item $item
     */
    private function resetDiscountPerItem(QuoteModel\Item $item)
    {
        $item->setDiscountAmount(0);
        $item->setBaseDiscountAmount(0);

        if ($item->getHasChildren() && $item->isChildrenCalculated()) {
            foreach ($item->getChildren() as $child) {
                $child->setDiscountAmount(0);
                $child->setBaseDiscountAmount(0);
            }
        }
    }

    /**
     * @param QuoteModel\Item $item
     * @param $total
     */
    private function calculateDiscountPerItem(QuoteModel\Item $item, QuoteAddressTotal $total)
    {
        $this->calculator->process($item);
        $this->distributeDiscount($item);
        foreach ($item->getChildren() as $child) {
            $eventArgs[self::SALES_QUOTE_ADDRESS_DISCOUNT_ITEM_ARRAY_KEY] = $child;
            $this->eventManager->dispatch(
                'sales_quote_address_discount_item',
                $eventArgs
            );
            $this->aggregateItemDiscount($child, $total);
        }
    }
}
