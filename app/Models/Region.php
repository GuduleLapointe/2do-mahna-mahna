<?php
/**
 * Region class
 *
 * Represents an OpenSim region.
 *
 * DO NOT REMOVE DOCUMENTATION BELOW
 *
 * Keep for reference (core principles are accurate but part of the logic
 * might be irrelevant with current approach):
 *
 * Parts of the data are collected at instantiation, aditional data
 * are actively fetched by parser and crawlers, querying both grid server
 * and region server, which is time-expansive.
 *
 * The constructor only parses the URL for validation and normalization
 * (no network I/O). Callers that need grid data or online status call
 * data() / online() explicitly — both are cached so repeated calls
 * within a run are cheap.
 *
 * Positions are always [x, y, z] arrays (matching OpenSim's vector type).
 * Convert to the appropriate string at call sites:
 *   "x/y/z" in URLs  →  implode('/', $pos)
 *   "x,y,z" in DB    →  implode(',', $pos)
 *   "<x,y,z>" in LSL →  '<' . implode(',', $pos) . '>'
 *
 * Cache TTLs:
 *   opensim_get_region       — 24 h  (grid coordinates rarely change)
 *   opensim_region_is_online —  1 h  (online status can flip between runs)
 *
 * OpenSimulator Region object
 * Positions are always [x, y, z] arrays (matching OpenSim's vector type).
 * Convert to the appropriate string at call sites:
 *   "x/y/z" in URLs  →  implode('/', $pos)
 *   "x,y,z" in DB    →  implode(',', $pos)
 *   "<x,y,z>" in LSL →  '<' . implode(',', $pos) . '>'
 *
 * Cache TTLs:
 *   opensim_get_region       — 24 h  (grid coordinates rarely change)
 *   opensim_region_is_online —  1 h  (online status can flip between runs)
 *
 * OpenSimSearch module SQL schema
 *      string  $regionName   Canonical region name (from grid data)
 *      string  $regionUUID   Region UUID
 *      string  $regionHandle Legacy 64-bit grid coordinate handle
 *      string  $url          Renamed as $dest_uri for disambiguation
 *      string  $owner        Owner display name
 *      string  $ownerUUID    Owner UUID
 *
 * opensim-helpers extended SQL schema?
 *      string  $gatekeeperURL  Gatekeeper base URL ("http://host:port")
 *
 * From get_region and region_link XMLRPC responses
 *      string  $host         Grid server hostname
 *      int     $port         Grid server port
 *      string  $region       Region name (from URL; updated to canonical after data())
 *      string  $uri          Canonical region URI "host:port/region" (no pos, host lowercased);
 *                                 empty string when URL is not parseable
 *      string  $dest_uri     Normalized destination URL (gatekeeperURL:region[/pos]), set from constructor args
 *      float[] $globalPos    Absolute map position [x, y, z] = grid origin + $pos;
 *                                 null until data() has been called
 *
 * From class instantiation
 *      float[] $pos          Local teleport position [x, y, z] from source URL;
 *                                 empty array when not specified
 *      string  $link_region_data   data returned by OpenSim link_region call
 *      string  $get_region_data   data returned by OpenSim get_region call
 *      array   $data         Cached region data
 *      string  $imageURL     Region image URL (from get_region response)
 *
 * Actual OpenSimulator internal regions table SQL schema
 * (OpenSimSearch module differs for historical reasons)
 *
 * Field	Type	Null	Key	Default	Extra
 * uuid	varchar(36)	NO	PRI	NULL
 * regionHandle	bigint(20) unsigned	NO	MUL	NULL
 * regionName	varchar(128)	YES	MUL	NULL
 * regionRecvKey	varchar(128)	YES		NULL
 * regionSendKey	varchar(128)	YES		NULL
 * regionSecret	varchar(128)	YES		NULL
 * regionDataURI	varchar(255)	YES		NULL
 * serverIP	varchar(64)	YES		NULL
 * serverPort	int(10) unsigned	YES		NULL
 * serverURI	varchar(255)	YES		NULL
 * locX	int(10) unsigned	YES		NULL
 * locY	int(10) unsigned	YES		NULL
 * locZ	int(10) unsigned	YES		NULL
 * eastOverrideHandle	bigint(20) unsigned	YES	MUL	NULL
 * westOverrideHandle	bigint(20) unsigned	YES		NULL
 * southOverrideHandle	bigint(20) unsigned	YES		NULL
 * northOverrideHandle	bigint(20) unsigned	YES		NULL
 * regionAssetURI	varchar(255)	YES		NULL
 * regionAssetRecvKey	varchar(128)	YES		NULL
 * regionAssetSendKey	varchar(128)	YES		NULL
 * regionUserURI	varchar(255)	YES		NULL
 * regionUserRecvKey	varchar(128)	YES		NULL
 * regionUserSendKey	varchar(128)	YES		NULL
 * regionMapTexture	varchar(36)	YES		NULL
 * serverHttpPort	int(10)	YES		NULL
 * serverRemotingPort	int(10)	YES		NULL
 * owner_uuid	varchar(36)	NO		00000000-0000-0000-0000-000000000000
 * originUUID	varchar(36)	YES		NULL
 * access	int(10) unsigned	YES		1
 * ScopeID	char(36)	NO	MUL	00000000-0000-0000-0000-000000000000
 * sizeX	int(11)	NO		0
 * sizeY	int(11)	NO		0
 * flags	int(11)	NO	MUL	0
 * last_seen	int(11)	NO		0
 * PrincipalID	char(36)	NO		00000000-0000-0000-0000-000000000000
 * Token	varchar(255)	NO		NULL
 * parcelMapTexture	varchar(36)	YES		NULL
 *
 * Keep for reference: OpenSim get_info output XML
 *
 * link_region: Array (
 *    [size_y] => 256
 *    [external_name] => http://yourgrid.org:8002/ Welcome
 *    [uuid] => 60d0e3f3-4802-49ad-90d5-ed7b601cc6d9
 *    [region_image] => http://192.168.0.9:9045/index.php?method=regionImage60d0e3f3480249ad90d5ed7b601cc6d9
 *    [handle] => 8796093024256256
 *    [result] => True
 *    [size_x] => 256
 * )
 *
 * get_region: Array (
 *    [server_uri] => http://192.168.0.9:9045/
 *    [region_name] => Welcome
 *    [http_port] => 9045
 *    [internal_port] => 9046
 *    [hostname] => 192.168.0.9
 *    [uuid] => 60d0e3f3-4802-49ad-90d5-ed7b601cc6d9
 *    [x] => 2048000
 *    [y] => 2048256
 *    [size_y] => 256
 *    [result] => true
 *    [size_x] => 256
 * )
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Region Model - Represents an OpenSim region
 * Clean Laravel implementation aligned with OpenSimSearch module format
 */
