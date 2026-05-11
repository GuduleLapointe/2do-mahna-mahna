<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parcel extends Model
{
    protected $table = "search_parcels";
    protected $primaryKey = "parcelUUID";
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        "regionUUID",
        "parcelName",
        "landingPoint",
        "description",
        "searchCategory",
        "build",
        "script",
        "public",
        "dwell",
        "infoUUID",
        "mature",
        "gatekeeperURL",
        "imageUUID",
    ];

    public function region()
    {
        return $this->belongsTo(Region::class, "regionUUID", "regionUUID");
    }

    public function objects()
    {
        return $this->hasMany(Object::class, "parcelUUID", "parcelUUID");
    }

    public function classifieds()
    {
        return $this->hasMany(Classified::class, "parcelUUID", "parcelUUID");
    }
}
