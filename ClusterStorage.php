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
	 * ClusterStorage Version
	 */
	const VERSION = "1.0";
	
	/**
	 * @var resource The current stream context
	 */
	public $context;
	
	/**
	 * @var array List of servers in the pool
	 */
	static protected $servers = array();
	
	/**
	 * @var \Memcache Memcache connection
	 */
	static protected $memcache;
	
	/**
	 * @var string Prefix for memcache keys
	 */
	static protected $memcache_prefix;
	
	/**
	 * @var PDO DB Connection
	 */
	static protected $db;
	
	/**
	 * @var string The scheme for the stream, defaults to cluster://
	 */
	static protected $scheme;
	
	/**
	 * @var string Absolute Base path, all requested files will be sub-directories of this path
	 */
	static protected $basepath;
	
	/**
	 * @var string Current node identity
	 */
	static protected $identity;
	
	/**
	 * @var string Protocol to use for internal cluster communication
	 */
	static protected $protocol;
	
	/**
	 * @var octal The default file and folder mode
	 */
	static protected $mode = 0775;
	
	/**
	 * @var resource Local file pointer
	 */
	protected $fp;
	
	/**
	 * @var boolean Whether the file has been written to or not
	 */
	protected $has_writes = false;
	
	/**
	 * Add a server to the storage pool
	 * 
	 * @param string $host Server hostname
	 * @param string $port Server port
	 * @param string $endpoint Server REST endpoint
	 * @param string $basepath Server local storage basepath
	 * @param array $options All other options (timeout, weight)
	 */
	static public function addStorageServer($host, $port, $endpoint, array $options = array())
	{
		self::$servers[] = array('host' => $host, 'post' => $port, 'endpoint' => $endpoint, 'options' => $options);
		$context = array(
			self::$protocol => array(
				'pool' => self::$servers,
			)
		);
		\stream_context_set_default($context);
	}
	
	/**
	 * Set the memcache connector
	 * 
	 * @param Memcache $memcache
	 * @param string $prefix Memcache key prefix, will be appended with an underscore
	 */
	static public function setMemcache(Memcache $memcache, $prefix = '')
	{
		self::$memcache = $memcache;
		if ($prefix != '') {
			self::$memcache_prefix = $prefix . '_';
		}
	}
	
	/**
	 * Set the DB connector
	 * 
	 * @param PDO $pdo PDO or PDO Compatible DB adapter. Currently supports MySQL.
	 */
	static public function setDBAdapter($db)
	{
		self::$db = $db;
	}
	
	/**
	 * Set the basepath for the storage
	 * 
	 * @param string $path Absolute Base path, all requested files will be sub-directories of this path
	 */
	static public function setBasePath($path)
	{
		self::$basepath = $path;
	}
	
	static public function useSSL($flag = true)
	{
		if ($flag) {
			self::$protocol = 'https';
		} else {
			self::$protocol = 'http';
		}
	}
	
	/**
	 * @param string $identity Identify of the current node
	 */
	static public function identify($identity)
	{
		self::$identity = $identity;
	}
	
	/**
	 * Registers the stream wrapper
	 * 
	 * @param string $scheme The protocol for the stream (without ://), defaults to cluster://
	 */
	static public function registerStream($scheme = 'cluster')
	{
		self::$scheme = $scheme;
		\stream_register_wrapper($scheme, __NAMESPACE__ . '\ClusterStorage');
	}
	
	/**
	 * Set the default permissions mode
	 * 
	 * @param octal $mode An octal number for the permissions
	 */
	static public function setDefaultMode($mode)
	{
		self::$mode = $mode;
	}
	
	protected function persist($path, $data)
	{
		$values = array(
			':key' => $path,
			':data' => \json_encode($data)
		);
		$query = self::$db->prepare("REPLACE INTO cluster_store (key, data) VALUES (:key, :data)");
		$query->execute($values);
	}
	
	protected function register($path)
	{
		// Fetch the memcache data again, in case this took a while
		if (!$data = $this->getRegistry($path)) {
			$data = array('nodes' => array(self::$identity));
		} else {
			$data = \json_decode($data, true);
			if (!in_array(self::$identity, $data['nodes'])) {
				$data['nodes'][] = self::$identity;
			}
		}
		
		self::$memcache->set(self::$memcache_prefix . $path, \json_encode($data));
	}
	
	protected function getRegistry($path)
	{
		if (!$data = self::$memcache->get(self::$memcache_prefix . $path)) {
			$query = self::$db->prepare("SELECT data FROM cluster_store WHERE key = :path");
			/* @var $query PDOStatement */
			$result = $query->execute(array(':path' => $path));
			if (!$result || $query->rowCount() == 0) {
				$data = '';
			}
			$data = $query->fetchColumn();
		}
		
		return $data;
	}
	
	public function __construct()
	{
		if (!(self::$memcache instanceof \Memcache)) {
			throw new DS\ClusterStorage\Exception("Memcache storage not set.");
		}
		
		if (!self::$basepath) {
			throw new DS\ClusterStorage\Exception("Basepath not set.");
		}
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

	/**
	 * Close the resource and push file to additional node(s)
	 */
	public function stream_close()
	{
		$this->stream_flush();
		return \fclose($this->fp);
	}

	public function stream_eof()
	{
		return \feof($this->fp);
	}

	public function stream_flush()
	{
		$return = \fflush($this->fp);
		
		// Push to another node if necessary
	}

	public function stream_lock($operation)
	{
		return \flock($this->fp, $operation);
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
	 * @param type $path Path within the cluster
	 * @param type $mode Mode — ignored, always set to r+, or rb+ on windows
	 * @param type $options
	 * @param type $opened_path 
	 */
	public function stream_open($path, $mode, $options, &$opened_path)
	{
		$file = self::$basepath .\DIRECTORY_SEPARATOR. $path;
		
		$data = $this->getRegistry($path);
			
		if (!$data) {
			// This is a new file
			$this->fp = fopen($file, $mode, false, $this->context);
			return true;
		}
		
		$data = \json_decode($data, true);
		if ($data['deleted']) {
			// The file has been deleted, lets delete the local file if it exists
			if (\file_exists($file)) {
				// We silence this, just in case
				@\unlink($file);
				// And we save to the DB to be sure
				$this->persist($path, $data);
			}
			return false;
		}
		
		if (in_array(self::$identity, $data['nodes']) && file_exists($file)) {
			// The file exists on the current node, open it
			$fp = \fopen($file, $mode, false, $this->context);
		} else {
			// Create all necessary parent directories
			mkdir(dirname($file), self::$mode, true);
			
			// Fetch the file from an existing node
			$remote = \array_rand($data['nodes']);
			$remote_fp = \fopen(self::$protocol . '://' . $remote . '/store/v' .self::VERSION. '?path=' . \urlencode($path));
			
			// Open the local pointer
			$fp = \fopen($file, $mode, false, $this->context);
			while ($remote_data = \fread($remote_fp, 1024)) {
				\fwrite($fp, $remote_data, 1024);
			}
			\fclose($remote_fp);
			unset($remote_data);
			
			// Move back to the beginning
			\fseek($fp, 0);
			
			$this->register($path);
		}
		
		$this->fp = $fp;
		
		// Do this last, so that if we pull and update for the current node, that gets synced
		if ($_SERVER['REQUEST_TIME'] % 60 == 0) {
			$this->persist($path, $data);
		}
		
		return true;
	}

	public function stream_read($count)
	{
		return \fread($fp, $count);
	}

	public function stream_seek($offset, $whence = SEEK_SET)
	{
		return \fseek($this->fp, $offset, $whence);
	}

	public function stream_set_option($option, $arg1, $arg2 = null)
	{
		switch ($option) {
			case \STREAM_OPTION_BLOCKING:
				return \stream_set_blocking($this->fb, $arg1);
				break;
			case \STREAM_OPTION_READ_TIMEOUT:
				return \stream_set_timeout($this->fp, $arg1, $arg2);
				break;
			case \STREAM_OPTION_WRITE_BUFFER:
				return \stream_set_write_buffer($this->fp, $arg1);
				break;
			case \STREAM_OPTION_READ_BUFFER:
				return \stream_set_read_buffer($this->fp, $arg1);
				break;
		}
	}

	public function stream_stat()
	{
		return \fstat($this->fp);
	}

	public function stream_tell()
	{
		return ftell($this->fp);
	}

	public function stream_write($data, $length = null)
	{
		$this->has_writes = true;
		return fwrite($this->fp, $data, $length);
	}

	/**
	 * Remove the file from the registry and the local disk.
	 * 
	 * @param string $path 
	 */
	public function unlink($path)
	{
		return unlink(self::$basepath . \DIRECTORY_SEPARATOR . $path);
	}

	public function url_stat($path, $flags = false)
	{
		$file = self::$basepath . \DIRECTORY_SEPARATOR . $path;
		
		if (!file_exists($file)) {
			return false;
		}
		
		if (!$flags) {
			$stat = stat($path);
		} elseif ($flags == \STREAM_URL_STAT_QUIET) {
			$stat = @stat($path);
		} elseif ($flags == \STREAM_URL_STAT_LINK) {
			$stat == lstat($path);
		} elseif ($flags == \STREAM_URL_STAT_LINK|\STREAM_URL_STAT_QUIET) {
			$stat == @lstat($path);
		}
		
		$stat = (array) $stat;
		
		$data = $this->getRegistry($path);
		if ($data['nodes']) {
			$stat['cluster_nodes'] = $data['nodes'];
		} else {
			$stat['cluster_nodes'] = array();
		}
		
		return $stat;
	}
}