<?php
/**
 *
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Ebizinfosys\SabpaisaPayment\Controller\Checkout;

class Start extends \Magento\Framework\App\Action\Action
{
    /**
    * @var \Magento\Checkout\Model\Session
    */
    protected $_checkoutSession;

    /**
    * @var \Coinbase\Magento2PaymentGateway\Model\PaymentMethod
    */
    protected $_paymentMethod;

	protected $_resultJsonFactory;
	
	protected $_logger;
	
    /**
    * @param \Magento\Framework\App\Action\Context $context
    * @param \Magento\Checkout\Model\Session $checkoutSession
    * @param \Coinbase\Magento2PaymentGateway\Model\PaymentMethod $paymentMethod
    */
    public function __construct(
    \Magento\Framework\App\Action\Context $context,
    \Magento\Checkout\Model\Session $checkoutSession,
    \Ebizinfosys\SabpaisaPayment\Model\PaymentMethod $paymentMethod,
	\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,\Psr\Log\LoggerInterface $logger
    ) {
        $this->_paymentMethod = $paymentMethod;
        $this->_checkoutSession = $checkoutSession;
		$this->_resultJsonFactory = $resultJsonFactory;
		$this->_logger = $logger;
        parent::__construct($context);
    }

    /**
    * Start checkout by requesting checkout code and dispatching customer to Coinbase.
    */
    public function execute()
    {
		//$this->_logger->debug('Entry Start Execute-'); 
		$html = $this->_paymentMethod->getPostHTML($this->getOrder());
		//$this->_logger->debug($html); 
		//echo json_encode($data);
		$result = $this->_resultJsonFactory->create();
		return $result->setData(['html' => $html]);

        //return json_encode($data);
		//AA Not Required $this->getResponse()->setRedirect($this->_paymentMethod->getCheckoutUrl($this->getOrder()));
    }

    /**
    * Get order object.
    *
    * @return \Magento\Sales\Model\Order
    */
    protected function getOrder()
    {
        return $this->_checkoutSession->getLastRealOrder();
    }
}
