<?php
/**
 * @category    CryPay
 * @package     CryPay_Merchant
 * @author      CryPay
 * @copyright   CryPay (https://crypay.com)
 * @license     https://github.com/crypay/magento2-plugin/blob/master/LICENSE The MIT License (MIT)
 */

declare(strict_types = 1);

namespace CryPay\Merchant\Api;

use CryPay\Merchant\Api\Response\PlaceOrderInterface;

/**
 * Interface PaymentInterface
 */
interface PaymentInterface
{
    /**
     * @return \CryPay\Merchant\Api\Response\PlaceOrderInterface
     */
    public function placeOrder(): PlaceOrderInterface;
}
