<?php

namespace Sd\MultiCoupons\Test\Controller\Cart;

class CouponPostTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Index
     */
    protected $controller;

    /**
     * @var \Magento\Checkout\Model\Session | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Framework\App\Request\Http | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $request;

    /**
     * @var \Magento\Framework\App\Response\Http | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $response;

    /**
     * @var \Magento\Quote\Model\Quote | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $quote;

    /**
     * @var \Magento\Framework\Event\Manager | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $eventManager;

    /**
     * @var \Magento\Framework\Event\Manager | \PHPUnit_Framework_MockObject_MockObject
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
        $this->request = $this->createMock(\Magento\Framework\App\Request\Http::class);
        $this->response = $this->createMock(\Magento\Framework\App\Response\Http::class);
        $this->quote = $this->createPartialMock(\Magento\Quote\Model\Quote::class, [
            'setCouponCode',
            'getItemsCount',
            'getShippingAddress',
            'setCollectShippingRates',
            'getCouponCode',
            'collectTotals',
            'save'
        ]);
        $this->eventManager = $this->createMock(\Magento\Framework\Event\Manager::class);
        $this->checkoutSession = $this->createMock(\Magento\Checkout\Model\Session::class);

        $this->objectManagerMock = $this->createPartialMock(\Magento\Framework\ObjectManager\ObjectManager::class, [
            'get', 'escapeHtml'
        ]);

        $this->messageManager = $this->getMockBuilder(\Magento\Framework\Message\ManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $context = $this->createMock(\Magento\Framework\App\Action\Context::class);
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

        $this->redirectFactory =
            $this->createMock(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $this->redirect = $this->createMock(\Magento\Store\App\Response\Redirect::class);

        $this->redirect->expects($this->any())
            ->method('getRefererUrl')
            ->willReturn(null);

        $context->expects($this->once())
            ->method('getRedirect')
            ->willReturn($this->redirect);

        $context->expects($this->once())
            ->method('getResultRedirectFactory')
            ->willReturn($this->redirectFactory);

        $this->cart = $this->getMockBuilder(\Magento\Checkout\Model\Cart::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->couponFactory = $this->getMockBuilder(\Magento\SalesRule\Model\CouponFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $this->quoteRepository = $this->createMock(\Magento\Quote\Api\CartRepositoryInterface::class);

        $objectManagerHelper = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);

        $this->controller = $objectManagerHelper->getObject(
            \Sd\MultiCoupons\Controller\Cart\CouponPost::class,
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
        $this->request->expects($this->at(0))
            ->method('getParam')
            ->with('remove')
            ->willReturn([]);

        $this->request->expects($this->at(1))
            ->method('getParam')
            ->with('coupon_code')
            ->willReturn([]);

        $this->cart->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->controller->execute();
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::execute
     */
    public function testExecuteWithStringCoupon()
    {
        $this->request->expects($this->at(0))
            ->method('getParam')
            ->with('remove')
            ->willReturn([]);

        $this->request->expects($this->at(1))
            ->method('getParam')
            ->with('coupon_code')
            ->willReturn('');

        $this->cart->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->controller->execute();
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::execute
     */
    public function testExecuteWithRemoveAndEmptyCoupon()
    {
        $this->request->expects($this->at(0))
            ->method('getParam')
            ->with('remove')
            ->willReturn(['REMOVECOUPON']);

        $this->request->expects($this->at(1))
            ->method('getParam')
            ->with('coupon_code')
            ->willReturn([]);

        $this->quote->expects($this->at(0))
            ->method('getCouponCode')
            ->willReturn('TEST,REMOVECOUPON');

        $this->cart->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->quote->expects($this->any())
            ->method('getItemsCount')
            ->willReturn(1);

        $shippingAddress = $this->createMock(\Magento\Quote\Model\Quote\Address::class);

        $this->quote->expects($this->any())
            ->method('setCollectShippingRates')
            ->with(true);

        $this->quote->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($shippingAddress);

        $this->quote->expects($this->any())
            ->method('collectTotals')
            ->willReturn($this->quote);

        $this->quote->expects($this->any())
            ->method('setCouponCode')
            ->with('TEST')
            ->willReturnSelf();

        $this->quote->expects($this->any())
            ->method('getCouponCode')
            ->willReturn('TEST');

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
    public function testExecuteWithRemoveAndCoupon()
    {
        $this->request->expects($this->at(0))
            ->method('getParam')
            ->with('remove')
            ->willReturn(['REMOVECOUPON']);

        $this->request->expects($this->at(1))
            ->method('getParam')
            ->with('coupon_code')
            ->willReturn(['TEST1']);

        $this->cart->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->quote->expects($this->at(0))
            ->method('getCouponCode')
            ->willReturn('TEST,REMOVECOUPON');

        $this->quote->expects($this->any())
            ->method('getItemsCount')
            ->willReturn(1);

        $coupon = $this->createMock(\Magento\SalesRule\Model\Coupon::class);
        $this->couponFactory->expects($this->once())
            ->method('create')
            ->willReturn($coupon);

        $coupon->expects($this->once())->method('load')->willReturnSelf();
        $coupon->expects($this->once())->method('getId')->willReturn(1);
        $this->quote->expects($this->any())
            ->method('getItemsCount')
            ->willReturn(1);

        $shippingAddress = $this->createMock(\Magento\Quote\Model\Quote\Address::class);

        $this->quote->expects($this->any())
            ->method('setCollectShippingRates')
            ->with(true);

        $this->quote->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($shippingAddress);

        $this->quote->expects($this->any())
            ->method('collectTotals')
            ->willReturn($this->quote);

        $this->quote->expects($this->any())
            ->method('setCouponCode')
            ->with('TEST,TEST1')
            ->willReturnSelf();

        $this->quote->expects($this->any())
            ->method('getCouponCode')
            ->willReturn('TEST,TEST1');

        $this->checkoutSession->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->quote->expects($this->any())
            ->method('setCouponCode')
            ->with('TEST,TEST1')
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
    public function testExecuteWithGoodCouponAndItems()
    {
        $this->request->expects($this->at(0))
            ->method('getParam')
            ->with('remove')
            ->willReturn([]);

        $this->request->expects($this->at(1))
            ->method('getParam')
            ->with('coupon_code')
            ->willReturn(['TEST']);

        $this->cart->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->quote->expects($this->at(0))
            ->method('getCouponCode')
            ->willReturn('');

        $this->quote->expects($this->any())
            ->method('getItemsCount')
            ->willReturn(1);

        $coupon = $this->createMock(\Magento\SalesRule\Model\Coupon::class);
        $this->couponFactory->expects($this->once())
            ->method('create')
            ->willReturn($coupon);

        $coupon->expects($this->once())->method('load')->willReturnSelf();
        $coupon->expects($this->once())->method('getId')->willReturn(1);
        $this->quote->expects($this->any())
            ->method('getItemsCount')
            ->willReturn(1);

        $shippingAddress = $this->createMock(\Magento\Quote\Model\Quote\Address::class);

        $this->quote->expects($this->any())
            ->method('setCollectShippingRates')
            ->with(true);

        $this->quote->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($shippingAddress);

        $this->quote->expects($this->any())
            ->method('collectTotals')
            ->willReturn($this->quote);

        $this->quote->expects($this->any())
            ->method('setCouponCode')
            ->with('TEST')
            ->willReturnSelf();

        $this->quote->expects($this->any())
            ->method('getCouponCode')
            ->willReturn('TEST');

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
        $this->request->expects($this->at(0))
            ->method('getParam')
            ->with('remove')
            ->willReturn(['']);

        $this->request->expects($this->at(1))
            ->method('getParam')
            ->with('coupon_code')
            ->willReturn([]);

        $this->cart->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->quote->expects($this->at(0))
            ->method('getCouponCode')
            ->willReturn('');

        $this->quote->expects($this->any())
            ->method('getItemsCount')
            ->willReturn(1);

        $shippingAddress = $this->createMock(\Magento\Quote\Model\Quote\Address::class);

        $this->quote->expects($this->any())
            ->method('setCollectShippingRates')
            ->with(true);

        $this->quote->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($shippingAddress);

        $this->quote->expects($this->any())
            ->method('collectTotals')
            ->willReturn($this->quote);

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
        $code = 'TEST';
        $coupon = $this->createMock(\Magento\SalesRule\Model\Coupon::class);
        $this->couponFactory->expects($this->once())
            ->method('create')
            ->willReturn($coupon);
        $coupon->expects($this->once())->method('load')->willReturnSelf();
        $coupon->expects($this->once())->method('getId')->willReturn(1);
        $this->assertTrue($this->controller->isValidCouponCode($code));
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::isValidCouponCode
     */
    public function testIsValidCouponCodeWithBadCode()
    {
        $code = 'TEST_TEST';
        $coupon = $this->createMock(\Magento\SalesRule\Model\Coupon::class);
        $this->couponFactory->expects($this->once())
            ->method('create')
            ->willReturn($coupon);
        $coupon->expects($this->once())->method('load')->willReturnSelf();
        $coupon->expects($this->once())->method('getId')->willReturn(0);

        $this->messageManager->expects($this->once())
            ->method('addError')
            ->willReturnSelf();

        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->willReturnSelf();

        $this->controller->isValidCouponCode($code);
    }

    /**
     * @covers \Sd\MultiCoupons\Controller\Cart\CouponPost::execute
     */
    public function testExecuteWithException()
    {
        $this->request->expects($this->at(0))
            ->method('getParam')
            ->with('remove')
            ->willReturn(['REMOVECOUPON']);

        $this->request->expects($this->at(1))
            ->method('getParam')
            ->with('coupon_code')
            ->willReturn([]);

        $this->quote->expects($this->at(0))
            ->method('getCouponCode')
            ->willReturn('TEST,REMOVECOUPON');

        $this->cart->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quote);

        $this->quote->expects($this->any())
            ->method('getItemsCount')
            ->willReturn(1);

        $this->quote->expects($this->any())
            ->method('getShippingAddress')
            ->willThrowException(new \Exception('Same problem'));

        $this->messageManager->expects($this->once())
            ->method('addError')
            ->willReturnSelf();

        $this->controller->execute();
    }
}