<?php
/**
 * @category    CryPay
 * @package     CryPay_Merchant
 * @author      CryPay
 * @copyright   CryPay (https://crypay.com)
 * @license     https://github.com/crypay/magento2-plugin/blob/master/LICENSE The MIT License (MIT)
 */

declare(strict_types = 1);

namespace CryPay\Merchant\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Module\ResourceInterface;

/**
 * Class ConfigManagement
 */
class ConfigManagement
{
    /**
     * @var string
     */
    private const NAME = 'CryPay_Merchant';

    /**
     * @var string
     */
    private const XML_PATH_PAYMENT_CRYPAY_MERCHANT_API_KEY = 'payment/crypay_merchant/api_key';

    /**
     * @var string
     */
    private const XML_PATH_PAYMENT_CRYPAY_MERCHANT_API_SECRET = 'payment/crypay_merchant/api_secret';

    /**
     * @var string
     */
    private const XML_PATH_PAYMENT_CRYPAY_MERCHANT_TEST_MODE = 'payment/crypay_merchant/test_mode';

    private ScopeConfigInterface $scopeConfig;
    private StoreManagerInterface $storeManager;
    private LoggerInterface $logger;
    private ResourceInterface $resource;
    private ?int $storeId = null;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param ResourceInterface $resource
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ResourceInterface $resource
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->resource = $resource;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->resource->getDataVersion(self::NAME);
    }

    /**
     * Get Api Authorization Key
     *
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_PAYMENT_CRYPAY_MERCHANT_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );
    }

    /**
     * Get Api Authorization Secret
     *
     * @return string|null
     */
    public function getApiSecret(): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_PAYMENT_CRYPAY_MERCHANT_API_SECRET,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );
    }

    /**
     * Get Status Mode
     *
     * @return bool
     */
    public function isTestMode(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PAYMENT_CRYPAY_MERCHANT_TEST_MODE,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        ) ?? false;
    }

    /**
     * Get Store Id
     *
     * @return int|null
     */
    private function getStoreId(): ?int
    {
        if (!$this->storeId) {
            try {
                $store = $this->storeManager->getStore();
                $this->storeId = (int) $store->getId();
            } catch (NoSuchEntityException $exception) {
                $this->logger->critical($exception->getMessage());

                return $this->storeId;
            }
        }

        return $this->storeId;
    }
}
