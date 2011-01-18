# ClusterStorage #

_ClusterStorage_ is intended to be drop-in replacement
for the native file:// I/O stream in PHP — allowing for
synced storage of data among many clustered nodes.

The goal of this project is to provide near-local disk speed
for retrieving data from the pool, as well as ensuring that
nodes in the cluster do not become stale. In addition, this
means that adding or removing nodes from the cluster is painless
and transparent — there is no initial syncing required.

## How it works ##

ClusterStorage uses a registry (stored in memcache, and ultimately
a database for persistent storage) to determine which nodes in a
cluster has a file. When a node that does not have the file is
hit, it will pull the file from an existing node and cache it locally
for all further requests.

To ensure redundancy, when a file is added to the cluster, the file is *pushed*
to at _least_ one other node.

## How fast is it? ##

According to initial proof-of-concept benchmarks, there is a 0.4% performance
loss when using ClusterStorage to retrieve a file Vs Apache hitting the local
disk directly. This test assumes ideal conditions of:

 * The file is in the pool
 * The file's location is in the memcache registry
 * The file exists on the current node

Benchmarks saw native apache getting 964 req/s, with ClusterStorage getting 959 req/s.
All tests were done using a 121KB JPG photo.

When the file does not exist in the pool, there is a 75% performance degradation
compared to Apache. Despite this performance hit, it is still capable of 2000 req/s
on commodity hardware (compared to Apache's 8.5K req/s).

This test is not entirely accurate as there is no further checking against the persistent
store to ensure if the file should be there.

## Requirements ##

 * PHP 5.3.3 (may work on older 5.3.x) 
   * ext/fileinfo
   * ext/pdo_mysql
   * ext/memcache
   * ext/apc (frapi)
   * ext/http (pecl, frapi)
 * Memcache
 * MySQL 5.1
 * [FRAPI](http://getfrapi.com/)
  * PEAR
  * PEAR::HTTP_Request2
