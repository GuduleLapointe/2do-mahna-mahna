<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Parcel Model - Represents a Land parcel in OpenSim
 * Note: In OpenSim terminology, "Parcel" = "Land"
 * Clean Laravel implementation best compromise between OpenSim, OpenSimSearch module and Helpers format
 */
class Parcel extends Model
{
    protected $fillable = [
        "uuid", // OpenSim UUID - primary identifier
        "uri", // Reconstructible URI (n/a for parcels)
        "name", // Display name
        "region_uuid", // Foreign key to region
        "owner_uuid",
        "group_uuid",
        "snapshot_uuid", // Parcel snapshot
        "info_uuid", // Helper-specific reference
        "local_land_id", // Land index in Region
        "description",
        "area", // Size in square meters
        "flags", // Bitwise flags (http://opensimulator.org/wiki/Land_(database_table)#LandFlags)
        "bitmap", // Serialized parcel map
        "category", // Search category
        "landing_type", // 0 = blocked, 1 = landing poit set, 2 = landing allowed everywhere
        "landing_point", // float[] [x,y,z]
        "sale_price",
        "status", // Lease status
        // "local_land_idx", // Land index in Region (unused, OpenSim internals)
    ];

    // OpenSim Land Management flags (complete set from OpenSimulator documentation)
    // Reference:   http://opensimulator.org/wiki/Land_(database_table)
    //              https://wiki.secondlife.com/wiki/LlGetParcelFlags
    const PARCEL_FLAG_ALLOW_FLY = 1; // 2^0 - Allow avatars to fly (a client-side only restriction)
    const PARCEL_FLAG_ALLOW_SCRIPTS = 2; // 2^1 - Allow foreign scripts to run
    const LAND_FLAG_FOR_SALE = 4; // 2^2 - This parcel is for sale
    const PARCEL_FLAG_ALLOW_LANDMARK = 8; // 2^3 - Allow avatars to create a landmark on this parcel
    const PARCEL_FLAG_ALLOW_TERRAFORM = 16; // 2^4 - Allows all avatars to edit the terrain on this parcel
    const PARCEL_FLAG_ALLOW_DAMAGE = 32; // 2^5 - Avatars have health and can take damage on this parcel. If set, avatars can be killed and sent home here
    const PARCEL_FLAG_ALLOW_CREATE_OBJECTS = 64; // 2^6 - Foreign avatars can create objects here
    const LAND_FLAG_FOR_SALE_OBJECTS = 128; // 2^7 - All objects on this parcel can be purchased
    const PARCEL_FLAG_USE_ACCESS_GROUP = 256; // 2^8 - Access is restricted to a group
    const PARCEL_FLAG_USE_ACCESS_LIST = 512; // 2^9 - Access is restricted to a whitelist
    const PARCEL_FLAG_USE_BAN_LIST = 1024; // 2^10 - Ban blacklist is enabled
    const PARCEL_FLAG_USE_LAND_FLAG_PASS_LIST = 2048; // 2^11 - Unknown
    const LAND_FLAG_SHOW_DIRECTORY = 4096; // 2^12 - List this parcel in the search directory
    const LAND_FLAG_ALLOW_DEED_TO_GROUP = 8192; // 2^13 - Allow personally owned parcels to be deeded to group
    const LAND_FLAG_CONTRIBUTE_WITH_DEED = 16384; // 2^14 - If Deeded, owner contributes required tier to group parcel is deeded to
    const PARCEL_FLAG_LOCAL_SOUND_ONLY = 32768; // 2^15 - Restrict sounds originating on this parcel to the parcel boundaries
    const LAND_FLAG_SELL_PARCEL_OBJECTS = 65536; // 2^16 - Objects on this parcel are sold when the land is purchsaed
    const LAND_FLAG_ALLOW_PUBLISH = 131072; // 2^17 - Allow this parcel to be published on the web
    const LAND_FLAG_MATURE_PUBLISH = 262144; // 2^18 - The information for this parcel is mature content
    const LAND_FLAG_URL_WEB_PAGE = 524288; // 2^19 - The media URL is an HTML page
    const LAND_FLAG_URL_RAW_HTML = 1048576; // 2^20 - The media URL is a raw HTML string
    const PARCEL_FLAG_RESTRICT_PUSHOBJECT = 2097152; // 2^21 - Restrict foreign object pushes
    const LAND_FLAG_DENY_ANONYMOUS = 4194304; // 2^22 - Ban all non identified/transacted avatars
    const LAND_FLAG_LINDEN_HOME = 8388608; // 2^23 - No comment in documentation or this one!
    const PARCEL_FLAG_ALLOW_GROUP_SCRIPTS = 33554432; // 2^25 - Allow group-owned scripts to run
    const PARCEL_FLAG_ALLOW_CREATE_GROUP_OBJECTS = 67108864; // 2^26 - Allow object creation by group members or group objects
    const PARCEL_FLAG_ALLOW_ALL_OBJECT_ENTRY = 134217728; // 2^27 - Allow all objects to enter this parcel
    const PARCEL_FLAG_ALLOW_GROUP_OBJECT_ENTRY = 268435456; // 2^28 - Only allow group and owner objects to enter this parcel
    const LAND_FLAG_ALLOW_VOICE_CHAT = 536870912; // 2^29 - Voice Enabled on this parcel
    const LAND_FLAG_USE_ESTATE_VOICE_CHAN = 1073741824; // 2^30 - Use Estate Voice channel for Voice on this parcel. This correspond to unchecking "Restrict voice to this parcel" in the viewer.
    const LAND_FLAG_DENY_AGE_UNVERIFIED = 2147483648; // 2^31 - Deny Age Unverified Users

