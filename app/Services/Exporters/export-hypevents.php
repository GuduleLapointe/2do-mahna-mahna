<?php
/**
 * HYPEvents Exporter
 *
 * Export events to HYPEvents legacy format
 */
if (!IS_AGGR) {
	die("No direct calls, run main script aggregator.php instead." . PHP_EOL);
}

class HYPEvents_Exporter
{
	private $output_dir;

	public function __construct($output_dir)
	{
		$this->output_dir = $output_dir;
		$this->export();
	}

	private function deploy(string $src, string $dest): void
	{
		Console::detail("copy " . basename($dest) . " ← $src");
		if (copy($src, $dest)) {
			touch($dest, filemtime($src));
		} else {
			Console::error("Failed to copy $src → $dest", 1);
		}
	}

	public function export()
	{
		$this->deploy(
			APP_DIR . "/src/bundle/standalone/templates/events.lsl",
			$this->output_dir . "/events.lsl",
		);

		$output = LSL_BOARD_VERSION . "\n";

		Console::detail("build events.lsl2");
		$prev_day = "";
		$today = date("l F j");
		foreach (EventStorage::readEvents() as $event) {
			$name = $event->name;
			// make sure name is converted to utf8 if not already
			if (!mb_detect_encoding((string) $name, "UTF-8", true)) {
				$name = utf8_encode($name);
			}
			// Transliterating name to ASCII
			$name = Aggregator::remove_emoji($name);
			$name = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $name);
			if (empty($name)) {
				continue;
			}

			// calculate end datetime
			$end_stamp = strtotime($event->dateUTC) + $event->duration * 60;
			if ($end_stamp < time()) {
				continue;
			}

			$begin = new DateTime($event->dateUTC, new DateTimeZone("UTC"));
			$begin->setTimezone(new DateTimeZone("America/Los_Angeles"));

			$end = new DateTime();
			$end->setTimestamp($end_stamp);
			$end->setTimezone(new DateTimeZone("America/Los_Angeles"));

			$time_parts = [
				$begin->format("h:iA"),
				$begin->format("Y-m-d"),
				$begin->getTimestamp(),
				$end->format("h:iA"),
				$end->format("Y-m-d"),
				$end->getTimestamp(),
			];

			$hgurl = $event->simname;

			$output .= "$name\n" . implode("~", $time_parts) . "\n$hgurl\n";
		}
		// echo "\n$output\n\n";

		$result = file_put_contents(
			$this->output_dir . "/events.lsl2",
			$output,
		);
		if ($result === false) {
			Console::error("Failed to write events.lsl2", 1, true);
		}
	}
}
