<?php
/**
 * ClusterStorage - A stream to handle clustered
 * file storage transparently with minimal speed
 * impact.
 * 
 * @todo Handle directories
 */

/**
 * DS Namespace
 */
namespace DS;

/**
 * ClusterStorage Class
 */
class ClusterStorage {
	/**
	 * @var resource The current stream context
	 */
	public $context;
	
	/**
	 * @var array List of servers in the pool
	 */
	static protected $servers = array();
	
	/**
	 * @var string The protocol for the stream, defaults to cluster://
	 */
	static protected $protocol;
	
	/**
	 * @var string The current nodes hostname
	 */
	static protected $identity;
	
	/**
	 * Add a server to the storage pool
	 * 
	 * @param string $host Server hostname
	 * @param string $port Server port
	 * @param string $endpoint Server REST endpoint
	 * @param string $basepath Server local storage basepath
	 * @param array $options All other options (timeout, weight)
	 */
	static public function addServer($host, $port, $endpoint, $basepath, array $options = array())
	{
		self::$servers[] = array('host' => $host, 'post' => $port, 'endpoint' => $endpoint, 'basepath' => $basepath, 'options' => $options);
		$context = array(
			self::$protocol => array(
				'pool' => self::$servers,
			)
		);
		\stream_context_set_default($context);
	}
	
	/**
	 * Registers the stream wrapper
	 * 
	 * @param string $protocol The protocol for the stream (without ://), defaults to cluster://
	 */
	static public function registerStream($protocol = 'cluster')
	{
		self::$protocol = $protocol;
		\stream_register_wrapper($protocol, __NAMESPACE__ . '\ClusterStorage');
	}
	
	/**
	 * Identify the current node
	 * 
	 * @param string $host 
	 */
	static public function identify($host)
	{
		self::$identity = $host;
	}
	
	public function __construct()
	{
		
	}
	
	public function __destruct()
	{
		
	}

	public function dir_closedir()
	{

	}
	
	public function dir_opendir($path, $options)
	{

	}
	
	public function dir_readdir()
	{

	}
	
	public function dir_rewinddir()
	{

	}

	public function mkdir($path, $mode, $options)
	{

	}

	public function rename($path_from, $path_to)
	{

	}

	public function rmdir($path, $options)
	{

	}

	public function stream_cast($cast_as)
	{

	}

	/**
	 * Close the resource and push file to additional node(s)
	 */
	public function stream_close()
	{

	}

	public function stream_eof()
	{

	}

	public function stream_flush()
	{

	}

	public function stream_lock($operation)
	{

	}

	/**
	 * Open the file resource
	 * 
	 * This method must perform the following tasks:
	 * 
	 * 1. Check if the requested resource is in the memcache registry
	 * 2. If not, check if it is in the database registry
	 * 3. If not, the file does not exist, check and delete from local node
	 * 4. Sync the memcache registry with the database registry
	 * 5. If memcache had it, randomly sync the database registry with memcache
	 * 6. Check if the local node has file, if not, pull it from a remote node
	 * 7. Add the local node to the registry
	 * 8. Open the local file
	 * 
	 * @param type $path
	 * @param type $mode
	 * @param type $options
	 * @param type $opened_path 
	 */
	public function stream_open($path, $mode, $options, &$opened_path)
	{

	}

	public function stream_read($count)
	{

	}

	public function stream_seek($offset, $whence = SEEK_SET)
	{

	}

	public function stream_set_option($option, $arg1, $arg2)
	{

	}

	public function stream_stat()
	{

	}

	public function stream_tell()
	{

	}

	public function stream_write($data)
	{

	}

	/**
	 * Remove the file from the registry and the local disk.
	 * 
	 * @param string $path 
	 */
	public function unlink($path)
	{

	}

	public function url_stat($path, $flags)
	{

	}
}

/*
ClusterStorage::registerStream();
ClusterStorage::addServer('localhost', '11311', '/clusterfs.php', 'test');
*/
