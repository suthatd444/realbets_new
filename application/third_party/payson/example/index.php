<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

/*
 * Payson API Integration example for PHP
 *
 * More information can be found att https://api.payson.se
 *
 */

/*
 * On every page you need to use the API you
 * need to include the file lib/paysonapi.php
 * from where you installed it.
 */

require_once '../lib/paysonapi.php';

/*
 * Account information. Below is all the variables needed to perform a purchase with
 * payson. Replace the placeholders with your actual information 
 */

// Your agent ID and md5 key
$agentID = "4";
$md5Key = "2acab30d-fe50-426f-90d7-8c60a7eb31d4";

// URLs used by payson for redirection after a completed/canceled purchase.

$returnURL = "http://localhost/work/payson/example/return.php";
$cancelURL = "http://localhost/work/payson/example/cancel.php";

// Please note that only IP/URLS accessible from the internet will work
$ipnURL = "http://my.local/phpAPI/example/ipn-example.php";

// Account details of the receiver of money
$receiverEmail = "testagent-1@payson.se";

// Amount to send to receiver
$amountToReceive = "10";

// Information about the sender of money
$senderEmail = "test-shopper@payson.se";
$senderFirstname = "Test";
$senderLastname = "Person";


/* Every interaction with Payson goes through the PaysonApi object which you set up as follows.  
 * For the use of our test or live environment use one following parameters:
 * TRUE: Use test environment, FALSE: use live environment */
$credentials = new PaysonCredentials($agentID, $md5Key);
$api = new PaysonApi($credentials, TRUE);

/*
 * To initiate a direct payment the steps are as follows
 *  1. Set up the details for the payment
 *  2. Initiate payment with Payson
 *  3. Verify that it suceeded
 *  4. Forward the user to Payson to complete the payment
 */

/*
 * Step 1: Set up details
 */


// Details about the receiver
$receiver = new Receiver(
        $receiverEmail, // The email of the account to receive the money
        $amountToReceive); // The amount you want to charge the user, here in SEK (the default currency)
$receivers = array($receiver);

// Details about the user that is the sender of the money
$sender = new Sender($senderEmail, $senderFirstname, $senderLastname);

$payData = new PayData($returnURL, $cancelURL, $ipnURL, "Ticket Payment", $sender, $receivers);

//Set the list of products. For direct payment this is optional
//$orderItems = array();
//$orderItems[] = new OrderItem("Test produkt", 100, 1, 0.25, "kalle");

//$payData->setOrderItems($orderItems);


//Set the payment method
$constraints = array(FundingConstraint::BANK, FundingConstraint::CREDITCARD); // bank and card
//$constraints = array(FundingConstraint::INVOICE); // only invoice
//$constraints = array(FundingConstraint::BANK, FundingConstraint::CREDITCARD, FundingConstraint::INVOICE); // bank, card and invoice
//$constraints = array(FundingConstraint::SMS); // only live environment. 
//$constraints = array(FundingConstraint::BANK); // only bank
$payData->setFundingConstraints($constraints);

//Set the payer of Payson fees
//Must be PRIMARYRECEIVER if using FundingConstraint::INVOICE
$payData->setFeesPayer(FeesPayer::PRIMARYRECEIVER);

// Set currency code
$payData->setCurrencyCode(CurrencyCode::EUR);

// Set locale code
$payData->setLocaleCode(LocaleCode::SWEDISH);

// Set guarantee options
$payData->setGuaranteeOffered(GuaranteeOffered::OPTIONAL);


/*
 * Step 2 initiate payment
 */
$payResponse = $api->pay($payData);


 

/*
 * Step 3: verify that it suceeded
 */
if ($payResponse->getResponseEnvelope()->wasSuccessful()) {
    /*
     * Step 4: forward user
     */
    header("Location: " . $api->getForwardPayUrl($payResponse));
}
else
{
      $detailsErrors = $payResponse->getResponseEnvelope()->getErrors();

foreach ($detailsErrors as $error) {
  echo  $error->getMessage();
}
}
?>