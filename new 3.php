<?php

class className extends JobRouter\Engine\Runtime\PhpFunction\RuleExecutionFunction
{
	
    function appendChecksum($format, $prefix, $serial) {
		$want = -1;

		switch ($format) {
		case 'ean13':
			$want = 12;
			$code = $prefix .$serial;
			$a = str_split($code);
			if (count($a) !== $want)
				throw new Exception(sprintf('wrong code length: got %d, want %d (code: %s)',
					count($a), $want, $code));
			foreach ($a as $key => &$digit) {
				if (!is_numeric($digit))
					throw new Exception(sprintf('want numeric code, got "%s" at offset %d (code: "%s")',
						$digit, $key, $code));
				$pos = $key + 1;
				if ($pos % 2)
					;	// take odd digits as-is
				else
					$digit *= 3; }	// weight even digits by `3'
			$base = array_sum($a) %10;
			$csum = (10 - $base) % 10;
			$code .= $csum;
			$ret = $serial .$csum;
			$want += 1;
			if (strlen($code) !== $want)
				throw new Exception(sprintf('internal error: calculated code has len %d, want %d (full code: %s)',
					strlen($code), $want, $code));
				$result=array($ret,$code);	
			return $ret;
		default:
			throw new Exception(sprintf('unsupported format `%s`', $format)); }
	
	}
	
	 
     function padSerial($format, $prefix, $serial) {
		switch ($format) {
		case 'ean13':
			$want = 12;
			break;
		default:
			throw new Exception(sprintf('unsupported format `%s`', $format)); }

		$padTo = $want - strlen($prefix);
		if ($padTo < 1)
			throw new Exception(sprintf('code format error: tried to pad to %d (%s, %s)',
				$padTo, $prefix, $serial));
		if ($padTo < strlen($serial))
			throw new Exception(sprintf('code format error: tried to pad to %d, but serial is %d already (%s, %s)',
				$padTo, strlen($serial), $prefix, $serial));
		return str_pad($serial, $padTo, '0', STR_PAD_LEFT);
	}
	
	function assignSerialNumber($in, $codeFormat)
	{
		
		
		
		
		$orderProducts=array('BKF-MONO' => '261','CW-MONO' => '1864','CW2M5' => 'CW2M5','CW2T5' => '144', 'CW3M5' => '142', 
							'CW3T5' => '1841', 'CW4T5' => '235', 'CW5T5' => 150, 'CW6T5' => 152, 'CW7T5' => 1778, 'CW8T5' => 1779,
							'HCM' => 192, 'OD1' => 237, 'OD2' => 229, 'ODK1' => 232, 'ODK2' => 1645, 'ROZ-BKF' => 1865,
							'ROZ-COM' => 233, 'TRZ-1' => 1626, 'TRZ-1K' => 1626, 'TRZ-2' => 1626, 'TRZ-2K' => 1626, 'YETI' => 1874);
							
		$countryList=array('GRECJA' => 19086, 'SZWECJA' => 19085, 'WĘGRY' => 19084, 'RUMUNIA' => 19083, 'NORWEGIA' => 19082, 
					'SERBA' => 19081, 'UKRAINA' => 19080, 'CHORWACJA' => 19079, 'FRANCJA' => 19078, 
					'ŁOTWA' => 19077, 'LITWA' => 19076, 'ESTONIA' => 19075, 'MOŁDAWIA' => 19074, 'KAZACHSTAN' => 19073, 
					'NIEMCY' => 19072, 'CZECHY' => 19071, 'SŁOWACJA' => 19070, 'BIAŁORUŚ' => 19069);
					
					
					
					
	    $username='test123';
        $password='12345678';
        $login='http://10.0.2.71:82/login/change/session';
		$post='http://10.0.2.71:82/orders/change/order/';
		$product1='http://10.0.2.71:82/orders/change/addprtopo/';
        $curl=curl_init();

        curl_setopt($curl, CURLOPT_URL, $login);

        curl_setopt($curl, CURLOPT_POST, 1);

        curl_setopt($curl, CURLOPT_POSTFIELDS, 'login='.$username.'&password='.$password.'&language=pl_PL&returnTo=/');


        curl_setopt($curl, CURLOPT_COOKIEJAR, 'cookies.txt');

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        //execute the request (the login)
        $store = curl_exec($curl);

	    curl_setopt($curl, CURLOPT_URL, $post);

        curl_setopt($curl, CURLOPT_POST, 1);

        curl_setopt($curl, CURLOPT_POSTFIELDS, 'order_nr='.$this->getDialogValue('orderId').'&'.$this->getDialogValue('finalDate').'&cid=18944&_dealer=18944');

        curl_setopt($curl, CURLOPT_COOKIEJAR, 'cookies.txt');

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);
        
        
        $externalDB = $this->getDBConnection('I2m');
        $sql = 'select oid from bkfOrders group by oid desc limit 1;';
        $resulti2m = $externalDB->query($sql);
            if ($result === false) {
				throw new JobRouterException($externalDB->getErrorMessage());
            }
			$lastrow = $jobDB->fetchRow($resulti2m);
			$oid=$lastrow['oid'];
			


	    
	   $a=0;

