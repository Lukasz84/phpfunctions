<?php

class className extends JobRouter\Engine\Runtime\PhpFunction\RuleExecutionFunction
{
	public function execute($rowId = null)
	{
	    
	    $subtable='ST_PAYMENT_RAPORT';
	    
	    
                          $externalDB = $this->getDBConnection('CDNXL');
                          $jobDB=$this->getJobDB();
                          
               
               
                $sql = "SELECT 'Dzial'=
                        CASE
                            	WHEN TrN_DokumentObcy LIKE('%WDS%') THEN 'WDS'
                            	WHEN TrN_DokumentObcy LIKE('%SYS%') THEN 'WDS'
                            	WHEN TrN_DokumentObcy LIKE('%SRWS%') THEN 'SERWIS'
                            	WHEN TrN_DokumentObcy LIKE('%MT%') THEN 'SERWIS'
                            	WHEN TrN_DokumentObcy LIKE('%CW%') THEN 'HANDLOWY'
                            	WHEN TrN_DokumentObcy LIKE('%EBKF%') THEN 'EBKF'
                            	WHEN TrN_DokumentObcy LIKE('%SAL%') THEN 'SALON'
                            	ELSE 'KSIEGOWOSC'
                        END, 
						'Odpowiedzialny'=
                        CASE
                            	WHEN TrN_DokumentObcy LIKE('%WDS%') THEN 'a.pok'
                            	WHEN TrN_DokumentObcy LIKE('%SYS%') THEN 'a.pok'
                            	WHEN TrN_DokumentObcy LIKE('%SRWS%') THEN 'a.dengusiak'
                            	WHEN TrN_DokumentObcy LIKE('%MT%') THEN 'a.dengusiak'
                            	WHEN TrN_DokumentObcy LIKE('%CW%') THEN 'a.gohra'
                            	WHEN TrN_DokumentObcy LIKE('%EBKF%') THEN 'p.krzywania'
                            	WHEN TrN_DokumentObcy LIKE('%SAL%') THEN 'p.zarynska'
                            	ELSE 'k.domino'
                        END, 
						
                        TrN_DokumentObcy as NumerDokumentu, DATEADD(day,TrN_Data2,CONVERT(DATE,'1800-12-28',120)) as DataWystawienia, Knt_Akronim, TrP_FormaNazwa, 
                        
                        DATEADD(day,TrP_Termin,CONVERT(DATE,'1800-12-28',120)) as Termin, Trp_Kwota, TrP_Pozostaje, TrP_Waluta, Ope_Ident, 
                        
                        DATEDIFF(day,DATEADD(day,TrP_Termin,CONVERT(DATE,'1800-12-28',120)),GETDATE()) as IloscDni
                        
                        FROM CDN.TraPlat JOIN CDN.KntKarty ON Knt_GIDNumer=TrP_KntNumer JOIN CDN.TraNag ON TrN_GIDTyp=TrP_GIDTyp AND TrN_GIDNumer=TrP_GIDNumer 
                        
                        LEFT JOIN CDN.OpeKarty ON TrN_OpeNumerW = Ope_GIDNumer 
                        
                        WHERE ((TRP_Typ=2 AND TRP_GIDTyp NOT IN(1497,1529,1320,1498,2010,3352,4146,2977) 
                        
                        OR TRP_Typ=1 AND TRP_GIDTyp IN(2009,2013,2041,2045,2044,2042,2043,1625,1832,2008,4146,2977)) 
                        
                        AND DATEADD(day,TrP_Termin,CONVERT(DATE,'1800-12-28',120))<=GETDATE()+3)
                        
                        -- AND TrP_Waluta like 'PLN'
                        
                        AND TrP_Rozliczona=0 AND Knt_Akronim NOT IN('JEDNORAZOWY') 
                        
                        --AND  TrN_DokumentObcy LIKE('%WDS%')
                        
                        ORDER BY CDN.TraPlat.TrP_Termin ASC";



                $result = $externalDB->query($sql);
                if ($result === false) {
                             throw new JobRouterException($externalDB->getErrorMessage());
                                     }
                        
              while($row=$jobDB->fetchRow($result))
                   {
                      $insertion=array('DataWystawienia'=>$row['DataWystawienia'],'Dzial'=>$row['Dzial'],
                      'IloscDni'=>$row['IloscDni'],'Knt_Akronim'=>$row['Knt_Akronim'],'NumerDokumentu'=>$row['NumerDokumentu'],
                      'Ope_Ident'=>$row['Ope_Ident'],'Termin'=>$row['Termin'],'TrP_FormaNazwa'=>$row['TrP_FormaNazwa'],'Trp_Kwota'=>$row['Trp_Kwota'],
                      'TrP_Pozostaje'=>$row['TrP_Pozostaje'],'TrP_Waluta'=>$row['TrP_Waluta']);
                        
                       $this->insertSubtableRow($subtable, ++$cnt, $insertion);
                       
                    }
                

                 
                
                
                
                
 

	}//zamkniecie public function
}// zamkniecie class

?>