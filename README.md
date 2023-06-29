# Magento 2 CryPay Plugin

Sign up for CryPay account at <https://crypay.com> for production and <https://dev.crypay.com> for testing (sandbox) environment.

Please note, that for "Test" mode you **must** generate separate API credentials on <https://dev.crypay.com>. API credentials generated on <https://crypay.com> will **not** work for "Test" mode.

## Installation via Composer

You can install Magento 2 CryPay plugin via [Composer](http://getcomposer.org/). Run the following command in your terminal:

1. Go to your Magento 2 root folder.

2. Enter following commands to install plugin:

    ```bash
    composer require crypay/magento2-plugin
    ```
   Wait while dependencies are updated.

3. Enter following commands to enable plugin:

    ```bash
    php bin/magento module:enable CryPay_Merchant --clear-static-content
    php bin/magento setup:upgrade
    ```

## Plugin Configuration

Enable and configure CryPay plugin in Magento Admin under `Stores / Configuration / Sales / Payment Methods / Bitcoin and Altcoins via CryPay`.
