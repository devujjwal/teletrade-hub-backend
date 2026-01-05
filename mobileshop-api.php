<?php
class API
{
  private $ApiUrl	  = false;
  public $CurlDefault = true;

  public function __construct($ApiUrl)
  {
	if (!in_array('curl', get_loaded_extensions()) && !ini_get('allow_url_fopen'))
	  Throw new Exception('Curl or Get_File_Contents needed to run this script!');

	if (!in_array('curl', get_loaded_extensions()))
	  $this->CurlDefault = false;

	$this->ApiUrl = trim($ApiUrl);
  }
///////////////////////////////////////////////////////////////////////////
  public function GetCurrentStock($SelectedType='')
  {
	if (trim($SelectedType) == '')
	  $ReturnType['SelectedType'] = 'XML';
	else
	  $ReturnType['SelectedType'] = $SelectedType;
	if ($ReturnType['SelectedType'] != 'XML' && $ReturnType['SelectedType'] != 'Array' && $ReturnType['SelectedType'] != 'CSV')
	  return 'Invalid parameter!';
	return $this->Post('GetCurrentStock/',$ReturnType);
  }
  public function ReserveArticle($gensoft_id='',$warehouse='',$amount='')
  {
	if(trim($gensoft_id) != '') $Article['gensoft_id'] = $gensoft_id;
    if(trim($warehouse) != '') $Article['warehouse'] = $warehouse;
	if(trim($amount) != '') $Article['amount'] = $amount;

	if(!is_array($Article) || count($Article) < 3)
	  return 'No device parameters defined!';
	return $this->Post('ReserveArticle/',$Article);
  }
  public function GetReservedArticles($SelectedType='')
  {
	if (trim($SelectedType) == '')
	  $ReturnType['SelectedType'] = 'Array';
	else
	  $ReturnType['SelectedType'] = $SelectedType;
	if ($ReturnType['SelectedType'] != 'XML' && $ReturnType['SelectedType'] != 'Array')
	  return 'Invalid parameter!';
	return $this->Post('GetReservedArticles/',$ReturnType);
  }
  public function GetOrderImeiNumbers($order_id='',$SelectedType='')
  {
	if (trim($SelectedType) == '')
	  $PostParams['SelectedType'] = 'Array';
	else
	  $PostParams['SelectedType'] = $SelectedType;
	if ($PostParams['SelectedType'] != 'XML' && $PostParams['SelectedType'] != 'Array')
	  return 'Invalid parameter!';
	$PostParams['order_id'] = $order_id;
	if ($order_id < 1 || $order_id > 99999999)
	  return 'Invalid parameter order ID!';
	return $this->Post('GetOrderImeiNumbers/',$PostParams);
  }
  public function RemoveReservedArticle($reservation_id='')
  {
	if(trim($reservation_id) != '') $Article['reservation_id'] = $reservation_id;

	if(!is_array($Article) || count($Article) < 1)
	  return 'No device parameters defined!';
	return $this->Post('RemoveReservedArticle/',$Article);
  }
  public function CreateSalesOrder($payWith='Wire',$insurance='no',$reservations=array())
  {
	$SalesOrder['reservations'] = json_encode($reservations);
	$SalesOrder['payWith']		= $payWith;
	$SalesOrder['insurance']	= $insurance;

	if ($SalesOrder['payWith'] != 'Wire' && $SalesOrder['payWith'] != 'OnDelivery')
	  return 'Invalid parameter!';
	if ($SalesOrder['insurance'] != 'yes' && $SalesOrder['insurance'] != 'no')
	  return 'Invalid parameter!';
#	if (!is_array($SalesOrder['reservations']))
#	  return 'Invalid parameter!';
	return $this->Post('CreateSalesOrder/',$SalesOrder);
  }
  public function GetDeviceSpecifications($device_id)
  {
  	$DeviceSpec['device_id'] = $device_id;
	if ($device_id < 1 || $device_id > 99999)
	  return 'Invalid parameter device ID!';
	return $this->Post('GetDeviceSpecifications/',$DeviceSpec);
  }
///////////////////////////////////////////////////////////////////////////
  private function Post($Uri, $Params)
  {
	$PostFileds			= '';
	if (isset($Params) && is_array($Params))
	{
	  foreach ($Params as $Var=>$Val)
		$PostFileds	   .= $Var.'='.$Val.'&';
	}

	$curl = curl_init($this->ApiUrl.$Uri);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array( 'Expect:' ) );
	curl_setopt($curl, CURLOPT_RETURNTRANSFER , 1 );
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER , false );
	if (trim($PostFileds) != ''){
	  curl_setopt($curl, CURLOPT_POST, 1);
	  curl_setopt($curl, CURLOPT_POSTFIELDS, $PostFileds);
	}
	$result = curl_exec($curl);
	if(curl_errno($curl))
	  return 'Failed to connect to the API server! '.curl_error($curl);
	curl_close($curl);
	if (unserialize($result))
	  return unserialize($result);
	else
	  return $result;
  }
}
?>
