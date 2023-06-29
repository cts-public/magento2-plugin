<?php

namespace CryPay\Merchant\Controller\Payment;

use CryPay\Merchant\Model\Payment as CryPayPayment;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Laminas\Http\Response;
use Laminas\Http\AbstractMessage;

class Callback implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var string
     */
    private const TOKEN_KEY = 'token';

    /**
     * @var string
     */
    private const VARIABLE_SYMBOL = 'variableSymbol';

    /**
     * @var string
     */
    private const ID_KEY = 'id';

    /**
     * @var string
     */
    private const NOT_FOUND_PHRASE = 'Not Found';

    /**
     * @var string
     */
    private const UNPROCESSABLE_CONTENT_PHRASE = 'Unprocessable Content';

    private ResponseInterface $response;
    private RequestInterface $request;
    private Order $order;
    private CryPayPayment $crypayPayment;
    private SerializerInterface $serializer;
    private EventManagerInterface $eventManager;

    /**
     * @param Order $order
     * @param CryPayPayment $crypayPayment
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param SerializerInterface $serializer
     * @param EventManagerInterface $eventManager
     */
    public function __construct(
        Order                 $order,
        CryPayPayment         $crypayPayment,
        RequestInterface      $request,
        ResponseInterface     $response,
        SerializerInterface   $serializer,
        EventManagerInterface $eventManager
    )
    {
        $this->order = $order;
        $this->crypayPayment = $crypayPayment;
        $this->request = $request;
        $this->response = $response;
        $this->serializer = $serializer;
        $this->eventManager = $eventManager;
    }

    /**
     * Execute action based on request and return result
     *
     * @return ResponseInterface
     */
    public function execute(): ResponseInterface
    {
        $requestBody = $this->request->getContent();
        $signature = $this->request->getHeaders()->get('X-Signature')->getFieldValue();
        $data = \Safe\json_decode($requestBody, true);

        $requestOrderId = $data[self::VARIABLE_SYMBOL] ?? '';

        if (!$requestOrderId) {
            return $this->response->setStatusHeader(
                Response::STATUS_CODE_422,
                AbstractMessage::VERSION_11,
                self::UNPROCESSABLE_CONTENT_PHRASE
            );
        }

        $order = $this->order->loadByIncrementId($requestOrderId);

        if (!$order->getId()) {
            return $this->response->setStatusHeader(
                Response::STATUS_CODE_422,
                AbstractMessage::VERSION_11,
                self::UNPROCESSABLE_CONTENT_PHRASE
            );
        }

        $payment = $order->getPayment();
//        $token = $this->request->getParam(self::TOKEN_KEY) ?? '';
//
//        if (!$token || $token !== $payment->getAdditionalInformation(CryPayPayment::CRYPAY_ORDER_TOKEN_KEY)) {
//            return $this->response->setStatusHeader(
//                Response::STATUS_CODE_422,
//                AbstractMessage::VERSION_11,
//                self::UNPROCESSABLE_CONTENT_PHRASE
//            );
//        }

        if (!$this->crypayPayment->validateCryPayCallback($order, $requestBody, $signature)) {
            return $this->response->setStatusHeader(
                Response::STATUS_CODE_404,
                AbstractMessage::VERSION_11,
                self::NOT_FOUND_PHRASE
            );
        }

        $this->eventManager->dispatch('crypay_merchant_callback_send', ['order' => $order]);

        return $this->response->setStatusHeader(Response::STATUS_CODE_200, AbstractMessage::VERSION_11);

        //$this->request = Tools::file_get_contents('php://input');
        //            $this->logInfo('CryPay reportPayload: ' . $this->request);
        //            $headers = $this->get_ds_headers();
        //            if (!array_key_exists("XSignature", $headers)) {
        //                $error_message = 'CryPay X-SIGNATURE: not found';
        //                $this->logError($error_message);
        //                throw new Exception($error_message, 400);
        //            }
        //
        //            $signature = $headers["XSignature"];
        //
        //            $this->requestData = json_decode($this->request, true);
        //            if (false === $this->checkIfRequestIsValid()) {
        //                $error_message = 'CryPay Request: not valid request data';
        //                $this->logError($error_message);
        //                throw new Exception($error_message, 400);
        //            }
        //
        //            if ($this->requestData['type'] !== 'PAYMENT') {
        //                $error_message = 'CryPay Request: not valid request type';
        //                $this->logError($error_message);
        //                throw new Exception($error_message, 400);
        //            }
        //
        //            $cart_id = (int)$this->requestData['variableSymbol'];
        //            $order_id = Order::getOrderByCartId($cart_id);
        //            $order = new Order($order_id);
        //            $currency = new Currency($order->id_currency);
        //
        //
        //            if (!$cart_id) {
        //                $error_message = 'Shop order #' . $this->requestData['variableSymbol'] . ' does not exists';
        //                $this->logError($error_message, $cart_id);
        //                throw new Exception($error_message, 400);
        //            }
        //
        //            if ($currency->iso_code != $this->requestData['currency']) {
        //                $error_message = 'CryPay Currency: ' . $this->requestData['currency'] . ' is not valid';
        //                $this->logError($error_message, $cart_id);
        //
        //                throw new Exception($error_message, 400);
        //            }
        //
        //            $apiKey = Configuration::get('CRYPAY_API_KEY');
        //            $environment = (Configuration::get('CRYPAY_TEST')) == 1;
        //            $client = new \CryPay\Client($apiKey, $environment);
        //
        //            $token = $client->generateSignature($this->request, Configuration::get('CRYPAY_API_SECRET'));
        //
        //            if (empty($signature) || strcmp($signature, $token) !== 0) {
        //                $error_message = 'CryPay X-SIGNATURE: ' . $signature . ' is not valid. valid X-SIGNATURE ' . $token;
        //                $this->logError($error_message, $cart_id);
        //                throw new Exception($error_message, 400);
        //            }
        //
        //            switch ($this->requestData['state']) {
        //                case 'SUCCESS':
        //                    if (((float)$order->getOrdersTotalPaid()) == ((float)$this->requestData['amount'])) {
        //                        $order_status = 'PS_OS_PAYMENT';
        //                        break;
        //                    } else {
        //                        $order_status = 'CRYPAY_INVALID';
        //                        $this->logError('PS Orders Total does not match with Crypay Price Amount', $cart_id);
        //                    }
        //                    break;
        //                case 'WAITING_FOR_PAYMENT':
        //                    $order_status = 'CRYPAY_PENDING';
        //                    break;
        //                case 'WAITING_FOR_CONFIRMATION':
        //                    $order_status = 'CRYPAY_CONFIRMING';
        //                    break;
        //                case 'EXPIRED':
        //                    $order_status = 'CRYPAY_EXPIRED';
        //                    break;
        //                default:
        //                    $order_status = false;
        //            }
        //
        //            if ($order_status && Configuration::get($order_status) != $order->current_state && $order->current_state != Configuration::get('PS_OS_PAYMENT')) {
        //                $history = new OrderHistory();
        //                $history->id_order = $order->id;
        //                $history->changeIdOrderState((int)Configuration::get($order_status), $order->id);
        //                $history->addWithemail(true, array(
        //                    'order_name' => $cart_id,
        //                ));
        //
        //                $this->response('OK');
        //
        //            } else {
        //                $this->response('Order Status ' . $this->requestData['state'] . ' not implemented');
        //            }
    }

    /**
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
