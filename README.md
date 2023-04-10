# Smart Connect

Open source VL/EID Dashboard

  
#### How do I get set up?
* Download the Source Code and put it into your server's root folder (www or htdocs).
* Create a database and import the initial sql file
* Modify the config files (configs/autoload/global.php and configs/autoload/local.php ) and update the database parameters
* Ensure that the apache rewrite module is enabled
* Create a virtual host pointing to the public folder of the source code. 

You can see an example below : 
```
<VirtualHost *:80>
   DocumentRoot "C:\wamp\www\vldashboard\public"
   ServerName vldashboard

   <Directory "C:\wamp\www\vldashboard\public">
       Options Indexes MultiViews FollowSymLinks
       AllowOverride All
       Order allow,deny
       Allow from all
   </Directory>
</VirtualHost>
```

#### Next Steps
*Manually populate the r_vl_test_reasons, r_vl_sample_type, and geographical_divisions tables in the database (we are working on adding UI in the application to populate these details)
* Make sure that your Viral Load data from LIS or other sources is populated in to the **dash_form_vl** table
* Once you have the application set up, you can visit the URL http://vldashboard/ and log in with the credentials admin@example.com and 12345
* Now you can start adding Users
* Click on "clear cache" link at the bottom right to clear the cache after importing fresh data. 

#### Who do I talk to?
You can reach us at hello (at) deforay (dot) com
