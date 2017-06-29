<?php
require_once('C:\inetpub\wwwroot\jobrouter\tcpdf\tcpdf.php');
class MYPDF extends TCPDF
{
    public function Header(){
        $obraz=K_PATH_IMAGES.'bkf.jpg';
        $this->Image($obraz, 5, 15, 200,'', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        //  $this->SetFont('helvetica', 'B', 20);
        // $this->Cell(0, 150, 'BKF Myjnie Bezdotykowe', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }


}




class className extends JobRouter\Engine\Runtime\PhpFunction\DialogFunction
{
    public function execute($rowId = null)
    {


//Polaczenie z BD JR


        $jobDB = $this->getJobDB();
        $sql = 'select * from bkf_fnieoplacone';
        $result = $jobDB->query($sql);
        if ($result === false) {
            throw new JobRouterException($jobDB->getErrorMessage());
        }
        $row = $jobDB->fetchRow($result);


//Polaczenie z BD XL


        $externalDB = $this->getDBConnection('CDNXL');
        $sql = "select Knt_Nazwa1,Knt_Nazwa2, Knt_Nazwa3, Knt_Ulica,Knt_KodP,Knt_Miasto,Knt_NipE,
Knt_EMail from cdn.KntKarty where Knt_Akronim='".$row['Knt_Akronim']."';";
        $res = $externalDB->query($sql);
        if ($res === false) {
            throw new JobRouterException($externalDB->getErrorMessage());
        }
        $rowE = $externalDB->fetchRow($res);
        $sql2="";



// tworzenie dokumentu PDF
        $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'iso-8859-2', false);

        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Lukasz Filipiak');
        $pdf->SetTitle('Wezwanie');
        $pdf->SetSubject('Raport Przeterminowanych Faktur');
        $pdf->SetKeywords('TCPDF');

        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));

        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);


        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);

        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        if (@file_exists(dirname(__FILE__).'/lang/pol.php')) {
            require_once(dirname(__FILE__).'/lang/pol.php');
            $pdf->setLanguageArray($l);
        }

// ---------------------------------------------------------

        $pdf->SetFont('dejavusansextralight', 'B', 12);   //dejavuserifcondensed //dejavusansextralight //dejavusans
//GENEROWANIE KOLEJNEGO NUMERU WEZWANIA
        $number="JR/".$row['Knt_Akronim']."/".date('m')."/".date('d');
//KONIEC GENEROWANIA KOLEJNEGO NUMERU WEZWANIA

        $pdf->AddPage();
        $html = '
<br><br>
<div align="right"><h5>Skarbimierzyce, dnia '.date('d-m-Y').'</h5></div>
<br><div align="left"><h6>BKF Myjnie Bezdotykowe Sp. z o.o.<br>
72-002 Dołuje k/Szczecina<br>
Skarbimierzyce 22<br>
GIOŚ: E0021655WBW<br>
NIP: 782-237-01-46<br>
Kapitał zakł: 300000 PLN, wpłacony: 300000 PLN</h6></div>
<br>
<div align="right">Kontrahent:
<h6>'.$rowE['Knt_Nazwa1'].' '.$rowE['Knt_Nazwa2'].' '.$rowE['Knt_Nazwa2'].'<br>
'.$rowE['Knt_Ulica'].'<br>
'.$rowE['Knt_KodP'].' '.$rowE['Knt_Miasto'].'<br>
NIP: '.$rowE['Knt_NipE'].'</h6></div>
<br><div align="center"><h2>WEZWANIE DO ZAPŁATY nr '.$number.'</div>
<div align="justify"><p><h6><blockquote>W związku z nieuregulowaniem przez Państwa płatności dokumentu:</blockquote>
<i>'.$row['NumerDokumentu'].'</i> wystawionego w dniu <i>'.$row['DataWystawienia'].'</i> z terminem płatności do <i>'.$row['Termin'].'</i>, 
wzywam do zapłaty kwoty wynikającej z w/w dokumentu tj. <i>'.$row['TrP_Pozostaje'].' zł</i> w termininie <i>7 dni</i> od otrzymania niniejszego wezwania.
Podstawa prawna: art. 481 oraz art. 482 ustawy z 23 kwietnia 1964 r. Kodeksu Cywilnego.   <br><br>
Proszę o przekazanie powyższej kwoty na rachunek bankowy o numerze:<br>
<i>18 1500 1113 1211 1006 2585 0000</i><br>
<bold>Bank Zachodni WBK S.A.</bold><br><br>

<div align="left">W przypadku nieuregulowania długu w wyznaczonym terminie zostaną naliczone odsetki, a sprawa zostanie skierowana do sądu, 
jednocześnie narażając Państwa na dodatkowe koszty związane z postępowaniem procesowym.</div><br></p>
<div align="right"><h6>Z poważaniem</h6></div> 


</h6></div>
';

        $pdf->writeHTML($html, true, false, true, false, '');


//$txt = <<<EOD

//EOD;


//$pdf->Write(0, $txt, '', 0, 'C', true, 0, false, false, 0);

// ---------------------------------------------------------

//Zapisz pdf do pliku
        $pdf->Output('c:\inetpub\wwwroot\jobrouter\wezwania/'.$row['Knt_Akronim'].'.pdf','F');


    }
}
?>