	     $numRows=$this->getSubtableCount('BKF_ORDERPRODUCTS');
	             $nuIds=$this->getSubtableRowIds('BKF_ORDERPRODUCTS');

        for($i=1;$i<=$numRows;$i++)
        {
	    
        $jobDB = $this->getJobDB();
	     $first='SELECT MAX(serialNumber) 
            DIV 10 + 1 AS nextBase 
            FROM bkf_numerSeryjny 
            WHERE snPool="'.$in.'";';
            $result = $jobDB->query($first);
        if ($result === false) {
        throw new JobRouterException($jobDB->getErrorMessage());
        }
        $row = $jobDB->fetchRow($result);
        $firstResult=$row['nextBase'];
        $prefixCheck=$this->padSerial($codeFormat,'200200',$firstResult);
        $newSerial=$this->appendChecksum($codeFormat,'200200',$prefixCheck);
        
        $insertSerialDB='INSERT INTO bkf_numerSeryjny VALUES(NULL,'.$in.',"'.$newSerial.'");';
        
        $result2 = $jobDB->query($insertSerialDB);
         if ($result2 === false) {
        throw new JobRouterException($jobDB->getErrorMessage());
        }
        $nuIds=$this->getSubtableRowIds('BKF_ORDERPRODUCTS');
        
        $orderDate=date("Y-m-d");
        $newSerial=substr($newSerial,2);
        $insertProduct = 'INSERT IGNORE INTO `bkf_product`(`ID`, `productId`, `prodOrderNumber`, `serialNumber`, `prodOrderId`, 
        `productionLine`, `pwNumber`, `pwProductNumber`, `containerRW`, `productStatus`, `AddDate`) 
        VALUES (NULL ,"'.$this->getSubtableValue(BKF_ORDERPRODUCTS,$nuIds[$a], 'productId').'", NULL, "'.$newSerial.'", NULL,
        "'.$this->getSubtableValue(BKF_ORDERPRODUCTS,$nuIds[$a], 'productionLine').'", NULL, NULL, NULL, 1, "'.$orderDate.'")';
		
		$prodId=$this->getSubtableValue(BKF_ORDERPRODUCTS,$nuIds[$a], 'productId');
		//cURL zapisywanie produktów w bazie i2m
		
        curl_setopt($curl, CURLOPT_URL, $product1);

		curl_setopt($curl, CURLOPT_POST, 1);

		curl_setopt($curl, CURLOPT_POSTFIELDS, '_bkfOrder='.$oid.'&_product='.$orderProducts[$prodId].'&cnt=1&orderDate='.$orderDate.'&_poStatus=7');

		curl_setopt($curl, CURLOPT_COOKIEJAR, 'cookies.txt');

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$result2 = curl_exec($curl);		
        
        
        
        
        //koniec
        
        $result3 = $jobDB->query($insertProduct);
         if ($result3 === false) {
        throw new JobRouterException($jobDB->getErrorMessage());}
        $sql_ID = 'select ID from bkf_product where serialNumber = "'.$newSerial.'" order by ID desc LIMIT 1';
        $result_ID = $jobDB->query($sql_ID);
        $row_ID = $jobDB->fetchRow($result_ID);
       // $this->dump($row_ID['ID']);
        $proc=$this->getProcessId();
        $sql_insertbkf='UPDATE bkf_orderproducts set serialNumber="'.$newSerial.'", product='.$row_ID['ID'].' where processid="'.$proc.'" and row_id='.$nuIds[$a].';';
        $r3 = $jobDB->query($sql_insertbkf);
         if ($r3 === false) {
        throw new JobRouterException($jobDB->getErrorMessage());
             
            }
        
        
        $a=$a+1;    
    }

	    
}
	
	public function execute($rowId = null)
	{
	   // $a=$this->padSerial('ean13','200200','2066');
         //   echo $a;
           // $b=$this->appendChecksum('ean13','200200','002066');
        //    echo '<br>'.$b;
          $this->assignSerialNumber(2, 'ean13');

	}
}
?>