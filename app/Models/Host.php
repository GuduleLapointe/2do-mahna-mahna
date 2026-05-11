<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Host extends Model
{
    protected $table = "search_hostsregister";
    protected $primaryKey = "hostURI";
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        "host",
        "port",
        "register",
        "nextCheck",
        "checked",
        "failCounter",
        "gatekeeperURL",
    ];
}
