#Stripe To Xero
*Update:* If you are looking for a online service to automically import your sales and fees from Stripe into your Xero account, have a look at https://go.bankfeeds.io/

An IronWorker (http://www.iron.io/worker/) written in PHP designed to create the sales, fees and transfer transaction from Stripe in Xero. 
Using webhooks, this worker will be executed every time a successful charge is processed or a bank transaction is made by Stripe.

Disclaimer: I don’t work for Iron.io, I built this for my ecommerce and I thought it would be good to share it.

##Why An IronWorker?
Every transaction on Stripe gets recorded almost instantly in your Xero account avoiding to run manual imports or batch processing enormous files. This is thanks to the webhooks provided by Stripe and the ability to trigger our IwonWorker with them. It doesn’t matter how many transactions per second we have, the worker will queue the jobs and they will get done one by one. Once we make the worker live there is no infrastructure to maintain.
On top of all this, you can start using this for FREE! Iron.io has a free plan with 10 compute hours per month. 

##Requirements
* Iron.io account
* Iron Worker CLI installed in your local machine http://dev.iron.io/worker/reference/cli/

##Xero Setup
First you need to create a new bank account and named it Stripe (or anything you want). Xero is going to complain that there is no bank under that name but you can ignore this and you will need to enter a fake account number. Make sure this account has an account code (check this on Settings -> Charts Of Accounts). This is where all your Stripe transactions are going to be recorded.

##Xero Private Application
To use the Xero API and interact with your books, you need create an application to obtain some credentials. As this worker will only work with your company, we will create a Private application.

1. Visit https://app.xero.com/Application/Add
2. Select Private
3. Complete Application Name and select your organisation 
4. You will need to generate a public key certificate. To do this follow this steps http://developer.xero.com/documentation/advanced-docs/public-private-keypair/
5. Once you save, you are going to be able to get your consumer key and consumer secret.

##IronWoker Setup
1. Clone this repo
2. Place your privatekey.pem and publickey.cer in the folder where you cloned the repo. By default I named this files xero_privatekey.pem and xero_publickey.cer, if you want to change this names you need to reflect this changes in stripe_to_xero.worker and stripe_to_xero.php
3. Open config.json and update the file with your details
```
{
  "xero": {
    "consumer_key": "", //from your Xero private application
    "consumer_secret": "", //from your Xero private application
    "bank_account": "", //where Stripe transfer your money (code)
    "stripe_account": "", //the "fake" bank account that will have your Stripe transactions (code)
    "sales_account": "", //the account code for your sales
    "fees_account": "" //the account code for your fees
  },
  "stripe": {
    "api_key": "" //you can get your Stripe private api key going to Account Settings -> API Keys
  }
}
```

##IronWorker Deployment
If you follow the instructions of http://dev.iron.io/worker/reference/cli/ you should have configured your credentials with your iron.io account.

1. Within the repo directory run $ iron_worker upload stripe_to_xero —worker-config config.json  
2. Run $ iron_worker webhook stripe_to_xero and keep this url handy

##Stripe Setup
1. Go to Account Settings -> Webhooks
2. Click on Add Enpoint and paste the url that you got before
3. Click on Select event and mark charge.succeeded and transfer.paid
4. Click on Update endpoint and you are ready to go!
5. Checkout your https://hud.iron.io/dashboard to see your task running.

##Contributions
Feel free to fork and send a PR if you want to add more events to the code or make any improvements.

##Are you using this for your business?
This is MIT License so no hard feelings but if you feel generous you can <a href='http://ko-fi.com?i=1494Y4X6QWBW0' target='_blank'><img style='border:0px' src='https://az743702.vo.msecnd.net/cdn/btn1.png' border='0' height='30' alt='Buy Me A Coffee at Ko-Fi.com' /></a> 

##Issues, Questions, Saying Hi?
You can ping me [@guillegette](https://twitter.com/guillegette)
