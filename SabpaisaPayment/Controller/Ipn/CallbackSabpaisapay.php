<?php
/** 
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Ebizinfosys\SabpaisaPayment\Controller\Ipn;

use Magento\Framework\App\Config\ScopeConfigInterface;

use Magento\Framework\App\Action\Action as AppAction;

class CallbackSabpaisapay extends AppAction
{
    /**
    * @var \Citrus\Icp\Model\PaymentMethod
    */
    protected $_paymentMethod;

    /**
    * @var \Magento\Sales\Model\Order
    */
    protected $_order;

    /**
    * @var \Magento\Sales\Model\OrderFactory
    */
    protected $_orderFactory;

    /**
    * @var Magento\Sales\Model\Order\Email\Sender\OrderSender
    */
    protected $_orderSender;

    /**
    * @var \Psr\Log\LoggerInterface
    */
    protected $_logger;
	
	protected $request;

    /**
    * @param \Magento\Framework\App\Action\Context $context
    * @param \Magento\Sales\Model\OrderFactory $orderFactory
    * @param \Citrus\Icp\Model\PaymentMethod $paymentMethod
    * @param Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
    * @param  \Psr\Log\LoggerInterface $logger
    */
    public function __construct(
    \Magento\Framework\App\Action\Context $context,
	\Magento\Framework\App\Request\Http $request,
    \Magento\Sales\Model\OrderFactory $orderFactory,
    \Ebizinfosys\SabpaisaPayment\Model\PaymentMethod $paymentMethod,
    \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,	
    \Psr\Log\LoggerInterface $logger
    ) {
        $this->_paymentMethod = $paymentMethod;
        $this->_orderFactory = $orderFactory;
        $this->_client = $this->_paymentMethod->getClient();
        $this->_orderSender = $orderSender;		
        $this->_logger = $logger;	
		$this->request = $request;
        parent::__construct($context);
    }

    /**
    * Handle POST request to callback endpoint.
    */
    public function execute()
    {
        try {
            // Cryptographically verify authenticity of callback
     
            if($this->getRequest()->getParam('query'))
			{				
				$this->_success();
				$this->paymentAction();
			}
			else
			{
	            $this->_logger->addError("SabPaisaPay: no post back data received in callback");
				return $this->_failure();
			}
        } catch (Exception $e) {
            $this->_logger->addError("SabPaisaPay: error processing callback");
            $this->_logger->addError($e->getMessage());
            return $this->_failure();
        }
		
		$this->_logger->addInfo("Transaction END from SabPaisaPay");
    }
	
	protected function paymentAction()
	{
		$authKey = $this->_paymentMethod->getConfigData('espumkey');	
		$authIV = $this->_paymentMethod->getConfigData('espumsalt');	
		
		
		if ($this->getRequest()->getParam('query')) {
			
			$postdata = $this->getRequest()->getParam('query');			
			$responseData=$this->_paymentMethod->esPaydecrypt($postdata,$authIV,$authKey);
			parse_str($responseData,$postdata);

			if ($postdata['pgRespCode']) {
				$ordid = $postdata['clientTxnId'];
    	    	$this->_loadOrder($ordid);

				$message = '';
				$message .= 'orderId / Transaction ID: ' . $ordid . "\n";
				//$message .= 'Transaction Id: ' . $postdata['mihpayid'] . "\n";
				
				
				if (isset($postdata['pgRespCode']) && $postdata['pgRespCode'] == '0000') {

				 	$amount=$postdata['orgTxnAmount'];

					$message .= ' payid : '.$postdata['PGTxnNo'];
					$message .= ' sabpayid : '.$postdata['SabPaisaTxId'];
					
						// success	
						$this->_registerPaymentCapture ($ordid, $amount, $message);
						//$this->_logger->addInfo("Payum Response Order success..".$txMsg);
				
						$redirectUrl = $this->_paymentMethod->getSuccessUrl();
						//AA Where 
						$this->_redirect($redirectUrl);
					}
				else if (isset($postdata['pgRespCode']) && $postdata['pgRespCode'] == '0001') {

					 	$amount=$postdata['orgTxnAmount'];
	
						$message .= 'payid : '.$postdata['PGTxnNo'];
						$message .= 'return message : '.$postdata['reMsg'];
						$this->_createSabPaisaPayUMComment($message);
						$this->_order->setStatus('pending')->setState('pending');
						$this->_order->save();
						
						$redirectUrl = $this->_paymentMethod->getSuccessUrl();
						//AA Where 
						$this->_redirect($redirectUrl);
					}
				else {
						//tampered
						$errormsg=$postdata['reMsg'];
						$this->_createSabPaisaPayUMComment($errormsg, true);
						$this->_order->cancel()->save();

						$this->_logger->addError($errormsg);

						//AA display error to customer = where ???
						$this->messageManager->addError("<strong>Error:</strong> ".$errormsg);
						$this->_redirect('checkout/onepage/failure');
					}
				} else {
		    		$historymessage = $message;//.
			
					$this->_createSabPaisaPayUMComment($historymessage);
					$this->_order->cancel()->save();				

					//$this->_logger->addInfo("Payum Response Order cancelled ..");
			
					$this->messageManager->addError("<strong>Error:</strong> $message <br/>");
					//AA where 
					$redirectUrl = $this->_paymentMethod->getCancelUrl();
					$this->_redirect($redirectUrl);			
				} 
			}
	}
	

	//AA - To review - required 
    protected function _registerPaymentCapture($transactionId, $amount, $message)
    {
        $payment = $this->_order->getPayment();
		
		
        $payment->setTransactionId($transactionId)       
        ->setPreparedMessage($this->_createSabPaisaPayUMComment($message))
        ->setShouldCloseParentTransaction(true)
        ->setIsTransactionClosed(0)
		->setAdditionalInformation(['esubpaisapay','esubpaisapay'])		
        ->registerCaptureNotification(
            $amount,
            true 
        );
		
		$this->_order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
	    $this->_order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
	    $this->_order->addStatusToHistory($this->_order->getStatus(), 'Order processed successfully with reference '.$transactionId);
	    
        $this->_order->save();

        $invoice = $payment->getCreatedInvoice();
        if ($invoice && !$this->_order->getEmailSent()) {
            $this->_orderSender->send($this->_order);
            $this->_order->addStatusHistoryComment(
                __('You notified customer about invoice #%1.', $invoice->getIncrementId())
            )->setIsCustomerNotified(
                true
            )->save();
        }
    }

	//AA Done
    protected function _loadOrder($order_id)
    {
        $this->_order = $this->_orderFactory->create()->loadByIncrementId($order_id);

        if (!$this->_order && $this->_order->getId()) {
            throw new Exception('Could not find Magento order with id $order_id');
        }
    }

	//AA Done
    protected function _success()
    {
        $this->getResponse()->setStatusHeader(200);
    }

	//AA Done
    protected function _failure()
    {
        $this->getResponse()->setStatusHeader(400);
    }

    /**
    * Returns the generated comment or order status history object.
    *
    * @return string|\Magento\Sales\Model\Order\Status\History
    */
	//AA Done
    protected function _createSabPaisaPayUMComment($message = '')
    {       
        if ($message != '')
        {
            $message = $this->_order->addStatusHistoryComment($message);
            $message->setIsCustomerNotified(null);
        }
		
        return $message;
    }
	
}
