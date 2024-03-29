<?php
/** 
 *
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Ebizinfosys\SabpaisaPayment\Model;

use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;

class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{
    protected $_code = 'esubpaisapay';
    protected $_isInitializeNeeded = true;

    /**
    * @var \Magento\Framework\Exception\LocalizedExceptionFactory
    */
    protected $_exception;

    /**
    * @var \Magento\Sales\Api\TransactionRepositoryInterface
    */
    protected $_transactionRepository;

    /**
    * @var Transaction\BuilderInterface
    */
    protected $_transactionBuilder;

    /**
    * @var \Magento\Framework\UrlInterface
    */
    protected $_urlBuilder;

    /**
    * @var \Magento\Sales\Model\OrderFactory
    */
    protected $_orderFactory;
	protected $_countryHelper;
    /**
    * @var \Magento\Store\Model\StoreManagerInterface
    */
    protected $_storeManager;
	
	protected $adnlinfo;
	protected $title;

    /**
    * @param \Magento\Framework\UrlInterface $urlBuilder
    * @param \Magento\Framework\Exception\LocalizedExceptionFactory $exception
    * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
    * @param Transaction\BuilderInterface $transactionBuilder
    * @param \Magento\Sales\Model\OrderFactory $orderFactory
    * @param \Magento\Store\Model\StoreManagerInterface $storeManager
    * @param \Magento\Framework\Model\Context $context
    * @param \Magento\Framework\Registry $registry
    * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
    * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
    * @param \Magento\Payment\Helper\Data $paymentData
    * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    * @param \Magento\Payment\Model\Method\Logger $logger
    * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
    * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
    * @param array $data
    */
    public function __construct(
      \Magento\Framework\UrlInterface $urlBuilder,
      \Magento\Framework\Exception\LocalizedExceptionFactory $exception,
      \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
      \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
      \Magento\Sales\Model\OrderFactory $orderFactory,
      \Magento\Store\Model\StoreManagerInterface $storeManager,
      \Magento\Framework\Model\Context $context,
      \Magento\Framework\Registry $registry,
      \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
      \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
      \Magento\Payment\Helper\Data $paymentData,
      \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
      \Magento\Payment\Model\Method\Logger $logger,
      \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
      \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
      array $data = []
    ) {
      $this->_urlBuilder = $urlBuilder;
      $this->_exception = $exception;
      $this->_transactionRepository = $transactionRepository;
      $this->_transactionBuilder = $transactionBuilder;
      $this->_orderFactory = $orderFactory;
      $this->_storeManager = $storeManager;
	  $this->_countryHelper = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Directory\Model\Country');
      parent::__construct(
          $context,
          $registry,
          $extensionFactory,
          $customAttributeFactory,
          $paymentData,
          $scopeConfig,
          $logger,
          $resource,
          $resourceCollection,
          $data
      );
    }

    /**
     * Instantiate state and set it to state object.
     *
     * @param string                        $paymentAction
     * @param \Magento\Framework\DataObject $stateObject
     */
    public function initialize($paymentAction, $stateObject)
    {
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);		
		
        $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
    }

	
	//AA Done
	public function _generateHmacKey($data, $apiKey=null){
		//$hmackey = Zend_Crypt_Hmac::compute($apiKey, "sha1", $data);
		$hmackey = hash_hmac('sha1',$data,$apiKey);
		return $hmackey;
	}

	
	public function getPostHTML($order, $storeId = null)
    {
			$spDomain='https://uatsp.sabpaisa.in/SabPaisa/sabPaisaInit';
			
			
			$clientCode = 		$this->getConfigData('espclientcode');	
			$userName =		$this->getConfigData('espusername');	
			$userPassword = 		$this->getConfigData('esppassword');	
			$authKey =		$this->getConfigData('espumkey');
			$authIV =		$this->getConfigData('espumsalt');	
			
			$txnid = $order->getIncrementId();
    	    $amount = $order->getGrandTotal();
        	$amount = number_format((float)$amount, 2, '.', '');

			$currency = $order->getOrderCurrencyCode();
        	$billingAddress = $order->getBillingAddress();
			$productInfo  = "Product Information";	        
			
			
			
			$firstname = $billingAddress->getData('firstname');
			$lastname = $billingAddress->getData('lastname');
			$zipcode = $billingAddress->getData('postcode');
			$email = $billingAddress->getData('email');
			$phone = $billingAddress->getData('telephone');
			$address ='';//$billingAddress->getStreet();
        	$state = $billingAddress->getData('region');
        	$city = $billingAddress->getData('city');
        	$country = $billingAddress->getData('country_id');
			$countryObj = $this->_countryHelper->loadByCode($country);
			$country = $countryObj->getName();
			//$addressfull=$address.', '.$city.', '.$state;
			$surl = self::getPayUMReturnUrl();			
			$URLfailure=self::getCancelUrl();
			
			$spURL = "?clientName=".$clientCode."&usern=".$userName."&pass=".$userPassword."&amt=".$amount."&txnId=".$txnid."&firstName=".$firstname."&lstName=".$lastname."&contactNo=".$phone."&Email=".$email."&Add=".$address."&ru=".$surl."&failureURL=".$URLfailure;
			
			$spURL = self::esPayencrypt($spURL,$authIV,$authKey);
			$spURL = str_replace("+", "%2B",$spURL);
			$spURL="?query=".$spURL."&clientName=".$clientCode;
			$spURL = $spDomain.$spURL; 
			$spURL = str_replace(" ", "%20",$spURL);
			
			$html = $spURL;
					
			return $html;
    }

    public function getOrderPlaceRedirectUrl($storeId = null)
    {
        return $this->_getUrl('esubpaisapay/checkout/start', $storeId);
    }

	protected function addHiddenField($arr)
	{
		$nm = $arr['name'];
		$vl = $arr['value'];	
		$input = "<input name='".$nm."' type='hidden' value='".$vl."' />";	
		
		return $input;
	}
	
    /**
     * Get return URL.
     *
     * @param int|null $storeId
     *
     * @return string
     */
	 //AA may not be required
    public function getSuccessUrl($storeId = null)
    {
        return $this->_getUrl('checkout/onepage/success', $storeId);
    }

	/**
     * Get return (IPN) URL.
     *
     * @param int|null $storeId
     *
     * @return string
     */
	 //AA Done
    
	 public function getPayUMReturnUrl($storeId = null)
    {
        return $this->_getUrl('esubpaisapay/ipn/callbacksabpaisapay', $storeId, false);
    }
	/**
     * Get cancel URL.
     *
     * @param int|null $storeId
     *
     * @return string
     */
	 //AA Not required
    public function getCancelUrl($storeId = null)
    {
        return $this->_getUrl('checkout/onepage/failure', $storeId);
    }

	/**
     * Build URL for store.
     *
     * @param string    $path
     * @param int       $storeId
     * @param bool|null $secure
     *
     * @return string
     */
	 //AA Done
    protected function _getUrl($path, $storeId, $secure = null)
    {
        $store = $this->_storeManager->getStore($storeId);

        return $this->_urlBuilder->getUrl(
            $path,
            ['_store' => $store, '_secure' => $secure === null ? $store->isCurrentlySecure() : $secure]
        );
    }
	
	
	function esPayencrypt($str,$authIV,$authKey) {
		  $iv = $authIV;
		  $key=$authKey;
		  $s = self::esPaypkcs5_pad($str);
		  $td =@mcrypt_module_open('rijndael-128', '', 'cbc', $iv);
		  @mcrypt_generic_init($td, $key, $iv);
		  $encrypted = @mcrypt_generic($td, $s);
		  $encrypted1=base64_encode($encrypted);
		  
		  return (trim($encrypted1));
	  
	}

	function esPaydecrypt($code,$authIV,$authKey) {
	 
		  $iv = $authIV;
		  $key=$authKey;
		  
		  $an=base64_decode($code);
		  $td = @mcrypt_module_open('rijndael-128', '', 'cbc', $iv);
		  @mcrypt_generic_init($td, $key, $iv);
		  $decrypted = @mdecrypt_generic($td, $an);
		  return (trim($decrypted));
	}
	protected function esPaypkcs5_pad ($text) {
		  $blocksize = 16;
		  $pad = $blocksize - (strlen($text) % $blocksize);
		  return $text . str_repeat(chr($pad), $pad);
		}
}
