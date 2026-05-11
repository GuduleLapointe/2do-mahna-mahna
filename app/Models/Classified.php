<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classified extends Model
{
    protected $table = "search_classifieds";
    protected $primaryKey = "classifiedUUID";
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        "creatorUUID",
        "creationDate",
        "expirationDate",
        "category",
        "name",
        "description",
        "parcelUUID",
        "parentEstate",
        "snapshotUUID",
        "simName",
        "posGlobal",
        "parcelName",
        "classifiedFlags",
        "priceForListing",
    ];

    public function parcel()
    {
        return $this->belongsTo(Parcel::class, "parcelUUID", "parcelUUID");
    }
}
