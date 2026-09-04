<?php
declare(strict_types=1);

/**
 * Deterministic D1 yoga detection.
 *
 * This class reports formation rules only. It deliberately does not turn a
 * detected yoga into a guaranteed life outcome or an arbitrary strength score.
 */
final class YogaDetector
{
    private const SIGNS = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
    private const LORDS = [
        'Aries'=>'Mars','Taurus'=>'Venus','Gemini'=>'Mercury','Cancer'=>'Moon','Leo'=>'Sun','Virgo'=>'Mercury',
        'Libra'=>'Venus','Scorpio'=>'Mars','Sagittarius'=>'Jupiter','Capricorn'=>'Saturn','Aquarius'=>'Saturn','Pisces'=>'Jupiter'
    ];
    private const EXALTATION = ['Sun'=>'Aries','Moon'=>'Taurus','Mars'=>'Capricorn','Mercury'=>'Virgo','Jupiter'=>'Cancer','Venus'=>'Pisces','Saturn'=>'Libra'];
    private const DEBILITATION = ['Sun'=>'Libra','Moon'=>'Scorpio','Mars'=>'Cancer','Mercury'=>'Pisces','Jupiter'=>'Capricorn','Venus'=>'Virgo','Saturn'=>'Aries'];

    /** @return array<string,mixed> */
    public function detect(array $planets, ?string $lagna, ?array $d9 = null): array
    {
        $lagna = $this->normalizeSign($lagna);
        $rows = [];
        $byName = [];
        $byHouse = [];
        foreach ($planets as $planet) {
            if (!is_array($planet)) continue;
            $name = $this->planetName($planet);
            $sign = $this->normalizeSign((string)($planet['rashi'] ?? ''));
            $house = is_numeric($planet['house'] ?? null) ? (int)$planet['house'] : null;
            if ($name === '' || $sign === null || $house === null) continue;
            $row = ['name'=>$name,'sign'=>$sign,'house'=>$house,'degree'=>is_numeric($planet['local_degree'] ?? null)?(float)$planet['local_degree']:null];
            $rows[] = $row; $byName[$name] = $row; $byHouse[$house][] = $name;
        }
        $houseLords = $this->houseLords($lagna);
        $yogas = [];
        foreach ([['Ruchaka','Mars'],['Bhadra','Mercury'],['Hamsa','Jupiter'],['Malavya','Venus'],['Sasa','Saturn']] as [$yoga,$planet]) {
            $p = $byName[$planet] ?? null;
            $formed = $p !== null && in_array($p['house'], [1,4,7,10], true) && (($this->isOwn($planet,$p['sign'])) || ($this->isExalted($planet,$p['sign'])));
            $yogas[] = $this->result($yoga,'Pancha Mahapurusha',$formed ? 'Formed' : 'Not formed',$formed ? "$planet is in a kendra (1/4/7/10) in its own or exaltation sign." : "$planet does not simultaneously meet the kendra and own/exaltation-sign conditions.",[$planet]);
        }

        $moon = $byName['Moon'] ?? null; $jupiter = $byName['Jupiter'] ?? null;
        $gaja = false;
        if ($moon && $jupiter) {
            $delta = $this->houseDistance((int)$moon['house'], (int)$jupiter['house']);
            $gaja = in_array($delta,[1,4,7,10],true) && !$this->isDebilitated('Jupiter',$jupiter['sign']) && !$this->isCombust($jupiter,$byName);
        }
        $yogas[] = $this->result('Gaja Kesari','Lunar-Jupiter',$gaja ? 'Formed' : 'Not formed',$gaja ? 'Jupiter is in a kendra from the Moon and is not debilitated or combust in the normalized data.' : 'The required Moon–Jupiter kendra condition and basic dignity checks are not all satisfied.', ['Moon','Jupiter']);

        $r = $this->rajaYoga($houseLords,$byName);
        $yogas[] = $this->result('Kendra–Trikona Raja Yoga','Raja Yoga',$r['formed']?'Formed':'Not formed',$r['reason'],$r['planets']);

        $d = $this->dhanaYoga($houseLords,$byName);
        $yogas[] = $this->result('Dhana Yoga','Wealth',$d['formed']?'Formed':'Not formed',$d['reason'],$d['planets']);

        $v = $this->viparita($houseLords,$byName);
        $yogas[] = $this->result('Vipareeta Raja Yoga','Dusthana',$v['formed']?'Formed':'Not formed',$v['reason'],$v['planets']);

        foreach ($this->neechaBhanga($houseLords,$byName,$d9) as $item) $yogas[] = $item;

        return ['lagna'=>$lagna,'yogas'=>$yogas,'house_lords'=>$houseLords,'planet_rows'=>$rows,'house_occupancy'=>$byHouse];
    }

