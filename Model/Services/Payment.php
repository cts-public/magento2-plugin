<?php
/**
 * @category    CryPay
 * @package     CryPay_Merchant
 * @author      CryPay
 * @copyright   CryPay (https://crypay.com)
 * @license     https://github.com/crypay/magento2-plugin/blob/master/LICENSE The MIT License (MIT)
 */

declare(strict_types = 1);

namespace CryPay\Merchant\Model\Services;

use CryPay\Merchant\Api\PaymentInterface;
use CryPay\Merchant\Api\Response\PlaceOrderInterface as Response;
use CryPay\Merchant\Model\Payment as CryPayPayment;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;

/**
 * Class Payment
 */
class Payment implements PaymentInterface
{
    private Response $response;
    private CheckoutSession $checkoutSession;
    private OrderRepository $orderRepository;
    private CartRepositoryInterface $quoteRepository;
    private CryPayPayment $cryPayPayment;
    private LoggerInterface $logger;

    /**
     * @param Response $response
     * @param CheckoutSession $checkoutSession
     * @param OrderRepository $orderRepository
     * @param CartRepositoryInterface $quoteRepository
     * @param CryPayPayment $cryPayPayment
     * @param LoggerInterface $logger
     */
    public function __construct(
        Response $response,
        CheckoutSession $checkoutSession,
        OrderRepository $orderRepository,
        CartRepositoryInterface $quoteRepository,
        CryPayPayment $cryPayPayment,
        LoggerInterface $logger
    ) {
        $this->response = $response;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        $this->cryPayPayment = $cryPayPayment;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function placeOrder(): Response
    {
        $orderId = $this->checkoutSession->getLastOrderId();

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (InputException | NoSuchEntityException $exception) {
            $this->logger->critical($exception->getMessage());
            $this->response->setStatus(false);

            return $this->response;
        }

        if (!$order->getIncrementId()) {
            $this->response->setStatus(false);

            return $this->response;
        }

        $quote = $this->quoteRepository->get($order->getQuoteId());
        $quote->setIsActive(1);
        $this->quoteRepository->save($quote);
        $cgOrder = $this->cryPayPayment->getCryPayOrder($order);

        if (!$cgOrder) {
            $this->response->setStatus(false);

            return $this->response;
        }

        $this->response->setStatus(true);
        $this->response->setPaymentUrl($cgOrder->shortLink);

        return $this->response;
    }
}
