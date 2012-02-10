<?php
/* $Id$*/
/* contributed by Chris Bice */

include('includes/session.inc');
$title = _('Inventory Location Transfer Shipment');
include('includes/header.inc');
include('includes/SQL_CommonFunctions.inc');

if (isset($_POST['Submit']) OR isset($_POST['EnterMoreItems'])){
/*Trap any errors in input */

	$InputError = False; /*Start off hoping for the best */
	$TotalItems = 0;
	//Make sure this Transfer has not already been entered... aka one way around the refresh & insert new records problem
	$result = DB_query("SELECT reference FROM loctransfers WHERE reference='" . $_POST['Trf_ID'] . "'",$db);
	if (DB_num_rows($result)!=0){
		$InputError = true;
		$ErrorMessage = _('This transaction has already been entered') . '. ' . _('Please start over now').'<br />';
		unset($_POST['submit']);
		unset($_POST['EnterMoreItems']);
		for ($i=$_POST['LinesCounter']-10;$i<$_POST['LinesCounter'];$i++){
			unset($_POST['StockID' . $i]);
			unset($_POST['StockQTY' . $i]);
		}
	}
	$ErrorMessage='';
	for ($i=$_POST['LinesCounter']-10;$i<$_POST['LinesCounter'];$i++){
		if (isset($_POST['StockID' . $i]) and $_POST['StockID' . $i]!=''){
			$_POST['StockID' . $i]=trim(strtoupper($_POST['StockID' . $i]));
			$result = DB_query("SELECT COUNT(stockid) FROM stockmaster WHERE stockid='" . $_POST['StockID' . $i] . "'",$db);
			$myrow = DB_fetch_row($result);
			if ($myrow[0]==0){
				$InputError = True;
				$ErrorMessage .= _('The part code entered of'). ' ' . $_POST['StockID' . $i] . ' '. _('is not set up in the database') . '. ' . _('Only valid parts can be entered for transfers'). '<br />';
				$_POST['LinesCounter'] -= 10;
			}
			DB_free_result( $result );
			if (!is_numeric($_POST['StockQTY' . $i])){
				$InputError = True;
				$ErrorMessage .= _('The quantity entered of'). ' ' . $_POST['StockQTY' . $i] . ' '. _('for part code'). ' ' . $_POST['StockID' . $i] . ' '. _('is not numeric') . '. ' . _('The quantity entered for transfers is expected to be numeric').'<br />';
				$_POST['LinesCounter'] -= 10;
			}
			if ($_POST['StockQTY' . $i] <= 0){
				$InputError = True;
				$ErrorMessage .= _('The quantity entered for').' '. $_POST['StockID' . $i] . ' ' . _('is less than or equal to 0') . '. ' . _('Please correct this or remove the item').'<br />';
				$_POST['LinesCounter'] -= 10;
			}
			// Only if stock exist at this location
			$result = DB_query("SELECT quantity FROM locstock WHERE stockid='" . $_POST['StockID' . $i] . "' and loccode='".$_POST['FromStockLocation']."'",$db);
			$myrow = DB_fetch_row($result);
			if ($myrow[0] < $_POST['StockQTY' . $i]){
				$InputError = True;
				$ErrorMessage .= _('The part code entered of'). ' ' . $_POST['StockID' . $i] . ' '. _('does not have enough stock available for transfer.') . '.<br />';
				$_POST['LinesCounter'] -= 10;
			}
			DB_free_result( $result );
			$TotalItems++;
		}
	}//for all LinesCounter
	if ($TotalItems == 0){
		$InputError = True;
		$ErrorMessage .= _('You must enter at least 1 Stock Item to transfer').'<br />';
	}

/*Ship location and Receive location are different */
	if ($_POST['FromStockLocation']==$_POST['ToStockLocation']){
		$InputError=True;
		$ErrorMessage .= _('The transfer must have a different location to receive into and location sent from');
	}
}

