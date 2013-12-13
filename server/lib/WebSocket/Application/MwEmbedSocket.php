<?php 
// simple socket that manages subscription events 
namespace WebSocket\Application;

/**
 * Shiny WSS Status Application
 * Provides live server infos/messages to client/browser.
 *
 * @author Simon Samtleben <web@lemmingzshadow.net>
 * @author Michael Dale <michael.dale@kaltura.com>
 */
class MwEmbedSocket extends Application
{
	private $_clients = array();
	private $_serverClients = array();
	private $_serverInfo = array();
	private $_serverClientCount = 0;

	private $_uuidSubscribers = array();

	public function onConnect($client)
	{
		$id = $client->getClientId();
		$this->_clients[$id] = $client;
		$this->_sendServerinfo($client);
	}

	public function onDisconnect($client)
	{
		$id = $client->getClientId();
		unset($this->_clients[$id]);
	}

	public function onData($data, $client)
	{
		$decodedData = $this->_decodeData( $data );
		if( $decodedData === false ){
			// @todo: invalid request trigger error...
			return false;
		}
		switch( $dataObject['action'] ){
			case 'subscribe':
				if( isset( $dataObject['uuid'] ) ){
					$this->subscribeClientToUuid(  $client->getClientId(), $dataObject['uuid'] );
				}
			break;
			case 'log':
				if( isset( $dataObject['uuid'],  $dataObject['message'] ) ){
					$this->sendMessageToUuids(
						$dataObject['uuid'], 
						$dataObject['message'],
						 $client->getClientId()
					);
				}
				break;
		}
	}

	public function setServerInfo($serverInfo)
	{
		if(is_array($serverInfo))
		{
			$this->_serverInfo = $serverInfo;
			return true;
		}
		return false;
	}


	public function clientConnected($ip, $port)
	{
		$this->_serverClients[$port] = $ip;
		$this->_serverClientCount++;
		$this->statusMsg('Client connected: ' .$ip.':'.$port);
		$data = array(
			'ip' => $ip,
			'port' => $port,
			'clientCount' => $this->_serverClientCount,
		);
		$encodedData = $this->_encodeData('clientConnected', $data);
		$this->_sendAll($encodedData);
	}

	public function clientDisconnected($ip, $port)
	{
		if(!isset($this->_serverClients[$port]))
		{
			return false;
		}
		unset($this->_serverClients[$port]);
		$this->_serverClientCount--;
		$this->statusMsg('Client disconnected: ' .$ip.':'.$port);
		$data = array(
				'port' => $port,
				'clientCount' => $this->_serverClientCount,
		);
		$encodedData = $this->_encodeData('clientDisconnected', $data);
		$this->_sendAll($encodedData);
	}

	public function clientActivity($port)
	{
		$encodedData = $this->_encodeData('clientActivity', $port);
		$this->_sendAll($encodedData);
	}

	public function statusMsg($text, $type = 'info')
	{
		$data = array(
				'type' => $type,
				'text' => '['. strftime('%m-%d %H:%M', time()) . '] ' . $text,
		);
		$encodedData = $this->_encodeData('statusMsg', $data);
		$this->_sendAll($encodedData);
	}
	
	private function sendMessageToUuids( $uuid, $message, $excludeClientId ){
		foreach( $this->_uuidSubscribers[ $uuid ] as $clientId => $currentClient ){
			if( $currentClient->getClientId() != $excludeClientId ){
				$encodedData = $this->_encodeData('message', $message);
				$currentClient->send( $encodedData );
			}
		}
	}
	private function subscribeClientToUuid( $clientId, $uuid)
	{
		if( !$this->_uuidSubscribers[ $uuid ] ){
			$this->_uuidSubscribers[ $uuid ] = array();
		}
		$this->_uuidSubscribers[ $uuid ][$clientId ] = $client;
	}
	
	
	private function _sendServerinfo($client)
	{
		if(count($this->_clients) < 1)
		{
			return false;
		}
		$currentServerInfo = $this->_serverInfo;
		$currentServerInfo['clientCount'] = count($this->_serverClients);
		$currentServerInfo['clients'] = $this->_serverClients;
		$encodedData = $this->_encodeData('serverInfo', $currentServerInfo);
		$client->send($encodedData);
	}
	private function _sendAll($encodedData)
	{
		if(count($this->_clients) < 1)
		{
			return false;
		}
		foreach($this->_clients as $sendto)
		{
			$sendto->send($encodedData);
		}
	}
}