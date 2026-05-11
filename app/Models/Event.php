<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = "search_events";
    protected $primaryKey = "eventID";
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        "ownerUUID",
        "name",
        "creatorUUID",
        "category",
        "description",
        "dateUTC",
        "duration",
        "coverCharge",
        "coverAmount",
        "simName",
        "parcelUUID",
        "globalPos",
        "eventFlags",
        "gatekeeperURL",
        "landingPoint",
        "parcelName",
        "mature",
    ];
}