if(isset($_POST['Submit']) AND $InputError==False){

	$ErrMsg = _('CRITICAL ERROR') . '! ' . _('Unable to BEGIN Location Transfer transaction');
	DB_Txn_Begin($db);
	for ($i=0;$i < $_POST['LinesCounter'];$i++){

		if($_POST['StockID' . $i] != ""){
			$DecimalsSql = "SELECT decimalplaces
							FROM stockmaster
							WHERE stockid='" . $_POST['StockID' . $i] . "'";
			$DecimalResult = DB_Query($DecimalsSql, $db);
			$DecimalRow = DB_fetch_array($DecimalResult);
			$sql = "INSERT INTO loctransfers (reference,
								stockid,
								shipqty,
								shipdate,
								shiploc,
								recloc)
						VALUES ('" . $_POST['Trf_ID'] . "',
							'" . $_POST['StockID' . $i] . "',
							'" . round($_POST['StockQTY' . $i], $DecimalRow['decimalplaces']) . "',
							'" . Date('Y-m-d') . "',
							'" . $_POST['FromStockLocation']  ."',
							'" . $_POST['ToStockLocation'] . "')";
			$ErrMsg = _('CRITICAL ERROR') . '! ' . _('Unable to enter Location Transfer record for'). ' '.$_POST['StockID' . $i];
			$resultLocShip = DB_query($sql,$db, $ErrMsg);
		}
	}
	$ErrMsg = _('CRITICAL ERROR') . '! ' . _('Unable to COMMIT Location Transfer transaction');
	DB_Txn_Commit($db);

	prnMsg( _('The inventory transfer records have been created successfully'),'success');
	echo '<p><a href="'.$rootpath.'/PDFStockLocTransfer.php?TransferNo=' . $_POST['Trf_ID'] . '">'.
		_('Print the Transfer Docket'). '</a></p>';
	unset($_SESSION['DispatchingTransfer']);
	unset($_SESSION['Transfer']);
	include('includes/footer.inc');

} else {
	//Get next Inventory Transfer Shipment Reference Number
	if (isset($_GET['Trf_ID'])){
		$Trf_ID = $_GET['Trf_ID'];
	} elseif (isset($_POST['Trf_ID'])){
		$Trf_ID = $_POST['Trf_ID'];
	}

	if(!isset($Trf_ID)){
		$Trf_ID = GetNextTransNo(16,$db);
	}

	if (isset($InputError) and $InputError==true){
		echo '<br />';

		prnMsg($ErrorMessage, 'error');
		echo '<br />';

	}

	echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/supplier.png" title="' . _('Dispatch') . '" alt="" />' . ' ' . $title . '</p>';

	echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

	echo '<table class="selection">';
	echo '<tr><th colspan="4"><input type="hidden" name="Trf_ID" value="' . $Trf_ID . '"><font size="3" color="blue" />'.
			_('Inventory Location Transfer Shipment Reference').' # '. $Trf_ID. '</font></th></tr>';

	$sql = "SELECT loccode, locationname FROM locations";
	$resultStkLocs = DB_query($sql,$db);
	echo '<tr><td>'._('From Stock Location').':</td><td><select name="FromStockLocation">';
	while ($myrow=DB_fetch_array($resultStkLocs)){
		if (isset($_POST['FromStockLocation'])){
			if ($myrow['loccode'] == $_POST['FromStockLocation']){
				echo '<option selected="True" value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
			} else {
				echo '<option value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
			}
		} elseif ($myrow['loccode']==$_SESSION['UserStockLocation']){
			echo '<option selected="True" value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
			$_POST['FromStockLocation']=$myrow['loccode'];
		} else {
			echo '<option value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
		}
	}
	echo '</select></td>';

	DB_data_seek($resultStkLocs,0); //go back to the start of the locations result
	echo '<td>'._('To Stock Location').':</td><td><select name="ToStockLocation">';
	while ($myrow=DB_fetch_array($resultStkLocs)){
		if (isset($_POST['ToStockLocation'])){
			if ($myrow['loccode'] == $_POST['ToStockLocation']){
				echo '<option selected="True" value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
			} else {
				echo '<option value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
			}
		} elseif ($myrow['loccode']==$_SESSION['UserStockLocation']){
			echo '<option selected="True" value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
			$_POST['ToStockLocation']=$myrow['loccode'];
		} else {
			echo '<option value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
		}
	}
	echo '</select></td></tr></table><br />';

	echo '<table class="selection">';

	$tableheader = '<tr><th>'. _('Item Code'). '</th><th>'. _('Quantity'). '</th></tr>';
	echo $tableheader;

	$k=0; /* row counter */
	if(isset($_POST['LinesCounter'])){

		for ($i=0;$i < $_POST['LinesCounter'] AND $_POST['StockID' . $i] !='';$i++){

			if ($k==18){
				echo $tableheader;
				$k=0;
			}
			echo '<tr>
				<td><input type="text" name="StockID' . $i .'" size="21"  maxlength="20" value="' . $_POST['StockID' . $i] . '" /></td>
				<td><input type="text" name="StockQTY' . $i .'" size="10" maxlength="10" class="number" value="' . $_POST['StockQTY' . $i] . '" /></td>
			</tr>';
		}
	}else {
		$i = 0;
	}
	// $i is incremented an extra time, so 9 to get 10...
	$z=($i + 9);

	while($i < $z) {
		if (!isset($_POST['StockID' . $i])) {
			$_POST['StockID' . $i]='';
		}
		if (!isset($_POST['StockQTY' . $i])) {
			$_POST['StockQTY' . $i]=0;
		}
		echo '<tr>
			<td><input type="text" name="StockID' . $i .'" size="21"  maxlength="20" value="' . $_POST['StockID' . $i] . '" /></td>
			<td><input type="text" name="StockQTY' . $i .'" size="10" maxlength="10" class="number" value="' . $_POST['StockQTY' . $i] . '" /></td>
		</tr>';
		$i++;
	}

	echo '</table><br /><div class="centre">
		<input type="hidden" name="LinesCounter" value="'. $i .'" />
		<input type="submit" name="EnterMoreItems" value="'. _('Add More Items'). '" />
		<input type="submit" name="Submit" value="'. _('Create Transfer Shipment'). '" /><br />';
	echo '<script  type="text/javascript">defaultControl(document.forms[0].StockID0);</script>';
	echo '</form></div>';
	include('includes/footer.inc');
}
?>