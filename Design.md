
## Basic Design Overview

### Configuration

This library uses an .ini configuration file, located by default in 
`~/.phpnode/bitcoin.ini`. An empty config file can be generated
using `phpnode print-config`. 

At the very least, an SQL database must be configured as a data store. 

It can be accessed in a custom location using -c/--config:
`phpnode start -c ~/bin/node-php/config.ini`

### ZMQ

The library uses ZMQ for control messages, current limited only 
to the shutdown command. This is to avoid people using CTRL-C, 
which can cause corruption. Instead, ReactPHP's ZMQ bindings 
will deliver the message to the application. 


### Peer-to-Peer connectivity

The default means of connecting to the p2p network is using DNS seeds. 
These are frequently updating DNS records, containing IP addresses of
recently connected bitcoin nodes.

The library leverages https://github.com/Bit-Wasp/bitcoin-p2p-php 
which ultimately uses ReactPHP's event loop and networking libraries. 


### Peer State

The `PeerState` class is lacking love at the moment, as it's methods
are for new nonexistant code. It can be used to store arbitrary values 
(it extends Doctrines ArrayCache, and implements the native \ArrayAccess 
interface)





### Block Index

A `BlockIndex` instance represents a header as a member of a chain. 
It captures the height, chain-work-to-date, the `BlockHeader`, and 
the hash. It can be loaded from the Headers index. 

### ChainCache

A `ChainCache` is used to hold a map of height-to-hash, and vice versa.

### Chain

A `Chain` instance represents a chain tip, ie, a BlockIndex which 
_does not have a child_. They also capture the hashes in the chain
(in memory), to allow for getHashByHeight(), and getHeightByHash(). 

Chain instances will be useful throughout the project, as they
retain enough knowledge about the chain to avoid heavy DB calls.

### Header Index

The 'Headers' index provides raw access to the headers storage,
(lookups by a hash), and has other methods for maintaining this dataset.

It initializes by loading querying for known tips (Chain instances)
and adding the best valid, and greatest-work chain into Chain::$activeTip.
It exposes functions to generate a BlockLocator (see getheaders msg), 
to fetch a header by it's hash, to return the active Chain, to 
return any other known tips, and to process new headers received. 

The header chain is maintained in the headerIndex SQL table. 
This table implements the nested set model, a two column 
coordinate that describes the links in the chain. 
This model is typically used where hierarchical data is used,
and allows simple queries to find (for example):
 * the longest work chain
 * the tips of all forks
 * set of parent blocks for any block
without the use of recursive functions, and without requiring 
much data from other tables to function. The state of the table 
captures every chain the software is aware of, and will make 
querying for consensus-determined data (such as transactions) 
easier. 

The application will always seed the headerIndex table with 
the genesis block. With this, it can begin requesting headers
from peers. 

It will only actively sync headers from one peer.

The application will handle `Headers` network messages, and makes 
certain assumptions: 

`Headers::accept()` - Accept a single header 
`Headers::acceptBatch()` - Accept an array of headers 
The usual situation is we will be elongating some chain tip, creating
an index for each header, and updating the chain instance. (See Chain::updateTip())
`Chain::updateTip` will ensure that the sequence of headers is continuous, 
and if any headers were previously unknown, they are inserted as a batch. 

### Block Downloader / Block Request


### Block Storage (Index\Blocks)

This class is used along with Index\Headers to re-trace a `Chain` whilst
downloading it's blocks. 

Blocks::accept() will accept a block so long as the header is already in 
the chain, or is an otherwise acceptable header. 

