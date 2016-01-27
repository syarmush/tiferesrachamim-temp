<?php
	ini_set('display_errors','On');

	require_once('braintree-php/lib/Braintree.php');
	require_once("dal/access.php");

	$dbAccess = new MysqlAccess();

	$braintree = include('config/braintree.php');
	Braintree_Configuration::environment($braintree['environment']);
	Braintree_Configuration::merchantId($braintree['merchantId']);
	Braintree_Configuration::publicKey($braintree['publicKey']);	
	Braintree_Configuration::privateKey($braintree['privateKey']);
	
	$nonce = $_POST['payment_method_nonce'];
	$amount = $_POST['amount'];
	$email = $_POST['email'];
	
	$result = Braintree_Transaction::sale([
  		'amount' => $amount,
  		'paymentMethodNonce' => $nonce,
		'options' => [
		    'submitForSettlement' => True
		]
	]);

	if(!$result->transaction)
	{
	 	header('Location: /donate'); 
		exit();
	}

	$trans = $result->transaction;
	$card = $trans->creditCard;//last4, debit, prepaid

	$message = '';
	$header = '';

	$paymentType 		= 'Credit card';
	$responseCode 		= 0;
	$gatewayRejectReason 	= $trans->gatewayRejectionReason;
	$transStatus 		= $trans->status;
	$processorResponseCode	= $trans->processorResponseCode;
	$resultAmount 		= $trans->amount;
	$last4 			= $card['last4'];
	$cardType 		= $card['cardType'];
	$cardHolderName 	= $card['cardholderName'];

	$transDate = $trans->createdAt;
	$transDate = $transDate->setTimezone(new DateTimeZone($_POST['client-tz']));
	$transDateStr = $transDate->format('Y-m-d H:i:s');

	if(strtolower($card['debit']) == 'yes')
		$paymentType = 'Debit card';

	else if(strtolower($card['prepaid']) == 'yes')
		$paymentType = 'Prepaid card'; 

	$sql = 'INSERT INTO donations (cardholder_name,	result_amount, request_amount, email, payment_type, card_type, trans_status,
			processor_response_code,trans_date,gateway_reject_reason,last4) ';
	$sql .= "VALUES ('$cardHolderName', $resultAmount, $amount, '$email', '$paymentType', '$cardType', '$transStatus', 
			$processorResponseCode, '$transDateStr', '$gatewayRejectReason', '$last4');";

	$confirmationNumber = $dbAccess->insert($sql);

	if ($result->success)
	{
		$header = 'Thank you for your donation!';
		$message = "Your donation has been succesfully processed. Thank you for helping us help you.";
	}
	else
	{
		$header = 'There was a problem processing your request';
		if ($transStatus == 'gateway_rejected')
		{
			if ($gatewayRejectReason == 'avs')
				$message = "Your bank was not able to validate the Billing Information. Please try again.";

		 	else if ($gatewayRejectReason == 'fraud')
				$message = "Your transaction was rejected due to suspected fraudulant activities.";

			else if ($gatewayRejectReason == 'cvv')
				$message = "Your bank was not able to validate the Security code. Please try again.";

			else
				$message = "The transaction was declined due to an unknown reason. Please contact your merchant for more information.";
		}
		else if ($transStatus == 'processor_declined')
		{
			if ($processorResponseCode == 2004)
				$message = "The card provided is expired. Please try again with a different card.";

			else if ($processorResponseCode == 2005)
				$message = "The card number provided is invalid. Please try again.";		

			else if ($processorResponseCode == 2006)
				$message = "The Expiration Date provided was invalid. Please try again.";

			else
				$message = "The transaction was declined due to an unknown reason. Please contact your bank for more information.";
		}
		else
		{
			$message = "The transaction was declined due to an unknown reason. Please contact your merchant for more information.";
		}
	}
?>﻿<!DOCTYPE HTML>
<html>
   <head>
      <title> Tiferes Rachamim - Donate</title>
<?php
	require_once("include/headers.html");
?>
      <link rel="stylesheet" type="text/css" href="styles/donate.css" />
   </head>
   <body>
<?php
	require_once("include/body-head.html");
?>

         <div class="wrap">
	    <h1><?php echo $header ?></h1>
            <div class="clear"></div>

	    <h2> <?php echo $message ?> </h2>
<?php
	if ($result->success)
	{
?>
	<div class="vals-wrapper">
            <span class='lbl'>Confirmation Number</span>
            <span class='val'><?php echo $confirmationNumber; ?></span>
            <div class="clear"></div>

            <span class='lbl'>Amount</span>
            <span class='val'><?php echo $resultAmount; ?></span>
            <div class="clear"></div>

            <span class='lbl'>Date</span>
            <span class='val'><?php echo $transDate->format('n/j/Y g:i A'); ?></span>
            <div class="clear"></div>

            <span class='lbl'>Authorization Code</span>
            <span class='val'><?php echo $trans->processorAuthorizationCode; ?></span>
            <div class="clear"></div>

            <span class='lbl'>Payment Type</span>
            <span class='val'><?php echo $paymentType; ?></span>
            <div class="clear"></div>

            <span class='lbl'>Card Type</span>
            <span class='val'><?php echo $cardType; ?></span>
            <div class="clear"></div>

            <span class='lbl'>Last 4 of Account Number</span>
            <span class='val'><?php echo $last4; ?></span>
            <div class="clear"></div>
         </div>
<?php
	}
?>
	    <a onclick='window.print();' class='btn'> Print </a>
	    <a href='donate.php' class='btn'> Return to donate page </a>
         </div>

<?php
	require_once("include/body-foot.html");
?>
   </body>
</html>





