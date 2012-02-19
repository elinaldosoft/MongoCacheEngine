<?php
/**
 * A MongoDB (http://www.mongodb.org/) document-oriented database CacheEngine for the CakePHP.
 *
 * This datasource uses Pecl Mongo (http://php.net/mongo)
 * and is thus dependent on PHP 5.0 and greater.
 *
 * Reference:
 *      (Yasushi Ichikawa) http://github.com/ichikaway/
 *
 * Copyright 2012, Chelder Guimarães http://github.com/cheldernunes/
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2012, Chelder Guimarães http://github.com/chelder/
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

class MongodbEngine extends CacheEngine {


        
        public $connected = null;
        
        public $connection =null;
        
        protected $_db = null;
        
        protected $_driverVersion = Mongo::VERSION;                
        
        public $settings = array(
		'persistent' => true,
                'probability'=>'100',
                'serialize' =>  null,
		'host'       => 'localhost',
		'database'   => 'cache',
                'collection' => 'default',
                'prefix'     =>  null,
		'port'       => '27017',
		'login'		=> '',
		'password'	=> '',
		'replicaset'	=> '',
	);
               
	function init($settings = array()) {
            
		if (!class_exists('Mongo')) {
			return false;
		}
                
                if (!empty($settings))
                    $this->settings=array_merge($this->settings,$settings);		
		return $this->connect();
	}
        
        
	public function commit() {
		return false;
	}
        
        
        
	public function connect() {
		$this->connected = false;

		try{

			$host = $this->createConnectionName($this->settings, $this->_driverVersion);

			if (isset($this->settings['replicaset']) && count($this->settings['replicaset']) === 2) {
				$this->connection = new Mongo($this->settings['replicaset']['host'], $this->settings['replicaset']['options']);
			} else if ($this->_driverVersion >= '1.2.0') {
				$this->connection = new Mongo($host, array("persist" => $this->settings['persistent']));
			} else {
				$this->connection = new Mongo($host, true, $this->settings['persistent']);
			}

			if (isset($this->settings['slaveok'])) {
				$this->connection->setSlaveOkay($this->settings['slaveok']);
			}

			if ($this->_db = $this->connection->selectDB($this->settings['database'])) {
				if (!empty($this->settings['login']) && $this->_driverVersion < '1.2.0') {
					$return = $this->_db->authenticate($this->settings['login'], $this->settings['password']);
					if (!$return || !$return['ok']) {
						trigger_error('Mongodb::connect ' . $return['errmsg']);
						return false;
					}
				}

				$this->connected = true;
			}

		} catch(MongoException $e) {
                        
			$this->error = $e->getMessage();
			trigger_error($this->error);
		}
		return $this->connected;
	}     
        
        
/**
 * create connection name.
 *
 * @param array $settings
 * @param string $version  version of MongoDriver
*/
        public function createConnectionName($settings, $version) {
                $host = null;

                if ($version >= '1.0.2') {
                        $host = "mongodb://";
                } else {
                        $host = '';
                }
                $hostname = $settings['host'] . ':' . $settings['port'];

                if(!empty($settings['login'])){
                        $host .= $settings['login'] .':'. $settings['password'] . '@' . $hostname . '/'. $settings['database'];
                } else {
                        $host .= $hostname;
                }

                return $host;
        }        

        
        function write($key, &$data, $duration) {
		if ($data === '' || !$this->connected) {
			return false;
		}
		
		if (!empty($this->settings['serialize'])) {
                    $data = serialize($data);
		}
                $expires = time() + $duration;
                $cacheMongo['data']=$data;
                $cacheMongo['expires']=$expires;
                $cacheMongo['key']=$key;
		
                $success = $this->_db->{$this->settings['collection']}->save($cacheMongo);
		return $success;
	}
        

	function read($key) {
		$valor = $this->_db->{$this->settings['collection']}->findOne(array('key'=>$key));
                if ($valor['expires']-time()<=0){ //expirou
                    $this->delete($key);
                    
                    return false;
                }
                
                if (empty($valor)){
                    return false;
                }else{
                    return unserialize($valor['data']);    
                }
                
                
	}


	function delete($key) {
		return $this->_db->{$this->settings['collection']}->remove(array('key'=>$key));
	}

	function clear() {
		return $this->_db->{$this->settings['collection']}->remove();
	}


}
?>