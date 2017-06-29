<?php

class className extends JobRouter\Engine\Runtime\PhpFunction\RuleExecutionFunction
{
	public function execute($rowId = null)
	{
	    $temproc=$this->getProcessId();
	    $tmpproc=$this->getTableValue('tmpId');
	            $date=date('Y-m-d  G:i:s');

	    
        $jobDB = $this->getJobDB();
        $sqlold = 'select equipmentId, variantId from bkf_confighistory where process="'.$temproc.'" 
                        and tab="ST_ORSTANDEQEDIT" and status=0;';
        $sqlnew = 'select equipmentId, variantId from bkf_confighistory where process="'.$temproc.'" 
                        and tab="ST_ORSTANDEQEDIT" and status=1;';
                         
                         
        $sqlold2 = 'select equipmentId, variantId,isSelected,equipmentCnt from bkf_confighistory where process="'.$temproc.'" 
                        and tab="ST_OROPSTANEQEDIT" and status=0;';
        $sqlnew2 = 'select equipmentId, variantId,isSelected,equipmentCnt from bkf_confighistory where process="'.$temproc.'" 
                        and tab="ST_OROPSTANEQEDIT" and status=1;';
                        
        $sqlold3 = 'select equipmentId, variantId,isSelected from bkf_confighistory where process="'.$temproc.'" 
                        and tab="ST_OROPEQEDIT" and status=0;';
        $sqlnew3 = 'select equipmentId, variantId,isSelected from bkf_confighistory where process="'.$temproc.'" 
                        and tab="ST_OROPEQEDIT" and status=1;';

        $resultold = $jobDB->query($sqlold);
        $resultnew = $jobDB->query($sqlnew);
        
        $resultold2 = $jobDB->query($sqlold2);
        $resultnew2 = $jobDB->query($sqlnew2);
        
        $resultold3 = $jobDB->query($sqlold3);
        $resultnew3 = $jobDB->query($sqlnew3);

        if ($resultold === false) {
                throw new JobRouterException($jobDB->getErrorMessage());
    }
         if ($resultnew === false) {
                throw new JobRouterException($jobDB->getErrorMessage());
    }
        if ($resultold2 === false) {
                throw new JobRouterException($jobDB->getErrorMessage());
    }
         if ($resultnew2 === false) {
                throw new JobRouterException($jobDB->getErrorMessage());
    }
         if ($resultold3 === false) {
                throw new JobRouterException($jobDB->getErrorMessage());
    }
         if ($resultnew3 === false) {
                throw new JobRouterException($jobDB->getErrorMessage());
    }
           $listOld=array();
           $listNew=array();
           
           $listOld2=array();
           $listNew2=array();
           
           $listOld3=array();
           $listNew3=array();
           
           
            while($rowold = $jobDB->fetchRow($resultold))
           
             {
                array_push($listOld,$rowold);
                 
             }
            
            while($rownew = $jobDB->fetchRow($resultnew))
            {
                array_push($listNew,$rownew);
            }
            $cnt=count($listOld);
            
            while($rowold2 = $jobDB->fetchRow($resultold2))
           
             {
                array_push($listOld2,$rowold2);
                 
             }
            
            while($rownew2 = $jobDB->fetchRow($resultnew2))
            {
                array_push($listNew2,$rownew2);
            }
            $cnt2=count($listOld2);
            echo $cnt2;
            
            while($rowold3 = $jobDB->fetchRow($resultold3))
           
             {
                array_push($listOld3,$rowold3);
                 
             }
            
            while($rownew3 = $jobDB->fetchRow($resultnew3))
            {
                array_push($listNew3,$rownew3);
            }
            $cnt3=count($listOld3);
            echo $cnt3;
               // $arr=array_diff($listOld[1],$listNew[1]);
                
//echo $cnt;
              //  print_r($arr);
              $send='';
        for($i=0;$i<$cnt;$i++)
            {
                if($listOld[$i]!==$listNew[$i])
                    {
                     $send=$send.' 
                     -- Akronim urzadzenia: ['.$listOld[$i]['equipmentId'].']
                     Poprzednia wartosc: ['.$listOld[$i]['variantId'].'] 
                     Nowa wartosc: ['.$listNew[$i]['variantId'].']';
                     
                     $sql1='INSERT INTO bkf_confighistorytmp VALUES(NULL,"'.$tmpproc.'","'.$temproc.'","ST_ORSTANDEQEDIT","'
                        .$listOld[$i]['equipmentId'].'","","","'.$listOld[$i]['variantId'].'","'
                        .$listNew[$i]['equipmentId'].'","","","'.$listNew[$i]['variantId'].'","'.$date.'");';

        
            $result = $jobDB->exec($sql1);
            if ($result === false) {
                 throw new JobRouterException($jobDB->getErrorMessage());
            }
                    }
            
                
            }
            $this->setTableValue('desc1', $send);
            $send2='';
         for($i=0;$i<$cnt2;$i++)
            {
                if($listOld2[$i]!==$listNew2[$i])
                    {
                        
                        
                        $send2=$send2.'
                        -- Akronim urzadzenia: ['.$listOld2[$i]['equipmentId'].'] 
                        Wybrany: ['.$listOld2[$i]['isSelected'].
                      '] 
                      Poprzedni wariant: ['.$listOld2[$i]['variantId'].'] 
                      Poprzednia ilosc: ['.$listOld2[$i]['equipmentCnt']
                      .'] 
                      Nowa wartosc: ['.$listNew2[$i]['isSelected'].'] Nowa ilosc: ['.$listNew2[$i]['equipmentCnt']
                       .']   
                       Nowy wariant: ['.$listNew2[$i]['variantId'].']';
                        
                        $sql1='INSERT INTO bkf_confighistorytmp VALUES(NULL,"'.$tmpproc.'","'.$temproc.'","ST_OROPSTANEQEDIT","'
                        .$listOld2[$i]['equipmentId'].'","'.$listOld2[$i]['isSelected'].'","'.$listOld2[$i]['equipmentCnt'].'","'.$listOld2[$i]['variantId'].'","'
                        .$listNew2[$i]['equipmentId'].'","'.$listNew2[$i]['isSelected'].'","'.$listNew2[$i]['equipmentCnt'].'","'.$listNew2[$i]['variantId'].'","'.$date.'");';

        
            $result = $jobDB->exec($sql1);
            if ($result === false) {
                 throw new JobRouterException($jobDB->getErrorMessage());
            }   
                        
                        
                        
                    }
            }
            $this->setTableValue('desc2', $send2);
            $send3='';
            
            for($i=0;$i<$cnt3;$i++)
            {
                if($listOld3[$i]!==$listNew3[$i])
                    {
                    
                      
                    $send3=$send3.' 
                    -- Akronim urzadzenia: ['.$listOld3[$i]['equipmentId'].'] 
                    Wybrany: ['.$listOld3[$i]['isSelected'].
                     '] 
                     Poprzedni wariant: ['.$listOld3[$i]['variantId'].'] 
                     Nowa wartosc: ['.$listNew3[$i]['isSelected'].']   
                     Nowy wariant: ['.$listNew3[$i]['variantId'].']';
                       
                       $sql1='INSERT INTO bkf_confighistorytmp VALUES(NULL,"'.$tmpproc.'","'.$temproc.'","ST_OROPEQEDIT","'
                        .$listOld3[$i]['equipmentId'].'","'.$listOld3[$i]['isSelected'].'","","'.$listOld3[$i]['variantId'].'","'
                        .$listNew3[$i]['equipmentId'].'","'.$listNew3[$i]['isSelected'].'","","'.$listNew3[$i]['variantId'].'","'.$date.'");';
                       // $this->dump($sql1);

        
                        $result = $jobDB->exec($sql1);
                         if ($result === false) {
                             throw new JobRouterException($jobDB->getErrorMessage());
                                                }   
                    }
            }

	    
	                $this->setTableValue('desc3', $send3);

	   
	}
}
?>