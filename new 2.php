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