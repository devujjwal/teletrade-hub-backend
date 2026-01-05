<?php
  require_once 		'mobileshop-api.php';
  $MyApi 			= new API('copy and paste the URL displayed in the sample codes in your account');

/*
   *  GetCurrentStock operation,
   *  default return type=> XML, Available return types => CSV, Array, XML
   *  Result returned is an array: 'status'(ok,error), 'ReturnVal'=>actual return results(CSV, Array, XML)
*/
  // XML example
  $GetCurrentStock	= $MyApi->GetCurrentStock();
  header ("content-type: text/xml");
  echo $GetCurrentStock['ReturnVal'];
  exit;
  // XML example
  $GetCurrentStock	= $MyApi->GetCurrentStock('XML');
  header ("content-type: text/xml");
  echo $GetCurrentStock['ReturnVal'];
  exit;

  // Array example
  $GetCurrentStock	= $MyApi->GetCurrentStock('Array');
  echo '<pre>';
  var_export($GetCurrentStock);
  echo '</pre>';
  exit;

  // Csv example
  $GetCurrentStock	= $MyApi->GetCurrentStock('CSV');
  header('Content-type: text/csv');
  header('Content-disposition: attachment;filename=CurrentStock.csv');
  echo $GetCurrentStock['ReturnVal'];
  exit;
/*************************************************************************************************/
/*
   *  GetOrderImeiNumbers operation,
   *  required arguments=>order id, return type
   *  order_id was received through the CreateSalesOrder operation, integer number
   *  default return type=> Array, Available return types => Array, XML. Note
   *  Result returned is an array: 'status'(ok,error), 'ReturnVal'=>(returns the items imei numbers found in order document, Error message if failed)
*/
  $ImeiNumbers		= $MyApi->GetOrderImeiNumbers(19571);
  echo '<pre>';
  var_export($ImeiNumbers);
  echo '</pre>';
  exit;

  $ImeiNumbers		= $MyApi->GetOrderImeiNumbers(19571,'Array');
  echo '<pre>';
  var_export($ImeiNumbers);
  echo '</pre>';
  exit;

  $ImeiNumbers		= $MyApi->GetOrderImeiNumbers(19571,'XML');
  header ("content-type: text/xml");
  echo $ImeiNumbers['ReturnVal'];
  exit;
/*************************************************************************************************/

/*************************************************************************************************/
/*
   *  ReserveArticle operation,
   *  required arguments=>bar_code, warehouse_code, amount
   *  Result returned is an array: 'status'(ok,error), 'ReturnVal'=>(reservation id if successfull, Error message if failed)
*/
  $ReserveArticle	= $MyApi->ReserveArticle('B30800130','HU01','12');
  echo '<pre>';
  var_export($ReserveArticle);
  echo '</pre>';
  exit;

/*************************************************************************************************/
/*
   *  GetReservedArticles operation
   *  default return type=> Array, Available return types => Array, XML
   *  Result returned is an array: 'status'(ok,error), 'ReturnVal'=>actual return results(Array, XML)
*/
  $GetReservedArticles	= $MyApi->GetReservedArticles();
  echo '<pre>';
  var_export($GetReservedArticles);
  echo '</pre>';
  exit;

/*************************************************************************************************/
/*
   *  RemoveReservedArticle operation
   *  required argument=>reservation_id
   *  Result returned is an array: 'status'(ok,error), 'ReturnVal'=>result message
*/
  $RemoveReservedArticle	= $MyApi->RemoveReservedArticle('');
  echo '<pre>';
  var_export($RemoveReservedArticle);
  echo '</pre>';

/*************************************************************************************************/
/*
   *  CreateSalesOrder operation,
   *  optional argument=>reservations(array), If left empty, all active reservations will be converted to sales order
   *  optional argument=>payWith(OnDelivery,Wire) default:Wire
   *  optional argument=>insurance(yes,no) default:no
   *  Result returned is an array: 'status'(ok,error), 'ReturnVal'=>Returns the created order id if successfull, error message if failed
*/
  $reservations			= array();
  $reservations[]		= '0000024527';
  $reservations[]		= '0000046918';
  $CreateSalesOrder	= $MyApi->CreateSalesOrder('OnDelivery','no',$reservations);
  echo '<pre>';
  var_export($CreateSalesOrder);
  echo '</pre>';
  exit;

/*************************************************************************************************/
/*
   *  GetDeviceSpecifications operation,
   *  required arguments=>device ID
   *  Result returned is XML: 'status'(ok,error), 'ReturnVal'=>Returns the device specifications in XML
*/
  $Specifications	= $MyApi->GetDeviceSpecifications('2648');

  // Simple output example
  $device = new SimpleXMLElement($Specifications['ReturnVal']);
  foreach ($device->main as $mainGrp)
  {
	echo($mainGrp->name).'<br>';
	foreach ($mainGrp->sub as $SubGrp)
  	{
	  echo($SubGrp->name).'<br>';
	  foreach ($SubGrp->value as $Values)
	  {
	  	echo $Values.'<br>';
	  }
  	}
  }
  exit;

  // XML example
  header ("content-type: text/xml");
  echo $Specifications['ReturnVal'];