<?php
declare(strict_types=1);

/** Deterministic D1 yoga formation detector. Formation is separated from context. */
final class YogaDetector
{
    private const SIGNS=['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
    private const LORDS=['Aries'=>'Mars','Taurus'=>'Venus','Gemini'=>'Mercury','Cancer'=>'Moon','Leo'=>'Sun','Virgo'=>'Mercury','Libra'=>'Venus','Scorpio'=>'Mars','Sagittarius'=>'Jupiter','Capricorn'=>'Saturn','Aquarius'=>'Saturn','Pisces'=>'Jupiter'];
    private const EXALTATION=['Sun'=>'Aries','Moon'=>'Taurus','Mars'=>'Capricorn','Mercury'=>'Virgo','Jupiter'=>'Cancer','Venus'=>'Pisces','Saturn'=>'Libra'];
    private const DEBILITATION=['Sun'=>'Libra','Moon'=>'Scorpio','Mars'=>'Cancer','Mercury'=>'Pisces','Jupiter'=>'Capricorn','Venus'=>'Virgo','Saturn'=>'Aries'];
    private const OWN=['Sun'=>['Leo'],'Moon'=>['Cancer'],'Mars'=>['Aries','Scorpio'],'Mercury'=>['Gemini','Virgo'],'Jupiter'=>['Sagittarius','Pisces'],'Venus'=>['Taurus','Libra'],'Saturn'=>['Capricorn','Aquarius']];
    private const KENDRA=[1,4,7,10];
    private const TRIKONA=[1,5,9];
    private const DUSTHANA=[6,8,12];

    public function detect(array $planets,?string $lagna,?array $d9=null):array
    {
        $lagna=$this->normalizeSign($lagna);$byName=[];$byHouse=[];
        foreach($planets as $planet){
            if(!is_array($planet))continue;
            $name=$this->planetName($planet);$sign=$this->normalizeSign((string)($planet['rashi']??''));$house=is_numeric($planet['house']??null)?(int)$planet['house']:null;
            if($name===''||$sign===null||$house===null)continue;
            $byName[$name]=['name'=>$name,'sign'=>$sign,'house'=>$house,'degree'=>is_numeric($planet['local_degree']??null)?(float)$planet['local_degree']:null,'combust'=>$planet['combust']??null];
            $byHouse[$house][]=$name;
        }
        $lords=$this->houseLords($lagna);$y=[];
        foreach([['Ruchaka','Mars'],['Bhadra','Mercury'],['Hamsa','Jupiter'],['Malavya','Venus'],['Sasa','Saturn']] as [$yoga,$p]){
            $x=$byName[$p]??null;$formed=$x!==null&&in_array($x['house'],self::KENDRA,true)&&($this->isOwn($p,$x['sign'])||$this->isExalted($p,$x['sign']));
            $status=$formed&&$this->isCombust($x,$byName)?'Formed but weakened':($formed?'Formed':'Not formed');
            $reason=$formed?"$p is in a kendra in its own or exaltation sign.".($status==='Formed but weakened'?' Combustion is reported as a weakening context.':''):"$p does not meet both the kendra and own/exaltation-sign conditions.";
            $y[]=$this->result($yoga,'Pancha Mahapurusha',$status,$reason,[$p]);
        }
        $moon=$byName['Moon']??null;$jup=$byName['Jupiter']??null;
        if($moon&&$jup){$formed=in_array($this->houseDistance($moon['house'],$jup['house']),[1,4,7,10],true);$weak=$this->isDebilitated('Jupiter',$jup['sign'])||$this->isCombust($jup,$byName);$status=!$formed?'Not formed':($weak?'Formed but weakened':'Formed');$reason=$formed?'Jupiter is in a kendra from Moon.'.($weak?' Jupiter has a dignity/combustion weakening flag.':''):'Jupiter is not in a kendra (1/4/7/10) from Moon.';$y[]=$this->result('Gaja Kesari','Lunar-Jupiter',$status,$reason,['Moon','Jupiter']);}
        else $y[]=$this->result('Gaja Kesari','Lunar-Jupiter','Not formed','Moon and/or Jupiter is missing from normalized D1 data.',['Moon','Jupiter']);
        $r=$this->connectionYoga($lords,$byName,self::KENDRA,self::TRIKONA);$y[]=$this->result('Kendra–Trikona Raja Yoga','Raja Yoga',$r['status'],$r['reason'],$r['planets']);
        $d=$this->connectionYoga($lords,$byName,[2,11],[1,2,5,9,11]);$y[]=$this->result('Dhana Yoga','Wealth',$d['status'],$d['reason'],$d['planets']);
        $v=$this->viparita($lords,$byName);$y[]=$this->result('Vipareeta Raja Yoga','Dusthana',$v['status'],$v['reason'],$v['planets']);
        foreach($this->yogakaraka($lagna,$byName) as $item)$y[]=$item;
        foreach($this->neechaBhanga($byName,$d9) as $item)$y[]=$item;
        return ['lagna'=>$lagna,'yogas'=>$y,'house_lords'=>$lords,'house_occupancy'=>$byHouse];
    }

    private function result(string $name,string $family,string $status,string $reason,array $planets):array{return ['name'=>$name,'family'=>$family,'status'=>$status,'reason'=>$reason,'planets'=>array_values(array_unique($planets))];}

    private function connectionYoga(array $lords,array $byName,array $aH,array $bH):array
    {
        $connections=[];$p=[];
        foreach($aH as $ha)foreach($bH as $hb){if($ha===$hb)continue;$a=$lords[$ha]['lord']??null;$b=$lords[$hb]['lord']??null;if(!$a||!$b||$a===$b||!isset($byName[$a],$byName[$b]))continue;if($this->connected($byName[$a],$byName[$b])){$connections[]="$a (H$ha) ↔ $b (H$hb)";$p[]=$a;$p[]=$b;}}
        if(!$connections)return ['status'=>'Not formed','reason'=>'No conjunction, qualifying Parashari mutual aspect, or sign exchange was found among the relevant lords.','planets'=>[]];
        $p=array_values(array_unique($p));$weak=false;$notes=[];
        foreach($p as $name){$x=$byName[$name]??null;if(!$x)continue;if(in_array($x['house'],self::DUSTHANA,true)){$weak=true;$notes[]="$name in H{$x['house']}";}if($this->isDebilitated($name,$x['sign'])){$weak=true;$notes[]="$name debilitated";}}
        return ['status'=>$weak?'Formed but weakened':'Formed','reason'=>'Connected lords: '.implode('; ',$connections).($notes?' Contextual weakening: '.implode(', ',$notes).'.':''),'planets'=>$p];
    }

    private function yogakaraka(?string $lagna,array $byName):array
    {
        if(!$lagna)return[];$start=$this->signNumber($lagna);$out=[];
        foreach(self::OWN as $planet=>$ownedSigns){$owned=[];foreach($ownedSigns as $sign){$n=$this->signNumber($sign);if($n)$owned[]=((($n-$start+12)%12)+1);}if(!in_array(1,$owned,true)||!array_intersect($owned,self::KENDRA)||!array_intersect($owned,self::TRIKONA))continue;$x=$byName[$planet]??null;if(!$x){$out[]=$this->result('Yogakaraka — '.$planet,'Yogakaraka','Not assessable',"$planet owns both a kendra and a trikona from $lagna, but is missing from normalized D1 data.",[$planet]);continue;}$status=$this->isDebilitated($planet,$x['sign'])?'Formed but weakened':'Formed';$reason="$planet owns both a kendra and a trikona from $lagna (houses ".implode(', ',$owned).').".($status==='Formed but weakened'?' Its natal dignity is debilitated.':'');$out[]=$this->result('Yogakaraka — '.$planet,'Yogakaraka',$status,$reason,[$planet]);}
        return$out;
    }

    private function viparita(array $lords,array $byName):array{$found=[];foreach(self::DUSTHANA as $h){$lord=$lords[$h]['lord']??null;if($lord&&isset($byName[$lord])&&in_array($byName[$lord]['house'],self::DUSTHANA,true)&&$byName[$lord]['house']!==$h)$found[]="$lord: H$h lord in H".$byName[$lord]['house'];}return$found?['status'=>'Formed','reason'=>'Dusthana lord(s) occupy another dusthana: '.implode('; ',$found),'planets'=>array_values(array_unique(array_map(fn($x)=>preg_replace('/:.*/','',$x),$found)))]:['status'=>'Not formed','reason'=>'No 6th/8th/12th lord is placed in another dusthana in the normalized D1 chart.','planets'=>[]];}

    private function neechaBhanga(array $byName,?array $d9):array{$out=[];foreach(self::DEBILITATION as $p=>$deb){$x=$byName[$p]??null;if(!$x||$x['sign']!==$deb)continue;$conditions=[];$debLord=self::LORDS[$deb]??null;$exalt=null;foreach(self::EXALTATION as $q=>$s)if($s===$deb){$exalt=$q;break;}if($debLord&&isset($byName[$debLord])&&in_array($byName[$debLord]['house'],self::KENDRA,true))$conditions[]="$debLord, lord of $deb, is in a kendra";if($exalt&&isset($byName[$exalt])&&in_array($byName[$exalt]['house'],self::KENDRA,true))$conditions[]="$exalt, exalted in $deb, is in a kendra";$d=$this->findD9($d9,$p);if($d&&($d['d9_sign']??null)===(self::EXALTATION[$p]??null))$conditions[]="$p is exalted in D9";$out[]=$this->result('Neecha Bhanga — '.$p,'Debilitation',$conditions?'Potentially cancelled':'Debilitated; no implemented cancellation condition matched',$conditions?'Debilitation in '.$deb.'. Conditions matched: '.implode('; ',$conditions):'Debilitated in '.$deb.'.',[$p]);}return$out;}

    private function connected(array $a,array $b):bool{if($a['house']===$b['house'])return true;if($this->planetAspects((string)$a['name'],(int)$a['house'],(int)$b['house']))return true;if($this->planetAspects((string)$b['name'],(int)$b['house'],(int)$a['house']))return true;return $this->lordsExchange($a,$b);}
    private function planetAspects(string $planet,int $from,int $to):bool{$d=$this->houseDistance($from,$to);$targets=[7];if($planet==='Mars')$targets=[4,7,8];elseif($planet==='Jupiter')$targets=[5,7,9];elseif($planet==='Saturn')$targets=[3,7,10];return in_array($d,$targets,true);}
    private function lordsExchange(array $a,array $b):bool{return(self::LORDS[$a['sign']]??null)===$b['name']&&(self::LORDS[$b['sign']]??null)===$a['name'];}
    private function isCombust(array $p,array $all):bool{$v=$p['combust']??null;if($v===true||strtolower((string)$v)==='true'||(string)$v==='1')return true;if($v===false||strtolower((string)$v)==='false'||(string)$v==='0')return false;$s=$all['Sun']??null;if(!$s||$p['name']==='Sun'||$p['sign']!==$s['sign']||!is_numeric($p['degree'])||!is_numeric($s['degree']))return false;return abs($p['degree']-$s['degree'])<=8.0;}
    private function houseDistance(int $from,int $to):int{return(($to-$from+12)%12)+1;}
    private function isOwn(string $p,string $s):bool{return in_array($s,self::OWN[$p]??[],true);}
    private function isExalted(string $p,string $s):bool{return(self::EXALTATION[$p]??null)===$s;}
    private function isDebilitated(string $p,string $s):bool{return(self::DEBILITATION[$p]??null)===$s;}
    private function houseLords(?string $lagna):array{if(!$lagna)return[];$n=$this->signNumber($lagna);$o=[];for($h=1;$h<=12;$h++){$s=self::SIGNS[($n-1+$h-1)%12];$o[$h]=['house'=>$h,'sign'=>$s,'lord'=>self::LORDS[$s]];}return$o;}
    private function findD9(?array $d9,string $name):?array{if(!is_array($d9))return null;foreach(($d9['positions']??[]) as $r)if(is_array($r)&&strcasecmp((string)($r['name']??''),$name)===0)return$r;return null;}
    private function planetName(array $p):string{$n=trim((string)($p['name']??$p['full_name']??''));$a=['sun'=>'Sun','moon'=>'Moon','mars'=>'Mars','mercury'=>'Mercury','jupiter'=>'Jupiter','venus'=>'Venus','saturn'=>'Saturn','rahu'=>'Rahu','ketu'=>'Ketu'];return$a[strtolower($n)]??$n;}
    private function normalizeSign(?string $s):?string{foreach(self::SIGNS as $v)if(strcasecmp($v,trim((string)$s))===0)return$v;return null;}
    private function signNumber(string $s):int{foreach(self::SIGNS as $i=>$v)if(strcasecmp($v,trim($s))===0)return$i+1;return 0;}
}
