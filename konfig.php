<?php



class className extends JobRouter\Engine\Runtime\PhpFunction\StepInitializationFunction
{
	public function execute($rowId = null)
	{
	
        $jobDB = $this->getJobDB();
        $subtable = 'ST_OROPEQEDIT';
        $cnt = 0;
        $IDproduktu = filter_input(INPUT_GET, 'IDproduktu', FILTER_SANITIZE_STRING);
        
        //$this->dump($IDproduktu);
        
        $sql = 'SELECT equipmentId, isSelected, variantId
                FROM `ST_OROPEQUIPMNT`
                WHERE `processid` = (SELECT DISTINCT processid FROM bkf_cworders where processid =
                (
                SELECT p.prodOrderNumber FROM bkf_product p
                JOIN bkf_productsorders po on p.ID = po.IDProd where detachDate is NULL and p.ID =  "'.$IDproduktu.'")
                order by prodOrderId DESC LIMIT 1)
                and step_id = (SELECT max(step_id) FROM ST_OROPEQUIPMNT WHERE `processid` = (SELECT DISTINCT processid FROM bkf_cworders where processid =
                (
                SELECT p.prodOrderNumber FROM bkf_product p
                JOIN bkf_productsorders po on p.ID = po.IDProd where detachDate is NULL and p.ID = "'.$IDproduktu.'")
                order by prodOrderId DESC LIMIT 1) )';

        $result = $jobDB->query($sql);
        
        if ($result === false) {
            throw new JobRouterException($jobDB->getErrorMessage());
        }
        
        while ($row = $jobDB->fetchRow($result)) {
            $this->insertSubtableRow($subtable, ++$cnt, $row);
            	
        }
       
        
        // wyposażenie opcjonalne

        $subtable = 'ST_OROPSTANEQEDIT';
        $cnt = 0;
        
        $sql = 'SELECT equipmentId, variantId, equipmentCnt, isSelected
                FROM `ST_OROPSTANEQUIPMENT`
                WHERE `processid` = (SELECT DISTINCT processid FROM bkf_cworders where processid =
                (
                SELECT p.prodOrderNumber FROM bkf_product p
                JOIN bkf_productsorders po on p.ID = po.IDProd where detachDate is NULL and p.ID =  "'.$IDproduktu.'")
                order by prodOrderId DESC LIMIT 1)
                and step_id = (SELECT max(step_id) FROM ST_OROPSTANEQUIPMENT WHERE `processid` = (SELECT DISTINCT processid FROM bkf_cworders where processid =
                (
                SELECT p.prodOrderNumber FROM bkf_product p
                JOIN bkf_productsorders po on p.ID = po.IDProd where detachDate is NULL and p.ID = "'.$IDproduktu.'")
                order by prodOrderId DESC LIMIT 1) )';

        $result = $jobDB->query($sql);
        
        if ($result === false) {
            throw new JobRouterException($jobDB->getErrorMessage());
        }
        
        while ($row = $jobDB->fetchRow($result)) {
            $this->insertSubtableRow($subtable,  ++$cnt, $row);
        }                
                
        // wyposażenie opcjonalne stanowiska
        
        $subtable = 'ST_ORSTANDEQEDIT';
        $cnt = 0;
        
        $sql = 'SELECT equipmentId, variantId, equipmentCnt
                FROM `ST_ORSTANDEQUIPMNT`
                WHERE `processid` = (SELECT DISTINCT processid FROM bkf_cworders where processid =
                (
                SELECT p.prodOrderNumber FROM bkf_product p
                JOIN bkf_productsorders po on p.ID = po.IDProd where detachDate is NULL and p.ID =  "'.$IDproduktu.'")
                order by prodOrderId DESC LIMIT 1)
                and step_id = (SELECT max(step_id) FROM ST_ORSTANDEQUIPMNT WHERE `processid` = (SELECT DISTINCT processid FROM bkf_cworders where processid =
                (
                SELECT p.prodOrderNumber FROM bkf_product p
                JOIN bkf_productsorders po on p.ID = po.IDProd where detachDate is NULL and p.ID = "'.$IDproduktu.'")
                order by prodOrderId DESC LIMIT 1) )';

        $result = $jobDB->query($sql);
        
        if ($result === false) {
            throw new JobRouterException($jobDB->getErrorMessage());
        }
        
        while ($row = $jobDB->fetchRow($result)) {
            $this->insertSubtableRow($subtable, ++$cnt, $row);
        }              
        
      // echo $this->getSubtableValue('ST_OROPEQEDIT',2, 'equipmentId');  
         
	}

}
?>