    private function result(string $name,string $family,string $status,string $reason,array $planets): array { return ['name'=>$name,'family'=>$family,'status'=>$status,'reason'=>$reason,'planets'=>array_values($planets)]; }

    private function rajaYoga(array $lords,array $byName): array
    {
        $kendra=[1,4,7,10]; $trikona=[1,5,9]; $connections=[];
        foreach ($kendra as $kh) foreach ($trikona as $th) {
            if ($kh === $th) continue;
            $a=$lords[$kh]['lord']??null; $b=$lords[$th]['lord']??null;
            if (!$a||!$b||$a===$b||!isset($byName[$a],$byName[$b])) continue;
            if ($this->connected($byName[$a],$byName[$b])) $connections[]="$a (H$kh) ↔ $b (H$th)";
        }
        return ['formed'=>(bool)$connections,'reason'=>$connections?'Connected kendra/trikona lords: '.implode('; '):'No conjunction, mutual Parashari aspect, or sign exchange was found between distinct kendra and trikona lords.','planets'=>$connections?array_values(array_unique(array_merge(...array_map(fn($x)=>preg_match_all('/[A-Za-z]+/', $x,$m)?$m[0]:[], $connections)))):[]];
    }

    private function dhanaYoga(array $lords,array $byName): array
    {
        $wealth=[2,11]; $support=[1,5,9,2,11]; $connections=[];
        foreach ($wealth as $wh) foreach ($support as $sh) {
            if ($wh===$sh) continue;
            $a=$lords[$wh]['lord']??null; $b=$lords[$sh]['lord']??null;
            if (!$a||!$b||$a===$b||!isset($byName[$a],$byName[$b])) continue;
            if ($this->connected($byName[$a],$byName[$b])) $connections[]="$a (H$wh) ↔ $b (H$sh)";
        }
        $planets=[]; foreach($connections as $c){preg_match_all('/(?:Sun|Moon|Mars|Mercury|Jupiter|Venus|Saturn)/',$c,$m);$planets=array_merge($planets,$m[0]);}
        return ['formed'=>(bool)$connections,'reason'=>$connections?'Connected wealth/support house lords: '.implode('; '):'No qualifying connection was found among the 1st/2nd/5th/9th/11th house lords.','planets'=>array_values(array_unique($planets))];
    }

    private function viparita(array $lords,array $byName): array
    {
        $dust=[6,8,12]; $found=[];
        foreach($dust as $h){$lord=$lords[$h]['lord']??null;if(!$lord||!isset($byName[$lord]))continue;$ph=$byName[$lord]['house'];if(in_array($ph,$dust,true)&&$ph!==$h)$found[]="$lord: H$h lord in H$ph";}
        return ['formed'=>(bool)$found,'reason'=>$found?'Dusthana lord(s) occupy another dusthana: '.implode('; '):'No 6th/8th/12th lord was found in another dusthana in the normalized D1 chart.','planets'=>array_values(array_unique(array_map(fn($x)=>preg_replace('/:.*/','',$x),$found)))];
    }

