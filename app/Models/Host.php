<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Host extends Model
{
    protected $table = "search_hostsregister";
    protected $primaryKey = "hostURI";
    public $incrementing = false;

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
