<?php

namespace Sd\MultiCoupons\Controller\Cart;

use Magento\Checkout\Helper\Cart as CartHelper;

use Magento\Checkout\Model\Cart as Cart;
use Magento\Quote\Model\Quote as MagentoQuote;
use Magento\Checkout\Model\Session as Session;
use Magento\Framework\App\Action\Context as Context;
use Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfigInterface;
use Magento\Framework\Data\Form\FormKey\Validator as Validator;
use Magento\Framework\Escaper as Escaper;
use Magento\Quote\Api\CartRepositoryInterface as CartRepositoryInterface;
use Magento\SalesRule\Model\CouponFactory as CouponFactory;
use Magento\Store\Model\StoreManagerInterface as StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CouponPost extends \Magento\Checkout\Controller\Cart
{

    /**
     * Sales quote repository
     *
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * Coupon factory
     *
     * @var CouponFactory
     */
    protected $couponFactory;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param Session $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param Validator $formKeyValidator
     * @param Cart $cart
     * @param CouponFactory $couponFactory
     * @param CartRepositoryInterface $quoteRepository
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        Session $checkoutSession,
        StoreManagerInterface $storeManager,
        Validator $formKeyValidator,
        Cart $cart,
        CouponFactory $couponFactory,
        CartRepositoryInterface $quoteRepository
    ) {
        parent::__construct(
            $context,
            $scopeConfig,
            $checkoutSession,
            $storeManager,
            $formKeyValidator,
            $cart
        );

        $this->couponFactory = $couponFactory;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $cartQuote = $this->cart->getQuote();
        $itemsCount = $cartQuote->getItemsCount();

        if (!$itemsCount) {
            return $this->_goBack();
        }

        $removeCoupons = $this->getRequest()->getParam('remove') ?: [];
        $couponCodes = $this->getRequest()->getParam('coupon_code') ?: [];

        $resultCoupons = $this->getCouponCodes($cartQuote, $couponCodes, $removeCoupons);

        if ($resultCoupons) {
            try {
                $cartQuote->getShippingAddress()->setCollectShippingRates(true);
                $cartQuote->setCouponCode($resultCoupons)->collectTotals();
                $this->_checkoutSession->getQuote()->setCouponCode($resultCoupons)->save();
                $this->messageManager->addSuccess(
                    __(
                        'You used coupon code "%1".',
                        $this->_objectManager
                            ->get(Escaper::class)
                            ->escapeHtml($resultCoupons)
                    )
                );
            } catch (\Exception $e) {
                $this->messageManager->addError($e->getMessage());
            }
        } else {
            $this->messageManager->addSuccess(__('You canceled the coupon code.'));
        }

        return $this->_goBack();
    }

    /**
     * @param MagentoQuote $cartQuote
     * @param array $couponCodes
     * @param array $removeCoupons
     * @return string
     */
    public function getCouponCodes(
        MagentoQuote $cartQuote,
        array $couponCodes,
        array $removeCoupons
    ): string {
        $oldCouponCode = $cartQuote->getCouponCode();

        if ($oldCouponCode) {
            $arrayOldCouponCodes = explode(',', $oldCouponCode);
        } else {
            $arrayOldCouponCodes = [];
        }

        $validatedCodes = [];
        foreach ($couponCodes as $code) {
            if ($code && $this->isValidCouponCode($code)) {
                $validatedCodes[] = $code;
            }
        }

        $arrayOldCouponCodes = array_diff($arrayOldCouponCodes, $removeCoupons);
        $arrayNewCoupons = array_diff($validatedCodes, $arrayOldCouponCodes);
        $resultCoupons = implode(',', array_merge($arrayOldCouponCodes, $arrayNewCoupons));

        if ($oldCouponCode == $resultCoupons) {
            return '';
        }

        return $resultCoupons;
    }

    /**
     * @param string $code
     * @return bool
     */
    public function isValidCouponCode(string &$code): bool
    {
        if (!$code) {
            return false;
        }

        $code = trim($code);
        $codeLength = strlen($code);

        $coupon = $this->couponFactory->create();

        $coupon->load($code, 'code');
        if (
            $codeLength &&
            $codeLength <= CartHelper::COUPON_CODE_MAX_LENGTH &&
            $coupon->getId()
        ) {
            return true;
        } else {
            $this->messageManager->addError(
                __(
                    'The coupon code "%1" is not valid.',
                    $this->_objectManager
                        ->get(Escaper::class)
                        ->escapeHtml($code)
                )
            );

            return false;
        }
    }
}
