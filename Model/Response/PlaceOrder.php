<?php
/**
 * @category    CryPay
 * @package     CryPay_Merchant
 * @author      CryPay
 * @copyright   CryPay (https://crypay.com)
 * @license     https://github.com/crypay/magento2-plugin/blob/master/LICENSE The MIT License (MIT)
 */

declare(strict_types = 1);

namespace CryPay\Merchant\Model\Response;

use CryPay\Merchant\Api\Response\PlaceOrderInterface as Response;

/**
 * Class PlaceOrder
 */
class PlaceOrder implements Response
{
    private string $paymentUrl = '';
    private bool $status = false;

    /**
     * @inheritDoc
     */
    public function getPaymentUrl(): string
    {
        return $this->paymentUrl;
    }

    /**
     * @inheritDoc
     */
    public function setPaymentUrl(string $paymentUrl): void
    {
        $this->paymentUrl = $paymentUrl;
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): bool
    {
        return $this->status;
    }

    /**
     * @inheritDoc
     */
    public function setStatus(bool $status): void
    {
        $this->status = $status;
    }
}
