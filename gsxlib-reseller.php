<?php
/**
 * gsxlib/gsxlib-reseller.php
 * @package gsxlib-reseller
 * @author Filipp Lepalaan <filipp@fps.ee>, Matteo Crippa
 * https://gsxwsut.apple.com/apidocs/html/WSReference.html?user=asp
 * @license
 * This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details.
 */
class GsxLib
{
    private $client;
    private $region;
    private $session_id;
    private $environment;

    private $wsdl = 'https://gsxws%s.apple.com/wsdl/%sReseller/gsx-%sReseller.wsdl';

    static $_instance;

    const timeout = 30;     // session timeout in minutes

    public static function getInstance(
        $account,
        $username,
        $password,
        $environment = '',
        $region = 'emea',
        $tz = 'CEST')
    {
        if(!(self::$_instance instanceof self)) {
            self::$_instance = new self(
                $account,
                $username,
                $password,
                $environment,
                $region,
                $tz
            );
        }

        return self::$_instance;

    }

    private function __clone() {}

    private function __construct(
        $account,
        $username,
        $password,
        $environment = '',
        $region = 'emea',
        $tz = 'CEST' )
    {
        if(!class_exists('SoapClient')) {
            throw new GsxException('Looks like your PHP lacks SOAP support');
        }

        if(!preg_match('/\d+/', $account)) {
            throw new GsxException('Invalid Sold-To: ' . $account);
        }

        $regions = array('am', 'emea', 'apac', 'la');

        if(!in_array($region, $regions))
        {
            $error = 'Region "%s" should be one of: %s';
            $error = sprintf($error, $region, implode(', ', $regions));
            throw new GsxException($error);
        }
        
        $envirs = array('ut', 'it');
        
        if(!empty($environment))
        {
            if(!in_array($environment, $envirs))
            {
                $error = 'Environment "%s" should be one of: %s';
                $error = sprintf($error, $environment, implode(', ', $envirs));
                throw new GsxException($error);
            }
        } else {
           // GSX2...
           $environment = '2';
        }
        
        $this->wsdl = sprintf($this->wsdl, $environment, $region, $region);
                
        $this->client = new SoapClient(
            $this->wsdl, array('exceptions' => TRUE, 'trace' => 1)
        );
        
        if(!$this->client) {
           throw new GsxException('Failed to create SOAP client.');
        }
                
        if(@$_SESSION['_gsxlib_timeout'][$account] > time()) {
 //          return $this->session_id = $_SESSION['_gsxlib_id'][$account];
        }
        
        $a = array(
            'AuthenticateRequest' => array(
                'userId'            => $username,
                'password'          => $password,
                'serviceAccountNo'  => $account,
                'languageCode'      => 'it',
                'userTimeZone'      => $tz,
            )
        );
        
        try {
            $this->session_id = $this->client
                ->Authenticate($a)
                ->AuthenticateResponse
                ->userSessionId;
        } catch(SoapFault $e) {
            if($environment == '2') $environment = 'production';

            $error = 'Authentication with GSX failed. Does this account have access to '
                .$environment."?\n";
            throw new GsxException($error);

        }
        
        // there's a session going, put the credentials in there
        if(session_id()) {
            $_SESSION['_gsxlib_id'][$account] = $this->session_id;
            $timeout = time()+(60*self::timeout);
            $_SESSION['_gsxlib_timeout'][$account] = $timeout;
        }

    }

    function getClient()
    {   
        return $this->client;
    }

    function setClient($client)
    {
        $this->client = $client;
    }

    public function resellerWarrantyStatus($serialNumber){
      
      $unitDetail = array();
      if(is_array($serialNumber)){
        $unitDetail = array('alternateDeviceId' => $serialNumber['alternateDeviceId']);
      }else{
        $unitDetail = array('serialNumber' => $serialNumber);
      }
      
      //print_r($unitDetail);

      $req = array('ResellerWarrantyStatusRequest' => array(
        'userSession' => array('userSessionId' => $this->session_id),
        'unitDetail' => $unitDetail
      ));

      return $this->client->ResellerWarrantyStatus($req)->ResellerWarrantyStatusResponse;

    }

    public function isValidSerialNumber($serialNumber)
    {
        $serialNumber = trim( $serialNumber );

        // SNs should never start with an S, but they're often coded into barcodes
        // and since an "old- ormat" SN + S would still qualify as a "new format" SN,
        // we strip it here and not in self::looksLike
        $serialNumber = ltrim($serialNumber, 'sS');
        
        return self::looksLike($serialNumber, 'serialNumber');
        
    }
  
    /**
    * return the GSX user session ID
    * I still keep the property private since it should not be modified
    * outside the constructor
    * @return string GSX session ID
    */
    public function getSessionId()
    {
        return $this->session_id;
    }
    
    /**
    * Do the actual SOAP request
    */
    public function request($req)
    {
        $result = FALSE;

        // split the request name and data
        list($r, $p) = each($req);
        
        // add session info
        $p['userSession'] = array('userSessionId' => $this->session_id);
        
        if($r == 'CarryInRepairUpdate'){
            $request = array('UpdateCarryInRequest' => $p);
        }else if($r == 'KGBSerialNumberUpdate'){
            $request = array('UpdateKGBSerialNumberRequest' => $p);
        }else{
            $request = array($r.'Request' => $p);
        }
        
        
        /*$functions = $this->client->__getFunctions();
        var_dump ($functions);
        die;*/
        
        try {
            $result = $this->client->$r($request);
            $resp = "{$r}Response";
            
            return $result->$resp;
        } catch(SoapFault $e) {
            throw new GsxException($e->getMessage(), $this->client->__getLastRequest());
        }

        return $result;

    }

    /**
    * Try to "categorise" a string
    * About identifying serial numbers - before 2010, Apple had a logical
    * serial number format, with structure, that you could id quite reliably.
    * unfortunately, it's no longer the case
    * @param string $string string to check
    */
    static function looksLike($string, $what = null)
    {
        $result = false;
        $rex = array(
            'partNumber'            => '/^([A-Z]{1,2})?\d{3}\-?(\d{4}|[A-Z]{2})(\/[A-Z])?$/i',
            'serialNumber'          => '/^[A-Z0-9]{11,12}$/i',
            'eeeCode'               => '/^[A-Z0-9]{3,4}$/',     // only match ALL-CAPS!
            'returnOrder'           => '/^7\d{9}$/',
            'repairNumber'          => '/^\d{12}$/',
            'dispatchId'            => '/^G\d{9}$/',
            'alternateDeviceId'     => '/^\d{15}$/',
            'diagnosticEventNumber' => '/^\d{23}$/',
            'productName'           => '/^i?Mac/',
        );

        foreach ($rex as $k => $v) {
            if (preg_match($v, $string)) {
                $result = $k;
            }
        }
    
        return ($what) ? ($result == $what) : $result;
  
  }
  
}

class GsxException extends Exception
{
    function __construct($message, $request = null)
    {
        $this->request = $request;
        $this->message = $message;
    }
}
