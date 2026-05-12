<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $table = "search_regions";
    protected $primaryKey = "regionUUID";
    public $incrementing = false;

    // Region flags
    // http://opensimulator.org/wiki/Regions_(database_table)
    const REGION_DEFAULT_REGION = 1; // 2^0 - Default region for new avatars. Region is randomly selected if multiple regions have fallback flag set.
    const REGION_FALLBACK_REGION = 2; // 2^1 - Regions we redirect to when the destination is down
    const REGION_REGION_ONLINE = 4; // 2^2 - Set when a region comes online, unset when it unregisters and DeleteOnUnregister is false
    const REGION_NO_DIRECT_LOGIN = 8; // 2^3 - Region unavailable for direct logins (by name)
    const REGION_PERSISTENT = 16; // 2^4 - Don't remove on unregister
    const REGION_LOCKED_OUT = 32; // 2^5 - Don't allow registration
    const REGION_NO_MOVE = 64; // 2^6 - Don't allow moving this region
    const REGION_RESERVATION = 128; // 2^7 - This is an inactive reservation
    const REGION_AUTHENTICATE = 256; // 2^8 - Require authentication
    const REGION_HYPERLINK = 512; // 2^9 - Record represents a HG link
    const REGION_DEFAULT_HGREGION = 1024; // 2^10 - Record represents a default region for hypergrid teleports only.

    protected $fillable = [
        "regionName",
        "regionHandle",
        "url",
        "owner",
        "ownerUUID",
        "gatekeeperURL",
    ];

    public function parcels()
    {
        return $this->hasMany(Parcel::class, "regionUUID", "regionUUID");
    }

    public function events()
    {
        return $this->hasMany(Event::class, "simName", "regionName");
    }
}
