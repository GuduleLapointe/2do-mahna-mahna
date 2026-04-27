<?php
/**
 * HTML Exporter
 * 
 * Generate mobile-first responsive html page, css and js, with events taken dynamically from events.json
 */
if( ! IS_AGGR ) {
    die('No direct calls, run main script aggregator.php instead.' . PHP_EOL);
}

require_once APP_DIR . '/vendor/autoload.php';
use MatthiasMullie\Minify;

class HTML_Exporter {
    private $events = array();
    private $output_dir;

    public function __construct($events, $output_dir) {
        $this->events = $events;
        $this->output_dir = $output_dir;
        $this->export();
    }

    public function export() {
        // Minify CSS and JS directly into output
        $css = new Minify\CSS(APP_DIR . '/src/bundle/standalone/css/styles.css');
        $css->minify($this->output_dir . '/styles.min.css');

        $js = new Minify\JS(APP_DIR . '/src/bundle/standalone/js/script.js');
        $js->minify($this->output_dir . '/script.min.js');

        // Fill sections in index.html

        $Parsedown = new Parsedown();

        // Lire et convertir le contenu du README
        
        // Charger le modèle de la page HTML
        $page = file_get_contents(APP_DIR . '/src/bundle/standalone/templates/calendar.html');
        
        // Remplacer le contenu de la section 'readme' par le contenu du README
        // $page = str_replace('<section id="readme"></section>', '<section id="readme">' . $html . '</section>', $page);
        
        $md_files = array(
            'README.md',
            'CHANGELOG.md',
            'FAQ.md',
        );
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $page, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        foreach ($md_files as $md_file) {
            $sectionId = strtolower(basename($md_file, '.md'));
            $section = $dom->getElementById($sectionId);            
            
        if ($section) {

            $text = file_get_contents( APP_DIR . '/' . $md_file);
            $html = $Parsedown->text($text);
            // Créer un nouveau DOMDocument pour le contenu du README
            $domForContent = new DOMDocument();
            $domForContent->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            // Obtenir tous les éléments de niveau supérieur du contenu du README
            $body = $domForContent->getElementsByTagName('body')->item(0);

            // Créer un nouvel élément div avec la classe wrapper
            $wrapper = $dom->createElement('div');
            $wrapper->setAttribute('class', 'wrapper');

            // Créer un nouvel élément div avec la classe content
            $content = $dom->createElement('div');
            $content->setAttribute('class', 'content');

            // Si body n'est pas null, importer chaque élément dans le DOM principal et l'ajouter au content
            if ($body !== null) {
                while ($body->hasChildNodes()) {
                    $child = $body->firstChild;
                    $importedNode = $dom->importNode($child, true);
                    $content->appendChild($importedNode);
                    $body->removeChild($child);
                }
            } else {
                // Si body est null, ajouter le contenu du README directement au content
                $fragment = $dom->createDocumentFragment();
                $fragment->appendXML($html);
                $content->appendChild($fragment);
            }

            // Ajouter le content au wrapper
            $wrapper->appendChild($content);

            // Ajouter le wrapper à la section
            $section->appendChild($wrapper);

            Aggregator::notice("section $sectionId updated with $md_file");
        } else {
            Aggregator::admin_notice("section $sectionId not found for $md_file", 1);
        }
        }

        // Inject boards.html into the boards section
        $boardsSection = $dom->getElementById('boards');
        if ($boardsSection) {
            $boardsHtml = file_get_contents(APP_DIR . '/src/bundle/standalone/templates/boards.html');
            $boardsDom  = new DOMDocument();
            libxml_use_internal_errors(true);
            $boardsDom->loadHTML('<?xml encoding="UTF-8">' . $boardsHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            // Move any <style> blocks from boards.html <head> into the main <head>
            $mainHead   = $dom->getElementsByTagName('head')->item(0);
            $boardsHead = $boardsDom->getElementsByTagName('head')->item(0);
            if ($boardsHead && $mainHead) {
                foreach (iterator_to_array($boardsHead->childNodes) as $child) {
                    if ($child->nodeName === 'style') {
                        $mainHead->appendChild($dom->importNode($child, true));
                    }
                }
            }

            // Inject body content wrapped the same way as markdown sections
            $boardsBody = $boardsDom->getElementsByTagName('body')->item(0);
            if ($boardsBody) {
                $wrapper = $dom->createElement('div');
                $wrapper->setAttribute('class', 'wrapper');
                $content = $dom->createElement('div');
                $content->setAttribute('class', 'content');
                while ($boardsBody->hasChildNodes()) {
                    $child = $boardsBody->firstChild;
                    $content->appendChild($dom->importNode($child, true));
                    $boardsBody->removeChild($child);
                }
                $wrapper->appendChild($content);
                $boardsSection->appendChild($wrapper);
            }
            Aggregator::notice("section boards updated with boards.html");
        } else {
            Aggregator::admin_notice("section boards not found", 1);
        }

        $page = $dom->saveHTML();
        
        // Ajouter la classe list-check aux éléments li qui contiennent [x] et remplacer [x] par une case à cocher cochée
        $page = preg_replace('/<li>\s*\[x\]/', '<li class="list-check"><input type="checkbox" checked disabled>', $page);

        // Ajouter la classe list-check aux éléments li qui contiennent [ ] et remplacer [ ] par une case à cocher non cochée
        $page = preg_replace('/<li>\s*\[\s\]/', '<li class="list-check"><input type="checkbox" disabled>', $page);

        $file = 'index.html';
        // Sauvegarder la page dans le répertoire de sortie
        $result = file_put_contents($this->output_dir . '/' . $file, $page);
        if($result !== false) Aggregator::notice("updated " . $this->output_dir . '/' . $file);
        else Aggregator::admin_notice("Error $result writing " . $this->output_dir . '/' . $file, 1, true);

        // Copy CSS and JS source files for dev/debug access
        foreach (['css/styles.css' => 'styles.css', 'js/script.js' => 'script.js'] as $src => $dest) {
            $result = copy(APP_DIR . '/src/bundle/standalone/' . $src, $this->output_dir . '/' . $dest);
            if ($result !== false) Aggregator::notice("updated " . $this->output_dir . '/' . $dest);
            else Aggregator::admin_notice("Error $result writing " . $this->output_dir . '/' . $dest, 1, true);
        }

        // Copy images from assets/images/ to public/
        $imageFiles = array_merge(
            glob(APP_DIR . '/assets/images/banner*.png') ?: [],
            [
                APP_DIR . '/assets/images/2do-logo.png',
                APP_DIR . '/assets/images/2do-logo-square.png',
            ]
        );
        foreach ($imageFiles as $src) {
            $dest = $this->output_dir . '/' . basename($src);
            $result = copy($src, $dest);
            if ($result !== false) Aggregator::notice("updated " . $dest);
            else Aggregator::admin_notice("Error $result writing " . $dest, 1, true);
        }
    }
}
