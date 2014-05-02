Version 1.0.1

©2014 GoCoin Holdings Limited and GoCoin International Group of companies hereby grants you permission to utilize a copy of this software and documentation in connection with your use of the GoCoin.com service subject the the published Terms of Use and Privacy Policy published on the site and subject to change from time to time at the discretion of GoCoin.<br><br>

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NON-INFRINGEMENT. IN NO EVENT SHALL THE DEVELOPERS OR AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.<br><br>

## Using the Official GoCoin Zencart Plugin
When a shopper chooses the GoCoin payment method and places their order, they will be redirected to gateway.GoCoin.com to pay.  
GoCoin will send a notification to your server which this plugin handles.  Then the customer will be redirected to an order summary page.  

The order status in the admin panel will be "Processing" when the order is placed and payment has been confirmed. 

#### Important Note: 
Version 1.0.0 of this plugin only supports US Dollars as the Base Currency. Please make sure your Currency is set to US Dollars. Support for additional currencies is coming soon. 

This plugin now supports Litecoin as well as Bitcoin

### 1. Installation
[ZenCart](https://www.zen-cart.com/) must be installed before installing this plugin.

a. 	download the code structure in cataglog folder and copy the containts to corresponding folders in catalog folder of ZenCart application.<br>
b.	copy gocointoken.php and gocoinnotify.php files on root folder od ZenCart application.

### 2. Setting up an application.
1) [Enable the GoCoin Hosted Payment Gateway](http://www.gocoin.com/docs/hosted_gateway)<br>
2) Create an application in the [GoCoin Dashboard](https://dashboard.gocoin.com)

##### Navigate to the Applications menu from the dashboard home<br>
![applications](https://dl.dropboxusercontent.com/spa/pvghiam459l0yh2/rj1pj_-a.png)

##### Create a new application <br>
![applications home](https://dl.dropboxusercontent.com/spa/pvghiam459l0yh2/s61g2gn8.png)<br>
Make sure your redirect_uri is equal to:

```
https://YOUR_DOMAIN/gocointoken.php
```

Set the Application and Callback URL. The Callback URL will be https://YOUR_DOMAIN/gocointoken.php<br>
Make sure to use https for a production site - its part of the OAuth standard.<br>

More information on creating GoCoin connected applications can be found [here](http://www.gocoin.com/docs/create_application)

### 3. Configuration

1. Install GoCoinpay Module: In the Admin panel click Module > Payment > Install Module , You will see list of Payment module. Locate GoCoin, and click Install. On Successful installation user is redirected to GoCoin Payment Setting page.<br><br>
2. Also make sure the Module is Enabled.
3. Configure GoCoin Payment module, by editing and entering<br>
  a) Enter Merchant ID, Secret key and Access Token<br>
  b) Obtain a token:<br>
    i) Set client key and client id. <br>
    ii) Click "Get Access token from GoCoin" button. You will be redirected to dashboard.gocoin.com. Allow permission to access your info then you will be redirected back to this page. The Access Token will have populated the field. <br>
4. SAVE AGAIN. You are now ready to accept payments with GoCoin!
