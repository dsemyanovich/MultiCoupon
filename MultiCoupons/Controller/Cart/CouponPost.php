<?php
/**
 * Created by PhpStorm.
 * User: claretyoung
 * Date: 27/02/2018
 * Time: 16:39
 */

namespace Sd\MultiCoupons\Controller\Cart;

use \Magento\Checkout\Helper\Cart as CartHelper;


use Magento\Checkout\Controller\Cart;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;



/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CouponPost extends \Magento\Checkout\Controller\Cart
{

    /**
     * Sales quote repository
     *
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * Coupon factory
     *
     * @var \Magento\SalesRule\Model\CouponFactory
     */
    protected $couponFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \Magento\SalesRule\Model\CouponFactory $couponFactory
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @codeCoverageIgnore
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\SalesRule\Model\CouponFactory $couponFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
    )
    {
        parent::__construct (
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
     * Initialize coupon
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $couponCodes = $this->getRequest()->getParam ('remove') == 1
            ? ''
            : $this->getRequest()->getParam ('coupon_code');

        $cartQuote = $this->cart->getQuote();
        $oldCouponCode = $cartQuote->getCouponCode();

        if (empty($couponCodes)) {
            return $this->_goBack();
        }

        $validatedCodes = [];
        foreach ($couponCodes as $code) {
            if ($this->isValidCouponCode($code)) {
                $validatedCodes[] = $code;
            }
        }

        $itemsCount = $cartQuote->getItemsCount();

        if ($itemsCount) {
            if ($oldCouponCode) {
                $arrayOldCouponCodes = explode(',', $oldCouponCode);
            } else {
                $arrayOldCouponCodes = [];
            }

            $arrayNewCoupons = array_diff($validatedCodes, $arrayOldCouponCodes);
            $resultCoupons = array_merge($arrayOldCouponCodes, $arrayNewCoupons);
            $resultCoupons = implode(',', $resultCoupons);

            if ($resultCoupons && $oldCouponCode != $resultCoupons) {
                try {
                    $cartQuote->setCouponCode($resultCoupons)->collectTotals();

                    $this->_checkoutSession->getQuote()->setCouponCode($oldCouponCode)->save();

                    $this->messageManager->addSuccess(
                        __ (
                            'You used coupon code "%1".',
                            $this->_objectManager
                                ->get(Escaper::class)
                                ->escapeHtml($resultCoupons)
                        )
                    );
                } catch (\Exception $e) {
                    $this->messageManager->addError($e->getMessage());
                }
            }
        }


        return $this->_goBack();
    }

    public function isValidCouponCode(&$code)
    {
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
            $this->messageManager->addError (
                __ (
                    'The coupon code "%1" is not valid.',
                    $this->_objectManager
                        ->get(Escaper::class)
                        ->escapeHtml ($code)
                )
            );

            return false;
        }
    }
}
