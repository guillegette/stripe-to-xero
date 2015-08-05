<?php
/**
 * Xero To Strip Worker
 * 
 * This worker will create your sales transaction and fee transactions on Xero for every succesfull charge 
 * processed on Stripe. It will also record the money transfer from Stripe to your Bank Account every time
 * you get paid.
 *
 * @author Guillermo Gette @guillegette
 */

date_default_timezone_set('Australia/Melbourne');
require 'vendor/autoload.php';

$event = getPayload();
$app_config = read_config_file($argv); print_r($app_config);

//Set up Xero API client
$xero_oauth = new XeroOAuth(array(
	'consumer_key' => $app_config['xero']['consumer_key'],
	'shared_secret' => $app_config['xero']['consumer_secret'],
	'rsa_private_key' => 'xero_privatekey.pem',
	'rsa_public_key' => 'xero_publickey.cer',
	'core_version' => '2.0',
	'payroll_version' => '1.0',
	'file_version' => '1.0',
	'application_type' => 'Private',
	'oauth_callback' => 'oob'
));

//As a private app tokens are the same as credentials
$xero_oauth->config['access_token'] = $xero_oauth->config['consumer_key'];
$xero_oauth->config['access_token_secret'] = $xero_oauth->config['shared_secret'];

//Stripe
\Stripe\Stripe::setApiKey($app_config['stripe']['api_key']); 


switch($event['type']) {
	case 'charge.succeeded':
		new_sale_transaction($event, $xero_oauth, $app_config);
		new_fee_transaction($event, $xero_oauth, $app_config);
		break;
	case 'transfer.paid':
		new_bank_transfer_transaction($event, $xero_oauth, $app_config);
		break;
	default:
		throw new Exception('Event '.$event['type'].' not defined');
		break;
}

function new_sale_transaction($event, $xero_oauth, $app_config) {

	$charge = $event['data']['object'];
	$date = date('Y-m-d', $charge['created']);
	$amount = $charge['amount']/100;
	
	$xml_transaction = 
	"<BankTransactions>
	    <BankTransaction>
	      <Type>RECEIVE</Type>
	      <Contact>
	        <Name>{$charge['source']['name']}</Name>
	      </Contact>
	      <Date>{$date}</Date>
	      <LineItems>
	        <LineItem>
	          <Description>{$charge['description']}</Description>
	          <Quantity>1</Quantity>
	          <UnitAmount>{$amount}</UnitAmount>
	          <AccountCode>{$app_config['xero']['sales_account']}</AccountCode>
	        </LineItem>
	      </LineItems>
	      <Reference>{$charge['id']}</Reference>
	      <BankAccount>
	        <Code>{$app_config['xero']['stripe_account']}</Code>
	      </BankAccount>
	    </BankTransaction>
	</BankTransactions>";
	
	$response = $xero_oauth->request('PUT', $xero_oauth->url('BankTransactions', 'core'), array(), $xml_transaction);
	
	if($response['code'] != 200) {
		print_r($response);
		throw new Exception('Error creating sales transaction');
	}
}

function new_fee_transaction($event, $xero_oauth, $app_config) {

	$balance_transaction_id = $event['data']['object']['balance_transaction'];
	
	$transaction = \Stripe\BalanceTransaction::retrieve($balance_transaction_id)->__toArray(true);;
	$date = date('Y-m-d', $transaction['created']);
	$amount = $transaction['fee']/100;

	$xml_transaction = 
	"<BankTransactions>
	    <BankTransaction>
	      <Type>SPEND</Type>
	      <Contact>
	        <Name>Stripe</Name>
	      </Contact>
	      <Date>{$date}</Date>
	      <LineItems>
	        <LineItem>
	          <Description>Stripe processing fees</Description>
	          <Quantity>1</Quantity>
	          <UnitAmount>{$amount}</UnitAmount>
	          <AccountCode>{$app_config['xero']['fees_account']}</AccountCode>
	        </LineItem>
	      </LineItems>
	      <Reference>{$transaction['id']}</Reference>
	      <BankAccount>
	        <Code>{$app_config['xero']['stripe_account']}</Code>
	      </BankAccount>
	    </BankTransaction>
	</BankTransactions>";
	
	$response = $xero_oauth->request('PUT', $xero_oauth->url('BankTransactions', 'core'), array(), $xml_transaction);
	
	if($response['code'] != 200) {
		print_r($response);
		throw new Exception('Error creating fees transaction');
	}
}

function new_bank_transfer_transaction($event, $xero_oauth, $app_config) {

	$transfer = $event['data']['object'];
	$date = date('Y-m-d', $transfer['created']);
	$amount = $transfer['amount']/100;

	$xml_transaction =
	"<BankTransfers>
		<BankTransfer>
			<FromBankAccount>
				<Code>{$app_config['xero']['stripe_account']}</Code>
			</FromBankAccount> 
			<ToBankAccount>
				<Code>{$app_config['xero']['bank_account']}</Code>
			</ToBankAccount> 
			<Amount>{$amount}</Amount>
		</BankTransfer>
	</BankTransfers>";

	$response = $xero_oauth->request('PUT', $xero_oauth->url('BankTransfers', 'core'), array(), $xml_transaction);
	
	if($response['code'] != 200) {
		print_r($response);
		throw new Exception('Error creating bank transfer transaction');
	}
}

function read_config_file($argv){
	if(is_array($argv)) {
		foreach ($argv as $i => $arg) {
			if($arg == "-config") {
				return json_decode(file_get_contents($argv[$i+1]),true);
			}
		}
	}
	throw new Exception("Config file not present");	
}
?>