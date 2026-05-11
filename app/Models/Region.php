<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $table = "search_regions";
    protected $primaryKey = "regionUUID";
    public $incrementing = false;
    public $timestamps = true;

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
