<?php


/*	bkfOrderStatus
		the `sid' field is expected to be strictly monotonically increasing order
		(by, for example, testing logic: `Gotowe do wysyłki' must be > `Oczekuje na sprawdzenie') */


/*	Production Orders (POrd) [bkf%ProductionOrders] has `state' field linking to bkfOrderStatus.sid

		between self::testStateBefore (`Oczekuje na sprawdzenie' and self::testStateAfter (`Gotowe do wysyłki') there is extra processing for testing.

		test request:
			with serial number and test deadline and without test note attached
		planned:
			state == self::testStateBefore and with test note attached and with serial number
		recently tested:
			state >= self::testStateAfter and with test note attached and test note date ~ 2 weeks from now
		WARNING: it is possible to set a request to `Gotowe do wysyłki' without attaching any test note */

class Orders extends PluginGui {
	const testStateProduction = 2;
	const testStateBefore = 3;
	const testStateAfter = 4;
	/** whatever is suitable for automatic creation by Service2 plugin and won't list as a new order */
	const afterInstallationState = 6;
	const IN_AND_OUT_PORDER_SCHEMA = '/orders/canon/porder';
	const SERVICE_TYPE_PRODUCTION_TEST = 'production-test';

	/* Module description */
	static
	function description() {
	    $obj = array();
	    $obj['group']	= 'Production';
	    $obj['logo']	= 'fa-money';
	    $obj['tabs']	= array('orders#listorders','orders#list/pcoorders','orders#list/potorders','orders#maporders','orders#stats','orders#statsproduction','orders#overview/porder-cleanup');
	    $obj['langs']	= array(L::_H('Sale orders'),L::_H('Construction'),L::_H('Add-ons'),L::_H('Order map'), L::_H('Stats'), L::_H('Prduction Stats'), L::_H('Cleanups'));
	    return $obj;
	}


	static
	function commonPorderFiles() {
		return [
			'rzut kontenera',
			'wrys w pomieszczenie klienta',
			'rozmieszczenie stanowisk',
		];
	}


	static
	function tests() {
		return array(
			'wrys_st' => L::_('ZP Files'),
			'sticker_st' => L::_('Sticker Project'),
			'stainlessSteel_st' => L::_('Stainless Steel'),
			'device' => L::_('Device'),
			'pallet' => L::_('Pallet'),
			'pulpit' => L::_('Pulpit'),
			'steelCuts' => L::_H('Steel cuts'),
			'steelBending' => L::_H('Steel bending'),
			'steelPainting' => L::_H('Steel painting'),
			'steelCompletion' => L::_H('Steel completion'),
		);
	}


	static
	function capable($key) {
		switch ($key) {
		case 'defer-js':
		case 'ajax':
			return true;
		default:
			return null;
		}
	}


	var $stanyZam;
	var $stanyTests;
	var $stanyPrd;

	var $piece = array(
		'0'=>'-',
		'1'=>'Hm TC 85',
		'2'=>'Delta 55',
		'3'=>'Hm 60',
		'4'=>'Hm 100',
		'5'=>'Hm 200',
		'6'=>'Delta 25',
		'7'=>'Junkers',
		'8'=>'E-tech',
		'9'=>'Kospel',
		'10'=>'Inny',
		'11'=>'Hm TC 35',
	);

	function __construct() {
		$a = func_get_args();
		call_user_func_array(array('parent', '__construct'), $a);

		$this->stanyZam = array(
		'0'=>L::_H('None'),
		'1'=>L::_H('On the way'),
		'2'=>L::_H('In stock'),
		'3'=>L::_H('Not applicable'),
		'4' =>L::_H('Test')
		);

		$this->stanyTests = array(
		0 =>L::_H('before'),
		1 =>L::_H('not applicable'),
		2 =>L::_H('after'),
		);

		$this->stanyPrd = array(
		0 =>L::_H('to do'),
		1 =>L::_H('contracted'),
		2 =>L::_H('done'),
		3 =>L::_H('not applicable'),
		);

	}


	static
	function radioQuantity($rcd) {
		return ($rcd['cnt'] * $rcd['product_length_meters']);
	}


	static
	function radioQuantityExpression($rcd) {
		return sprintf('%s * %s -> %s',
			$rcd['cnt'], $rcd['product_length_meters'], $rcd['cnt'] * $rcd['product_length_meters']);
	}


	static
	function generalRadioProductionOrderFields() {
		static $a = null;

		if ($a === null)
			$a = array(
				'product_length_meters' => [
					'label' => L::_('unit length'),
					'default-value' => Config([ 'orders', 'radio', 'unit_length_default' ]) ],
				'component_base' => [
					'label' => L::_('film') ],
				'component_top' => [
					'label' => L::_('fliz górny') ],
				'component_bottom' => [
					'label' => L::_('fliz dolny') ],
			);

		return $a;
	}


	/** for example: 3 -> 'bkfOthersProductionOrders' */
	static
	function getProductionTables($index = null) {
		$productionTables = array(
				0 => '((bez tabeli))',
				1 => 'bkfCarwashProductionOrders',
				2 => 'bkfConstructionProductionOrders',
				3 => 'bkfOthersProductionOrders',
				4 => 'bkfStainlessProductionOrders',
				5 => 'bkfSubassemblyBatchProductionOrder',
				6 => 'bkfLinematchProductionOrder',
				7 => 'bkfRadioProductionOrder' );

		if (func_num_args() === 0)
			return $productionTables;
		if (isset($productionTables[$index]))
			return $productionTables[$index];

		throw new OutOfRangeException(sprintf("unsupported production table index `%s'", $index));
	}


	/** for example: 'bkfOthersProductionOrders' -> 3 */
	static
	function getProductionTableNr($table = null) {
		static $a = null;
		if ($a === null)
			$a = array_flip(self::getProductionTables());

		if (func_num_args() === 0)
			return $a;
		if (isset($a[$table]))
			return $a[$table];

		throw new OutOfRangeException(sprintf("unsupported production table `%s'", $table));
	}


	/// this thingie is canonical
	/// like bkfCarwashProductionOrders -> _carwash
	static
	function productionCross($table = null) {
		$a = array('bkfCarwashProductionOrders' => '_carwash',
			'bkfConstructionProductionOrders' => '_construction',
			'bkfOthersProductionOrders' => '_others',
			'bkfStainlessProductionOrders' => '_stainless',
			'bkfSubassemblyBatchProductionOrder' => '_subassemblyBatch',
			'bkfLinematchProductionOrder' => '_linematch',
			'bkfRadioProductionOrder' => '_radio' );

		if (func_num_args() === 0)
			return $a;

		if (isset($a[$table]))
			return $a[$table];

		throw new OutOfRangeException(sprintf("no relation configured for table `%s'", $table));
	}


	static
	function procureProductiontablesForGenericPo($rcd) {
		$a = static::relationMetaForGenericPo($rcd);
		$rcd['poid'] = $a['poid'];
		$rcd['productiontables'] = static::getProductionTableNr($a['table']);
		return $rcd;
	}


	/// WARNING: only supports single relation.
	/// for multiple relations, implement a different method :D
	static
	function relationMetaForGenericPo($rcd) {
		if (!$rcd['_po'])
			throw new Exception('expects a valid Production Order instance');

		$ret = array();
		$a = static::productionTableForRelation();
		foreach ($a as $rel => $table)
			if ($rcd[$rel]) {
				$poid = $rcd[$rel];
				$ret[] = compact('rel', 'table', 'poid'); }

		switch (count($ret)) {
		case 1:
			return $ret[0];
		case 0:
			throw new Exception(sprintf('got Production Order without any supported relation'));
		default:
			throw new Exception(sprintf('got Production Order with multiple relations, dunno what to do.')); }
	}


	/// like _carwash -> bkfCarwashProductionOrders
	static
	function productionTableForRelation($relation = null) {
		static $a = null;

		if ($a === null)
			$a = array_flip(self::productionCross());

		if (func_num_args() === 0)
			return $a;

		if (isset($a[$relation]))
			return $a[$relation];

		throw new OutOfRangeException(sprintf("no table configured for relation `%s'", $relation));
	}