class Region extends Model
{
    // Region flags
    // http://opensimulator.org/wiki/Regions_(database_table)
    const REGION_DEFAULT_REGION = 1; // 2^0 - Default region for new avatars. Region is randomly selected if multiple regions have fallback flag set.
    const REGION_FALLBACK_REGION = 2; // 2^1 - Regions we redirect to when the destination is down
    const REGION_ONLINE = 4; // 2^2 - Set when a region comes online, unset when it unregisters and DeleteOnUnregister is false
    const REGION_NO_DIRECT_LOGIN = 8; // 2^3 - Region unavailable for direct logins (by name)
    const REGION_PERSISTENT = 16; // 2^4 - Don't remove on unregister
    const REGION_LOCKED_OUT = 32; // 2^5 - Don't allow registration
    const REGION_NO_MOVE = 64; // 2^6 - Don't allow moving this region
    const REGION_RESERVATION = 128; // 2^7 - This is an inactive reservation
    const REGION_AUTHENTICATE = 256; // 2^8 - Require authentication
    const REGION_HYPERLINK = 512; // 2^9 - Record represents a HG link
    const REGION_DEFAULT_HGREGION = 1024; // 2^10 - Record represents a default region for hypergrid teleports only.

    const REGION_PG = 13; // 0b001101 - Region is set to PG
    const REGION_MODERATE = 21; // 0b010101 - Region is set to Moderate
    const REGION_ADULT = 42; // 0b101010 - Region is set to Adult

    protected $fillable = [
        "uuid", // OpenSim UUID - primary identifier
        "uri", // Reconstructible URI (host:port/region_name)
        "name", // Display name
        "owner_uuid", // Owner UUID
        "server_url", // Used to fetch region info (url in legacy helpers, serverURI in OpenSIm)
        "flags", // Bitwise region flags
        "access", // (int) {REGION_PG|REGION_MODERATE|REGION_ADULT} - NOT a bitwise

        ##  DO NOT REMOVE DOCUMENTATION BELOW

        // Useless in our context
        // "size", // Useless int[x=256,y=256] - Not implemented, derived from Robust.regions.sixeX and Robust.regions.sixeY
        // "globalPos", // Useless - [x,y,z=NULL] - Not implemented, derived from Robust.regions.locX, Robust.regions.locY and Robust.regions.locZ
        // "handle", // Useless - OpenSim grid coordinate handle (LocX*256*65536)+(LocY*256)
        // "hostname", // Useless, included in server_url
        // "http_port", // Useless, included in server_url
        // "internal_port", // Useless, no external access
        // "region_map_texture", // Texture for the map as displayed in the client minimap

        // Unknown usage - In OpenSim schema, but even dev team does not know the purpose
        // "token", // Unknown usage
        // "scope_id", // Unknown usage
        // "principal_id", // Unknown usage
        // "parcel_map_texture", // Unknown usage
        // "last_seen", // Unknown usage
    ];

    /**
     * Bitwise flag accessor methods
     */
    public function isOnline(): bool
    {
        return $this->flags & Region::REGION_ONLINE;
    }

    public function allowsDirectLogin(): bool
    {
        return $this->flags & ~Region::REGION_NO_DIRECT_LOGIN;
    }

    public function isHyperlink(): bool
    {
        return $this->flags & Region::REGION_HYPERLINK;
    }

    /**
     * Rating accessor
     */
    public function rating(): string
    {
        if ($this->isAdult()) {
            return "Adult";
        } elseif ($this->isModerate()) {
            return "Moderate";
        } else {
            return "PG";
        }
    }

    public function isPG(): bool
    {
        return $this->access == Region::REGION_PG;
    }

    public function isModerate(): bool
    {
        return $this->access == Region::REGION_MODERATE;
    }

    public function isAdult(): bool
    {
        return $this->access == Region::REGION_ADULT;
    }

    /**
     * Gatekeeper URL accessor
     *
     * TODO: Don't care for now, fully tested and improved code exists somewhere else
     */
    public function gatekeeper(): string
    {
        return "Not implemented";
    }

    /**
     * Parcels Relationship
     * @return HasMany<Parcel,Region>
     */
    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class, "region_uuid", "uuid");
    }

    /**
     * Events Relationship
     *
     * @return HasMany<Event,Region>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, "sim_name", "name");
    }
}
