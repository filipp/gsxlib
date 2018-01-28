About
=====

GsxLib is a PHP library that simplifies communication with Apple's [GSX web service API][1]. It frees the application developer
from knowing the underlying PHP SOAP architecture and to some extent even the GSX API itself. GsxLib also tries to provide
some performance benefits by minimizing the number of requests made to the servers as well as doing some rudimentary input
validation (as opposed to burdening Apple's servers with totally invalid requests).


Requirements
===========

- SOAP support in your PHP
- Client certificates for GSX access
- Whitelisted IP address of the source of your requests
- GSX account with the "Can access Web Services" privilege enabled


Usage
=====

Best illustrated with a simple example:

    <?php
  
      include 'gsxlib/gsxlib.php';
      $_ENV['GSX_CERT'] = '/path/to/gsx/client/cert.pem';
      $_ENV['GSX_KEYPASS'] = 'MySuperSecretPrivateKeyPassPhrase';
      $gsx = GsxLib::getInstance($sold_to, $username);
      $info = $gsx->warrantyStatus($serialnumber);
      echo $info->productDescription;
      > MacBook Pro (15-inch 2.4/2.2GHz)
      
    ?>

If you're in the US, remember to set the fifth argument to the constructor to 'am'.


gsxcl
=====

The package includes a rudimentary command line client to the GSX API called _gsxcl_. It can perform various functions in the library and is meant
mainly as a simple test tool for the library.


FAQ
===

### Q: How do I create the necessary PEM file?
A: The PEM file must be a concatenation of the certificate you got from Apple and your private key file. You can create this from the Terminal:

    $ cat Applecare-APP1234-0000123456.Test.apple.com.chain.pem privatekey.pem > certbundle.pem

After that you would use _certbundle.pem_ as your client certificate. The contents of _certbundle.pem_ should look something like this:

    -----BEGIN CERTIFICATE-----
    BLASOQ()*Q#()**)REW)*(EW*)*E)WUR)*EW(UR)
    ...
    -----END CERTIFICATE-----
    -----BEGIN CERTIFICATE-----
    0990320003q43090435J403439590S-S=DS=-
    ...
    -----END CERTIFICATE-----
    -----BEGIN CERTIFICATE-----
    )_#_)#)$IK_#@))KDE_)FD_SF)DSF_DS)FDS_FDSFSD
    ....
    -----END CERTIFICATE-----
    -----BEGIN RSA PRIVATE KEY-----
    Proc-Type: ....
    DEK-Info: ...
    BUNCH OF GIBBERISH
    -----END RSA PRIVATE KEY-----


### Q: Do I need to make changes to my web server configuration for the SSL authentication to work?
A: No, the library takes care of everything. That's why the certificate path and passphrase are implemented as environment variables. This
ensures the certificate is sent with each request and you only have to define the paths once in your code.

### Q: How can I remove the passphrase from my private key?

    $ openssl rsa -in privatekey.pem -out privatekey.nopass.pem


### Q: How do I run the test suite?

A: First, install [simpletest][3], then:

    $ GSX_DEBUG=1 GSX_TECHID=123456 GSX_SN=12345678 GSX_SHIPTO=123456 GSX_USER=me@example.com GSX_KEYPASS='MySuperSecretKey' GSX_SOLDTO=123456 GSX_CERT=/path/to/my/cert.chain.pem php runtests.php
    
    
### Q: How can I get Find My iPhone status?

A: Use the following code:

    $warrantylookup = $gsx->warrantyStatus($serialnumber);
    if (strpos($warrantylookup->activationLockStatus,'Find My iPhone is active') !== false) {
      $fmid = 1;
    } else {
      $fmid = 0;
    }


License
=======

    DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE 
    Version 2, December 2004
    Copyright (C) 2004 Sam Hocevar <sam@hocevar.net> 
    Everyone is permitted to copy and distribute verbatim or modified 
    copies of this license document, and changing it is allowed as long 
    as the name is changed. 
    
    DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE 
    TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION 
    0. You just DO WHAT THE FUCK YOU WANT TO.


[1]: https://gsxwsut.apple.com/apidocs/ut/html/WSHome.html
[2]: http://php.net/manual/en/book.soap.php
[3]: http://www.simpletest.org