	function statsQry($schema, $v = null, $x = null, $y = null) {
		switch ($schema) {
		case 'porders-bkfCarwashProductionOrders-byDate':
			$onlyTable = 'bkfCarwashProductionOrders';

			$finished	= DB::fetch('SELECT _bls FROM backlogStatus WHERE awaitsTestingP = 1 LIMIT 1', '_bls');
			$working	= DB::fetch('SELECT _bls FROM backlogStatus WHERE beingWorkedOnP = 1 LIMIT 1', '_bls');
			$cancelled= DB::fetch('SELECT sid FROM bkfOrderStatus WHERE sense = '.DB::str('bkfOrderStatus-cancelled').' LIMIT 1', 'sid');

			$month	= $x;
			$year	= $v;
			$team	= $y;

			$date	= $year.'-'.$month.'-01';
			$prevdate = date('Y/m/d', strtotime($date."-2 month"));
			$nextdate = date('Y/m/d', strtotime($date."+1 month"));

			$qry = DB::instance('
							SELECT  bH.changeMarker, persona.orgName,  persona._pe, YEAR(bH.changeMarker) AS year, MONTH(bH.changeMarker) AS `month`,bH.title,bkfOrders.order_nr, bH._status, hardware.product, hardware.serialNumber,pO._po FROM `backlog` AS b
									JOIN backlogHistory AS bH ON (b._bl = bH._bl  AND bH._backlogSense = 3)
									LEFT JOIN productionOrder AS pO ON (pO._po = bH._productionOrder)
									LEFT JOIN bkfOrders ON (bkfOrders.oid = pO._bkfOrder)
									LEFT JOIN persona ON (persona._pe = b._assignee)
									LEFT JOIN hardware ON (hardware._prorder = pO._po)
									WHERE
										((bH._status = '.DB::int($finished).' OR bH._status = '.DB::int($working).')) 
										AND b._assignee IS NOT NULL AND bH.idx IS NULL and b.idx IS NULL
										AND (b._backlogSense = 3 OR bH._backlogSense = 3)
										AND bH.changeMarker >= '.DB::str($prevdate).' AND bH.changeMarker <= '.DB::str($nextdate).'
										AND bH._assignee = '.DB::int($team).'
										AND pO._postatus != '.DB::int($cancelled).'
									ORDER BY bH.changeMarker DESC
							');

			return $qry;

		case 'porders-bkfCarwashProductionOrders-withStands-byCompleted-byQuarter':
			$onlyTable = 'bkfCarwashProductionOrders';

			$finished	= DB::fetch('SELECT _bls FROM backlogStatus WHERE awaitsTestingP = 1 LIMIT 1', '_bls');
			$working	= DB::fetch('SELECT _bls FROM backlogStatus WHERE beingWorkedOnP = 1 LIMIT 1', '_bls');

			$qry = DB::instance('
							SELECT *,COUNT(`quarter`) AS `cdx` FROM 
								(
									SELECT MAX(IFNULL(bH.changeMarker,b.changeMarker)) AS finishedDate, persona.orgName,  persona._pe, YEAR(bH.changeMarker) AS year, MONTH(bH.changeMarker) AS quarter FROM `backlog` AS b
									LEFT JOIN backlogHistory AS bH ON (b._bl = bH._bl AND bH._status = '.DB::int($finished).' AND bH._backlogSense = 3)
									LEFT JOIN persona ON (persona._pe = b._assignee)
									WHERE
										((bH._status = '.DB::int($finished).' AND b._status != '.DB::int($working).') OR b._status = '.DB::int($finished).') 
										AND b._assignee IS NOT NULL AND bH.idx IS NULL and b.idx IS NULL
										AND (b._backlogSense = 3 OR bH._backlogSense = 3)
									GROUP BY b._productionOrder
								) as tab
							GROUP BY `year` DESC, `quarter` DESC, _pe
							ORDER BY `year` DESC, `quarter` DESC, orgName
							' );

			$qry->logicTxt =  L::_("Production orders (bkfOrders) with completed production orders from table $onlyTable.
				Stand count from bkfProducts.standCount (NULL for some devices)." );

			return  $qry;

		case 'porders-bkfCarwashProductionOrders-withStands-byDeadline-byQuarter':

			$onlyTable = 'bkfCarwashProductionOrders';

			$qry = DB::instance('SELECT
				COUNT(_x.oid) AS ZSContracted,
					-- count number of products that have stands... basically, carwashes ;-)
				SUM(stuffWithStand) AS hardwareWithStandsContracted,
				SUM(standCount) AS stands,
				QUARTER(bkfOrders.deadline) AS quarter,
				YEAR(bkfOrders.deadline) AS year

				FROM bkfOrders
				LEFT JOIN (
					SELECT oo.oid, SUM(bp.standCount) AS standCount,
						COUNT(bp.standCount) AS stuffWithStand
						FROM bkfOrders AS oo
						JOIN productionOrder ON oo.oid = _bkfOrder
						JOIN ' .DB::e($onlyTable) .' AS bcpo ON _carwash = poid
						JOIN bkfProducts AS bp ON _product = bp.pid
						GROUP BY oo.oid
						ORDER BY NULL) AS _x ON bkfOrders.oid = _x.oid
				GROUP BY year, quarter
				WITH ROLLUP' );

			$qry->logicTxt =  L::_("Sales orders (bkfOrders) with all (open, completed, cancelled) production orders from table $onlyTable.
				Deadline from bkfOrders.deadline.
				Stand count from bkfProducts.standCount (NULL for some devices)." );

			return  $qry;
		default:
			throw new LogicException(sprintf('unsupported qry schema `%s\'', $schema)); }
	}


	function showList() {
		$data = null;
		$q = pick($this->params, 2, null);
		$show = 'orders/list/'.$q;

		$stanyZam = $this->stanyZam;
		$stanyTests = $this->stanyTests;

		switch ($q) {
		case 'test':
			$statusList	= self::qry('productionOrderStatus-byIdDesc', 0)->arrAll('sid');
			$qryRequested	= self::qry('requestedTesting-byDeadline');
			$qryPlanned	= self::qry('plannedTesting-byDate');
			$qryProduction	= self::qry('inproductionTesting-byDate');
			$qryRecent	= self::qry('recentTesting-twoWeeks-byDate');
			break;
		case 'pcoorders':
			$statusList = self::qry('productionOrderStatus-byListingFor-byIdDesc', 'listingForGeneric', 0)->arrAll('sid');
			$qry = self::qryProductionOrder('bkfConstructionProductionOrders', array_keys($statusList));
			break;
		case 'prorders':
			$statusList = self::qry('productionOrderStatus-byListingFor-byIdDesc', 'listingForGeneric', 0)->arrAll('sid');
			$qry = self::qryProductionOrder('bkfRadioProductionOrder', array_keys($statusList));
			$piece = $this->piece;
			$scheduleStatusQry = Backlog::scheduleQry('productionOrderScheduleStatus-all');
			break;
		case 'potorders':
			$statusList = self::qry('productionOrderStatus-byListingFor-byIdDesc', 'listingForGeneric', 0)->arrAll('sid');
			$qry = self::qryProductionOrder('bkfOthersProductionOrders', array_keys($statusList));
			$piece = $this->piece;
			break;
		case 'pcorders':
			$statusList = self::qry('productionOrderStatus-byListingFor-byIdDesc', 'listingForGeneric', 0)->arrAll('sid');
			$qry = self::qryProductionOrder('bkfCarwashProductionOrders', array_keys($statusList));
			$piece = $this->piece;
			$scheduleStatusQry = Backlog::scheduleQry('productionOrderScheduleStatus-all');
			break;
		case 'psorders':
			$productiontables = static::getProductionTableNr('bkfStainlessProductionOrders');
			$statusList = self::qry('productionOrderStatus-byListingFor-byIdDesc', 'listingForStainless', 0)->arrAll('sid');
			$qry = self::qryProductionOrder('bkfStainlessProductionOrders', array_keys($statusList));
			$piece = $this->piece;
			break;
		case 'psbrequests':
			$statusList = self::qry('productionOrderStatus-byListingFor-byIdDesc', 'listingForGeneric', 0)->arrAll('sid');
			$relQry = DB::instance('
				SELECT DISTINCT
					radio_subassembly.*,
					blSubassembly.title AS subassembly, _subassembly, _po, serialNumber,
					radio_subassembly.quantity,
					porder_nr,
					product_width_meters, product_length_meters,
					name AS bkfProduct
				FROM radio_subassembly
				JOIN backlog AS blSubassembly ON _subassembly = _bl
				JOIN productionOrder USING(_po)
				JOIN bkfProducts ON _final_pid = pid
				LEFT JOIN serialNumber ON _serialNumber = _sn
				LEFT JOIN backlogSubassemblyBatch ON _po = _batch
				WHERE (radio_subassembly.quantity != 0 OR radio_subassembly.quantity IS NULL)
					AND _batch IS NULL
					AND _poStatus IN (' .DB::listExp(array_keys($statusList)) .') ' );
			break;
		case 'psborders':
			$statusList = self::qry('productionOrderStatus-byListingFor-byIdDesc', 'listingForGeneric', 0)->arrAll('sid');
			$qry = self::qryProductionOrder('bkfSubassemblyBatchProductionOrder', array_keys($statusList));
			$productionOrdersForSubassemblyBatchQry = Backlog::subassemblyBatchQry(
				'productionOrder-forSubassemblyBatch-by_poStatus', array_keys($statusList) );
			$scheduleStatusQry = Backlog::scheduleQry('productionOrderScheduleStatus-all');
			$piece = $this->piece;
			break;
		case 'radio_subassembly':
			$statusList = self::qry('productionOrderStatus-byListingFor-byIdDesc', 'listingForGeneric', 0)->arrAll('sid');
			$qry = self::qrySubassemblyProductionOrder('bkfRadioProductionOrder', array_keys($statusList));
			$productionOrdersForSubassemblyBatchQry = Backlog::subassemblyBatchQry(
				'productionOrder-forSubassemblyBatch-by_poStatus', array_keys($statusList) );
			$scheduleStatusQry = Backlog::scheduleQry('productionOrderScheduleStatus-all');
			$piece = $this->piece;
			break;
		default:
			$this->ajaxP
				OR Http::notFound();
			$show = 'dialog/error';
			$title = 'Błąd -- nie znaleziono';
			$text = 'Zasób nie został znaleziony'; }

		if ($data === null)
			$data = compact('title', 'text', 'stanyZam', 'piece', 'por', 'orders', 'statusList', 'qry',
				'qryRequested', 'qryPlanned', 'qryRecent', 'scheduleStatusQry', 'productiontables', 'qryProduction',
				'productionOrdersForSubassemblyBatchQry', 'relQry', 'stanyTests' );
		$this->body($show, $data);
	}


	static
	function sqlPart($schema, $table = 'generic-po') {
		$a = [];

		switch ($table) {
		case 'bkfLinematchProductionOrder':
			$a['porder-title'] = 'CONCAT_WS(" ", porder_nr, bkfProducts.name)';
			break;
		case 'bkfRadioProductionOrder':
			$a['porder-title'] = 'CONCAT_WS("\0",
					porder_nr,
					bkfProducts.name,
					CONCAT(cnt, " * ", product_length_meters, "m -> ",
						cnt * product_length_meters)
				)';
			break;
		case 'bkfOthersProductionOrders':
			$a['porder-title'] = 'CONCAT_WS("\0",
					bkfOrders.order_nr,
					bkfProducts.name,
					bkfClients.orgName,
					CONCAT("(", SUBSTR(bkfOrders.adres, 1, 20), ")")
				)';
			break;
		case 'generic-po':
		default:
			$a['porder-title'] = 'CONCAT_WS("\0",
					`index`,
					CONCAT("#", serialNumber),
					bkfClients.orgName,
					CONCAT("(", SUBSTR(bkfOrders.adres, 1, 20), ")")
				)';
			break; }

		return $a[$schema];
	}


	static
	function backlogStatusQry($schema /*, extra-args */) {
		$a = func_get_args();
		array_shift($a);

		switch ($schema) {
		case 'backlogStatus-byTeamworkOrdering':
			return DB::instance('SELECT *
				FROM backlogStatus
				ORDER BY teamworkOrdering');

		case 'backlogStatus-byAzpOrdering':
			return DB::instance('SELECT *
				FROM backlogStatus
				ORDER BY azpOrdering');

		case 'backlogStatus-byConcerned':
			list($column) = $a;

			return DB::instance('SELECT _bls, status, cssClass
				FROM backlogStatus
				WHERE ' .DB::e($column) );
		default:
			throw new Exception(sprintf('unsupported qry schema `%s\'', $schema)); }
	}


	function showOverview() {
		$q = pick($this->params, 2, null);
		$show = 'orders/overview/' .$q;

		switch ($q) {
		case 'porder-cleanup':
			$statusSense = 'bkfOrderStatus-cancelled';

			$openBacklogQry = DB::instance('SELECT _po, openBacklogCount,
				' .$this->sqlPart('porder-title') .' AS title
				FROM (
					SELECT _po AS _xPo, COUNT(_bl) AS openBacklogCount
					FROM bkfOrderStatus
					JOIN backlogStatus
					JOIN productionOrder ON sid = _poStatus
					JOIN backlog ON _po = _productionOrder
						AND _bls = _status
					WHERE  bkfOrderStatus.sense = "bkfOrderStatus-cancelled"
						AND !backlogStatus.closedP
					GROUP BY _po
					ORDER BY NULL) AS _x
				JOIN productionOrder ON _xPo = _po
				LEFT JOIN bkfProducts ON _product = pid
				LEFT JOIN bkfOrders ON _bkfOrder = oid
				LEFT JOIN bkfClients ON bkfOrders.cid  = bkfClients.cid
				LEFT JOIN serialNumber ON _serialNumber = _sn' );

			$subassemblyTemplateQry = DB::instance('SELECT
				_stage, _subassembly, quantity, subassemblyBl.title AS subassembly,
				stageBl.title AS stage,
				_po,
				' .$this->sqlPart('porder-title') .' AS poTitle
				FROM (
					SELECT _bl AS _xBl
					FROM backlogStatus
					JOIN backlog ON _bls = _status
					JOIN backlogSubassemblyTemplate ON _bl = _stage
					WHERE backlogStatus.closedP
						AND !backlogStatus.closedP
						AND (quantity !=0 OR quantity IS NULL)
					GROUP BY _bl
					ORDER BY NULL) AS _x
				JOIN backlog AS stageBl ON _xBl = stageBl._bl
				JOIN backlogSubassemblyTemplate ON _xBl = _stage
				JOIN backlog AS subassemblyBl ON _subassembly = subassemblyBl._bl
				LEFT JOIN productionOrder ON stageBl._productionOrder = _po
				LEFT JOIN bkfProducts ON _product = pid
				LEFT JOIN bkfOrders ON _bkfOrder = oid
				LEFT JOIN bkfClients ON bkfOrders.cid  = bkfClients.cid
				LEFT JOIN serialNumber ON _serialNumber = _sn
				WHERE quantity != 0 OR quantity IS NULL' );

			$subassemblyBatchQry = DB::instance('SELECT
				_stage, _batch, quantity, subassemblyBl.title AS subassembly,
				stageBl.title AS stage,
				productionOrder._po,
				' .$this->sqlPart("porder-title") .' AS poTitle
				FROM (
					SELECT _bl AS _xBl
					FROM backlogStatus
					JOIN backlog ON _bls = _status
					JOIN backlogSubassemblyBatch ON _bl = _stage
					WHERE backlogStatus.closedP
						AND !backlogStatus.closedP
						AND (quantity != 0 OR quantity IS NULL)
					GROUP BY _bl
					ORDER BY NULL) AS _x
				JOIN backlog AS stageBl ON _xBl = stageBl._bl
				JOIN backlogSubassemblyBatch ON _xBl = _stage
				JOIN productionOrder AS batchPo ON _batch = batchPo._po
				JOIN backlog AS subassemblyBl ON batchPo._technology = subassemblyBl._bl
				LEFT JOIN productionOrder ON stageBl._productionOrder = productionOrder._po
				LEFT JOIN bkfProducts ON productionOrder._product = pid
				LEFT JOIN bkfOrders ON productionOrder._bkfOrder = oid
				LEFT JOIN bkfClients ON bkfOrders.cid  = bkfClients.cid
				LEFT JOIN serialNumber ON productionOrder._serialNumber = _sn
				WHERE quantity != 0 OR quantity IS NULL' );

			break;
		case 'recent-teamwork-changesets':
			$nDays = 14;
			$recentChangesetsQry = DB::instance('SELECT _bc, ctime, TIME(ctime) AS ctimeTime,
					DATE(ctime) AS ctimeDate, persona.title AS persona
				FROM (SELECT _changeset
						FROM backlogSense
						JOIN backlog ON _bs = _backlogSense
						JOIN backlogChangeset ON _changeset = _bc
						WHERE backlogSense = "backlogSense-teamwork"
							AND ctime >= DATE_SUB(CURDATE(), INTERVAL ' .DB::int($nDays) .' DAY)
						GROUP BY _changeset
					ORDER BY NULL) AS _x
				JOIN backlogChangeset ON _x._changeset = _bc
				JOIN persona ON _persona = _pe
				ORDER BY _x._changeset DESC' );
			$recentChangesetsQry->logicTxt = L::_(
				'RecentChanges: %d day',
				'Recent changes, %d days', $nDays);

			break;
		case 'test':
			break;
		default:
			return $this->showNotFound(); }

		$data = compact('assignedQry', 'unassignedQry', 'recentlyClosedQry', 'teamQry', 'backlogChangesetRcd',
			'nDays', 'recentChangesetsQry', 'backlogStatusQry', 'openBacklogQry', 'subassemblyTemplateQry',
			'subassemblyBatchQry', 'statusQry' );
		$this->body($show, $data);
	}


	static
	function bkfOrderStatusRcdBySense($sense, $onlyField = null) {
		return DB::fetch('SELECT * FROM bkfOrderStatus WHERE sense = ' .DB::str($sense), $onlyField);
	}


	static
	function porder_descriptor($rcd) {
		static $schema = null;

		switch ($schema) {
		case null:
			$schema = Config([ 'orders', 'descriptor', 'porder' ]);
			return static::porder_descriptor($rcd);
		case 'porder_nr+serialNumber':
			return sprintf('%s / %s',
				$rcd['porder_nr'], andStr('#', $rcd['serialNumber']) );
		case 'porder_nr+bkfProduct+width+priv_label':
			return sprintf('%s / %s / %s / %s',
# FIXME -- use .width, .private_label
				$rcd['porder_nr'], $rcd['bkfProduct'], $rcd['product_width_meters'], '?' );
		default:
			throw new Exception(sprintf('unsupported schema `%s\'', $schema)); }
	}


	static
	function productionPlanRcd($_porder) {
		return DB::fetchOne('SELECT productionPlan.*
			FROM (SELECT NULL) AS _x
			LEFT JOIN productionPlan ON _porder = ' .DB::int($_porder) );
	}

	function changeTest() {
		$id = pick($this->params,2,null);
		$sid = pick($this->params,3,null);

		$trans = DB::trans();

		if ($id) {
			$rcd				= service2::hardwareRcd($id);
			$productiontables	= '1'; /* Only CarWASH */
			$poid			= $rcd['_carwash'];
			$plcSerial		= pick($this->_post,'plcSerial',null);
			$plcMac			= pick($this->_post,'plcMac',null);
			$start			= pick($this->_post,'start',null);
			$text1			= pick($this->_post,'text1',null);
			$text2			= pick($this->_post,'text2',null);
			$plan__assignee	= pick($this->_post,'plan__assignee',null);
			$boiler			= pick($this->_post,'boiler',null);
			$boiler_serial		= pick($this->_post,'boiler_serial',null);
			$burner			= pick($this->_post,'burner',null);
			$burner_serial		= pick($this->_post,'burner_serial',null);
			$burner_nozzle		= pick($this->_post,'burner_nozzle',null);
			$_creator			= $_SESSION['user']['_pe'];

			if ($plan__assignee > 0)
				$_creator = $plan__assignee;

			$table = static::getProductionTables($productiontables);
			$crossOn = self::productionCross($table);

			$_po = DB::fetchNoExpect('SELECT _po
				FROM productionOrder
				WHERE ' .DB::e($crossOn) .' = ' .DB::int($poid), '_po' );


			$this->saveTests($productiontables, $poid, $_po);

			$data = array('plcSerial'=>$plcSerial,'plcMac'=>$plcMac);

			DB::Q('UPDATE _hardware SET ' .DB::setExp($data)
					.' WHERE _hw=' .DB::int($id)); 

			$text=$text1."\n---------------\n".$text2;

			DB::Q('UPDATE bkfCarwashProductionOrders SET boiler = '.DB::int($boiler).', 
												boiler_serial = '.DB::str($boiler_serial).',
												burner = '.DB::str($burner).', 
												burner_serial = '.DB::str($burner_serial).',
												burner_nozzle = '.DB::str($burner_nozzle).'
						WHERE poid = '.DB::int($poid));

			$entries = DB::fetchOne('SELECT COUNT(*) AS cdx FROM service WHERE _hardware =  '.DB::int($rcd['_hw']).' AND _type = (SELECT _tp FROM serviceType WHERE sense="production-test" LIMIT 1)');

			if ($sid) {
				$sth = DB::fetchOne('SELECT * FROM service WHERE _sv = '.DB::int($sid));
				DB::Q('UPDATE appointment SET text='.DB::str($text).', _assignee = '.DB::int($plan__assignee).', start='.DB::str($start).'  WHERE _ap='.DB::int($sth['_request']));
			}else{

				if ($entries['cdx'] > 0)
					return $this->showNotFound();

				$_hardware = $rcd['_hw'];
				$title=L::_H('Test report');
				$st = DB::prepare('INSERT INTO appointment (created, _creator, start, title, text, _assignee)
				SELECT NOW(), :_creator, :start, :title, :text, :plan__assignee' );
				$st->execute(compact('_creator', 'start', 'title', 'text', 'plan__assignee'));
				$_request = DB::insertId();

				$st = DB::prepare('INSERT INTO _service (_request, _hardware, _type)
				SELECT :_request, :_hardware, (SELECT _tp FROM serviceType WHERE sense='.DB::str('production-test').' LIMIT 1)' );
				$st->execute(compact('_request', '_hardware'));
				$sid = DB::insertId();

			}


			if (array_key_exists('_teamworkStatus', $this->_post)) {
				$poRcd = static::rcd($crossOn, $poid);
				$from = array('_status' => $poRcd['_teamworkStatus'],
					'_assignee' => $poRcd['_team'],
					'ordering' => array('method' => 'whatever', '_bl' => $poRcd['_teamwork']) );
				$to = array('_status' => $this->_post['_teamworkStatus'],
					'_assignee' => $poRcd['_team'],
					'ordering' => array('method' => 'asLast') );

				BacklogPatcher::backlogApplyPatch($poRcd['_teamwork'], $poRcd['_po'], 'backlogSense-teamwork',
					compact('from', 'to') ); } }
		else
			return $this->showNotFound();

		$trans->commit();


		if (Plugin::isPlugin('oauth2callback') && strlen($start)) {
			$title			= pick($this->_post,'title',null);
			$sth = DB::fetchOne('SELECT * FROM service LEFT JOIN googleCalendar ON (googleCalendar._ap = service._request AND googleCalendar._persona = '.DB::int($_creator).') WHERE _sv = '.DB::int($sid));

			if ($sth['eventId']) {
				$sequence = $sth['sequence'] * 1 + 1;
				$id = oauth2callback::updateEventFromCalendar($sth['eventId'],$start,$start,'['.Config(array('siteName')).'] '.L::_H('TEST #').$rcd['serialNumber'],$title,$sequence);
					if (strlen($id) && strlen($sth['eventId']))
						DB::Q('UPDATE googleCalendar SET sequence = '.DB::int($sequence).', eventId = '.DB::str($id).' WHERE eventId = '.DB::str($sth['eventId']).' AND _persona='.DB::int($_creator).'');
			}
			else{
				$id = oauth2callback::addEventToCalendar($start,$start,'['.Config(array('siteName')).'] '.L::_H('TEST #').$rcd['serialNumber'].'-'.$rcd['productName'],$title);
				if (strlen($id))
					DB::Q('INSERT INTO googleCalendar (_ap,eventId,_persona) VALUES ('.DB::int($sth['_request']).','.DB::str($id).','.DB::int($_creator).')');
			}
		}


		$show = 'yuijs/reloadplugintabs';
		$touri = '/orders/';
		$data = compact('touri');
		$this->body($show, $data);
	}

	function showEdit() {
		$q = pick($this->params, 2, null);
		list($id, $params) = uriPathParams(pick($this->params, 3, null));
		$id2 = pick($this->params, 4, null);
		$show = 'orders/edit/' .$q;
		$referer = pick($_SERVER, 'HTTP_REFERER', null);

		$stanyZam		= $this->stanyZam;
		$piece		= $this->piece;
		$clientsQry	= null;
		$offer		= null;

		switch ($q) {
		case 'test':
			$relation			= '_carwash';
			$rcd				= service2::hardwareRcd($id);
			$productiontables	= '1'; /* Only CarWASH */
			$poid			= $rcd['_carwash'];
			$tests			= self::qry('testsFor',$productiontables,$poid)->arrAll('object');
			$nrcd 			= self::rcd($relation, $poid);
			$record			= service2::serviceRcd($id2);
			$_creator			= $_SESSION['user']['_pe'];

			$t = explode("\n---------------\n",$record['text']);
			$record['text1'] = pick($t,0,'');
			$record['text2'] = pick($t,1,'');
			$personnelQry		= Service2::personnelQry('everything-byTitle','testteams');
			$backlogStatusQry = static::backlogStatusQry('backlogStatus-byTeamworkOrdering');
			$targetBacklogStatusRcd = DB::fetchOne('SELECT *
				FROM backlogStatus
				WHERE teamworkOrdering IS NOT NULL
					AND closedP
					AND storageP');
			$onlySupportedSourceStatusRcd = DB::fetchOne('SELECT *
				FROM backlogStatus
				WHERE teamworkOrdering IS NOT NULL
						-- the one-before
					AND teamworkOrdering = (' .DB::int($targetBacklogStatusRcd['teamworkOrdering']) .' -1 )' );

			if (Plugin::isPlugin('oauth2callback') && ($record['_sv'])) {
				$sth = DB::fetchOne('SELECT * FROM service LEFT JOIN googleCalendar ON (googleCalendar._ap = service._request AND googleCalendar._persona = '.DB::int($_creator).') WHERE _sv = '.DB::int($record['_sv']));
				if ($sth['eventId']) {
					$event = oauth2callback::getEventFromCalendar($sth['eventId']);
					if (is_array($event)) 
						$record['start'] = $event['date']->date;
				}
			}

			break;
		case '3way-porder':
			$InAndOut = pick($this->_get, 'InAndOut', null);
			$InAndOutRcd = InAndOut::importRcdPlusExtrasFrom(static::IN_AND_OUT_PORDER_SCHEMA, $InAndOut);

			$rcd = static::genericRcdByPorderNr($InAndOutRcd['record']['porder_nr']);
			$incomingRcd = $InAndOutRcd['record'];

			$outputRcd = static::genericRcd(null);

			foreach ($rcd as $k => $localV) {
				$incomingV = $incomingRcd[$k];
				if (($rcd['_po'] === null)
					|| ($localV === $incomingV) )
					$outputRcd[$k] = $incomingV;
				if (is_int($localV) && is_string($incomingV)
					&& (((string)$localV) === $incomingV) )
						$outputRcd[$k] = $incomingV; }

			$serialPoolQry = DB::instance('SELECT *
				FROM serialPoolLabel
				ORDER BY serialPool' );

			$statusQry = self::qry('productionOrderStatus-byIdDesc');
			$defaultStatus = static::bkfOrderStatusRcdBySense('bkfOrderStatus-new', 'sid');

			break;
		case 'generic-po':
			$_po				= $id;
			$rcd				= static::genericRcd($_po);
			$statusList		= Orders::qry('productionOrderStatus-byIdDesc')->arrAll('sid');
			$productionPlanRcd	= static::productionPlanRcd($rcd['_po']);
			if (PG::hasPgP()) {
				$sellerQrys = supply::qry('dataforseller',$rcd['_technology']);
				$sellerQry = supply::qry('statusList');
				$supply = supply::pick('supplyFor',$rcd['_technology'],$_po); }
			else
				$sellerQrys = null;
	
			$porderTekQry = static::porderTekQry('porderTekQry-forPorder-byMemoFilename', $rcd['_po']);
			$commonPorderFiles = static::commonPorderFiles();

			break;
		case 'subassemblyBatchFromSubassembly':
			$_subassembly = $id;
			$_input_pid = $params['_input_pid'];
			try {
				$subassemblyRcd = Backlog::technologyRcd($_subassembly); }
			catch (SQLNotFoundException $e) {
				return $this->showNotFound(); }
			if (!$subassemblyRcd['_bl'])
				return $this->showNotFound();

			$_po = null;
			$poid = null;
			$relation = '_subassemblyBatch';
			$productiontables = Orders::getProductionTableNr(Orders::productionTableForRelation($relation));
			$rcd = Orders::rcd($relation, $poid);
			$rcd['_poStatus'] = static::bkfOrderStatusRcdBySense('bkfOrderStatus-new', 'sid');
			$statusList = static::qry('productionOrderStatus-byIdDesc')->arrAll('sid');
			$openStatusList = static::qry('productionOrderStatus-byListingFor-byIdDesc', 'listingForGeneric', 0)->arrAll('sid');
			$productQry = Backlog::productQry('bkfProducts-byTechnologyAndPid-byIndex',
				$subassemblyRcd['_backlog'], $_input_pid);
			$subassemblyTemplateQry = Backlog::backlogSubassemblyTemplateQry(
				'backlogSubassemblyTemplate-bySubassemblyAndStatusListAndRadioAndInputPid',
					$_subassembly, array_keys($openStatusList), $_input_pid );

			$preselectAll = $subassemblyTemplateQry->arrAll(null, '_productionOrder');
			$subassemblyTemplateQry->rewind();
			$preselect = (array)pick($params, 'preselect', $preselectAll);

			break;
		case 'supply':
			$dfQry		= offers::qry('bkfOffersProductsByOid',$id);
			$rcd				= supply::qry('elementsUsedinOffer',$id);
			$elementsQry		= supply::qry('elementsList');
			$productQry		= supply::qry('technologiesUsedInOffer',$id);
			$configSupplyQry	= supply::qry('configSupply');
			$this->layout($this->layoutSchema('json'));
			break;

		case 'offerorder':
			$show		= 'orders/edit/order';
			$offer		= $id;
			$id			= null;
			$clientsQry	= offers::qry('bkfOffersClientsByOid',$offer);

		case 'order':
			$clientQry = Clients::qry('bkfClients-withClientContacts-forAutocompletion-byAkronim');
			$rcd = self::orderRcd($id);
			$statusList = self::qry('bkfOrderForClientStatus-byIdDesc')->arrAll('bofcs');
			$dfQry = DB::instance('SELECT * FROM dealForm ORDER BY _df');
			break;

		case 'porder':
			$productiontables = $id;
			$poid = $id2;
			$statusList = self::qry('productionOrderStatus-byIdDesc')->arrAll('sid');
			$tests = self::qry('testsFor',$productiontables,$poid)->arrAll('object');
			try {
				$relation = static::productionCross(static::getProductionTables($productiontables)); }
			catch (OutOfRangeException $e) {
				return $this->showBadRequest($e->getMessage()); }
			$rcd = self::rcd($relation, $poid);

			if ($rcd['poid'] === null)
				$rcd['productiontables'] = $productiontables;

			if (!$rcd['poid'])
				$rcd['_poStatus'] = static::bkfOrderStatusRcdBySense('bkfOrderStatus-new', 'sid');

			switch (static::getProductionTables($productiontables)) {
			case 'bkfSubassemblyBatchProductionOrder':
				$_subassembly = $rcd['_technology'];
				$subassemblyRcd = Backlog::technologyRcd($_subassembly);
				$_batch = $rcd['_po'];
				$subassemblyBatchQry = Backlog::subassemblyBatchQry('subassemblyBatch-byBatch', $_batch);
				$productRcd = Products::productRcd($rcd['_product']);

				break;
			default:
				$productQry = self::productQry('forProductiontables-byIndex', $productiontables);
				true; }

			$teamQry = static::teamQry('assemblyTeams-byTitle-includingDesignated', $rcd['_team']);
			$backlogStatusQry = static::backlogStatusQry('backlogStatus-byTeamworkOrdering');

			$productionPlanRcd = static::productionPlanRcd($rcd['_po']);

			if (!$productionPlanRcd['adjustmentHours'])
				$productionPlanRcd['adjustmentHours'] = 0;

			$stageQry = DB::instance('SELECT _bl, title, _status
				FROM backlog
				JOIN backlogSense ON _backlogSense = _bs
				WHERE _productionOrder = ' .DB::int($rcd['_po']) .'
					AND backlogSense = "backlogSense-stage"
				ORDER BY technologyOrdering' );

			$stageStatusQry = DB::instance('SELECT _bls, status, defaultP
				FROM backlogStatus
				ORDER BY _bls' );

			$InAndOutSchemaList = InAndOut::export_emailSchemaList();

			$porderTekQry = static::porderTekQry('porderTekQry-forPorder-byMemoFilename', $rcd['_po']);
			$commonPorderFiles = static::commonPorderFiles();

			$compatibleProductQry = static::productQry('productQry-compatible-plusCurrent-byPorder',
				$rcd['_po'] );

			$show = 'orders/edit/' .$q .'/' .$relation;
			break;
		default:
			return $this->showNotFound(); }

		$data = compact('stanyZam', 'statusList', 'piece', 'rcd', 'clientQry', 'productQry', 'subassemblyBatchQry',
			'subassemblyRcd', 'subassemblyTemplateQry', 'preselect', 'productRcd', 'teamQry',
			'backlogStatusQry', 'dfQry', 'productionPlanRcd', 'referer', 'nrcd','supply','elementsQry',
			'stageQry', 'stageStatusQry', 'outputRcd', 'incomingRcd', 'InAndOutRcd','sellerQrys','sellerQry',
			'serialPoolQry', 'InAndOutSchemaList', 'statusQry', 'defaultStatus', 'InAndOut',
			'_input_pid', 'porderTekQry', 'commonPorderFiles', 'tests', 'record', 'personnelQry', 'targetBacklogStatusRcd',
			'onlySupportedSourceStatusRcd', 'compatibleProductQry', 'configSupplyQry', 'offer', 'clientsQry' );
		$this->body($show, $data);
	}

	static
	function porderTekQry($schema) {
		$args = func_get_args();
		array_shift($args);

		switch ($schema) {
		case 'porderTekQry-forPorder-byMemoFilename':

			list($_po) = $args;

			return DB::instance('SELECT _po, memo, tekno.*,
					mimeHandling.*
				FROM productionOrder
				JOIN porderTek USING(_po)
				JOIN tekno USING(_tk)
				LEFT JOIN mimeHandling ON _mime = _mh
				WHERE _po = '. DB::int($_po) .'
				ORDER BY memo, filename' );
		default:
			throw new Exception(sprintf('unsupported schema `%s\'', $schema)); }
	}


	static
	function teamQry($schema) {
		switch ($schema) {
		case 'assemblyTeams-byTitle-includingDesignated':
			list( , $designated) = func_get_args();

			$tag = 'assemblyteams';

			return DB::callOneRowset('persona_withOrgName_directly_tagName_plusDesignated_byTitle',
				compact('tag', 'designated') );
		case 'personas-inAssemblyTeams-byTitle-includingDesignated':
			list( , $designated) = func_get_args();

			$tag = 'assemblyteams';

			return DB::callOneRowset('persona_withoutOrgName_inTagName_plusDesignated_byTitle',
				compact('tag', 'designated') );
		default:
			throw new Exception(sprintf('unsupported qry schema `%s\'', $schema)); }
	}

	static
	function productionOrderQry($schema, $v = null) {
		switch ($schema) {
		case 'productionOrders-inProgress-forBatch-forAc':
			$_batch = $v;

			/*	_po
				ZS
				ZP
				client
				#serial
				product type (index, title)
				address	*/
			return DB::instance('
				SELECT _po,
					CONCAT_WS(" / ",
						CONCAT("#", serialNumber),
						bkfProducts.`index`,
						order_nr,
						porder_nr,
						akronim,
						bkfOrders.adres ) AS label
				FROM productionOrder
				JOIN serialNumber ON _serialNumber = _sn
				LEFT JOIN bkfCarwashProductionOrders ON _carwash = bkfCarwashProductionOrders.poid
				LEFT JOIN bkfOrderStatus ON _poStatus = bkfOrderStatus.sid
				LEFT JOIN bkfOrders ON bkfCarwashProductionOrders.oid = bkfOrders.oid
				LEFT JOIN bkfClients ON bkfOrders.cid = bkfClients.cid
				LEFT JOIN bkfProducts ON bkfCarwashProductionOrders.pid = bkfProducts.pid
				WHERE bkfOrderStatus.openP
					OR _po IN (SELECT _target FROM subassemblyBatch WHERE _batch = ' .DB::int($_batch) .')' );

		case 'productionOrder':
			return DB::instance('SELECT * FROM productionOrder WHERE _po = '.DB::int($v) );

		default:
			throw new Exception('unsupported query schema ' .$schema); }
	}


	/** ONLY for cases when adding needs VERY different dialog than normal edition */
	function showAdd() {
		$q = pick($this->params, 2, null);
		$id = pick($this->params, 3, null);
		$show = 'orders/add/' .$q;

		$stanyZam = $this->stanyZam;
		$piece = $this->piece;

		switch ($q) {
		case 'porder':
			$oid = $id;
			$toSkip = array('bkfSubassemblyBatchProductionOrder');
			$pr = $this->productQry('bkfProducts-withProductionSet-skipCertainSets-byIndex',
				$toSkip );
			$statusQry = self::qry('productionOrderStatus-byIdDesc');
			$defaultStatus = static::bkfOrderStatusRcdBySense('bkfOrderStatus-new', 'sid');
			break;
		default:
			return $this->showNotFound(); }

		$data = compact('stanyZam', 'piece', 'oid', 'pr', 'statusQry', 'defaultStatus');
		$this->body($show, $data);
	}


	function showPartNotFound($message) {
		$this->httpStatus(404);
		$show = 'dialog/error';
		$title = L::_('Error: not found');
		$text = sprintf(L::_('An important part (%s) of the requested resource was not found on the server'),
			$message);

		$data = compact('title', 'text');
		$this->body($show, $data);
	}


	function showSwap() {
		$q = pick($this->params, 2, null);
		$show = 'orders/swap/' .$q;
		list($id, $params) = uriPathParams(pick($this->params, 3, null));
		$id2 = pick($this->params, 4, null);
		$data = array();

		switch ($q) {
		case 'porder':
			$productiontables = $id;
			$poid = $id2;
			$relation = static::productionCross(static::getProductionTables($productiontables));
			$rcd = self::rcd($relation, $poid);

			$statusList = self::qry('productionOrderStatus-byListingFor-byIdDesc', 'listingForGeneric', 0)->arrAll('sid');
			$qry = self::qryProductionOrder('bkfCarwashProductionOrders', array_keys($statusList));

			$data = compact('rcd', 'qry', 'statusList');
			break;
		default:
			return $this->showNotFound(); }

		$this->body($show, $data);
	}


	function showTable() {
		$q = $this->params[2];
		$show = 'orders/table/' .$q;

		switch ($q) {
		case 'okb':
			break;
		case 'okv':
			break;
		default:
			return $this->showNotFound(); }

		$data = compact('PLACEHOLDER');
		$this->body($show, $data);
	}


	function showStats($type=null) {
		$show	= 'orders/stats'.$type;
		$year	= pick($this->params,2,null);
		$month	= pick($this->params,3,null);
		$person	= pick($this->params,4,null);
		$finished	= DB::fetch('SELECT _bls FROM backlogStatus WHERE awaitsTestingP = 1 LIMIT 1', '_bls');
		$working	= DB::fetch('SELECT _bls FROM backlogStatus WHERE beingWorkedOnP = 1 LIMIT 1', '_bls');


		if ($type == 'production')
			$perQuarterQry = $this->statsQry('porders-bkfCarwashProductionOrders-withStands-byCompleted-byQuarter');
		else
			$perQuarterQry = $this->statsQry('porders-bkfCarwashProductionOrders-withStands-byDeadline-byQuarter');

		if ($year && $month)
			$detailsQry = $this->statsQry('porders-bkfCarwashProductionOrders-byDate', $year, $month, $person);

		$data = compact('perQuarterQry', 'detailsQry', 'finished', 'working','year','month');
		$this->body($show, $data);
	}


	function show_mail_cycle() {
		$q = pick($this->params, 2, null);
		$id = pick($this->params, 3, null);
		$show = 'orders/mail-cycle/' .$q;

		switch ($q) {
		case 'generic-po':
			$_po = $id;
			$rcd = static::genericRcd($_po);
			$statusList = Orders::qry('productionOrderStatus-byIdDesc')->arrAll('sid');
			$exportSchemaList = Config(['InAndOut', 'export-email']);
			break;
		default:
			return $this->showNotFound(); }

		$data = compact('rcd', 'statusList', 'exportSchemaList');
		$this->body($show, $data);
	}


	function show() {
		$data = array();
		$q = pick($this->params, 1, 'index');
		$show = $q;

		switch($q) {
		case 'mail-cycle':
			return $this->show_mail_cycle();
		case 'table':
			return $this->showTable();
		case 'stats':
			return $this->showStats();
		case 'statsproduction':
			return $this->showStats('production');
		case 'index':
			$show = 'orders/' .$q;
			break;
		case 'swap':
			return $this->showSwap();
		case 'list':
			return $this->showList();
		case 'overview':
			return $this->showOverview();
		case 'edit':
			return $this->showEdit();
		case 'add':
			return $this->showAdd();
		case 'listorders':
		case 'maporders':
			$filter = pick($this->_get, 'filter', 'open');
			switch ($filter) {
			case 'open':
				$qry = 'open-byDeadline-byClient';
				break;
			case 'closed':
				$qry = 'closed-byDeadline-byClient';
				break;
			default:
				throw new Exception('unsupported filter '.$filter); }
			$orders = $this->qry($qry);
			$a = $orders->arrAll(null, 'oid');
			$orders->rewind();
			$pordersByOrder = array();
				# FIXME -- do it in one query
			foreach ($a as $oid)
				$pordersByOrder[$oid] = static::getProductionOrders($oid);
			$data = compact('orders', 'filter', 'pordersByOrder');
			break;
		case 'addproduct':
			$show = 'editproduct';
			break;
		case 'editporderparent':
			$this->layout( $this->newStruct() );
			$openOrderQry = $this->qry('open-byDeadline-byClient');
			$closedOrderQry = $this->qry('closed-byDeadline-byClient');
			$data = compact('openOrderQry', 'closedOrderQry');
			break;
		case 'editproduct':
			$data = Products::productRcd($this->params[2]);
			break;
		default:
			return $this->showNotFound(); }

		$this->body($show, $data);
	}


	function newStruct() {
		return array('body');
	}



	function change_mail_cycle() {
		$id = pick($this->params, 2, null);
		$export_emailSchema = pick($this->_post, 'export_emailSchema', null);

		$this->_change_mail_cycle($id, $export_emailSchema);

		$show = 'yuijs/reloadplugintabs';
		$touri = '/orders/';
		$data = compact('touri');
		$this->body($show, $data);
	}


	private
	function _change_mail_cycle($_po, $export_emailSchema) {

		$from = Config(['siteName']);

		$rcd = static::genericRcd($_po);

		$fields = array();

		$serialize = function($label, $field) use($rcd) {
				# prepend TAB to every new line
				# so we can easily pick continued text
			$wrappedField = implode(LF .TAB, explode(LF, $rcd[$field]));
			return sprintf('%s: %s',
				$label, $wrappedField );
		};

			# field names from perspective of /receiver/ === vendor
		$fields[] = $serialize('Source ZS', 'order_nr');
		$fields[] = $serialize('Source ZP', 'porder_nr');
			# lol wut haxx ;p
		$fields[] = $serialize('ZP', 'targetNierdzewka');
		$fields[] = $serialize('Product index', 'index');
		$fields[] = $serialize('Serial number prefix', 'serialNumberPrefix');
		$fields[] = $serialize('Serial number', 'serialNumber');
		$fields[] = $serialize('Comments', 'comments');

		$body = implode(LF, $fields);

		$subject = sprintf(L::_('New ZP: %s: %s, from: %s'),
			$rcd['porder_nr'], $rcd['index'], $from);

		InAndOut::exportViaEmail($export_emailSchema, $subject, $body);
	}


	function change_3way_porder() {
		$_po = pick($this->params, 2, null);

		$data = array();

		$trans = DB::trans();

		$data['comments'] = $this->_post['comments'];
		if ($_po) {
			$data['_po'] = $_po;
			$st = DB::prepare('UPDATE productionOrder
				SET comments = :comments
				WHERE _po = :_po' );
			$st->execute($data); }
		else {
			$st = DB::prepare('INSERT INTO productionOrder (comments)
				SELECT :comments' );
			$st->execute($data);
			$_po = DB::insertId(); }


		$this->store_serialNumber_override($_po);


		$trans->commit();

		$external_id = pick($this->_post, 'InAndOut', 'external_id', null);
		if ($external_id)
			InAndOut::handleAfterImport(static::IN_AND_OUT_PORDER_SCHEMA, $external_id, $_po);

		$show = 'yuijs/reloadplugintabs';
		$touri = '/orders/';
		$data = compact('touri');
		$this->body($show, $data);
	}


	function change_productionOrderProductionEstimate() {
		$trans = DB::trans();

		$stUpsert = DB::prepare('
			INSERT INTO productionOrderProductionEstimate (_po, days_from_teamwork, days_best_estimate)
				VALUES (:_po, :days_from_teamwork, :days_best_estimate)
			ON DUPLICATE KEY UPDATE
				days_from_teamwork = VALUES(days_from_teamwork),
				days_best_estimate = VALUES(days_best_estimate)' );
		$stDel = DB::prepare('DELETE FROM productionOrderProductionEstimate
			WHERE _po = :_po ');

		foreach ($this->_post as $_po => $days_from_teamwork) {
			if ($days_from_teamwork === null)
				$stDel->execute(compact('_po'));
			else {
				$days_best_estimate = $days_from_teamwork;

				$stUpsert->execute(compact('_po', 'days_from_teamwork', 'days_best_estimate')); } }

		$trans->commit();
	}


	function change() {
		$q = pick($this->params, 1, null);
		$show = 'yuijs/reloadplugintabs';
		$touri = '/orders/';
		$referer = pick($this->_post,'referer','');
		$handledP = false;

		switch ($q) {
		case 'productionOrderProductionEstimate':
			return $this->changeWrap('change_productionOrderProductionEstimate');
		case '3way-porder':
			$handledP = $this->changeWrap('change_3way_porder');
			break;
		case 'test':
			return $this->changeWrap('changeTest');
		case 'mail-cycle':
			return $this->change_mail_cycle();
		case 'order':
			$handledP = $this->changeWrap('changeOrder');
			break;
		case 'subassemblyBatchFromSubassembly':
		case 'addprtopo':
			$handledP = $this->changeWrap('changeAddPorder');
			break;
		case 'porder':
			$handledP = $this->changeWrap('changePorder');
			break;
		case 'generic-po':
			$handledP = $this->changeWrap('changeGenericPo');
			break;
		default:
			return $this->showNotFound(); }

		if (strpos($referer,'/teamwork')>0)
			$touri = '/teamwork/';

		if (!$handledP) {
			$data = compact('touri');
			$this->body($show, $data); }
	}

	function saveSupply($_po,$supply) {
		$rcd				= static::genericRcd($_po);
		$supplyRecord		= supply::pick('supplyFor',$rcd['_technology'],$_po);
		$data 			= array();

		if ($rcd['_technology'] > 0) {

			foreach ($supply as $key=>$val) {
				$status = str_replace('model_','_status_',$key);
					if ($val * 1 < 0)
						$data[$status] = abs($val * 1);
					elseif (strlen($val)>1) {
						$data[$key] = $val;
						if ($supplyRecord[$status] < 1)
							$data[$status] = Config(array('supply','defaultStatus'));
					}
			}

			if (count($data)>0) {
				if ($supplyRecord['_supply'] > 0) {
				/* UPDATE */

					$st = PG::prepare('UPDATE supply SET '.PG::setPlaMD5($data).' WHERE _po = :_po AND _technology = :_technology');
					$data = PG::plaDataMD5($data);
					$data['_po'] = $_po;
					$data['_technology'] = $rcd['_technology'];
					$st->execute($data);
				}else{
				/* INSERT */
					$st = PG::prepare('INSERT INTO supply ('.PG::colListExp($data).') VALUES ('.PG::plaMD5($data).')');
					$data = PG::plaDataMD5($data);
					$data['_po'] = $_po;
					$data['_technology'] = $rcd['_technology'];
					$st->execute($data);
				}
			}

		}
	}

	function changeGenericPo() {
		$_po		= pick($this->params, 2, null);
		$supply	= pick($this->_post,'supply',array());

		$trans = DB::trans();

		$this->saveSupply($_po,$supply);

		$fields = array('_poStatus', 'comments', 'productionDeadline', 'orderDate');
		$data = updatedValues($fields, $this->_post);

		DB::Q('UPDATE productionOrder
			SET ' .DB::setExp($data) .'
			WHERE _po = ' .DB::int($_po) );

		$newoid = pick($this->_post, 'newoid', null);
		if ($newoid)
			DB::Q('UPDATE productionOrder
				SET _bkfOrder = ' .DB::int($newoid) .'
				WHERE _po = ' .DB::int($_po) );

			# yep, update ._technology only if not set already
		$_technology = DB::fetchOne('SELECT bkfProducts._technology
			FROM productionOrder
			LEFT JOIN bkfProducts ON _product = pid
			WHERE _po = ' .DB::int($_po), '_technology' );
		if ($_technology)
			DB::Q('UPDATE productionOrder
				SET _technology = ' .DB::int($_technology) .'
				WHERE _po = ' .DB::int($_po) );

		DB::Q('UPDATE productionOrder
			SET version = version + 1
			WHERE _po = ' .DB::int($_po) );
		$rcd = DB::fetchOne('SELECT * FROM productionOrder WHERE _po = ' .DB::int($_po) );
		$this->storeGenericPorderVersionHistory($rcd);

		if (DB::fetch('SELECT _technology FROM productionOrder WHERE _po = ' .DB::int($_po), '_technology'))
			Backlog::generateForProductionOrder($_po);

		$this->storeProductionPlan($_po);

		$_change_parts = pick($this->_post, '_change_parts', array());
		if (in_array('porderTek', $_change_parts))
			$this->_store_porderTek($_po);

		$trans->commit();

		return $handled = false;
	}


	/* formerly ::getProdactionOrders() */
	static
	function getProductionOrders($oid) {
		return DB::fetchAll('
			SELECT serialNumber, _po, `index`, _poStatus, porder_nr, bkfProducts.pid, productionOrder.comments,
				_po,
				bkfProducts.name,
				productionDeadline
			FROM bkfOrders
			JOIN productionOrder ON oid = _bkfOrder
			LEFT JOIN bkfProducts ON productionOrder._product = bkfProducts.pid
			LEFT JOIN serialNumber ON _serialNumber = _sn
			WHERE bkfOrders.oid = ' .DB::int($oid) );
	}


	static
	function rcd($relation, $poid) {
		$by = 'relation';
		return static::_rcd($by, null, $relation, $poid);
	}


	static
	function genericRcd($_po) {
		$by = '_po';
		return static::_rcd($by, $_po, null, null);
	}


	static
	function genericRcdByPorderNr($porder_nr) {
		$by = 'porder_nr';
		return static::_rcd($by, null, null, null, $porder_nr);
	}


	private
	static
	function _rcd($by, $_po, $relation, $poid, $porder_nr = null) {

		switch ((string)$by) {
		case 'relation':
			$table = static::productionTableForRelation($relation);
			$tableSql = DB::e($table);
			$tableNr = self::getProductionTableNr($table);
			$porderJoinSql = ' po.poid = productionOrder.' .DB::e($relation);
			break;
		case 'porder_nr':
			$tableSql = ' ( SELECT NULL AS poid ) ';
			$tableNr = null;
			$porderJoinSql = ' porder_nr = ' .DB::str($porder_nr);
			break;
		case '_po':
			$tableSql = ' ( SELECT NULL AS poid ) ';
			$tableNr = null;
			$porderJoinSql = ' _po = ' .DB::int($_po);
			break;
		default:
			throw new Exception(sprintf('unsupported $by `%s\'', $by)); }

		$teamworkBacklogSense = 'backlogSense-teamwork';

		return DB::fetchOne('SELECT po.*, bkfProducts.*,
				bkfOrders.order_nr,
				bkfOrders.deadline,
				bkfClients.akronim,
				serialNumber,
				serialNumberPrefix,
				productionOrder._po,
				productionOrder.*,
				productionOrder.comments AS pcomments,
				' .DB::int($tableNr) .' AS productiontables,
				backlog._bl AS _teamwork,
				COALESCE(
					backlog._status,
					(SELECT _bls FROM backlogStatus WHERE toBeScheduledP) ) AS _teamworkStatus,
				backlog._assignee AS _team,
				plcSerial, plcMac
			FROM (SELECT NULL) AS _x
			LEFT JOIN ' .$tableSql .' AS po ON poid = ' .DB::int($poid) .'
			LEFT JOIN productionOrder ON ' .$porderJoinSql .'
			LEFT JOIN bkfOrders ON productionOrder._bkfOrder = bkfOrders.oid
			LEFT JOIN bkfClients ON bkfOrders.cid = bkfClients.cid
			LEFT JOIN bkfProducts ON _product = bkfProducts.pid
			LEFT JOIN bkfProductsGroups ON bkfProducts.gid = bkfProductsGroups.gid
			LEFT JOIN serialNumber ON _serialNumber = _sn
			LEFT JOIN serialPool ON _snPool = _sp
			LEFT JOIN backlogSense ON backlogSense = ' .DB::str($teamworkBacklogSense) .'
			LEFT JOIN backlog ON _bs = _backlogSense
				AND backlog._productionOrder = _po
			LEFT JOIN _hardware ON _po = _prorder
				AND _po = _productionOrder' );
	}


	static
	function orderRcd($oid) {
		return DB::fetchOne( 'SELECT bkfOrders.*,CONCAT(IFNULL(offerYearNo,"")," ",IFNULL(offerTitle,"")," #",bkfOffers.id) AS offerTitle
			FROM (SELECT NULL) AS _x
			LEFT JOIN bkfOrders ON bkfOrders.oid = ' .DB::int($oid) .'
			LEFT JOIN bkfOffers ON bkfOffers.id = bkfOrders._offerid
			WHERE bkfOrders.oid '.DB::is($oid) );
	}


	static
	function orderRcdByOrderNr($order_nr) {
		return DB::fetchOne( 'SELECT bkfOrders.*
			FROM (SELECT NULL) AS _x
			LEFT JOIN bkfOrders ON order_nr = ' .DB::str($order_nr) .'
			WHERE order_nr '.DB::is($order_nr) );
	}


	static
	function qrySubassemblyProductionOrder($table, $bkfOrderStatusList) {
		return static::_qryProductionOrder($table, 'backlogSubassemblyBatch', $bkfOrderStatusList);
	}


	static
	function qryProductionOrder($table, $bkfOrderStatusList) {
		return static::_qryProductionOrder($table, null, $bkfOrderStatusList);
	}


	static
	function _qryProductionOrder($table, $subassemblyTable, $bkfOrderStatusList) {
		$subassembly_cond = $groupBy = $extraJoin = $extraExp = '';

		switch ($table) {
		case 'bkfSubassemblyBatchProductionOrder':
			$extraExp = ' (SELECT SUM(backlogSubassemblyBatch.quantity)
					FROM backlogSubassemblyBatch
					WHERE _batch = productionOrder._po
				) AS quantity, ';
			break;
		case 'bkfCarwashProductionOrders':
			$extraExp = ' po.boiler_serial, po.burner_serial, po.wrys_st, pallet_st, po.sticker_st, po.stainlessSteel_st,
				subassemblies_st, consoles_st, b1.status AS cfg_device, b2.status AS cfg_pallet, b3.status AS cfg_pulpit, ';
			$extraJoin .= ' LEFT JOIN productionTests AS b1 ON (b1._poid = poid AND b1.object = "device" AND b1._productiontable = 1)';
			$extraJoin .= ' LEFT JOIN productionTests AS b2 ON (b2._poid = poid AND b2.object = "pallet" AND b2._productiontable = 1)';
			$extraJoin .= ' LEFT JOIN productionTests AS b3 ON (b3._poid = poid AND b3.object = "pulpit" AND b3._productiontable = 1)';
			break;
		case 'bkfRadioProductionOrder':
			$extraExp = ' po.length, po.private_label, po.unit_length,
				po.component_base, po.component_top, po.component_bottom, ';
			break;
		case 'bkfOthersProductionOrders':
			$extraExp = ' po.profile_length_meters, po.walk_length_meters, po.area_meters_squared, ';
			break;
		case 'bkfConstructionProductionOrders':
		case 'bkfStainlessProductionOrders':
			break;
		default:
			trigger_error('unsupported qryProductionOrder table '.$table, E_USER_ERROR); }

		switch ($subassemblyTable) {
		case 'backlogSubassemblyBatch':
			$extraExp = ' (SELECT SUM(backlogSubassemblyBatch.quantity)
					FROM backlogSubassemblyBatch
					WHERE _batch = productionOrder._po
				) AS quantity, ';
			$subassembly_cond = ' AND (_po IN (SELECT DISTINCT _batch FROM backlogSubassemblyBatch)) ';
			break;
		case null:
			break;
		default:
			throw new Exception(sprintf('unsupported subassemblyTable: `%s\'', $subassemblyTable)); }

		$crossOn = self::productionCross($table);
		$tableNr = self::getProductionTableNr($table);
		$backlogSenseTeamwork = 'backlogSense-teamwork';

		return DB::instance('SELECT bkfOrders.oid, bkfOrders.order_nr, bkfOrders.deadline,
			bkfProducts.pid, bkfProducts.`index`, bkfProducts.name,
			client.akronim AS client, dealer.akronim AS dealer, leasing.akronim AS leasing,
			productionOrder.comments, productionOrder.productionPlanComments, _po, _poStatus, porder_nr, cnt, productionDeadline,
			' .DB::int($tableNr) .' AS productiontables,
			serialNumber,
			po.poid,
			product_length_meters, product_width_meters,
			' .$extraExp .'
			' .DB::str($table) .' AS _table,
			backlog._assignee AS _team,
			persona.title AS team,
			dealForm.dealForm, dealForm.dealFormSense, dealForm.dealFormColor,
				-- FIXME ;p
			targetNierdzewka,
			bkfProducts.name AS bkfProduct
			FROM ' .DB::e($table) .'  AS po
			JOIN productionOrder ON po.poid = productionOrder.' .DB::e($crossOn) .'
			LEFT JOIN bkfOrders ON _bkfOrder = bkfOrders.oid
			LEFT JOIN bkfProducts ON _product = bkfProducts.pid
			LEFT JOIN bkfClients AS client ON bkfOrders.cid = client.cid
			LEFT JOIN bkfClients AS dealer ON bkfOrders._dealer = dealer.cid
			LEFT JOIN bkfClients AS leasing ON bkfOrders._leasing = leasing.cid
			LEFT JOIN serialNumber ON _serialNumber = _sn
			LEFT JOIN backlogSense ON backlogSense = ' .DB::str($backlogSenseTeamwork) .'
			LEFT JOIN dealForm ON _dealForm = _df
			LEFT JOIN backlog ON _bs = _backlogSense
				AND _po = _productionOrder
			LEFT JOIN persona ON _assignee = _pe
			' .$extraJoin .'
			WHERE _poStatus IN (' .DB::listExp($bkfOrderStatusList) .')'
			.$subassembly_cond
			.$groupBy .'
			ORDER BY bkfOrders.deadline
		');
	}


	static
	function productionTestQry() {
		$a = func_get_args();
		$schema = array_shift($a);

		switch ($schema) {
		case 'productionTest-completed-byPorder-byStart':
			list($_po) = $a;

			return DB::instance('SELECT service.*
				FROM serviceType
				JOIN service ON _tp = _type
				JOIN _hardware ON _hardware = _hw
				JOIN productionOrder ON _prorder = _po
				WHERE _po = ' .DB::int($_po) .'
					AND serviceType.sense = ' .DB::str(static::SERVICE_TYPE_PRODUCTION_TEST) .'
				ORDER BY start' );

		default:
			throw new Exception(sprintf('unsupported qry schema `%s\'', $schema)); }
	}


	private static
	function subqryTesting($cond, $ordering) {
		$table = 'bkfCarwashProductionOrders';
		return DB::instance('SELECT bkfProducts.index, _hardware._hw,
			bkfClients.akronim,
			bkfOrders.order_nr,
			bkfOrders.deadline,
			bXpo.*,
			serialNumber,
			productionOrder.*,
			_service._sv,
			appointment.start AS noteTime,
			appointment.title,
			productiontables,
			days_best_estimate,
			backlogStatus._bls AS _teamworkStatus,
			backlogStatus.status AS teamworkStatus
			FROM ' .DB::e($table) .' AS bXpo
			JOIN productionOrder ON bXpo.poid = _carwash
			LEFT JOIN bkfOrderStatus ON _poStatus = sid
			LEFT JOIN productionOrderProductionEstimate USING(_po)
			LEFT JOIN serialNumber ON _serialNumber = _sn
			LEFT JOIN _hardware ON _po = _prorder
			LEFT JOIN bkfOrders ON _bkfOrder = bkfOrders.oid
			LEFT JOIN bkfProducts ON _product = bkfProducts.pid
			LEFT JOIN bkfProductsGroups ON bkfProducts.gid = bkfProductsGroups.gid
			LEFT JOIN bkfClients ON bkfOrders.cid = bkfClients.cid
			LEFT JOIN serviceType ON serviceType.sense = ' .DB::str(static::SERVICE_TYPE_PRODUCTION_TEST) .'
			LEFT JOIN _service ON _hardware = _hw AND _type = _tp
			LEFT JOIN appointment ON _service._request = appointment._ap
			LEFT JOIN backlogSense ON backlogSense = "backlogSense-teamwork"
			LEFT JOIN backlog ON _po = _productionOrder AND _bs = _backlogSense
			LEFT JOIN backlogStatus ON backlog._status = _bls
			WHERE _hw IS NOT NULL ' .$cond .'
			' .$ordering );
	}


	static
	function qry($schema, $v = null, $x = null /*, ... */) {

		$openClosedQFragm = '	SELECT _bkfOrder
				FROM bkfOrders
				JOIN productionOrder ON oid = _bkfOrder
				JOIN bkfOrderStatus ON _poStatus = sid
				WHERE openP
			UNION
				SELECT oid
					FROM bkfOrders
					WHERE oid NOT IN (SELECT _bkfOrder FROM productionOrder WHERE _bkfOrder IS NOT NULL) ';
		$where = null;

		switch ($schema) {
		case 'bkfOrderForClientStatus-byIdDesc':
			return DB::instance('SELECT * FROM bkfOrderForClientStatus ORDER BY bofcs');
		case 'testsFor':
			return DB::instance('SELECT object,status
				FROM productionTests
				WHERE _productiontable = '.DB::int($v).' AND _poid = '.DB::int($x));
		case 'requestedTesting-byDeadline':
			/* why serviceType._tp and not just _service._sv? because we're interested in service of one particular type :P */
			$cond = ' AND _sv IS NULL
				AND (productionOrder.testDeadline
					OR backlogStatus.awaitsTestingP )
				AND !backlogStatus.closedP
				AND  bkfOrderStatus.openP';
			$ordering = ' ORDER BY productionOrderProductionEstimate.days_best_estimate DESC';
			return self::subqryTesting($cond, $ordering);
		case 'inproductionTesting-byDate':
			$cond = ' AND _sv IS NULL
				AND (backlogStatus.cssClass = "bls-awaiting-for-product" || backlogStatus.cssClass = "bls-under-construction")
				AND !backlogStatus.closedP
				AND  bkfOrderStatus.openP';
			$ordering = ' ORDER BY appointment.start';
			return self::subqryTesting($cond, $ordering);
		case 'plannedTesting-byDate':
			$cond = ' AND _sv IS NOT NULL
				AND (productionOrder.testDeadline
					OR backlogStatus.awaitsTestingP 
					OR backlogStatus.beingWorkedOnP )
				AND !backlogStatus.closedP
				AND  bkfOrderStatus.openP';
			$ordering = ' ORDER BY appointment.start';
			return self::subqryTesting($cond, $ordering);
		case 'recentTesting-twoWeeks-byDate':
			$cond = ' AND _sv IS NOT NULL
				AND backlogStatus.closedP
				AND appointment.start > DATE_SUB(NOW(), INTERVAL 1 MONTH) ';
			$ordering = ' ORDER BY appointment.start';
			return self::subqryTesting($cond, $ordering);
		case 'productionOrderStatusId-testingCandidates';
			return DB::Q('SELECT sid FROM bkfOrderStatus
				WHERE sid IN ()');
		case 'productionOrderStatus-byListingFor-byIdDesc':
			list(, $listingFor, $step) = func_get_args();
			$where = ' WHERE ' .DB::e($listingFor) .' = ' .DB::int($step);
		case 'productionOrderStatus-byIdDesc':
			return DB::instance('SELECT * FROM bkfOrderStatus
				'. $where .'
				ORDER BY ordering DESC');
		case 'open-byDeadline-byClient':

			return DB::instance( '
				SELECT o.*,
					c.akronim AS client, dealer.akronim AS dealer, leasing.akronim AS leasing
					, o.adres AS adres1
					, o.szerokosc AS szerokosc1
					, o.dlugosc AS dlugosc1
				FROM bkfOrders AS o
					LEFT JOIN bkfClients AS c ON o.cid = c.cid
					LEFT JOIN bkfClients AS dealer ON o._dealer = dealer.cid
					LEFT JOIN bkfClients AS leasing ON o._leasing = leasing.cid
					WHERE oid IN (' .$openClosedQFragm .')
					ORDER BY o.deadline, c.akronim ASC');
		case 'closed-byDeadline-byClient':
			return DB::instance( '
				SELECT o.*,
					c.akronim AS client, dealer.akronim AS dealer, leasing.akronim AS leasing
					, o.adres AS adres1
					, o.szerokosc AS szerokosc1
					, o.dlugosc AS dlugosc1
				FROM bkfOrders AS o
					LEFT JOIN bkfClients AS c ON o.cid = c.cid
					LEFT JOIN bkfClients AS dealer ON o._dealer = dealer.cid
					LEFT JOIN bkfClients AS leasing ON o._leasing = leasing.cid
					WHERE oid NOT IN (' .$openClosedQFragm .')
					ORDER BY o.deadline, c.akronim ASC');
		case 'technologyTimesByProductionOrder':
			return DB::instance('SELECT pid, bkfProducts.name AS product, bkfProducts.`index` AS productIndex, bStage._bl, bStage.title AS stage, bStage.idx, SEC_TO_TIME(productionTimeTemplate.hours * 3600) AS hours, productionTimeTemplate.hours AS orghours , strutP
							FROM productionOrder
								LEFT JOIN bkfProducts ON (bkfProducts.pid = productionOrder._product)
								LEFT JOIN backlog AS bTech ON productionOrder._technology = bTech._bl
								LEFT JOIN backlog AS bStage ON bTech._bl = bStage._parent
								LEFT JOIN productionTimeTemplate ON bStage.idx = _stageIdx AND pid = productionTimeTemplate._product
							WHERE _po = '.DB::int($v).'
							GROUp BY _stageIdx
							ORDER BY bkfProducts.`index`, bStage.technologyOrdering;
			');
		default:
			trigger_error('unsupported query schema '.$schema, E_USER_ERROR); }
	}


	static
	function productQry() {

		$args = func_get_args();
		$schema = array_shift($args);

		switch ($schema) {
		case 'forProductiontables-byIndex':
			$v = array_shift($args);
			assert('is_array($v) || is_int($v) || is_string($v)');
			$productiontablesA = (array)$v;

			return DB::instance('SELECT p.*
				FROM bkfProductsGroups AS g
				JOIN bkfProducts AS p ON g.gid = p.gid
				WHERE ' .DB::listExpExtra('productiontables IN ', $productiontablesA, false));
		case 'productQry-compatible-plusCurrent-byPorder':
			list($_po) = $args;

			return DB::instance('SELECT pid, name, `index`
				FROM bkfProducts
				JOIN productionOrder ON pid = _product OR bkfProducts._technology = productionOrder._technology
				WHERE _po = :_po',
				compact('_po') );
		case 'bkfProducts-withProductionSet-skipCertainSets-byIndex':
			$v = array_shift($args);
			$toSkip = (array)$v;

			$nrToSkip = array();
			foreach ($toSkip as $table)
				$nrToSkip[] = static::getProductionTableNr($table);

			return DB::instance('SELECT p.*,
					CONCAT_WS(" ", p.name, CONCAT_WS("", "[", `index`, "]") ) AS label
				FROM bkfProductsGroups AS g
				JOIN bkfProducts AS p ON g.gid = p.gid
				WHERE productiontables != 0
					AND ' .DB::listExpExtra(' productiontables NOT IN ', $nrToSkip, ' 1 ') .'
				ORDER BY `index`' );

		default:
			trigger_error('unsupported query schema '.$schema, E_USER_ERROR); }
	}


	function getAllProducts(){
		return DB::instance('SELECT * FROM bkfProducts ORDER BY `index`');
	}


	function _changeLinkService2($_po, $crossOn, $table) {

		$autoWarrantyP = Config([ 'service2', 'hardware', 'default-autoWarrantyP' ] );

		/* link stuff that has some serial number AND is not linked already */
		$q = 'INSERT INTO _hardware (_prorder, _owner, _dealer, _leasing, address, autolinkNeedsAttentionP, _serialNumber, autoWarrantyP)
			SELECT _po, cid, bkfOrders._dealer, bkfOrders._leasing, adres, 1, productionOrder._serialNumber,
				' .DB::int($autoWarrantyP) .'
			FROM productionOrder
			LEFT JOIN _hardware ON _po = _prorder
			LEFT JOIN bkfOrders ON _bkfOrder = bkfOrders.oid
			WHERE productionOrder._po = ' .DB::int($_po) .'
				AND _hw IS NULL /* means no matching entry in `_hardware` YET */';

		DB::Q($q);
		$_hw = DB::insertId();
		DB::Q('INSERT INTO _hardwareHistory
			SELECT * FROM _hardware
			WHERE _hw = ' .DB::int($_hw)) ;
	}


	function changeWrap($function) {
		try {
			return call_user_func(array($this, $function)); }
		catch (ContentFormatException $e) {
			return $this->showContentFormatException($e); }
		catch (DuplicateKeyException $e) {
			return $this->showDuplicateKey($e); }
		catch (QueryException $e) {
			error_log(exceptionLogStr($e));
			return $this->showIse($e->getMessage(), $e); }
	}


	function store_serialNumber_override($_po) {
		$serialNumber = pick($this->_post, 'serialNumber', null);
		$_serialPool = pick($this->_post, '_serialPool', null);

			# || (OR) to catch the case of missing one ;p
		if ($serialNumber || $_serialPool) {
			$rcd = DB::fetchOne('SELECT _sn, serialNumber
				FROM (SELECT NULL) AS _x
				LEFT JOIN serialNumber ON _snPool = ' .DB::int($_serialPool) .'
					AND serialNumber = ' .DB::int($serialNumber) );

			if ($rcd['_sn'])
				$_serialLink = $rcd['_sn'];
			else {
				$st = DB::prepare('INSERT INTO serialNumber (_snPool, serialNumber)
					SELECT :_serialPool, :serialNumber' );
				$st->execute(compact('_serialPool', 'serialNumber'));
				$_serialLink = DB::insertId(); }

			$st = DB::prepare('UPDATE productionOrder
				SET _serialNumber = :_serialLink
				WHERE _po = :_po');
			$st->execute(compact('_po', '_serialLink')); }
	}


	/** `auto' as in, only if needed :P */
	static
	function autoAssignSerialNumber($_po, $serialPool) {
		$poRcd = DB::fetch('SELECT _po, _serialNumber
			FROM productionOrder
			WHERE _po = ' .DB::int($_po));
		if ($poRcd['_serialNumber'])
			return;

		if (!$serialPool)
			return;

		$rcd = DB::fetch('SELECT * FROM serialPool
			WHERE _sp = ' .DB::int($serialPool) );
		if ($rcd['dateSealed'])
			throw new Exception(sprintf('cannot assign SN: pool `%s` is sealed since %s (@%s)',
				$rcd['serialPool'], $rcd['dateSealed'], $serialPool));

		switch ($rcd['serialFormat']) {
		case 'ean13':
			$nextBase = DB::fetch('SELECT MAX(serialNumber) DIV 10 + 1 AS nextBase
				FROM serialNumber
				WHERE _snPool = ' .DB::int($serialPool),
					'nextBase' );
			if ($nextBase === null)
				$nextBase = DB::fetch('SELECT COALESCE(MAX(serialNumber) DIV 10, 0) + 1 AS nextBase
					FROM serialNumber
					WHERE _snPool = (SELECT _continuePool
						FROM serialPool WHERE _sp = ' .DB::int($serialPool) .')',
					'nextBase' );
			$nextBase = Barcode::padSerial($rcd['serialFormat'], $rcd['serialNumberPrefix'], $nextBase);
			$next = Barcode::appendChecksum($rcd['serialFormat'], $rcd['serialNumberPrefix'], $nextBase);
			DB::Q('INSERT INTO serialNumber (_snPool, serialNumber)
				VALUES (' .DB::listExp(array($rcd['_sp'], $next)) .')' );
			DB::Q('UPDATE productionOrder
				SET _serialNumber = LAST_INSERT_ID()
				WHERE _po = ' .DB::int($_po) );
			break;
		case 'org-object-numeration':
		case 'raw':
			throw new Exception(sprintf('cannot auto-assign new serial number for format `%s` (@%s)',
				$rcd['serialFormat'], $serialPool));
		default:
			throw new Exception(sprintf('unsupported SN format `%s` (@%s)',
				$rcd['serialFormat'], $serialPool)); }
	}


		/** in short: move stuff between two tables: backlogSubassemblyTemplate
			and backlogSubassemblyBatch. update it along the way :P */
	protected
	function _changeCBRelation($_po, $crossOn, $table) {

		### important: this function must remain safe to be called with empty $this->_post[_stage],
		### $this->_post[quantity], $this->_post[variant]
		### which is the case when editing an already existing batch

		$_batch = $_po;
		$_subassembly = $this->_post['_subassembly'];

		### curius case: we may have more entries in $quantity or $variant than in $_stage
		### that's because $quantity is always submitted, while $_stage only when the checkbutton is checked

		# keys: sequential numbers
		# <input type="checkbox" name="_stage[]" value=" $stageRcd['_bl'] "/>
		$_stageA = pick($this->_post, '_stage', array());

		# keys: _bl (stage id)
		# <input name="quantity[ $stageRcd['_bl'] ]"/>
		$quantityA = pick($this->_post, 'quantity', array());
		$variantA = pick($this->_post, 'variant', array());

		if (in_array(null, $_stageA, $strict = true))
			throw new UnexpectedValueException('dunno what to do with null _stage');

		$data = array();
		foreach ($quantityA as $_stage => $quantity) {
				# yep, we want it to break badly if the index is missing
			$variant = $this->_post['variant'][$_stage];
			$data[$_stage] = compact('_stage', '_subassembly', 'quantity', 'variant');
		}

		$st = DB::prepare('INSERT INTO backlogSubassemblyTemplate (_stage, _subassembly, quantity, variant)
			SELECT :_stage, :_subassembly, :quantity, :variant
			ON DUPLICATE KEY UPDATE
				quantity = VALUES(quantity),
				variant = VALUES(variant)' );

		foreach ($data as $rcd)
			$st->execute($rcd);

		if (count($_stageA)) {
			DB::Q('INSERT INTO backlogSubassemblyBatch (_stage, _batch, quantity, variant)
				SELECT _stage, ' .DB::int($_batch) .', quantity, variant
				FROM backlogSubassemblyTemplate
				WHERE _subassembly = ' .DB::int($_subassembly) .'
					AND _stage IN (' .DB::listExp($_stageA) .')');

			DB::Q('DELETE FROM backlogSubassemblyTemplate
				WHERE _subassembly = ' .DB::int($_subassembly) .'
					AND _stage IN (' .DB::listExp($_stageA) .') '); }


		### update an already existing batch
		$batch = pick($this->_post, 'batch', array());
		$variant = pick($batch, 'variant', array());

		$a = array();
		foreach ($variant as $_stage => $atStage)
			foreach ($atStage as $_batch => $variant) {
				$quantity = $this->_post['batch']['quantity'][$_stage][$_batch];
				$a[] = compact('_stage', '_batch', 'variant', 'quantity'); }

		if ($a) {
			$s = DB::prepare('UPDATE backlogSubassemblyBatch
				SET variant = :variant, quantity = :quantity
				WHERE _stage = :_stage AND _batch = :_batch' );

			foreach ($a as $data)
				$s->execute($data); }

	}


	protected
	function _changeTestDeadline($_po, $crossOn, $table) {
		$fields = array('testDeadline');
		$dataPO = updatedValues($fields, $this->_post);
		DB::q('UPDATE productionOrder
			SET ' .DB::setExp($dataPO) .'
			WHERE _po = ' .DB::int($_po));
	}


	function storeGenericPorderVersionHistory($rcd) {
		$meta = static::relationMetaForGenericPo($rcd);
		return $this->storePorderVersionHistory($rcd['_po'], $meta['rel'], $meta['poid']);
	}


	function storePorderVersionHistory($_po, $crossOn, $poid) {
		$str = '';
		$pre = '';

		$rcd = static::rcd($crossOn, $poid);
		if ($rcd['_po'] != $_po)
			throw new LogicException(sprintf('unsupported case: _po !== _po (%s vs. %s)', $rcd['_po'], $_po));
		$orderRcd = static::orderRcd($rcd['_bkfOrder']);

		foreach ($rcd as $field => $value) {
			$valueStr = wordwrap($value, 75, LF .TAB, true);
			$str .= sprintf('%s%s: %s',
				$pre, $field, $valueStr );
			$pre = LF; }

		$data = array();
		$data['_cperson'] = $_SESSION['user']['_pe'];
		$data['dumbSerialization'] = $str;
		$data['version'] = $rcd['version'];
		$data['_po'] = $rcd['_po'];

		DB::Q('INSERT INTO productionOrderTextualHistory
			SET ' .DB::setExp($data) .',
				ctime = NOW()' );
	}


	function storeBkfOrdersVersionHistory($oid) {
		$str = '';
		$pre = '';

		$rcd = static::orderRcd($oid);
		if ($rcd['oid'] != $oid)
			throw new LogicException(sprintf('unsupported case: oid !== oid (%s vs. %s)', $rcd['oid'], $oid));

		foreach ($rcd as $field => $value) {
			$valueStr = wordwrap($value, 75, LF .TAB, true);
			$str .= sprintf('%s%s: %s',
				$pre, $field, $valueStr );
			$pre = LF; }

		$data = array();
		$data['_cperson'] = $_SESSION['user']['_pe'];
		$data['dumbSerialization'] = $str;
		$data['version'] = $rcd['version'];
		$data['oid'] = $rcd['oid'];

		DB::Q('INSERT INTO bkfOrdersTextualHistory
			SET ' .DB::setExp($data) .',
				ctime = NOW()' );
	}


	private
	function storeProductionPlan($_po) {
		DB::requireTransaction();

		$fields = array('hoursDaily', 'adjustmentHours');
		$data = updatedValues($fields, pick($this->_post, 'productionPlan', array()));
		$dataNotNull = array_filter($data,
			function($v) { return !is_null($v); });
		if (!count($dataNotNull)) {
			DB::Q('DELETE FROM productionPlan
				WHERE _porder = ' .DB::int($_po) );
			return; }


		$rcd = DB::fetchOne('SELECT _porder
			FROM (SELECT NULL) AS _x
			LEFT JOIN productionPlan ON _porder = ' .DB::int($_po) );
		if ($rcd['_porder'])
			DB::Q('UPDATE productionPlan
				SET ' .DB::setExp($data) .'
				WHERE _porder = ' .DB::int($_po) );
		else {
			$data['_porder'] = $_po;
			DB::Q('INSERT INTO productionPlan (' .DB::colListExp($data) .')
				SELECT ' .DB::listExp($data) ); }
	}


		# foreach ($this->_post['optional-part'][...]
	protected
	function _handleOptionalParts($_po) {
		$a = pick($this->_post, 'optional-part', array());
		foreach ($a as $part) {
		switch ($part) {
		case 'stage-status':
			$this->_handleOptionalPartStageStatus($_po);
			break;
		default:
			throw new Exception(sprintf('unsupported optional-part `%s\'', $part)); } }
	}


	private
	function _handleOptionalPartStageStatus($_po) {

		$a = pick($this->_post, 'backlogStatus', array());
		foreach ($a as $_bl => $change) {
			if ($change['from'] === $change['to'])
				continue;

			$requireBacklogSense = 'backlogSense-stage';
			$patch = array(
				'from' => array('_status' => $change['from']),
				'to' => array('_status' => $change['to']) );
			BacklogPatcher::backlogApplyPatch($_bl, $_po, $requireBacklogSense, $patch); }
	}


	protected
	function _cntSanity($fieldLabel, $cnt) {
		if (!is_numeric($cnt))
			throw new ContentFormatException(
				sprintf(L::_('Field `%s\': please enter just a number.'), $fieldLabel),
				sprintf(L::_('You have entered: `%s\'.'), $cnt) );
		if (!($cnt > 0))
			throw new ContentFormatException(
				sprintf(L::_('Field `%s\': please enter number greater than 0.'), $fieldLabel),
				sprintf(L::_('You have entered: `%s\'.'), $cnt) );
	}


	function _changeInAndOutTarget($_po, $crossOn, $table) {
			# FIXME - dun hardocde ;p
		$DA_SCHEMA = 'nierdzewka-zp';

		$targetNierdzewka = pick($this->_post, 'InAndOut', 'target', $DA_SCHEMA, null);
		DB::prepare('UPDATE productionOrder
			SET targetNierdzewka = :targetNierdzewka
			WHERE _po = :_po')
			->execute(compact('targetNierdzewka', '_po'));

		if ($targetNierdzewka) {
			$sendNowNierdzewka = pick($this->_post, 'InAndOut', 'sendNow', $DA_SCHEMA, null);
			if ($sendNowNierdzewka)
				$this->_change_mail_cycle($_po, $DA_SCHEMA); }
	}

	function saveTests($_productiontable, $_poid, $_productionOrder) {
		$tests = pick($this->_post,'tests',array());

		if (count($tests)>0) {
			foreach ($tests as $key=>$row) {
				DB::Q('INSERT INTO productionTests (object,_productiontable,_poid,status,_productionOrder) VALUES ('.DB::str($key).','.DB::int($_productiontable).','.DB::int($_poid).','.DB::int($row).','.DB::int($_productionOrder).')
					  ON DUPLICATE KEY UPDATE status = '.DB::int($row));
			}
		}
	}



	static
	function cable_length_fields() {
		return array(
			array(
				[ 'cable_length_high_pressure_1', 'Długość przewodów wysokiego ciśnienia st. 1' ],
				[ 'cable_length_high_pressure_2', 'Długość przewodów wysokiego ciśnienia st. 2' ],
				[ 'cable_length_high_pressure_3', 'Długość przewodów wysokiego ciśnienia st. 3' ],
				[ 'cable_length_high_pressure_4', 'Długość przewodów wysokiego ciśnienia st. 4' ],
				[ 'cable_length_high_pressure_5', 'Długość przewodów wysokiego ciśnienia st. 5' ],
				[ 'cable_length_high_pressure_6', 'Długość przewodów wysokiego ciśnienia st. 6' ],
			),
			array(
				[ 'cable_length_heating_1', 'Długość przewodów grzejnych st. 1' ],
				[ 'cable_length_heating_2', 'Długość przewodów grzejnych st. 2' ],
				[ 'cable_length_heating_3', 'Długość przewodów grzejnych st. 3' ],
				[ 'cable_length_heating_4', 'Długość przewodów grzejnych st. 4' ],
				[ 'cable_length_heating_5', 'Długość przewodów grzejnych st. 5' ],
				[ 'cable_length_heating_6', 'Długość przewodów grzejnych st. 6' ],
			),
			array(
				[ 'cable_for_brushes_1', 'Przewody do szczotek st. 1' ],
				[ 'cable_for_brushes_2', 'Przewody do szczotek st. 2' ],
				[ 'cable_for_brushes_3', 'Przewody do szczotek st. 3' ],
				[ 'cable_for_brushes_4', 'Przewody do szczotek st. 4' ],
				[ 'cable_for_brushes_5', 'Przewody do szczotek st. 5' ],
				[ 'cable_for_brushes_6', 'Przewody do szczotek st. 6' ],
			),
			array(
				[ 'cable_length_pulpit_control', 'Długość przewodów sterujuących do pulpitów (X2X)' ],
				[ 'cable_length_pulpit_power_dot5', 'Długość przewodów zasilania pulpitów (2x2.5)' ],
				[ 'cable_length_pulpit_power_2dot5', 'Długość przewodów zasilania pulpitów (3x2.5)' ],
				[ 'cable_quantity_liner_40x40', 'ilość korytek 40x40' ],
				[ 'cable_quantity_liner_90x60', 'ilość korytek 90x60' ],
				[ 'cable_balancy_potential', 'Przewód wyrównujący potencjał (2x0,5)'] ) );
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


	function _store_porderTek($_po, $JUNK = null, $JUNK = null) {

		DB::requireTransaction();

		$_teks = pick($this->_post, 'porderTek', '_tk', array());
		$memos = pick($this->_post, 'porderTek', 'memo', array());
		if (count($_teks) !== count($memos))
			throw new Exception(sprintf('inconsistency: count(_tk) !== count(memo) (%d !== %d)',
				count($_teks), count($memos) ));

		$preChange = DB::fetchAll('SELECT _tk, filename, memo
			FROM porderTek
			JOIN tekno USING(_tk)
			WHERE _po = ' .DB::int($_po) .'
			ORDER BY memo, filename' );

		DB::Q('DELETE FROM porderTek
			WHERE _po = ' .DB::int($_po)
			.DB::listExpExtra(' AND _tk NOT IN ', $_teks) );

		foreach ($_teks as $N => $_tk) {
			if ($_tk === null)
				continue;
			$memo = $memos[$N];
			$data = compact('_po', '_tk', 'memo');
			DB::Q('INSERT INTO porderTek (_po, _tk, memo)
				SELECT ' .DB::listExp($data) .'
				ON DUPLICATE KEY UPDATE memo = VALUES(memo)'); }

		$postChange = DB::fetchAll('SELECT _tk, filename, memo
			FROM porderTek
			JOIN tekno USING(_tk)
			WHERE _po = ' .DB::int($_po) .'
			ORDER BY memo, filename' );

		$this->_store_porderTek_mailDiff($_po, $preChange, $postChange);
	}


	private
	static
	function textual_porderTek($a) {
		$ret = [];

		foreach ($a as $rcd)
			$ret[] = sprintf('[%s] %s: %s',
				$rcd['_tk'], $rcd['filename'], $rcd['memo'] );

		return $ret;
	}


	function _store_porderTek_mailDiff($_po, $preChange, $postChange) {
		$targets = Config(['orders', 'porderTek', 'mailOnDiff' ]);
		if (!$targets)
			return;

		$pre = static::textual_porderTek($preChange);
		$post = static::textual_porderTek($postChange);

		$diff = new Horde_Text_Diff('auto', [ $pre, $post ]);
			# that is, no changes
		if ($diff->isEmpty())
			return;
		$renderer = new Horde_Text_Diff_Renderer_Unified();

		$rcd = DB::fetch('SELECT productionOrder.*, NOW() AS `NOW()`,
				persona.title AS mperson,
				CONCAT("#", serialNumber) AS serialNumberLabel,
				order_nr

			FROM productionOrder
			LEFT JOIN hardware ON _po = _prorder
			JOIN persona ON _pe = ' .DB::int($_SESSION['user']['_pe']) .'
			WHERE _po = ' .DB::int($_po));

		if ($diff->countDeletedLines())
			$subject = sprintf('Zmiany w plikach: %s%s%s',
				andStr('urządzenie: ', $rcd['serialNumberLabel'], ', '),
				andStr('ZP: ', $rcd['porder_nr'], ', '),
				andStr('ZS: ', $rcd['order_nr']) );
		else
			$subject = sprintf('Nowe pliki: %s%s%s',
				andStr('urządzenie: ', $rcd['serialNumberLabel'], ', '),
				andStr('ZP: ', $rcd['porder_nr'], ', '),
				andStr('ZS: ', $rcd['order_nr']) );

		$body = sprintf('Zmiany w plikach dołączonych do ZP wprowadzone %s przez %s',
			$rcd['NOW()'], $rcd['mperson'] );
		$body .= LF .LF;
		$body .= $renderer->render($diff);

		$headers = 'From: ' .Config(array('site', 'email', 'address')) . "\r\n" .
				'Content-Type: text/plain; charset=UTF-8'."\r\n";

		foreach ($targets as $target)
			mail($target, $subject, $body, $headers);
	}


	function changePorder() {
		$trans = DB::trans();

		$processed = $this->_changePorder();

		$trans->commit();

		return $handledP = false;
	}


	function changeAddPorder() {
		$q			= pick($this->params, 1, null);
		$_bkfOrder	= pick($this->_post, '_bkfOrder', null);
		$_product		= pick($this->_post, '_product', null);
		$porder_nr	= pick($this->_post, 'porder_nr', null);

		$trans = DB::trans();

		try {
			$product = Products::productRcd($this->_post['_product']); }
		catch (SQLNotFoundException $e) {
			return $this->showPartNotFound(sprintf('product with pid %s', $this->_post['_product'])); }

		switch ($q) {
		case 'subassemblyBatchFromSubassembly':
			break;
		default:
			break; }
		$this->params = array('change', 'add-porder', $product['productiontables']);

		$processed = $this->_changePorder();

		$trans->commit();

		if (count($processed)>0) {
			$_po = $processed['_po'][0];

			$toSend = DB::fetchAll('SELECT persona.firstName, persona.title, persona.email,
								 order_nr, bkfClients.orgName, bkfProducts.index, bkfProducts.name, CONCAT_WS(" ",bkfOrders.comments,bkfOrders.serviceTerms) as comments
									 FROM email_completion
										JOIN persona USING(_pe)
										CROSS JOIN bkfOrders
										CROSS JOIN bkfProducts
										LEFT JOIN bkfClients On (bkfClients.cid = bkfOrders.cid)
										WHERE new_zp_p AND oid = '. DB::int($_bkfOrder).' AND bkfProducts.pid = '.DB::int($_product));

		$newzp = pick(Config(),'orders','new_zp_p2', null);

			$toSend2 = DB::fetchAll('SELECT persona.firstName, persona.title, persona.email,
								 order_nr, bkfClients.orgName, bkfProducts.index, bkfProducts.name, CONCAT_WS(" ",bkfOrders.comments,bkfOrders.serviceTerms) as comments
									 FROM email_completion
										JOIN persona USING(_pe)
										CROSS JOIN bkfOrders
										CROSS JOIN bkfProducts
										LEFT JOIN bkfClients On (bkfClients.cid = bkfOrders.cid)
										WHERE new_zp_p2 AND oid = '. DB::int($_bkfOrder).' AND bkfProducts.pid = '.DB::int($_product). ' AND bkfProducts.pid IN ('.implode(',', $newzp).')');

			$toSend = array_merge($toSend, $toSend2);

			foreach ($toSend as $data) {

				$to = $data['email'];

				$bodyFmt = L::_(
'Hi %s,

New production order %s
by %s:

%s, %s [%s] - %s

%sorders/show/edit/generic-po/%s

Comments:
%s


Regards,
--
%s');
				$subject = sprintF(L::_H('New production order %s'),$porder_nr);
				$body = sprintf($bodyFmt,
					orStr($data['firstname'], $data['title']),

				$porder_nr, $_SESSION['user']['surname'],
				$data['order_nr'],$data['name'],$data['index'],$data['orgName'],
				Config([ 'site', 'mainURI' ]), urie($_po),

				$data['comments'],Config([ 'siteName' ]) );

				$from = Config(['site', 'email', 'address']);
				$headers = 'From: ' .Config(array('site', 'email', 'address')) . "\r\n" .
					'Content-Type: text/plain; charset=UTF-8'."\r\n";

				$success = mail($to, $subject, $body, $headers);

				if (!$success)
					throw new Exception('failed to send mail to: ' .$to);
			}
		}

		$external_id = pick($this->_post, 'InAndOut', 'external_id', null);
		if ($external_id) {
				# right now we only support simple case ;p
			if (count($processed['_po']) !== 1)
				throw new Exception(sprintf('unsupported case: InAndOut, and count($processed[_po]) !== 1 (%s)',
					count($processed['_po']) ));
				$_po = reset($processed['_po']);
			InAndOut::handleAfterImport(static::IN_AND_OUT_PORDER_SCHEMA, $external_id, $_po); }

			# in some cases this function generates multiple records
		if (count($processed['_po']) === 1) {
			$this->params = array('show', 'edit', 'porder', $processed['productiontables'][0], $processed['poid'][0]);
			$this->show();
			return true; }
		else
			return false;
	}


	function patchTeamwork() {
		$selector = $this->params[2];
		list($_bl, $pathParam) = uriPathParams($selector);

		$limitGlob	= pick(Config(), 'supply', 'limitSalesPersons', false);
		$limit		= false;

		if ($limitGlob == true) {
			foreach ($_SESSION['tags'] as $row) {
				if ($row['component'] == 'salespersons' || $row['component'] == 'salespersonslimit') {
					$limit = true;
					break;
				}

			}
		}

		$DEFAULT_START_STATUS_SQL = '(SELECT _bls
			FROM backlogStatus
			WHERE !closedP
				AND schedulableP
				AND !concernsProducerP )';

		$_pe = $_SESSION['user']['_pe'];

		$want = 'application/json';
		if (HttpStr::contentTypeMatchesP(null, $want)) {
			$patchStr = file_get_contents("php://input");
			$patch = json_decode($patchStr, /*as array*/ true); }
		else
			return $this->showBadRequest(sprintf('expected patch in `%s\' format', $want));

		$requireBacklogSense = 'backlogSense-teamwork';

		$trans = DB::trans();

		Backlog::generateForProductionOrder($pathParam['_po']);
			# after auto-generation, provide stuff.
		if (!$_bl) {
			$_bl = DB::fetchOne('SELECT _bl
				FROM backlog
				JOIN backlogSense on _backlogSense = _bs
				WHERE _productionOrder = ' .DB::int($pathParam['_po']) .'
					AND backlogSense = ' .DB::str($requireBacklogSense),
				'_bl' );
				# hack: it has just been auto-generated, use stub `from'
			$patch['from']['killbitP']=0; }

			$statFrom	= DB::fetchOne('SELECT backlogStatus.* FROM backlogStatus WHERE _bls = '.DB::int(pick($patch,'from','_status',1)));
			$statTo	= DB::fetchOne('SELECT backlogStatus.* FROM backlogStatus WHERE _bls = '.DB::int($patch['to']['_status']));

			$new = array();

			if ($limitGlob == true)
				if ($limit == true) {
					/* Shelf limited ? */
					if ($statTo['salespersonslimitP']>0) {
						/* Yes, validate limit */
						$counter = DB::fetchOne('SELECT COUNT(*) AS cdx FROM backlog WHERE _status = '.DB::int($patch['to']['_status']).' AND idx IS NULL AND _assignee = '.DB::int($patch['to']['_assignee']), 'cdx');

						if (($counter+1) > $statTo['salespersonslimitP']) {
							$times = Teamwork2::qry('productionTimes')->arrAll();
							$this->showDataAsJson(compact('new','times'));
							return;
						}
					}
				}else{
					/* Only sales can change ? */
					if ($statFrom['limitedToSales'] == 1) {
						/* Yes, denied others */
						$times = Teamwork2::qry('productionTimes')->arrAll();
						$this->showDataAsJson(compact('new','times'));
						return;
					}
				}

		try {
			TeamworkBacklogPatcher::backlogApplyPatch($_bl, $pathParam['_po'],
				$requireBacklogSense, $patch); }
		catch (PatchConflictException $e) {
				# fixme ;p
			$current = '<<dummy>>';
			return $this->showConflict($current); }

		$trans->commit();

		$new = DB::fetchAll('SELECT *
			FROM backlog
			LEFT JOIN productionOrder ON _productionOrder = _po
			LEFT JOIN serialNumber ON _serialNumber = _sn
			WHERE backlog._status IS NOT NULL
				AND _changeset > ' .DB::int($patch['fromChangeset']) );

		Teamwork2::calculateProductionTime($this);

		$times = Teamwork2::qry('productionTimes')->arrAll();

		$this->showDataAsJson(compact('new','times'));
	}


	function patch() {
		$q = pick($this->params, 1, null);
		$show = 'yuijs/reloadplugintabs';
		$touri = '/orders/';

		switch ($q) {
		case 'teamwork':
			try {
				return $this->patchTeamwork(); }
			catch (WorkflowException $e) {
				return $this->showWorkflowError($e->getMessage()); }
		default:
			return $this->showNotFound(); }
	}

	function saveForSupply($porder) {
		$elements	= pick($this->_post, 'supply_el', array());
		$models	= pick($this->_post, 'supply_model', array());
		$comments	= pick($this->_post, 'supply_comment', array());
		$techs	= pick($this->_post, 'supply_tech', array());
		$pids	= pick($this->_post, 'supply_pid', array());

		$last = 0;
		foreach ($pids as $key=>$pid) {

			if ($last != $pid) {

				$this->_post['_product']		= $pid;
				$this->_post['cnt']			= 1;
				$this->_post['_bkfOrder']	= $porder;
				$this->_post['_poStatus']	= static::bkfOrderStatusRcdBySense('bkfOrderStatus-new', 'sid');
				$this->_post['porder_nr']	= null;

				$product = Products::productRcd($pid);

				$this->params = array('change', 'add-porder', $product['productiontables']);

				$processed = $this->_changePorder();


				$inputs = array();
				$inputs['_technology']		= $techs[$key];
				$inputs['_po']				= $processed['_po'][0];


				for ($a=$key*1; $a<count($elements); $a++) {
					$ee = sprintf('%04d',$a);
					if (array_key_exists($ee,$elements)) {
						$element = $elements[$ee];
						if ($elements[$ee] != 'SKIP') {
							$inputs['count_'.$element]	= 1;
							$inputs['model_'.$element]	= $models[$ee];
							$inputs['comment_'.$element]	= $comments[$ee];
							$inputs['_status_'.$element]	= 3;
						}
					}
				}

			$fields='';
			$values='';

			foreach ($inputs as $key3=>$val) {
				if (strlen($key3)) {
					$fields.='"'.$key3.'", ';
					$values.=DB::str($val).', ';
				}
			}


				$fields = substr($fields,0,strlen($fields)-2);
				$values = substr($values,0,strlen($values)-2);

				supply::sync_my_pg_shadow_tables();

				PG::Q('INSERT INTO supply ('.$fields.') VALUES ('.$values.')');

			}

			$last = $pid;
		}
	}

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
}
