<?php
function changeOrder() {
$trans = DB::trans();

$fields = array('cid', 'order_nr', 'deadline', 'bconnections', 'adres', 'szerokosc', 'dlugosc',
'serviceTerms', '_dealForm', '_dealer', '_leasing', '_bofcsStatus', '_offerid' );
$data = updatedValues($fields, $this->_post);

$oid = pick($this->params, 2, null);

$data['comments'] = pick($this->_post,'comments','');

if ($oid) {
DB::q('UPDATE bkfOrders SET ' .DB::setExp($data) .',
version = version + 1
WHERE oid='.DB::str($oid) ); }
else {
DB::q('INSERT INTO bkfOrders SET ' .DB::setExp($data) .', `date` = NOW()');
$oid = DB::insertId(); }

$this->storeBkfOrdersVersionHistory($oid);

$this->saveForSupply($oid);

$trans->commit();
}



private
function _changePorder() {
DB::requireTransaction();

$processed = array('_po' => array(), 'productiontables' => array(), 'poid' => array(), 'oid'=>array());

$extraHandlers = array();

$requestedProductiontables =  $this->params[2];
$poid = pick($this->params, 3, null);

$_product = nullOnZerolenNULL(pick($this->_post, '_product', null));
$product = Products::productRcd($_product);

$linksToService2 = false;
$fields = array();


switch ($table = static::getProductionTables($requestedProductiontables)) {
default:
throw new OutOfRangeException(sprintf("unsupported production table `%s'", $table));
case 'bkfCarwashProductionOrders':
$linksToService2 = true;
$show = 'listpcorders';
$fields = array('boiler', 'boiler_st', 'boiler_serial', 'burner', 'burner_st', 'burner_serial',
'burner_nozzle', 'frame', 'water_softner', 'reverse_osmosis', 'chimney', 'dispenser',
'wrys_st', 'sticker_st', 'stainlessSteel_st', 'pallet_st', 'subassemblies_st', 'consoles_st','steelCuts','steelBending','steelCompletion','steelPainting',
'cable_length_high_pressure_1', 'cable_length_high_pressure_2', 'cable_length_high_pressure_3',
'cable_length_high_pressure_4', 'cable_length_high_pressure_5', 'cable_length_high_pressure_6',
'cable_length_heating_1', 'cable_length_heating_2', 'cable_length_heating_3',
'cable_length_heating_4', 'cable_length_heating_5', 'cable_length_heating_6',
'cable_for_brushes_1', 'cable_for_brushes_2', 'cable_for_brushes_3',
'cable_for_brushes_4', 'cable_for_brushes_5', 'cable_for_brushes_6',
'cable_length_pulpit_control', 'cable_length_pulpit_power_dot5',
'cable_length_pulpit_power_2dot5', 'cable_quantity_liner_40x40', 'cable_quantity_liner_90x60','sticker','cable_balancy_potential');

array_push($extraHandlers, array($this, '_changeTestDeadline'),
array($this, '_changeLinkService2'),
array($this, '_changeInAndOutTarget'));
break;
case 'bkfConstructionProductionOrders':
$show = 'listpcoorders';
$fields = array('attyka', 'oswietlenie', 'dach', 'sruby');
break;
case 'bkfOthersProductionOrders':
$fields = array('frame_st', 'outerpanels_st', 'profile_length_meters', 'walk_length_meters', 'area_meters_squared');
$show = 'listpotorders';
break;
case 'bkfStainlessProductionOrders':
$show = 'listpsorders';
$fields = array();
break;
case 'bkfLinematchProductionOrder':
$show = 'XXX';
$fields = array('stock', 'grit', 'sizing', 'expeditedP');
break;
case 'bkfRadioProductionOrder':
$show = 'listprorders';
$fields = array('length', 'unit_length', 'component_base', 'component_top', 'component_bottom');
break; }

$_change_parts = pick($this->_post, '_change_parts', array());
if (in_array('porderTek', $_change_parts))
array_push($extraHandlers, array($this, '_store_porderTek') );

$_subassembly = pick($this->_post, '_subassembly', null);
if ($_subassembly) {
array_push($extraHandlers, array($this, '_changeCBRelation')); }

$data = updatedValues($fields, $this->_post);

$cnt = $this->_post['cnt'];
$crossOn = self::productionCross($table);

$this->_cntSanity(L::_('quantity'), $cnt);

/*	there are two ways of handling $cnt:
- insert record witht .cnt = $cnt, when we don't care about serial number
- insert N records, each with .cnt = 1, when we care about numbers	*/
for ($toGo = $cnt; $toGo;) {
if ($product['_serialPool']) {	// yea, only when inserting new records
$localCnt = 1;
--$toGo; }
else {
$localCnt = $cnt;
$toGo = 0; }

if ($poid) { // WARNING: the $poid is cleared at the bottom of the loop
try {
$rcd = self::rcd($crossOn, $poid); }
catch (SQLNotFoundException $e) {
return $this->showNotFound(); }

if ($data)
DB::Q('UPDATE ' .DB::e($table) .' SET ' .DB::setExp($data)
.' WHERE poid=' .DB::int($poid)); }
else {
# hack: ensure we have at least one field in $data; NULL causes auto-increment auto-generation
$data['poid'] = null;
DB::Q('INSERT INTO ' .DB::e($table) .' SET ' .DB::setExp($data));
$poid = DB::insertId(); }

$poFields = array('comments', 'productionDeadline', '_poStatus', 'porder_nr', 'pw',
'_bkfOrder', 'productionPlanComments', 'shippingDeadline', 'orderDate' );
$dataPO = updatedValues($poFields, $this->_post);
$dataPO['cnt'] = $localCnt;
$dataPO['_product'] = $product['pid'];
$newoid = pick($this->_post, 'newoid', null);
if ($newoid)
$dataPO['_bkfOrder'] = $newoid;

$_po = DB::fetchNoExpect('SELECT _po
FROM productionOrder
WHERE ' .DB::e($crossOn) .' = ' .DB::int($poid), '_po' );
$_po = (int)$_po;
if ($_po) {
DB::Q('UPDATE productionOrder SET ' .DB::setExp($dataPO) .',
version = version +1		# increment even if productionOrder is unchanged -- because related tables may have been changed
WHERE _po = ' .DB::int($_po) );
# yep, update ._technology only if not set already
DB::Q('UPDATE productionOrder SET _technology = ' .DB::int($product['_technology']) .'
WHERE _technology IS NULL AND _po = '. DB::int($_po) ); }
else {
$dataPO[$crossOn] = $poid;
# yep, only set _technology once
$dataPO['_technology'] = $product['_technology'];
DB::Q('INSERT INTO productionOrder
SET ' .DB::setExp($dataPO));
$_po = DB::insertId(); }

$this->storeProductionPlan($_po);


$this->store_serialNumber_override($_po);

$this->autoAssignSerialNumber($_po, $product['_serialPool']);

foreach ($extraHandlers as $h)
call_user_func_array($h, array($_po, $crossOn, $table));

$this->storePorderVersionHistory($_po, $crossOn, $poid);

$processed['_po'][] = $_po;
$processed['productiontables'][] = $requestedProductiontables;
$processed['poid'][] = $poid;
$processed['oid'][] = $dataPO['_bkfOrder'];
if (DB::fetch('SELECT _technology FROM productionOrder WHERE _po = ' .DB::int($_po), '_technology'))
Backlog::generateForProductionOrder($_po);


$this->saveTests($requestedProductiontables,$poid,$_po);
$this->_handleOptionalParts($_po);

$poid = null; }

$calendar = pick(Config(),'google', 'calendarShipping', null);

if (Plugin::isPlugin('oauth2callback') && strlen($calendar) && $dataPO['shippingDeadline'] > 0) {
$title			= L::_H('Order');
$start 			= $dataPO['shippingDeadline'];
$_creator			= $_SESSION['user']['_pe'];

$sth = DB::fetchOne('SELECT * FROM (SELECT NULL) as _x LEFT JOIN googleCalendar ON (googleCalendar._ap IS NULL AND googleCalendar._po = '.DB::int($_po).') WHERE (googleCalendar._ap IS NULL AND googleCalendar._po = '.DB::int($_po).') OR _po IS NULL');

if ($sth['eventId']) {
$sequence = $sth['sequence'] * 1 + 1;
$id = oauth2callback::updateEventFromCalendar($sth['eventId'],$start,$start,'['.Config(array('siteName')).'] '.L::_H('Shipping ').$dataPO['porder_nr'],$title, $sequence, $calendar);
if (strlen($id) && strlen($sth['eventId']))
DB::Q('UPDATE googleCalendar SET sequence = '.DB::int($sequence).', eventId = '.DB::str($id).' WHERE eventId = '.DB::str($sth['eventId']).' AND _ap IS NULL');
}
else{
$id = oauth2callback::addEventToCalendar($start,$start,'['.Config(array('siteName')).'] '.L::_H('Shipping ').$dataPO['porder_nr'].'-'.$product['name'],$title,null,null,$calendar);
if (strlen($id))
DB::Q('INSERT INTO googleCalendar (_po, eventId, _persona) VALUES ('.DB::int($_po).','.DB::str($id).','.DB::int($_creator).')');
}
}

return $processed;
}



