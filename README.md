
 ## PHPNODE
 
 A bitcoin node, written in PHP. See Design.md for design overview. 
  
 ## Presently supports:
 
  Headers first syncing
 
 ## Requirements 
 
  * ext-zmq
  * ext-gmp
  * ext-json
  * ext-curl
  * ext-mcrypt
  
 ## Installation
 
  `composer install`
  
  You'll need a SQL database & credentials. Schema files are in ./sql
  Warning: these may be ruthlessly updated!
  
 ## Configuration
 
  Dump a blank config file:
  `phpnode print-config > config.ini`
   
  Create in the default location:
  `mkdir ~/.bitcoinphp && phpnode print-config > ~/.bitcoinphp/bitcoin.ini`
  
 ## Run the software
 
  See the list of commands: 
  `phpnode help`
 
  Start with config file in the default location:
  `phpnode start`
  
  Run with configuration in an alternative location:
  `phpnode start -c /tmp/yourconfig`
  
  IMPORTANT: Howto stop the stop the software:
  `phpnode stop`