<?php
/**
 * JSON Exporter
 * 
 * Export events to JSON format
 */
if( ! IS_AGGR ) {
    die('No direct calls, run main script aggregator.php instead.' . PHP_EOL);
}

class JSON_Exporter {
    private $events = array();
    private $output_dir;

    public function __construct($events, $output_dir) {
        $this->events = $events;
        $this->output_dir = $output_dir;
        $this->export();
    }

    public function export() {
        $events_array = array();

        foreach ($this->events as $event) {
            $name = $event->name;
            // Transliterating name to ASCII
            // $name = preg_replace('/[\x{1F600}-\x{1F6FF}]/u', '', $event->name);
            $name = Aggregator::remove_emoji($name);
            // $name = iconv('UTF-8', 'ASCII//TRANSLIT', utf8_encode($name));
            if( empty($name) ) {
                continue;
            }

            // calculate end datetime
            $end_stamp = strtotime($event->dateUTC) + $event->duration * 60;
            if ( $end_stamp < time() ) {
                continue;
            }

            $begin = new DateTime($event->dateUTC, new DateTimeZone('UTC'));
            // $begin->setTimezone(new DateTimeZone('America/Los_Angeles'));
            
            // calculate set $end as DateTime based on $end_stamp
            $end = new DateTime("@$end_stamp", new DateTimeZone('UTC'));
            // $end->setTimestamp($end_stamp);
            // $end->setTimezone(new DateTimeZone('America/Los_Angeles'));

            // example destination format
            // [
            //     {
            //         "start": "2023-07-02T19:00:00+00:00",
            //         "end": "2023-07-02T21:00:00+00:00",
            //         "title": "cine chez Jo a 21h (Fr) / 15h (Qc)",
            //         "description": "&lt;span&gt;Bonsoir a tous, comme tous les dimanches, cine chez Jo à 21h (Fr) / 15h (Qc).&lt;/span&gt;&lt;br&gt;&lt;span&gt;&lt;span&gt;Rendez-vous ici&lt;/span&gt;&lt;span&gt;: &lt;a&gt;hop://qcgrid.mooo.com:8002&lt;/a&gt;&lt;/span&gt; &lt;/span&gt;",
            //         "hgurl": "hop://qcgrid.mooo.com:8002",
            //         "hash": "c9d9320b0e3257d48b70ae91d84708a4",
            //         "categories": [
            //             "perfectlife"
            //         ]
            //     },
            // ]

            $events_array[] = array(
                'start' => $begin->format('c'),
                'end' => $end->format('c'),
                'title' => $name,
                'description' => $event->description,
                'hgurl' => $event->simname,
                'hash' => $event->hash,
                'categories' => $event->category,
                'tags' => $event->tags,
                'source' => $event->source,
                'teleport' => $event->teleport,
            );
        }

        // $output = json_encode($events_array, JSON_PRETTY_PRINT);
        $output = json_encode($events_array);

        $result = file_put_contents($this->output_dir . '/events.json', $output);
        if( $result != false ) {
            Aggregator::notice("exported " . $this->output_dir . '/events.json');
        } else {
            Aggregator::admin_notice("Error writing " . $this->output_dir . '/events.json', 1, true);
        }
    }
}
