
## PHPNODE

A toolkit for building a bitcoin node, built with ReactPHP. See Design.md for more detailed overview.

This repository includes a console application built from the Symfony Console package.
 
The `BitcoinNode` class supports headers first download, and will then attempt to download the full block history. Since this will likely take a few days, I haven't had the patience to leave it running. Still much code to write.
 
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
`mkdir ~/.phpnode && phpnode print-config > ~/.phpnode/bitcoin.ini`

## Run the software

See the list of commands: 
`phpnode list`

Or general help:
`phpnode help`

### Controlling the node 

Start with config file in the default location:
`phpnode node:start`

IMPORTANT: How to the stop the software (don't use CTRL-C)
`phpnode node:stop`

Run with configuration in an alternative location:
`phpnode node:start -c /tmp/yourconfig`

Watch a debug log of events reported by the node:
`phpnode node:watch`

Show information about the node's best chain:
`phpnode node:info`

Show information about all tracked chains:
`phpnode node:chains`


### Administering the database

Wipe the database:
`phpnode db:wipe`

Empty all tables:
`phpnode db:reset`

Empty only full block data (leave headers/index alone):
`phpnode db:blocks:reset`