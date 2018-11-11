<?php

/* 
 */


class Mastercard extends NonmerchantGateway {
 
    private static $version = "1.0.3";    
    private static $authors = array(array('name' => "Webline Technologies", 'url' => "http://www.yatosha.com"));
    
    private $meta;
    private $postData;
    private $errorMessage;
    private $responseMap; 
 
    private $vpcPaymentGatewayUrl = "https://migs.mastercard.com.au/vpcpay";
    private $vpcPaymentTestGatewayUrl = "https://migs-mtf.mastercard.com.au/vpcpay";
    
    public function __construct() {
        Loader::loadComponents($this, array("Input"));
        Loader::loadModels($this, array("Clients"));        
        Language::loadLang("mastercard", null, dirname(__FILE__) . DS . "language" . DS);
    }

    public function getName() {
        return Language::_("Mastercard.name", true);
    }
        
    public function getVersion() {
        return self::$version;
    }
      
    public function getAuthors() {
        return self::$authors;
    }

    public function getCurrencies() {
        return array("USD", "TZS","GBP");
    }
        
    public function setCurrency($currency) {
        $this->currency = $currency;
    }
        
    public function getSettings(array $meta=null) {
        $this->view = $this->makeView("settings", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));
        // Load the helpers required for this view
        Loader::loadHelpers($this, array("Form", "Html"));
        $this->view->set("meta", $meta);

