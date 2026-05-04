<?php
/**
 * iCal Exporter
 *
 * Export events to iCal format
 *
 * @package    2do-aggregator
 * @subpackage 2do-aggregator/exporters
 */
if (!TODO_APP) {
	die("No direct calls, run main script aggregator.php instead." . PHP_EOL);
}

require_once APP_DIR . "/vendor/autoload.php";
require_once APP_DIR . "/lib/opensim-functions.php";

use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

class iCal_Exporter
{
	private $output_dir;

	public function __construct($output_dir)
	{
		$this->output_dir = $output_dir;
		$this->export();
	}

	public function export()
	{
		Console::detail("build events.ics");
		$vcalendar = Vcalendar::factory();
		$vcalendar->setConfig(["unique_id" => "2do-aggregator"]);
		$vcalendar->setMethod("PUBLISH");
		$vcalendar->setXprop("X-WR-CALNAME", "2do Aggregator");
		$vcalendar->setXprop("X-WR-CALDESC", "Events from 2do Aggregator");
		$vcalendar->setXprop("X-WR-TIMEZONE", "America/Los_Angeles");

		foreach (EventStorage::readEvents() as $event) {
			if (empty($event->dateUTC)) {
				continue;
			}
			// calculate end datetime
			$end_stamp = strtotime($event->dateUTC) + $event->duration * 60;
			if ($end_stamp < time()) {
				continue;
			}
			$begin = new DateTime($event->dateUTC, new DateTimeZone("UTC"));
			// $begin->setTimezone(new DateTimeZone('America/Los_Angeles'));

			$end = new DateTime();
			$end->setTimestamp($end_stamp);
			// $end->setTimezone(new DateTimeZone('America/Los_Angeles'));

			$vevent = Vevent::factory();

			$uid = empty($event->uid) ? $event->hash : $event->uid;

			$vevent->setUid($event->uid);
			$vevent->setDtstart($begin);
			$vevent->setDtend($end);
			$vevent->setSummary($event->name);
			$vevent->setDescription($event->description);
			$vevent->setLocation($event->simname);
			$vevent->setUrl(opensim_format_tp($event->simname, TPLINK_HOP));
			$vevent->setCategories(join(",", array_filter($event->tags)));

			$vcalendar->setComponent($vevent);
		}

		$output = $vcalendar->createCalendar();

		$result = file_put_contents($this->output_dir . "/events.ics", $output);
		if ($result === false) {
			Console::error("Failed to write events.ics", 1, true);
		}
	}
}
