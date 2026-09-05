<?php
declare(strict_types=1);

final class KundliRepository
{
    public function findLatest(int $userId,int $profileId):?array{$s=db()->prepare('SELECT * FROM kundli_calculations WHERE user_id=? AND birth_profile_id=? ORDER BY id DESC LIMIT 1');$s->bind_param('ii',$userId,$profileId);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();return $r?:null;}
    public function findByHash(int $userId,int $profileId,string $hash):?array{$s=db()->prepare('SELECT * FROM kundli_calculations WHERE user_id=? AND birth_profile_id=? AND calculation_hash=? LIMIT 1');$s->bind_param('iis',$userId,$profileId,$hash);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();return $r?:null;}
    public function decode(array $c):array{$decode=static function($v,$fallback){$x=json_decode((string)($v??''),true);return is_array($x)?$x:$fallback;};return['planetary'=>$decode($c['planetary_data']??null,[]),'houses'=>$decode($c['house_data']??null,[]),'dasha'=>json_decode((string)($c['dasha_data']??'null'),true),'chart'=>$decode($c['chart_data']??null,[])];}
    public static function hashForProfile(array $profile,string $engineVersion,string $apiVersion):string{
        // Engine changes are local. They must not invalidate the paid source-data cache.
        return hash('sha256',implode('|',[(int)($profile['id']??0),(new DateTime((string)$profile['date_of_birth']))->format('d/m/Y'),substr((string)($profile['time_of_birth']??''),0,5),(float)($profile['latitude']??0),(float)($profile['longitude']??0),(float)($profile['timezone']??0),$apiVersion]));
    }
}
