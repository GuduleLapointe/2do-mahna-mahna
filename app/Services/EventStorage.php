<?php
/**
 * EventStorage — SearchDB read/write layer for events.
 *
 * The search DB (OpenSimSearch events table) is the single source of truth.
 * EventStorage::write() persists the freshly-fetched events; all exporters
 * and API endpoints read via EventStorage::readEvents().
 */
if (!TODO_APP) {
	die("No direct calls, run main script aggregator.php instead." . PHP_EOL);
}

class EventStorage
{
	/**
	 * Replace the entire events table with the current aggregated set.
	 *
	 * @param  Event[] $events  In-memory events produced by Fetcher.
	 * @return int              Number of events successfully written.
	 */
	public static function write(array $events): int
	{
		global $SearchDB;
		$db = $SearchDB;
		if (!$db) {
			Console::error("EventStorage::write — SearchDB not connected");
			return 0;
		}

		$table = SEARCH_TABLE_EVENTS;
		$notbefore = time() - 3600;

		$db->exec("DELETE FROM `$table`");

		$count = 0;
		$errors = 0;

		foreach ($events as $event) {
			$start = strtotime($event->dateUTC);
			if ($start < $notbefore) {
				continue;
			}

			// Prefix description with teleport links (matches eventsparser.php convention)
			$links = implode(
				"\n",
				array_filter([
					$event->teleport["HOP"] ?? "",
					$event->teleport["HG"] ?? "",
				]),
			);
			$description = $links
				? "$links\n\n{$event->description}"
				: $event->description;

			// Normalise UTF-8 encoding: some sources declare UTF-8 but send
			// latin1-encoded data. If the string is already valid UTF-8 it is left
			// unchanged; otherwise it is re-interpreted as ISO-8859-1 and converted.
			// (Replaces the deprecated utf8_encode/utf8_decode round-trip.)
			$name = self::fixEncoding(
				strip_tags(html_entity_decode($event->name)),
			);
			$description = self::fixEncoding(
				strip_tags(html_entity_decode($description)),
			);

			$fields = [
				"owneruuid" => $event->owner_uuid,
				"name" => $name,
				"creatoruuid" => $event->creatorUUID,
				"category" => $event->category,
				"description" => $description, // already normalised above
				"dateUTC" => $start,
				"duration" => $event->duration,
				"covercharge" => $event->covercharge,
				"coveramount" => $event->coverAmount,
				"simname" => $event->simName,
				"parcelUUID" => $event->parcelUUID,
				"globalPos" => $event->globalPos ?? implode(",", DEFAULT_POS),
				"eventflags" => $event->flags,
				"gatekeeperURL" => $event->gatekeeperURL,
				// 2DO-specific columns (added by SearchDB::extendSchema)
				"uid" => $event->uid,
				"tags" => json_encode($event->tags ?: []),
				"source" => $event->source,
			];

			if ($db->insert($table, $fields)) {
				$count++;
			} else {
				Console::error(
					"EventStorage: failed to insert \"{$event->name}\"",
				);
				$errors++;
			}
		}

		Console::detail(
			sprintf(
				"EventStorage: %d event%s written to %s%s",
				$count,
				$count === 1 ? "" : "s",
				$table,
				$errors ? ", $errors error(s)" : "",
			),
		);

		return $count;
	}

	/**
	 * Correct sources that declare UTF-8 but send latin1-encoded bytes.
	 *
	 * If the string is already valid UTF-8 it is returned unchanged.
	 * Otherwise it is re-interpreted as ISO-8859-1 and converted to UTF-8.
	 * This is the PHP 8.2-safe replacement for utf8_encode(utf8_decode($str)).
	 */
	private static function fixEncoding(string $str): string
	{
		if (mb_check_encoding($str, "UTF-8")) {
			return $str;
		}
		return mb_convert_encoding($str, "UTF-8", "ISO-8859-1");
	}

	/**
	 * Read events from the search DB as objects matching the Event interface.
	 *
	 * Computes hash and teleport links on the fly (both are derived from simname
	 * and dateUTC, which are stored). Returns an empty array if SearchDB is not
	 * connected.
	 *
	 * @param  int   $notbefore  Unix timestamp lower bound (default: 1 hour ago).
	 * @return object[]
	 */
	public static function readEvents(int $notbefore = 0): array
	{
		global $SearchDB;
		$db = $SearchDB;
		if (!$db) {
			return [];
		}

		if (!$notbefore) {
			$notbefore = time() - 3600;
		}

		$table = SEARCH_TABLE_EVENTS;
		$stmt = $db->prepare(
			"SELECT * FROM `$table` WHERE `dateUTC` >= :notbefore ORDER BY `dateUTC` ASC",
		);
		$stmt->execute([":notbefore" => $notbefore]);

		$events = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$obj = new stdClass();

			// Core fields (direct mapping)
			$obj->uid = $row["uid"] ?? null;
			$obj->name = $row["name"];
			$obj->description = $row["description"];
			$obj->simName = $row["simname"];
			$obj->duration = (int) $row["duration"];
			$obj->category = (int) $row["category"];
			$obj->owner_uuid = $row["owneruuid"];
			$obj->creatorUUID = $row["creatoruuid"];
			$obj->covercharge = (int) $row["covercharge"];
			$obj->coverAmount = (int) $row["coveramount"];
			$obj->parcelUUID = $row["parcelUUID"];
			$obj->globalPos = $row["globalPos"];
			$obj->flags = (int) $row["eventflags"];
			$obj->gatekeeperURL = $row["gatekeeperURL"];

			// 2DO-specific columns
			$obj->tags = json_decode($row["tags"] ?? "[]", true) ?: [];
			$obj->source = $row["source"] ?? null;

			// dateUTC: stored as Unix timestamp in DB, exposed as datetime string
			$obj->dateUTC = date("Y-m-d H:i:s", (int) $row["dateUTC"]);

			// Derived fields
			$obj->hash = md5($row["dateUTC"] . $row["simname"]);
			$obj->teleport = [
				"HOP" => opensim_format_tp($row["simname"], TPLINK_HOP),
				"HG" => opensim_format_tp($row["simname"], TPLINK_HG),
				"V3HG" => opensim_format_tp($row["simname"], TPLINK_V3HG),
			];

			$events[] = $obj;
		}

		return $events;
	}
}
