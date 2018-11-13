<?php
/**
 * Created by PhpStorm.
 * User: claretyoung
 * Date: 01/03/2018
 * Time: 17:17
 */

namespace Sd\MultiCoupons\Model\Quote;

use Magento\Quote\Api\Data\ShippingAssignmentInterface as ShippingAssignmentInterface;
use Magento\Quote\Model\Quote as Quote;

class Discount extends \Magento\SalesRule\Model\Quote\Discount
{
    /**
     * @var null|\Magento\Store\Api\Data\StoreInterface
     */
    protected $store;

    /**
     * @var null|\Magento\Quote\Api\Data\AddressInterface
     */
    protected $address;

    /**
     * @inheritdoc
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Quote\Address\Total $total
    ) {
        Quote\Address\Total\AbstractTotal::collect($quote, $shippingAssignment, $total);

        $this->store = $this->storeManager->getStore($quote->getStoreId());
        $this->address = $shippingAssignment->getShipping()->getAddress();
        $this->calculator->reset($this->address);

        $items = $shippingAssignment->getItems();
        if (!count($items)) {
            return $this;
        }

        $couponsCode = $quote->getCouponCode();
        $coupons = explode(',', $couponsCode);
        $couponsArray = is_array($coupons) ? $coupons : [$coupons];
        $items = $this->calculator->sortItemsByPriority($items, $this->address);
        foreach ($couponsArray as $couponCodeValue) {
            $this->applyCoupon($couponCodeValue, $items, $quote, $total);
            $this->calculateTotal($total);
        }
        return $this;
    }

    /**
     * @param Quote\Address\Total $total
     */
    private function calculateTotal($total)
    {
        /** Process shipping amount discount */
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
     * @param string $couponCodeValue
     * @param array $items
     * @param Quote $quote
     * @param Quote\Address\Total $total
     */
    public function applyCoupon($couponCodeValue, $items, $quote, $total)
    {
        $this->calculator->init(
            $this->store->getWebsiteId(),
            $quote->getCustomerGroupId(),
            $couponCodeValue
        );
        $this->calculator->initTotals($items, $this->address);

        $eventArgs = [
            'website_id' => $this->store->getWebsiteId(),
            'customer_group_id' => $quote->getCustomerGroupId(),
            'coupon_code' => $couponCodeValue,
        ];

        $this->address->setDiscountDescription([]);

        /** @var Quote\Item $item */
        foreach ($items as $item) {
            if ($item->getNoDiscount() || !$this->calculator->canApplyDiscount($item)) {
                $this->resetDiscountPerItem($item);
                continue;
            }

            // to determine the child item discount, we calculate the parent
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

    private function resetDiscountPerItem($item)
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

    private function calculateDiscountPerItem($item, $total)
    {
        $this->calculator->process($item);
        $this->distributeDiscount($item);
        foreach ($item->getChildren() as $child) {
            $eventArgs['item'] = $child;
            $this->eventManager->dispatch(
                'sales_quote_address_discount_item',
                $eventArgs
            );
            $this->aggregateItemDiscount($child, $total);
        }
    }
}
