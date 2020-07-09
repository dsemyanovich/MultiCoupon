<?php

namespace Sd\MultiCoupons\Test\Controller\Cart;

use \Magento\Checkout\Model\Session as SessionModel;
use \Magento\Framework\App\Request\Http as HttpRequest;
use \Magento\Framework\App\Request\Http as HttpResponse;
use \Magento\Quote\Model\Quote as QuoteModel;
use \Magento\Checkout\Model\Cart as CartModel;
use \Magento\Framework\Event\Manager as EventManager;
use \Magento\Framework\ObjectManager\ObjectManager as ObjectManager;
use \Magento\Framework\Message\ManagerInterface as ManagerInterface;
use \Magento\Framework\App\Action\Context as ActionContext;
use \Magento\Framework\Controller\Result\RedirectFactory as RedirectFactory;
use \Magento\Store\App\Response\Redirect as ResponseRedirect;
use \Magento\SalesRule\Model\CouponFactory as CouponFactory;
use \Magento\Quote\Api\CartRepositoryInterface as CartRepositoryInterface;
use \Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use \Sd\MultiCoupons\Controller\Cart\CouponPost as SdCouponPost;
use \Magento\SalesRule\Model\Coupon as CouponModel;

class CouponPostTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Index
     */
    protected $controller;

    /**
     * @var SessionModel | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $checkoutSession;

    /**
     * @var HttpRequest | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $request;

    /**
     * @var HttpResponse | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $response;

    /**
     * @var QuoteModel | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $quote;

    /**
     * @var EventManager | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $eventManager;

    /**
     * @var EventManager | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $objectManagerMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $cart;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $messageManager;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $couponFactory;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $quoteRepository;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $redirect;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $redirectFactory;

    /**
     * @return void
     */
    protected function setUp()
    {
        $this->request = $this->createMock(HttpRequest::class);
        $this->response = $this->createMock(HttpResponse::class);
        $this->quote = $this->createPartialMock(QuoteModel::class, [
            'setCouponCode',
            'getItemsCount',
            'getShippingAddress',
            'setCollectShippingRates',
            'getCouponCode',
            'collectTotals',
            'save'
        ]);
        $this->eventManager = $this->createMock(EventManager::class);
        $this->checkoutSession = $this->createMock(SessionModel::class);
        $this->objectManagerMock = $this->createPartialMock(ObjectManager::class, [
            'get', 'escapeHtml'
        ]);
        $this->messageManager = $this->getMockBuilder(ManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $context = $this->createMock(ActionContext::class);

        $context->expects($this->once())
            ->method('getObjectManager')
            ->willReturn($this->objectManagerMock);
        $context->expects($this->once())
            ->method('getRequest')
            ->willReturn($this->request);
        $context->expects($this->once())
            ->method('getResponse')
            ->willReturn($this->response);
        $context->expects($this->once())
            ->method('getEventManager')
            ->willReturn($this->eventManager);
        $context->expects($this->once())
            ->method('getMessageManager')
            ->willReturn($this->messageManager);

        $this->redirectFactory = $this->createMock(RedirectFactory::class);
        $this->redirect = $this->createMock(ResponseRedirect::class);

        $this->redirect->expects($this->any())
            ->method('getRefererUrl')
            ->willReturn(null);
        $context->expects($this->once())
            ->method('getRedirect')
            ->willReturn($this->redirect);
        $context->expects($this->once())
            ->method('getResultRedirectFactory')
            ->willReturn($this->redirectFactory);

        $this->cart = $this->getMockBuilder(CartModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->couponFactory = $this->getMockBuilder(CouponFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        $this->quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $objectManagerHelper = new ObjectManagerHelper($this);
        $this->controller = $objectManagerHelper->getObject(
            SdCouponPost::class,
            [
                'context' => $context,
                'checkoutSession' => $this->checkoutSession,
                'cart' => $this->cart,
                'couponFactory' => $this->couponFactory,
                'quoteRepository' => $this->quoteRepository
            ]
        );
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::execute
     */
    public function testExecuteWithEmptyCouponAndRemove()
    {
        $this->getCouponCodesAndQuoteItemsCount(0);

        $this->controller->execute();
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::execute
     */
    public function testExecuteWithRemoveAndEmptyCoupon()
    {
        $this->getCouponCodesAndQuoteItemsCount(1, [], ['REMOVECOUPON']);

        $this->messageManager->expects($this->once())
            ->method('addSuccess')
            ->willReturnSelf();

        $this->controller->execute();
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::execute
     */
    public function testExecuteWithRemoveAndCoupon()
    {
        $this->getCouponCodesAndQuoteItemsCount(1, ['TEST1'], ['REMOVECOUPON']);

        $this->loadCouponCode(1);

        $shippingAddress = $this->createMock(QuoteModel\Address::class);

        $this->quote->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($shippingAddress);

        $this->quote->expects($this->any())
            ->method('setCouponCode')
            ->with('TEST1')
            ->willReturnSelf();

        $this->checkoutSession->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->messageManager->expects($this->once())
            ->method('addSuccess')
            ->willReturnSelf();

        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->willReturnSelf();

        $this->controller->execute();
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::execute
     */
    public function testExecuteWithGoodCouponAndItems()
    {
        $this->getCouponCodesAndQuoteItemsCount(1, ['TEST']);

        $this->quote->expects($this->at(0))
            ->method('getCouponCode')
            ->willReturn('');

        $this->loadCouponCode(1);

        $shippingAddress = $this->createMock(QuoteModel\Address::class);

        $this->quote->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($shippingAddress);

        $this->quote->expects($this->any())
            ->method('setCouponCode')
            ->with('TEST')
            ->willReturnSelf();

        $this->checkoutSession->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->quote->expects($this->any())
            ->method('setCouponCode')
            ->with('TEST')
            ->willReturnSelf();

        $this->messageManager->expects($this->once())
            ->method('addSuccess')
            ->willReturnSelf();

        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->willReturnSelf();

        $this->controller->execute();
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::execute
     */
    public function testExecuteWithBadCouponAndItems()
    {
        $this->getCouponCodesAndQuoteItemsCount(1);

        $this->quote->expects($this->at(0))
            ->method('getCouponCode')
            ->willReturn('');

        $shippingAddress = $this->createMock(QuoteModel\Address::class);

        $this->quote->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($shippingAddress);

        $this->quote->expects($this->any())
            ->method('setCouponCode')
            ->with('')
            ->willReturnSelf();

        $this->messageManager->expects($this->once())
            ->method('addSuccess')
            ->with('You canceled the coupon code.')
            ->willReturnSelf();

        $this->controller->execute();
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::isValidCouponCode
     */
    public function testIsValidCouponCodeWithEmptyCode()
    {
        $code = '';

        $this->assertFalse($this->controller->isValidCouponCode($code));
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::isValidCouponCode
     */
    public function testIsValidCouponCodeWithGoodCode()
    {
        $this->loadCouponCode(1);

        $code = 'TEST';

        $this->assertTrue($this->controller->isValidCouponCode($code));
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::isValidCouponCode
     */
    public function testIsValidCouponCodeWithBadCode()
    {
        $this->loadCouponCode(0);

        $this->messageManager->expects($this->once())
            ->method('addError')
            ->willReturnSelf();

        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->willReturnSelf();

        $code = 'BAD_COUPON';

        $this->assertFalse($this->controller->isValidCouponCode($code));
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::execute
     */
    public function testExecuteWithException()
    {
        $this->getCouponCodesAndQuoteItemsCount(1, ['TEST'], ['REMOVECOUPON']);

        $this->loadCouponCode(1);

        $this->cart->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->quote->expects($this->any())
            ->method('getShippingAddress')
            ->willThrowException(new \Exception('Same problem'));

        $this->messageManager->expects($this->once())
            ->method('addError')
            ->willReturnSelf();

        $this->controller->execute();
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::getCouponCodes
     */
    public function testGetCouponCodesWithCouponAndRemoveCode()
    {
        $cartQuote = $this->quote;
        $couponCodes = ['TEST'];
        $removeCoupons = ['REMOVECOUPON'];

        $this->quote->expects($this->any())
            ->method('getCouponCode')
            ->willReturn('REMOVECOUPON,TEST1');

        $this->loadCouponCode(1);

        $this->assertEquals('TEST1,TEST', $this->controller->getCouponCodes($cartQuote, $couponCodes, $removeCoupons));
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::getCouponCodes
     */
    public function testGetCouponCodesWithEqualCouponAndOldCode()
    {
        $cartQuote = $this->quote;
        $couponCodes = ['TEST'];
        $removeCoupons = ['REMOVECOUPON'];

        $this->quote->expects($this->any())
            ->method('getCouponCode')
            ->willReturn('TEST');

        $this->loadCouponCode(1);

        $this->assertEquals('', $this->controller->getCouponCodes($cartQuote, $couponCodes, $removeCoupons));
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::getCouponCodes
     */
    public function testGetCouponCodesWithCouponCodes()
    {
        $cartQuote = $this->quote;
        $couponCodes = ['TEST'];
        $removeCoupons = [];

        $this->loadCouponCode(1);

        $this->assertEquals('TEST', $this->controller->getCouponCodes($cartQuote, $couponCodes, $removeCoupons));
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::getCouponCodes
     */
    public function testGetCouponCodesWithEmptyCodes()
    {
        $cartQuote = $this->quote;
        $couponCodes = ['TEST123'];
        $removeCoupons = [];

        $this->loadCouponCode(0);

        $this->messageManager->expects($this->once())
            ->method('addError')
            ->willReturnSelf();

        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->willReturnSelf();

        $this->assertEquals('', $this->controller->getCouponCodes($cartQuote, $couponCodes, $removeCoupons));
    }

    /**
     * @param int $id
     */
    public function loadCouponCode(int $id)
    {
        $coupon = $this->createMock(CouponModel::class);
        $this->couponFactory->expects($this->once())
            ->method('create')
            ->willReturn($coupon);
        $coupon->expects($this->once())->method('load')->willReturnSelf();
        $coupon->expects($this->once())->method('getId')->willReturn($id);
    }

    /**
     * @param int $itemCount
     * @param array $couponCode
     * @param array $removeCoupon
     */
    public function getCouponCodesAndQuoteItemsCount(
        int $itemCount = 0,
        array $couponCode = [],
        array $removeCoupon = []
    ) {
        $this->cart->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quote);
        $this->quote->expects($this->any())
            ->method('getItemsCount')
            ->willReturn($itemCount);

        if ($itemCount) {
            $this->request->expects($this->at(1))
                ->method('getParam')
                ->with('coupon_code')
                ->willReturn($couponCode);
            $this->request->expects($this->at(0))
                ->method('getParam')
                ->with('remove')
                ->willReturn($removeCoupon);
        }
    }
}
