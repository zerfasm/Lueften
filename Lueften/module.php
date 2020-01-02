<?php
require_once __DIR__.'/../libs/traits.php';  // Allgemeine Funktionen
// CLASS ClimateCalculation
class Lueften extends IPSModule
{
    use ProfileHelper, DebugHelper;
    public function Create()
    {
        //Never delete this line!
        parent::Create();
	    
        // Window variables
        $this->RegisterPropertyInteger('WindowValue', 0);
	$this->RegisterPropertyInteger('AirTime', 15);
        $this->RegisterPropertyInteger('DiffLimit', 5);
			
	// Alexa variables   
        $this->RegisterPropertyBoolean('TTSAlexa', false);
        $this->RegisterPropertyString('AlexaID', "");
	$this->RegisterPropertyInteger('AlexaVolume', 40);
        $this->RegisterPropertyString('NameRoom', "");
    }
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
	         
        // Profile "SCHB.Ventilate"
        $association = [
            [0, 'Nicht gelüftet', 'Window-0', 0xFF0000],
            [1, 'Gelüftet', 'Window-100', 0x00FF00],
        ];
        $this->RegisterProfile(vtInteger, 'LUEF.Ventilate', 'Window', 'Information', '', 0, 0, 0, 0, $association);
               
	//Geöffnet um
	$this->RegisterVariableInteger('WinOpen', 'Fenster geöffnet','~UnixTimestamp',12);
	    
	//Geschlossen um
	$this->RegisterVariableInteger('WinClose', 'Fenster geschlossen','~UnixTimestamp',13);
	    
	//Zeit Fenster Offen
	$this->RegisterVariableInteger('TimeWinOpen', 'Zeit Fenster geöffnet','time.min',14);
	    
        //Gelüftet
	$this->RegisterVariableInteger('Ventilate', 'Gelüftet','SCHB.Ventilate',15);   
	       
    	// Trigger Fenster
	if ($this->ReadPropertyInteger('WindowValue') > 0)
	{
		$this->RegisterTriggerWindow("Fenster", "TriggerFenster", 0, $this->InstanceID, 0,"SCHB_Update(\$_IPS['TARGET']);");
	};
    }
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * SCHB_Update($id);
     */
    public function Update()
    {
        $result = 'Ergebnis konnte nicht ermittelt werden!';
        // Daten lesen
        $state = true;
        
                
        // Gelüftet
        $tts = $this->ReadPropertyBoolean('TTSAlexa');
        $nr = $this->ReadPropertyString('NameRoom');
        $AID = $this->ReadPropertyString('AlexaID');   
        $AV = $this->ReadPropertyInteger('AlexaVolume'); 
	    
	$wv = $this->ReadPropertyInteger('WindowValue');
	if ($wv != 0) 
	{
            	$wv = GetValue($wv);
		if ($wv == true)
		{
			
            		$this->SetValue('WinOpen', IPS_GetVariable($this->ReadPropertyInteger('WindowValue'))["VariableChanged"]);
		}
		else
		{	
			$this->SetValue('WinClose', IPS_GetVariable($this->ReadPropertyInteger('WindowValue'))["VariableChanged"]);
			
			if ($this->GetValue('WinOpen') > 0)
			{
				$winopen = $this->GetValue('WinOpen'); 
				$winclose = $this->GetValue('WinClose');
				$timewinopen = $this->GetValue('TimeWinOpen');
				$airtime = $this->ReadPropertyInteger('AirTime');
			
				$timediff = (($winclose - $winopen)/60);
				$this->SetValue('TimeWinOpen',$timediff);
				
				//if ($timewinopen >= 15)
				if ($timewinopen >= $airtime)
				{
					// Status gelüftet setzen
					$this->SetValue('Ventilate', true);
					
					//TTS Alexa Echo Remote Modul   
					if ($tts == true)
					{
						EchoRemote_SetVolume($AID, $AV);
						EchoRemote_TextToSpeech($AID, "Lüften $nr benenden"); 
					}
				}
			}
        	} 
	} else 
	{
            $this->SendDebug('UPDATE', 'Window Contact not set!');
            $state = false;
        }
      }
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * SCHB_Duration($id, $duration);
     *
     * @param int $duration Wartezeit einstellen.
     */
    public function Duration(int $duration)
    {
        IPS_SetProperty($this->InstanceID, 'UpdateTimer', $duration);
        IPS_ApplyChanges($this->InstanceID);
    }
	
    private function RegisterTriggerWindow($Name, $Ident, $Typ, $Parent, $Position, $Skript)
    {
	$eid = @$this->GetIDForIdent($Ident);
	if($eid === false) {
		$eid = 0;
	} elseif(IPS_GetEvent($eid)['EventType'] <> $Typ) {
		IPS_DeleteEvent($eid);
		$eid = 0;
	}
	//we need to create one
	if ($eid == 0) {
	    $EventID = IPS_CreateEvent($Typ);
		IPS_SetEventTrigger($EventID, 1, $this->ReadPropertyInteger('WindowValue'));
		IPS_SetParent($EventID, $Parent);
		IPS_SetIdent($EventID, $Ident);
		IPS_SetName($EventID, $Name);
		IPS_SetPosition($EventID, $Position);
		IPS_SetEventScript($EventID, $Skript); 
		IPS_SetEventActive($EventID, true);  
	}
    }
}
