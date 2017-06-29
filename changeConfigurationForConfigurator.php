<?php

class className extends JobRouter\Engine\Runtime\PhpFunction\RuleExecutionFunction
{
	public function execute($rowId = null)
	{
	    //Pobranie numeru urzdzenia
	    //$prodID = filter_input(INPUT_GET, 'IDproduktu', FILTER_SANITIZE_STRING);
	    $prodID=$this->getDialogValue('IDproduktu');
        
        $date=date('Y-m-d  G:i:s');
        $process=$this->getProcessId();


        $jobDB = $this->getJobDB();

        $sql='SELECT p.prodOrderNumber FROM bkf_product p JOIN bkf_productsorders po on p.ID = po.IDProd where detachDate is NULL and p.ID =  "'.$prodID.'"
                order by prodOrderId DESC LIMIT 1;';
        $result = $jobDB->query($sql);
        $this->dump($sql);
        
        if ($result === false) {
            throw new JobRouterException($jobDB->getErrorMessage());
        }
        
            $row = $jobDB->fetchRow($result);
            //Zmienna procesowa
            $proseccID=$row['prodOrderNumber'];
            $this->setTableValue('tmpId',$proseccID );
   
	    
		 //Poczatek testów dla Konfiguratora
        //opcje dodatkowe stanowiska
        
        $numRows=$this->getSubtableCount('ST_OROPEQEDIT');
	    $nuIds=$this->getSubtableRowIds('ST_OROPEQEDIT');
	    $up1='ST_OROPEQUIPMNT';
	    
	    //echo 'Opcje stanowiska'.'<br><br>';
	    for($i=0;$i<$numRows;$i++)
	    {
        $a=$this->getSubtableValue('ST_OROPEQEDIT',$nuIds[$i], 'equipmentId');  
        $b=$this->getSubtableValue('ST_OROPEQEDIT',$nuIds[$i], 'isSelected');  
        $c=$this->getSubtableValue('ST_OROPEQEDIT',$nuIds[$i], 'variantId');  
        
        $sql='UPDATE ST_OROPEQUIPMNT SET variantId = "'.$c.'", isSelected = "'.$b.'" WHERE processid = "'.$proseccID.'" AND equipmentId = "'.$a.'";';
        $this->dump($sql);
        
        
            $result = $jobDB->exec($sql);
            if ($result === false) {
                 throw new JobRouterException($jobDB->getErrorMessage());
            }
        $sql1='INSERT INTO bkf_confighistory VALUES(NULL,"'.$proseccID.'","'.$process.'","ST_OROPEQEDIT","'.$a.'","'.$b.'","","'.$c.'","'.$date.'",1);';
        $this->dump($sql);
        
        
            $result = $jobDB->exec($sql1);
            if ($result === false) {
                 throw new JobRouterException($jobDB->getErrorMessage());
            }
        
	    
        
        
       //echo $a.' '.$b.' '.$c.'<br>';
        
	    }
	    
	     //opcje dodatkowe 
	//	echo 'Opcje dodatkowe'.'<br><br>';

	    $numRows=$this->getSubtableCount('ST_OROPSTANEQEDIT');
	    $nuIds=$this->getSubtableRowIds('ST_OROPSTANEQEDIT');
	    $up2='ST_OROPSTANEQUIPMENT';
	    
	    for($i=0;$i<$numRows;$i++)
	    {
        $a=$this->getSubtableValue('ST_OROPSTANEQEDIT',$nuIds[$i], 'equipmentId');  
        $b=$this->getSubtableValue('ST_OROPSTANEQEDIT',$nuIds[$i], 'isSelected');  
        $c=$this->getSubtableValue('ST_OROPSTANEQEDIT',$nuIds[$i], 'equipmentCnt');
        $d=$this->getSubtableValue('ST_OROPSTANEQEDIT',$nuIds[$i], 'variantId');
       // echo $a.' '.$b.' '.$c.' '.$d.'<br>';
         $sql='UPDATE ST_OROPSTANEQUIPMENT SET variantId = "'.$d.'", isSelected = "'.$b.'", equipmentCnt = "'.$c.'"  WHERE processid = "'.$proseccID.'" AND equipmentId = "'.$a.'";';
      //  $this->dump($sql);
        
        
            $result = $jobDB->exec($sql);
            if ($result === false) {
                 throw new JobRouterException($jobDB->getErrorMessage());
            }
	    
        $sql1='INSERT INTO bkf_confighistory VALUES(NULL,"'.$proseccID.'","'.$process.'","ST_OROPSTANEQEDIT","'.$a.'","'.$b.'","'.$c.'","'.$d.'","'.$date.'",1);';
       // $this->dump($sql);
        
        
            $result = $jobDB->exec($sql1);
            if ($result === false) {
                 throw new JobRouterException($jobDB->getErrorMessage());
            }   
	        
	    }
	    
	    //opcje standardowe
	    
	   // echo 'Opcje standardowe'.'<br><br>';
	    
	    
	    $numRows=$this->getSubtableCount('ST_ORSTANDEQEDIT');
	    $nuIds=$this->getSubtableRowIds('ST_ORSTANDEQEDIT');
	    $up3='ST_ORSTANDEQUIPMNT';

	    for($i=0;$i<$numRows;$i++)
	    {
        $a=$this->getSubtableValue('ST_ORSTANDEQEDIT',$nuIds[$i], 'equipmentId');  
        $b=$this->getSubtableValue('ST_ORSTANDEQEDIT',$nuIds[$i], 'variantId');
        $sql='UPDATE ST_ORSTANDEQUIPMNT SET variantId = "'.$b.'" WHERE processid = "'.$proseccID.'" AND equipmentId = "'.$a.'";';
      //  $this->dump($sql);
        
        
            $result = $jobDB->exec($sql);
            if ($result === false) {
                 throw new JobRouterException($jobDB->getErrorMessage());
            }

        $sql1='INSERT INTO bkf_confighistory VALUES(NULL,"'.$proseccID.'","'.$process.'","ST_ORSTANDEQEDIT","'.$a.'","","","'.$b.'","'.$date.'",1);';
       // $this->dump($sql);
        
        
            $result = $jobDB->exec($sql1);
            if ($result === false) {
                 throw new JobRouterException($jobDB->getErrorMessage());
            }
       
	    }
	   
	}
	
	
}
?>