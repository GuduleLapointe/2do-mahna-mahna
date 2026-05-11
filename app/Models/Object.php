<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Object extends Model
{
    protected $table = "search_objects";
    protected $primaryKey = "objectUUID";
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        "parcelUUID",
        "location",
        "name",
        "description",
        "regionUUID",
        "gatekeeperURL",
    ];

    public function parcel()
    {
        return $this->belongsTo(Parcel::class, "parcelUUID", "parcelUUID");
    }
}
