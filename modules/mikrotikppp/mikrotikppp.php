<?php
	class mikrotikppp
	{
		private $connected=false;
		private $API;
		private $id;
    private $db;
		private $notificationaddrlist;
    public $supportQueue;
		public function __construct($ip, $port, $login, $pass, $id=false)
		{
      global $db;
		  if (!class_exists('routeros_api') )require_once('routeros_api.class.php');
      $this->API = new routeros_api();
      $this->messageAddressList='message';
      $this->notificationAddressList='notification';
      $this->API->attempts=2;
      $this->supportQueue=true;
      $this->API->timeout=5;
      $this->API->delay=2;
      $this->API->port=$port;
      $this->db=$db;
		  if ($this->API->connect($ip, $login, $pass))
		  {
		    $this->connected=true;
				$this->id=$id;
      }
      else
      {
        addRequestError('cannotconnect');
      }
		}
		function __destruct()
		{
      if ($this->connected) $this->API->disconnect();
    }


    private function sync($sectionData)
    {
      foreach ($sectionData as $sectionKey => $sectionValue)
      {
        foreach ($sectionValue as $userId => $userData)
        {
					$response = $this->API->comm($sectionKey.'/print',array( '?comment' => $userId));

          for ($i=0; $i<count($userData) || $i<count($response); $i++)
          {
            if (array_key_exists($i, $userData) && array_key_exists($i, $response)) // Check and modify existing rules
            {

              $newRule=$userData[$i];
              $currentRule=$response[$i];

              $ruleNeedsUpdate=false;
              $newProperties=array();

              // Compare every property in rule
              foreach ($newRule as $propertyKey => $propertyValue)
              {
                if ($newRule[$propertyKey]!==$currentRule[$propertyKey])
                {
                  $ruleNeedsUpdate=true;
                  $newProperties[$propertyKey]=$propertyValue;
                }
              }

              if ($ruleNeedsUpdate)
              {
                $newProperties['.id']=$currentRule['.id'];
                $this->API->comm($sectionKey."/set", $newProperties);
              }

            }
            else if (array_key_exists($i, $response)) // remove other rules
            {
              $currentRule=$response[$i];
              $this->API->comm($sectionKey."/remove", array('.id' => $currentRule['.id']));
            }
            else // Add new rules
            {
              $newRule=$userData[$i];
              $newRule['comment'] = $userId;
              $this->API->comm($sectionKey."/add", $newRule);
            }
          }
        }
      }
    }

    public function checkConnection()
    {
      if ($this->connected) 
      {
        $addrlist='online';
        $filterArray=array("?list" => $addrlist, );
        $resp=$this->API->comm("/ip/firewall/address-list/print", $filterArray);
        $onlineaddr=array();
        foreach ($resp as $rkey => $rvalue) $onlineaddr[]=$rvalue['address'];

        $resp=$this->API->comm("/system/resource/print");
        $resp[0]['online']=$onlineaddr;
        return $resp[0];
      }
    }
    private function calculateMikrotikTime($time)
    {
      $timePostfix=array ('h', 'm', 's');
      $mikrotikTime='';

      $timeArray=explode(':', $time);
      for($i=0; $i<count($timeArray); $i++)
      {

        if (intval($timeArray[$i]))
        {
          $mikrotikTime .= intval($timeArray[$i]).$timePostfix[$i];
        }
      }
      if (!$mikrotikTime)
      {
        $mikrotikTime='0s';
      }
      return $mikrotikTime;
    }

    public function checkonline($userid)
    {
      if ($this->connected) return true;
    }
    public function shownotification($userid)
    {
      if ($this->connected)
      {
        if ($devuserres=$this->db->query("SELECT * FROM ".DB_TABLE_PREFIX."user WHERE id='$userid'")->fetchAll())
				{
					$devuserrow = $devuserres[0];
					$useraddr=json_decode($devuserrow['iplist'],true);
					if (empty($useraddr) || $useraddr==NULL) return false;

          $ipList=array();
          foreach ($useraddr as $ip => $mac)
          {
            $ipList[]=$ip;
          }
          
          $this->clearnotification($userid);

          $this->API->comm("/ip/firewall/address-list/add", array("list" => $this->notificationAddressList, "address" => $ipList[1], "comment" => $devuserrow['id'], "disabled" => "no", ));
        }
        return true;
      }
    } 
    public function clearnotification($userid=false)
    {
      if ($this->connected)
      {
        $filterArray=array("?list" => $this->notificationAddressList );
        if ($userid) $filterArray['?comment']=$userid;
        $resp=$this->API->comm("/ip/firewall/address-list/print", $filterArray);
        foreach ($resp as $rkey => $rvalue) $this->API->comm("/ip/firewall/address-list/remove", array(".id" => $rvalue['.id'],));
        return true;
      }
    }
    public function showmessage($userid)
    {
      if ($this->connected)
      {
        if ($devuserres=$this->db->query("SELECT * FROM ".DB_TABLE_PREFIX."user WHERE id='$userid'")->fetchAll())
				{
					$devuserrow = $devuserres[0];
					$useraddr=json_decode($devuserrow['iplist'],true);
					if (empty($useraddr) || $useraddr==NULL) return false;

          $ipList=array();
          foreach ($useraddr as $ip => $mac)
          {
            $ipList[]=$ip;
          }
          
          $this->hidemessage($userid);

          $this->API->comm("/ip/firewall/address-list/add", array("list" => $this->messageAddressList, "address" => $ipList[1], "comment" => $devuserrow['id'], "disabled" => "no", ));
        }
        return true;
      }
    } 
    public function hidemessage($userid=false)
    {
      if ($this->connected)
      {
        $filterArray=array("?list" => $this->messageAddressList );
        if ($userid) $filterArray['?comment']=$userid;
        $resp=$this->API->comm("/ip/firewall/address-list/print", $filterArray);
        foreach ($resp as $rkey => $rvalue) $this->API->comm("/ip/firewall/address-list/remove", array(".id" => $rvalue['.id'],));
        return true;
      }
    }

    public function getonline()
    {
      if ($this->connected)
      {
        $addrlist='online';
        $filterArray=array("?list" => $addrlist, );
        $resp=$this->API->comm("/ip/firewall/address-list/print", $filterArray);
        $onlineaddr=array();
        foreach ($resp as $rkey => $rvalue) $onlineaddr[]=$rvalue['address'];
        return $onlineaddr;
      }
    }
    public function getinterfaces()
    {
      if ($this->connected)
      {
        $resp=$this->API->comm("/interface/getall");
        //$response->header=array(array('id', 'varchar'));
        $onlineaddr=array();
        foreach ($resp as $rkey => $rvalue) $onlineaddr[$rvalue['name']]=array($rvalue['name'], $rvalue['name']);
        return $onlineaddr;
      }
    }
		public function update($userid)
		{
			if ($this->connected)
			{
        $resource=$this->checkConnection();
        $majorVersion=intval($resource['version'][0]);

        $usersTable=new table('user');
        $usersRes=$usersTable->load(" WHERE id=$userid");
				if (!$usersRes) return;

        

        foreach ($usersRes as $devuserrow)
        {
          $syncData=array(
          "/ip/firewall/address-list" => array(),
          "/queue/simple" => array(),
          "/ppp/secret" => array()
        );
          $userId=''.$devuserrow['id'];
					$useraddr=json_decode($devuserrow['iplist'],true);
          
					//	Address list section
          $currentTariff= getCurrentTariff($devuserrow['id'], $usersTable);
          if ($currentTariff)
          {
            $currentTariff= $currentTariff['detailsid'];
          }
					if (empty($useraddr) || $useraddr==NULL) return true;

          $ipList=array();
          foreach ($useraddr as $ip => $mac)
          {
            $ipList[]=$ip;
          }

          if (userIsDisabled($userid, $usersTable))
          {
            $addrlist='disabled';
          }
          else
          {
            $addrlist=$currentTariff ? 'allow' : 'deny';
          }
          
          if (count($ipList)===2)
          {
            $syncData["/ip/firewall/address-list"][$userId][] = array(
              "list" => $addrlist,
              "address" => $ipList[1]
            );
          }
				          
					//	Queque section
       
          if ($currentTariff)
          {
            $tariffTable=new table('tariff');
            $utariffres=$tariffTable->load("WHERE id=$currentTariff");
            $utariffrow = $utariffres[0];
            if ($utariffrow)
            {
              // Select right target addresses index
              if ($majorVersion===5)
              {
                $addressIndex='target-addresses';
              }
              else
              {
                $addressIndex='target';
              }
              // Normal / day
              if (pluginExists('night') && ($utariffrow['nightupspeed']  || $utariffrow['nightdownspeed'] ) )
              {
                $dayTime  = $this->calculateMikrotikTime ( configgetvalue('system', 'tariff', NULL, 'nightHourEnd') );
                $dayTime .= '-1d';
                //$dayTime .= '-'.$this->calculateMikrotikTime ( configgetvalue('system', 'tariff', NULL, 'nightHourStart') );
                $dayTime .= ',sun,mon,tue,wed,thu,fri,sat';
              }
              else
              {
                $dayTime  = '0s-1d,sun,mon,tue,wed,thu,fri,sat';
              }
              
              // Burst
              if ( pluginExists('burst') &&
                 ( $utariffrow['downburstlimit'] &&
                   $utariffrow['upburstlimit'] &&
                   $utariffrow['downburstthreshold'] &&
                   $utariffrow['upburstthreshold'] &&
                   $utariffrow['downbursttime'] &&
                   $utariffrow['upbursttime'] ) 
                 )
              {
                $burstLimit=$utariffrow['upburstlimit'].'/'.$utariffrow['downburstlimit'];
                $burstThreshold=$utariffrow['upburstthreshold'].'/'.$utariffrow['downburstthreshold'];
                $burstTime=$utariffrow['upbursttime'].'/'.$utariffrow['downbursttime'];
              }
              else
              {
                $burstLimit="0/0";
                $burstThreshold="0/0";
                $burstTime="0s/0s";
              }

              $useraddr=json_decode($devuserrow['iplist'],true);
              $responce = $this->API->comm('/queue/simple/print',array( '?name' => $userid ));
              $ipList=array();
              foreach ($useraddr as $ip => $mac)
              {
                $ipList[]=$ip."/32";
              }
            
              if (count($ipList)===2)
              {
                $syncData["/queue/simple"][$userId][] = array(
                  "limit-at" => $utariffrow['upspeed']."/".$utariffrow['downspeed'],
                  "max-limit" => $utariffrow['upspeed']."/".$utariffrow['downspeed'],
                  $addressIndex => $ipList[1],
                  "name" => $userId,
                  "time" => $dayTime,
                  "burst-limit"=> $burstLimit,
                  "burst-threshold"=> $burstThreshold,
                  "burst-time"=> $burstTime
                );

              }



              // Night

              $responce = $this->API->comm('/queue/simple/print',array( '?name' => "$userid-night" ));

              if (pluginExists('night') &&( $utariffrow['nightupspeed']  || $utariffrow['nightdownspeed'] ) )
              {
                $useraddr=json_decode($devuserrow['iplist'],true);
                $ipList=array();
                foreach ($useraddr as $ip => $mac)
                {
                  $ipList[]=$ip."/32";
                }

                //$time  = $this->calculateMikrotikTime ( configgetvalue('system', 'tariff', NULL, 'nightHourStart') );
                $time  = '0s';
                $time .= '-'.$this->calculateMikrotikTime ( configgetvalue('system', 'tariff', NULL, 'nightHourEnd') );
                $time .= ',sun,mon,tue,wed,thu,fri,sat';

              
                if (count($ipList)==2)
                {
                  $syncData["/queue/simple"][$userId][] = array(
                    "limit-at" => $utariffrow['nightupspeed']."/".$utariffrow['nightdownspeed'],
                    "max-limit" => $utariffrow['nightupspeed']."/".$utariffrow['nightdownspeed'],
                    $addressIndex => $ipList[1],
                    "name" => $userId.'-night',
                    "time" => $time,
                    "burst-limit"=> $burstLimit,
                    "burst-threshold"=> $burstThreshold,
                    "burst-time"=> $burstTime
                  );
                }


              }
              else if (count($responce))
              {
                $this->API->comm("/queue/simple/remove", array( ".id"=>$responce[0]['.id'], ));
              }

            }
          }


          //	PPP section
          //	
          $useraddr=json_decode($devuserrow['iplist'],true);
          $response = $this->API->comm('/ppp/secret/print',array( '?comment' => $userid ));
          $ipList=array();
          $disableSecretsForDisabledUsers=configgetvalue('router', 'ppp', $this->id, 'disablePPPSecretsOfBlockedUsers'); 
          $disabledState=( $devuserrow['disabled']=='1' && $disableSecretsForDisabledUsers) ? 'yes' : 'no';
          foreach ($useraddr as $ip => $mac)
          {
            $ipList[]=$ip;
          }
          
          if (count($ipList)===2)
          {
            $syncData["/ppp/secret"][$userId][] = array(
              "service" => "any",
              "profile" => "default",
              "local-address" => $ipList[0],
              "remote-address" => $ipList[1],
              "disabled" => $disabledState,
              "name" => $devuserrow['login'],
              "password" => $devuserrow['password']
            );
          }


				}
        $this->sync($syncData);

        return $this->checkConnection();
			}
		}
    public function delete($userId)
		{
			if ($this->connected)
      {
        $syncData=array(
          "/ip/firewall/address-list" => array($userId => array()),
          "/queue/simple" => array($userId => array()),
          "/ppp/secret" => array($userId => array())
        );

        $this->sync($syncData);

        return $this->checkConnection();
			}
		}
	
		public function export()
		{
      if ($this->connected)
      {
        $usersTable=new table('user');
        $res=$usersTable->load(" WHERE router=".$this->id);
        if ($res)
        {
          foreach ($res as $row)
          {
            $this->update($row['id']);
          }
          return $this->checkConnection();
        }
      }
		}
	}
?>