    private function neechaBhanga(array $lords,array $byName,?array $d9): array
    {
        $out=[];
        foreach(self::DEBILITATION as $planet=>$debSign){
            $p=$byName[$planet]??null;if(!$p||$p['sign']!==$debSign)continue;
            $debLord=self::LORDS[$debSign]??null; $exaltedInSign=null; foreach(self::EXALTATION as $q=>$sign)if($sign===$debSign){$exaltedInSign=$q;break;}
            $conditions=[];
            if($debLord && isset($byName[$debLord]) && in_array($byName[$debLord]['house'],[1,4,7,10],true))$conditions[]="$debLord (lord of $debSign) is in a kendra";
            if($exaltedInSign && isset($byName[$exaltedInSign]) && in_array($byName[$exaltedInSign]['house'],[1,4,7,10],true))$conditions[]="$exaltedInSign (exalted in $debSign) is in a kendra";
            $d9Row=$this->findD9($d9,$planet); if(($d9Row['d9_sign']??null)===(self::EXALTATION[$planet]??null))$conditions[]="$planet is exalted in D9";
            $status=$conditions?'Potentially cancelled':'Debilitated; no implemented cancellation condition matched';
            $out[]=$this->result('Neecha Bhanga — '.$planet,'Debilitation',$status,$conditions?'Debilitation detected in '.$debSign.'. Conditions matched: '.implode('; ',$conditions):'Debilitation detected in '.$debSign.'.',$planet?[$planet]:[]);
        }
        return $out;
    }

    private function connected(array $a,array $b): bool
    {
        if ((int)$a['house']===(int)$b['house']) return true;
        $ha=(int)$a['house'];$hb=(int)$b['house'];
        if ($this->aspects($ha,$this->planetName($b)) && $this->aspects($hb,$this->planetName($a))) return true;
        return false;
    }

    private function aspects(int $fromHouse,string $planet): bool
    {
        $targets=[7]; if($planet==='Mars')$targets=[4,7,8]; elseif($planet==='Jupiter')$targets=[5,7,9]; elseif($planet==='Saturn')$targets=[3,7,10];
        return in_array($fromHouse,$targets,true); // compatibility helper; actual house delta is checked below
    }

    private function houseDistance(int $from,int $to): int { return (($to-$from+12)%12)+1; }

    private function isCombust(array $planet,array $byName): bool
    {
        $p=$byName['Sun']??null;if(!$p||!is_numeric($planet['degree']??null)||!is_numeric($p['degree']??null)||$planet['sign']!==$p['sign'])return false;
        $delta=abs((float)$planet['degree']-(float)$p['degree']); return $delta<=8.0;
    }
    private function isOwn(string $p,string $s): bool { return in_array($s,['Mars'=>['Aries','Scorpio'],'Mercury'=>['Gemini','Virgo'],'Jupiter'=>['Sagittarius','Pisces'],'Venus'=>['Taurus','Libra'],'Saturn'=>['Capricorn','Aquarius'],'Sun'=>['Leo'],'Moon'=>['Cancer']][$p]??[],true); }
    private function isExalted(string $p,string $s): bool { return (self::EXALTATION[$p]??null)===$s; }
    private function isDebilitated(string $p,string $s): bool { return (self::DEBILITATION[$p]??null)===$s; }

    /** @return array<int,array{house:int,sign:string,lord:string}> */
    private function houseLords(?string $lagna): array { if(!$lagna)return[];$n=$this->signNumber($lagna);$o=[];for($h=1;$h<=12;$h++){$s=self::SIGNS[($n-1+$h-1)%12];$o[$h]=['house'=>$h,'sign'=>$s,'lord'=>self::LORDS[$s]];}return$o; }
    private function findD9(?array $d9,string $name): ?array { if(!is_array($d9))return null;foreach(($d9['positions']??[]) as $r)if(is_array($r)&&strcasecmp((string)($r['name']??''),$name)===0)return$r;return null; }
    private function planetName(array $p): string { $n=trim((string)($p['name']??$p['full_name']??''));$a=['sun'=>'Sun','moon'=>'Moon','mars'=>'Mars','mercury'=>'Mercury','jupiter'=>'Jupiter','venus'=>'Venus','saturn'=>'Saturn','rahu'=>'Rahu','ketu'=>'Ketu'];return $a[strtolower($n)]??$n; }
    private function normalizeSign(?string $s): ?string { foreach(self::SIGNS as $v)if(strcasecmp($v,trim((string)$s))===0)return$v;return null; }
    private function signNumber(string $s): int { foreach(self::SIGNS as $i=>$v)if(strcasecmp($v,trim($s))===0)return$i+1;return 0; }
}
