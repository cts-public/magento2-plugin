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

        if (!$this->crypayPayment->validateCryPayCallback($order, $requestBody, $signature)) {
            return $this->response->setStatusHeader(
                Response::STATUS_CODE_404,
                AbstractMessage::VERSION_11,
                self::NOT_FOUND_PHRASE
            );
        }

        $this->eventManager->dispatch('crypay_merchant_callback_send', ['order' => $order]);

        return $this->response->setStatusHeader(Response::STATUS_CODE_200, AbstractMessage::VERSION_11);
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
