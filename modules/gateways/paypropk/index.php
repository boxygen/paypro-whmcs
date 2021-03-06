<?php
/**
 * WHMCS Merchant Gateway 3D Secure Callback File
 *
 * The purpose of this file is to demonstrate how to handle the return post
 * from a 3D Secure Authentication process.
 *
 * It demonstrates verifying that the payment gateway module is active,
 * validating an Invoice ID, checking for the existence of a Transaction ID,
 * Logging the Transaction for debugging and Adding Payment to an Invoice.
 *
 * Users are expected to be redirected to this file as part of the 3D checkout
 * flow so it also demonstrates redirection to the invoice upon completion.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

// Require libraries needed for gateway module functions.
$WHMCS_ROOT = dirname(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME']))));
require_once "{$WHMCS_ROOT}/init.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

$whmcs->load_function('gateway');
$whmcs->load_function('invoice');

// Detect module name from filename.
$gatewayModuleName = basename(dirname(__FILE__));

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

?>
<pre>
<?php print_r($_POST); ?>
</pre>
<html>
<head>
    <script src="https://paypro.com.pk/static/paypropk-checkout.js"></script>
</head>
<body>
    ...
    <!-- Place this where you need payment button -->
    <button id="payment-button">Pay with PayProPk</button>
    <!-- Place this where you need payment button -->
    <!-- Paste this code anywhere in you body tag -->
    <script>
        var config = {
            // replace the publicKey with yours
            "publicKey": "test_public_key_dc74e0fd57cb46cd93832aee0a390234",
            "productIdentity": "1234567890",
            "productName": "Dragon",
            "productUrl": "http://gameofthrones.wikia.com/wiki/Dragons",
            "eventHandler": {
                onSuccess (payload) {
                    // hit merchant api for initiating verfication
                    console.log(payload);
                },
                onError (error) {
                    console.log(error);
                },
                onClose () {
                    console.log('widget is closing');
                }
            }
        };

        var checkout = new PayProPkCheckout(config);
        var btn = document.getElementById("payment-button");
        btn.onclick = function () {
            checkout.show({amount: 1000});
        }
    </script>
    <!-- Paste this code anywhere in you body tag -->
    ...
</body>
</html>
<?php die(); ?>


// Retrieve data returned in payment gateway callback
// Varies per payment gateway
$success = $_POST["x_status"];
$invoiceId = $_POST["x_invoice_id"];
$transactionId = $_POST["x_trans_id"];
$paymentAmount = $_POST["x_amount"];
$paymentFee = $_POST["x_fee"];
$hash = $_POST["x_hash"];

$transactionStatus = $success ? 'Success' : 'Failure';

/**
 * Validate callback authenticity.
 *
 * Most payment gateways provide a method of verifying that a callback
 * originated from them. In the case of our example here, this is achieved by
 * way of a shared secret which is used to build and compare a hash.
 */
$secretKey = $gatewayParams['secretKey'];
if ($hash != md5($secretKey . $invoiceId . $transactionId . $paymentAmount)) {
    $transactionStatus = 'Hash Verification Failure';
    $success = false;
}

/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 *
 * @param int $invoiceId Invoice ID
 * @param string $gatewayName Gateway Name
 */
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 *
 * @param string $transactionId Unique Transaction ID
 */
checkCbTransID($transactionId);

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */
logTransaction($gatewayParams['name'], $_POST, $transactionStatus);

$paymentSuccess = false;

if ($success) {

    /**
     * Add Invoice Payment.
     *
     * Applies a payment transaction entry to the given invoice ID.
     *
     * @param int $invoiceId         Invoice ID
     * @param string $transactionId  Transaction ID
     * @param float $paymentAmount   Amount paid (defaults to full balance)
     * @param float $paymentFee      Payment fee (optional)
     * @param string $gatewayModule  Gateway module name
     */
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $paymentAmount,
        $paymentFee,
        $gatewayModuleName
    );

    $paymentSuccess = true;

}

/**
 * Redirect to invoice.
 *
 * Performs redirect back to the invoice upon completion of the 3D Secure
 * process displaying the transaction result along with the invoice.
 *
 * @param int $invoiceId        Invoice ID
 * @param bool $paymentSuccess  Payment status
 */
callback3DSecureRedirect($invoiceId, $paymentSuccess);