        return $this->view->fetch();
    } 
    
    public function editSettings(array $meta) {
//        // Verify meta data is valid
//        $rules = array(
//            'key'=>array(
//                    'valid'=>array(
//                            'rule'=>array("betweenLength", 16, 16),
//                            'message'=>Language::_("Mastercard.!error.key.valid", true)
//                    )
//            )
//
//            #
//            # TODO: Do error checking on any other fields that require it
//            #
//
//        );
//
//        $this->Input->setRules($rules);

        // Validate the given meta data to ensure it meets the requirements
        $this->Input->validates($meta);
        // Return the meta data, no changes required regardless of success or failure for this gateway
        return $meta;
    }    
    
    public function encryptableFields() {
        return array("key");
    }
        
    public function setMeta(array $meta=null) {
        $this->meta = $meta;
    }
        
    public function requiresCustomerPresent() {
        return false;
    } 
    
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null) {
        
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'VPCPaymentConnection.php');

        $vpcVersion                 = $this->meta['version'];
        $vpcCommand                 = $this->meta['command'];
        $vpcAccessCode              = $this->meta['access_code'];        
        $vpcMerchant                = $this->meta['merchant'];
        $vpcMerchantSecretKey       = $this->meta['merchant_secret_key'];
        $vpcLocale                  = "en_US";
        $amount                     = number_format($amount,2,"","");
        $vpcPaymentTestGatewayUrl   = $this->meta['gateway_url'];
        
        $vpcReturnURL = Configure::get('Blesta.gw_callback_url')
            . Configure::get('Blesta.company_id') . '/mastercard/';
        
        //$vpcReturnURL           = "";
                
        $conn = new VPCPaymentConnection();
        $conn->setSecureSecret($vpcMerchantSecretKey);        

        $transaction_id         = substr(md5(microtime()), -16); 
        
        $requestParam['vpc_Version']        = $vpcVersion;
        $requestParam['vpc_Command']        = $vpcCommand;
        $requestParam['vpc_AccessCode']     = $vpcAccessCode;
        $requestParam['vpc_MerchTxnRef']    = $transaction_id;
        $requestParam['vpc_Amount']         = $amount;
        $requestParam['vpc_Merchant']       = $vpcMerchant;
        $requestParam['vpc_ReturnURL']      = $vpcReturnURL;
        $requestParam['vpc_Locale']         = $vpcLocale;
        $requestParam['vpc_Currency']       = $this->currency;

        if (isset($invoice_amounts) && is_array($invoice_amounts)) {
            $requestParam['vpc_OrderInfo'] = $this->serializeInvoices($invoice_amounts);
        }
        
        ksort($requestParam);

        foreach($requestParam as $key => $value) {
            if (strlen($value) > 0) {
                $conn->addDigitalOrderField($key, $value);
            }
        }        

        $secureHash = $conn->hashAllFields();
        $conn->addDigitalOrderField("Title", "Blesta VPC 3 Party Transaction");
        $conn->addDigitalOrderField("cid", $this->ifSet($contact_info['client_id']));
        $conn->addDigitalOrderField("vpc_SecureHash", $secureHash);
        $conn->addDigitalOrderField("vpc_SecureHashType", "SHA256");        
       
        $vpcURL = $conn->getDigitalOrder($vpcPaymentTestGatewayUrl);

        header("Location: ".$vpcURL);

        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));
        $this->view->set('post_url', $vpcURL);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);
        return $this->view->fetch();
    }
        
    public function validate(array $get, array $post)
    {
        
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'VPCPaymentConnection.php');        

        $client_id = $this->ifSet($get['cid']);

        $vpcVersion             = $this->meta['version'];
        $vpcCommand             = $this->meta['command'];
        $vpcAccessCode          = $this->meta['access_code'];        
        $vpcMerchant            = $this->meta['merchant'];
        $vpcMerchantSecretKey   = $this->meta['merchant_secret_key'];
        $vpcLocale              = "en_US";

        $conn = new VPCPaymentConnection();
        $conn->setSecureSecret($vpcMerchantSecretKey);
        
        $title  = $get["Title"];

        foreach($get as $key => $value) {
            if (($key!="vpc_SecureHash") && ($key != "vpc_SecureHashType") && ((substr($key, 0,4)=="vpc_") || (substr($key,0,5) =="user_"))) {
                $conn->addDigitalOrderField($key, $value);
            }
        }

        $serverSecureHash	= array_key_exists("vpc_SecureHash", $get) ? $get["vpc_SecureHash"] : "";
        $secureHash = $conn->hashAllFields();

        if ($secureHash!=$serverSecureHash) {
            $error = $this->getCommonError('invalid');
            if(isset($get['vpc_Message'])) {
                $error = array(array($get['vpc_Message']));
            }
            $this->Input->setErrors($error);
        }
        
        $Title 				= array_key_exists("Title", $get) ? $get["Title"] : "";
        $againLink 			= array_key_exists("AgainLink", $get) ? $get["AgainLink"] : "";
        $amount 			= array_key_exists("vpc_Amount", $get) ? $get["vpc_Amount"] : "";
        $currency 			= array_key_exists("vpc_Currency", $get) ? $get["vpc_Currency"] : "";        
        $locale 			= array_key_exists("vpc_Locale", $get) ? $get["vpc_Locale"] : "";
        $batchNo 			= array_key_exists("vpc_BatchNo", $get) ? $get["vpc_BatchNo"] : "";
        $command 			= array_key_exists("vpc_Command", $get) ? $get["vpc_Command"] : "";
        $message 			= array_key_exists("vpc_Message", $get) ? $get["vpc_Message"] : "";
        $version  			= array_key_exists("vpc_Version", $get) ? $get["vpc_Version"] : "";
        $cardType                       = array_key_exists("vpc_Card", $get) ? $get["vpc_Card"] : "";
        $orderInfo 			= array_key_exists("vpc_OrderInfo", $get) ? $get["vpc_OrderInfo"] : "";
        $receiptNo 			= array_key_exists("vpc_ReceiptNo", $get) ? $get["vpc_ReceiptNo"] : "";
        $merchantID                     = array_key_exists("vpc_Merchant", $get) ? $get["vpc_Merchant"] : "";
        $merchTxnRef                    = array_key_exists("vpc_MerchTxnRef", $get) ? $get["vpc_MerchTxnRef"] : "";
        $authorizeID                    = array_key_exists("vpc_AuthorizeId", $get) ? $get["vpc_AuthorizeId"] : "";
        $transactionNo                  = array_key_exists("vpc_TransactionNo", $get) ? $get["vpc_TransactionNo"] : "";
        $acqResponseCode                = array_key_exists("vpc_AcqResponseCode", $get) ? $get["vpc_AcqResponseCode"] : "";
        $txnResponseCode                = array_key_exists("vpc_TxnResponseCode", $get) ? $get["vpc_TxnResponseCode"] : "";
        $riskOverallResult              = array_key_exists("vpc_RiskOverallResult", $get) ? $get["vpc_RiskOverallResult"] : "";

        // Obtain the 3DS response
        $vpc_3DSECI                     = array_key_exists("vpc_3DSECI", $get) ? $get["vpc_3DSECI"] : "";
        $vpc_3DSXID                 	= array_key_exists("vpc_3DSXID", $get) ? $get["vpc_3DSXID"] : "";
        $vpc_3DSenrolled 		= array_key_exists("vpc_3DSenrolled", $get) ? $get["vpc_3DSenrolled"] : "";
        $vpc_3DSstatus 			= array_key_exists("vpc_3DSstatus", $get) ? $get["vpc_3DSstatus"] : "";
        $vpc_VerToken 			= array_key_exists("vpc_VerToken", $get) ? $get["vpc_VerToken"] : "";
        $vpc_VerType 			= array_key_exists("vpc_VerType", $get) ? $get["vpc_VerType"] : "";
        $vpc_VerStatus			= array_key_exists("vpc_VerStatus", $get) ? $get["vpc_VerStatus"] : "";
        $vpc_VerSecurityLevel           = array_key_exists("vpc_VerSecurityLevel", $get) ? $get["vpc_VerSecurityLevel"] : "";

        // CSC Receipt Data
        $cscResultCode 	= array_key_exists("vpc_CSCResultCode", $get) ? $get["vpc_CSCResultCode"] : "";
        $ACQCSCRespCode = array_key_exists("vpc_AcqCSCRespCode", $get) ? $get["vpc_AcqCSCRespCode"] : "";

        $txnResponseCodeDesc = "";
        $cscResultCodeDesc = "";
        $avsResultCodeDesc = "";
    
        if ($txnResponseCode != "No Value Returned") {
            $txnResponseCode = getResultDescription($txnResponseCode);
        }
    
        if ($cscResultCode != "No Value Returned") {
            $cscResultCodeDesc = getCSCResultDescription($cscResultCode);
        }
        
        if ($txnResponseCode=="7" || $txnResponseCode=="No Value Returned") {
            $this->Input->setErrors(array(array($get['vpc_Message'])));
        }        
            
        $status = 'error';
        switch ($acqResponseCode) {
            case '00':
                $status = 'approved';
                break;
        }

        return [
            'client_id' => $client_id,
            'amount' => number_format(((int)$amount / 100),2,".",""),
            'currency' => $currency,
            'status' => $status,
            'reference_id' => $transactionNo,
            'transaction_id' => $merchTxnRef,
            'parent_transaction_id' => $merchTxnRef,
            'invoices' => $this->unserializeInvoices($orderInfo)
        ];
    }
    
    public function success(array $get, array $post)
    {        
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'VPCPaymentConnection.php');        
     
        $client_id = $this->ifSet($get['cid']);
        
        $vpcVersion             = $this->meta['version'];
        $vpcCommand             = $this->meta['command'];
        $vpcAccessCode          = $this->meta['access_code'];        
        $vpcMerchant            = $this->meta['merchant'];
        $vpcMerchantSecretKey   = $this->meta['merchant_secret_key'];
        $vpcLocale              = "en_US";

        $conn = new VPCPaymentConnection();
        $conn->setSecureSecret($vpcMerchantSecretKey);
        
        $title  = $get["Title"];

        foreach($get as $key => $value) {
            if (($key!="vpc_SecureHash") && ($key != "vpc_SecureHashType") && ((substr($key, 0,4)=="vpc_") || (substr($key,0,5) =="user_"))) {
                $conn->addDigitalOrderField($key, $value);
            }
        }

        $serverSecureHash	= array_key_exists("vpc_SecureHash", $get) ? $get["vpc_SecureHash"] : "";
        $secureHash = $conn->hashAllFields();

        if ($secureHash!=$serverSecureHash) {
            $error = $this->getCommonError('invalid');
            if(isset($get['vpc_Message'])) {
                $error = array(array($get['vpc_Message']));
            }
            $this->Input->setErrors($error);
        }     

        $Title 				= array_key_exists("Title", $get) ? $get["Title"] : "";
        $againLink 			= array_key_exists("AgainLink", $get) ? $get["AgainLink"] : "";
        $amount 			= array_key_exists("vpc_Amount", $get) ? $get["vpc_Amount"] : "";
        $currency 			= array_key_exists("vpc_Currency", $get) ? $get["vpc_Currency"] : "";        
        $locale 			= array_key_exists("vpc_Locale", $get) ? $get["vpc_Locale"] : "";
        $batchNo 			= array_key_exists("vpc_BatchNo", $get) ? $get["vpc_BatchNo"] : "";
        $command 			= array_key_exists("vpc_Command", $get) ? $get["vpc_Command"] : "";
        $message 			= array_key_exists("vpc_Message", $get) ? $get["vpc_Message"] : "";
        $version  			= array_key_exists("vpc_Version", $get) ? $get["vpc_Version"] : "";
        $cardType                       = array_key_exists("vpc_Card", $get) ? $get["vpc_Card"] : "";
        $orderInfo 			= array_key_exists("vpc_OrderInfo", $get) ? $get["vpc_OrderInfo"] : "";
        $receiptNo 			= array_key_exists("vpc_ReceiptNo", $get) ? $get["vpc_ReceiptNo"] : "";
        $merchantID                     = array_key_exists("vpc_Merchant", $get) ? $get["vpc_Merchant"] : "";
        $merchTxnRef                    = array_key_exists("vpc_MerchTxnRef", $get) ? $get["vpc_MerchTxnRef"] : "";
        $authorizeID                    = array_key_exists("vpc_AuthorizeId", $get) ? $get["vpc_AuthorizeId"] : "";
        $transactionNo                  = array_key_exists("vpc_TransactionNo", $get) ? $get["vpc_TransactionNo"] : "";
        $acqResponseCode                = array_key_exists("vpc_AcqResponseCode", $get) ? $get["vpc_AcqResponseCode"] : "";
        $txnResponseCode                = array_key_exists("vpc_TxnResponseCode", $get) ? $get["vpc_TxnResponseCode"] : "";
        $riskOverallResult              = array_key_exists("vpc_RiskOverallResult", $get) ? $get["vpc_RiskOverallResult"] : "";

        // Obtain the 3DS response
        $vpc_3DSECI                     = array_key_exists("vpc_3DSECI", $get) ? $get["vpc_3DSECI"] : "";
        $vpc_3DSXID                 	= array_key_exists("vpc_3DSXID", $get) ? $get["vpc_3DSXID"] : "";
        $vpc_3DSenrolled 		= array_key_exists("vpc_3DSenrolled", $get) ? $get["vpc_3DSenrolled"] : "";
        $vpc_3DSstatus 			= array_key_exists("vpc_3DSstatus", $get) ? $get["vpc_3DSstatus"] : "";
        $vpc_VerToken 			= array_key_exists("vpc_VerToken", $get) ? $get["vpc_VerToken"] : "";
        $vpc_VerType 			= array_key_exists("vpc_VerType", $get) ? $get["vpc_VerType"] : "";
        $vpc_VerStatus			= array_key_exists("vpc_VerStatus", $get) ? $get["vpc_VerStatus"] : "";
        $vpc_VerSecurityLevel           = array_key_exists("vpc_VerSecurityLevel", $get) ? $get["vpc_VerSecurityLevel"] : "";

        // CSC Receipt Data
        $cscResultCode 	= array_key_exists("vpc_CSCResultCode", $get) ? $get["vpc_CSCResultCode"] : "";
        $ACQCSCRespCode = array_key_exists("vpc_AcqCSCRespCode", $get) ? $get["vpc_AcqCSCRespCode"] : "";

        $txnResponseCodeDesc = "";
        $cscResultCodeDesc = "";
        $avsResultCodeDesc = "";
    
        if ($txnResponseCode != "No Value Returned") {
            $txnResponseCode = getResultDescription($txnResponseCode);
        }
    
        if ($cscResultCode != "No Value Returned") {
            $cscResultCodeDesc = getCSCResultDescription($cscResultCode);
        }
        
        if ($txnResponseCode=="7" || $txnResponseCode=="No Value Returned") {
            $this->Input->setErrors(array(array($get['vpc_Message'])));
        }        
            
        $status = 'error';
        switch ($acqResponseCode) {
            case '00':
                $status = 'approved';
                break;
        }

        return [
            'client_id' => $client_id,
            'amount' => number_format(((int)$amount / 100),2,".",""),
            'currency' => $currency,
            'status' => $status,
            'reference_id' => $transactionNo,
            'transaction_id' => $merchTxnRef,
            'parent_transaction_id' => $merchTxnRef,
            'invoices' => $this->unserializeInvoices($orderInfo)
        ];        
    }
        
    public function captureCc($reference_id, $transaction_id, $amount, array $invoice_amounts=null) {
        // Gateway does not support this action
        $this->Input->setErrors($this->getCommonError("unsupported"));
    }
        
    public function voidCc($reference_id, $transaction_id) {
        // Gateway does not support this action
        $this->Input->setErrors($this->getCommonError("unsupported"));
    }
        
    public function refundCc($reference_id, $transaction_id, $amount) {
        // Gateway does not support this action
        $this->Input->setErrors($this->getCommonError("unsupported"));
    }

    private function serializeInvoices(array $invoices)
    {
        $str = '';
        foreach ($invoices as $i => $invoice) {
            $str .= ($i > 0 ? '|' : '') . $invoice['id'] . '=' . $invoice['amount'];
        }
        return $str;
    }
    
    private function unserializeInvoices($str)
    {
        $invoices = [];
        $temp = explode('|', $str);
        foreach ($temp as $pair) {
            $pairs = explode('=', $pair, 2);
            if (count($pairs) != 2) {
                continue;
            }
            $invoices[] = ['id' => $pairs[0], 'amount' => $pairs[1]];
        }
        return $invoices;
    }    
}