    /**
     * Bitwise flag accessor methods
     */
    public function forSale(): bool
    {
        return $this->flags & Parcel::LAND_FLAG_FOR_SALE;
    }

    public function hasPicture(): bool
    {
        // TODO: use helpers opensim_isuuid which handles both format and emptyness
        return $this->snapshot_uuid &&
            $this->snapshot_uuid !== "00000000-0000-0000-0000-000000000000";
    }

    public function allowsBuilding(): bool
    {
        return $this->flags & Parcel::PARCEL_FLAG_ALLOW_CREATE_OBJECTS;
    }

    public function allowsScripts(): bool
    {
        return $this->flags & Parcel::PARCEL_FLAG_ALLOW_SCRIPTS;
    }

    public function isPublic(): bool
    {
        return $this->landing_type > 0;
    }

    /**
     * Rating accessor
     *
     * @return string   Adult|Mature|PG
     */
    public function rating(): string
    {
        if ($this->isAdult()) {
            return "Adult";
        }
        if ($this->isMature()) {
            return "Mature";
        }
        return "PG";
    }

    /**
     * Gatekeeper URL accessor - delegates to region
     */
    public function gatekeeper(): string
    {
        return $this->region->gatekeeper();
    }

    /**
     * Landing point accessor
     *
     * @return array float[x,y,z] coordinates within the region (float, never negative, usually x<256 and y<256, z not limited)
     */
    public function landingPoint(): array
    {
        return $this->landing_point;
    }

    /**
     * Convenience methods
     */
    public function isPG(): bool
    {
        // PG if neither Mature nor Adult flags are set
        return $this->flags &
            ~Parcel::LAND_FLAG_MATURE_PUBLISH &
            ~Parcel::LAND_FLAG_DENY_AGE_UNVERIFIED;
    }

    public function isMature(): bool
    {
        return $this->flags & Parcel::LAND_FLAG_MATURE_PUBLISH;
    }

    public function isAdult(): bool
    {
        return $this->flags & Parcel::LAND_FLAG_DENY_AGE_UNVERIFIED;
    }

    /**
     * Relationships
     * @return BelongsTo<Region,Parcel>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(
            Region::class,
            "region_uuid",
            "uuid",
        )->withDefault([
            "name" => __("Unknown Region"),
        ]);
    }
    /**
     * @return HasMany<Object,Parcel>
     */
    public function objects(): HasMany
    {
        return $this->hasMany(Object::class, "parcel_uuid", "uuid");
    }
    /**
     * @return HasMany<Classified,Parcel>
     */
    public function classifieds(): HasMany
    {
        return $this->hasMany(Classified::class, "parcel_uuid", "uuid");
    }
    /**
     * @return HasMany<Event,Parcel>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, "parcel_uuid", "uuid");
    }
}
