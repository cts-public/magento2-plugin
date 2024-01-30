<?php
/**
 * @category    CryPay
 * @package     CryPay_Merchant
 * @author      CryPay
 * @copyright   CryPay (https://crypay.com)
 * @license     https://github.com/crypay/magento2-plugin/blob/master/LICENSE The MIT License (MIT)
 */

declare(strict_types=1);

namespace CryPay\Merchant\Model;

use CryPay\Client;
use CryPay\Exception\ApiErrorException;
use CryPay\Resources\CreateOrder;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\StoreManagerInterface;
use CryPay\Merchant\Model\Ui\ConfigProvider;
use Magento\Framework\App\ProductMetadataInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Payment
 */
class Payment
{
    /**
     * @var string
     */
    public const CRYPAY_ORDER_TOKEN_KEY = 'crypay_order_token';

    /**
     * @var string
     */
    private const PAID_STATUS = 'SUCCESS';

    /**
     * @var array
     */
    private const STATUSES_FOR_CANSEL = [
        'EXPIRED'
    ];

    private UrlInterface $urlBuilder;
    private StoreManagerInterface $storeManager;
    private OrderManagementInterface $orderManagement;
    private OrderPaymentRepositoryInterface $paymentRepository;
    private ?Client $client = null;
    private ConfigManagement $configManagement;
    private OrderRepository $orderRepository;
    private LoggerInterface $logger;
    private ProductMetadataInterface $metadata;

    /**
     * @param UrlInterface $urlBuilder
     * @param StoreManagerInterface $storeManager
     * @param OrderManagementInterface $orderManagement
     * @param OrderPaymentRepositoryInterface $paymentRepository
     * @param ConfigManagement $configManagement
     * @param OrderRepository $orderRepository
     * @param LoggerInterface $logger
     * @param ProductMetadataInterface $metadata
     */
    public function __construct(
        UrlInterface                    $urlBuilder,
        StoreManagerInterface           $storeManager,
        OrderManagementInterface        $orderManagement,
        OrderPaymentRepositoryInterface $paymentRepository,
        ConfigManagement                $configManagement,
        OrderRepository                 $orderRepository,
        LoggerInterface                 $logger,
        ProductMetadataInterface        $metadata
    )
    {
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->orderManagement = $orderManagement;
        $this->paymentRepository = $paymentRepository;
        $this->configManagement = $configManagement;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->metadata = $metadata;
    }

    /**
     * Get CryPay Order From API
     *
     * @param OrderInterface $order
     *
     * @return CreateOrder|mixed
     */
    public function getCryPayOrder(OrderInterface $order)
    {
        $description = [];
        $token = substr(hash('sha256', (string)rand()), 0, 32);
        $payment = $order->getPayment();
        $payment->setAdditionalInformation(self::CRYPAY_ORDER_TOKEN_KEY, $token);
        $this->paymentRepository->save($payment);

        try {
            $params = $this->getCryPayOrderParams($order);
        } catch (LocalizedException $exception) {
            $this->logger->critical($exception->getMessage());

            return null;
        }

        $client = $this->getClient();

        try {
            $cgOrder = $client->payment->create($params);
        } catch (ApiErrorException $exception) {
            $this->logger->critical($exception->getMessage());

            return null;
        }

        return $cgOrder;
    }

    /**
     * Validate CryPay Callback
     *
     * @param Order $order
     * @param String $data
     * @param String $signature
     *
     * @return bool
     */
    public function validateCryPayCallback(Order $order, String $data, String $signature): bool
    {
        if (!$this->isCrypayPaymentMerchant($order)) {
            return false;
        }

        try {
            $client = $this->getClient();
            $hash = $client->generateSignature($data, $this->configManagement->getApiSecret());

            if($hash != $signature) {
                throw new Exception('Signature missmatch');
            }
            $dataOrder = \Safe\json_decode($data);

            if ($dataOrder->state === self::PAID_STATUS) {
                $order->setState(Order::STATE_PROCESSING);
                $orderConfig = $order->getConfig();
                $order->setStatus($orderConfig->getStateDefaultStatus(Order::STATE_PROCESSING));
                $this->orderRepository->save($order);
            } elseif (in_array($dataOrder->state, self::STATUSES_FOR_CANSEL)) {
                $this->orderManagement->cancel($order->getId());
            }
        } catch (Exception $exception) {
            $this->logger->critical($exception);

            return false;
        }

        return true;
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    public function isCrypayPaymentMerchant(Order $order): bool
    {
        $payment = $order->getPayment();

        return $payment->getMethod() === ConfigProvider::CODE;
    }

    /**
     * Get Http CryPay Client
     *
     * @return Client|null
     */
    private function getClient(): ?Client
    {
        if (!$this->client) {
            $environment = $this->configManagement->isTestMode();
            Client::setAppInfo($this->configManagement->getName(), $this->configManagement->getVersion());
            $this->client = new Client($this->configManagement->getApiKey(), $environment);
        }

        return $this->client;
    }

    /**
     * @param OrderInterface $order
     * @param array $description
     *
     * @return array
     *
     * @throws LocalizedException
     */
    private function getCryPayOrderParams(OrderInterface $order): array
    {
        $params = [
            'variableSymbol' => (string)$order->getIncrementId(),
            'amount' => (float)$order->getGrandTotal(),
            'symbol' => $order->getOrderCurrencyCode(),
            'currency' => $order->getOrderCurrencyCode(),
            'failUrl' => $this->urlBuilder->getUrl('crypay/payment/cancelOrder'),
            'successUrl' => $this->urlBuilder->getUrl('checkout/onepage/success'),
            'timestamp' => time(),
            'name' => $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),
            'email' => $order->getCustomerEmail(),
        ];

        return $params;
    }
}
