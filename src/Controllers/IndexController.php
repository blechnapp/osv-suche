<?php

namespace OSVSuche\Controllers;

use Plenty\Modules\Category\Contracts\CategoryRepositoryContract;
use Plenty\Modules\Plugin\Storage\Contracts\StorageRepositoryContract;
use Plenty\Modules\Webshop\ItemSearch\SearchPresets\VariationList;
use Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;

/**
 * Suchindex aller im Shop sichtbaren Varianten.
 *
 * Der Aufbau dauert gemessen ~45 s (05.10.2026, ~3.900 Varianten) und passt nicht in den
 * CachingRepository (Plugins: max. 512 Byte je Wert). Deshalb: Neuaufbau nur ueber
 * /rebuild mit Schluessel, Ergebnis als Datei im Plugin-Speicher; /index liefert nur aus.
 */
class IndexController extends Controller
{
    const PLUGIN     = 'OSVSuche';
    const FILE_KEY   = 'suchindex.json';
    const SALES_KEY  = 'verkauf.json';
    const FACET_KEY  = 'facetten.json';
    const MAX_IDS    = 24;
    const VERSION    = '0.7.1';

    /** @var array Kategoriepfade je ID, beim Neuaufbau gefuellt */
    private $katCache = [];
    const PAGE_SIZE  = 100;
    const MAX_PAGES  = 80; // Sicherung: hoechstens 8.000 Varianten

    public function index(Response $response)
    {
        /** @var StorageRepositoryContract $storage */
        $storage = pluginApp(StorageRepositoryContract::class);
        if (!$storage->doesObjectExist(self::PLUGIN, self::FILE_KEY)) {
            return $response->make('{"_meta":{"fehlt":true},"docs":[]}', 200, ['Content-Type' => 'application/json; charset=utf-8']);
        }
        $object = $storage->getObject(self::PLUGIN, self::FILE_KEY);
        return $response->make((string)$object->body, 200, [
            'Content-Type'  => 'application/json; charset=utf-8',
            'Cache-Control' => 'public, max-age=600',
        ]);
    }

    public function rebuild(Request $request, ConfigRepository $config)
    {
        if (!$this->tokenOk($request, $config)) {
            return ['ok' => false, 'fehler' => 'Schlüssel fehlt oder falsch'];
        }

        /** @var StorageRepositoryContract $storage */
        $storage = pluginApp(StorageRepositoryContract::class);
        $facetten = ['werte' => [], 'v' => []];
        if ($storage->doesObjectExist(self::PLUGIN, self::FACET_KEY)) {
            $facetten = json_decode((string)$storage->getObject(self::PLUGIN, self::FACET_KEY)->body, true) ?: $facetten;
        }
        $sales = [];
        if ($storage->doesObjectExist(self::PLUGIN, self::SALES_KEY)) {
            $sales = json_decode((string)$storage->getObject(self::PLUGIN, self::SALES_KEY)->body, true) ?: [];
        }

        /** @var ItemSearchService $searchService */
        $searchService = pluginApp(ItemSearchService::class);

        $start = microtime(true);
        $docs = [];
        $pageTimes = [];
        $total = 0;
        $page = 1;

        do {
            $t0 = microtime(true);
            $factory = VariationList::getSearchFactory(['sorting' => 'variation.id_asc']);
            $factory->setPage($page, self::PAGE_SIZE);
            $result = $searchService->getResult($factory);
            $total = (int)($result['total'] ?? 0);
            foreach (($result['documents'] ?? []) as $document) {
                $doc = $this->toDoc($document['data'] ?? []);
                $doc['kat'] = $this->katPfad((int)$doc['k']);
                $nr = (string)$doc['nr'];
                $doc['vk'] = isset($sales[$nr]) ? round((float)$sales[$nr], 1) : 0;
                $vid = (string)$doc['id'];
                $doc['fa'] = isset($facetten['v'][$vid]) ? $facetten['v'][$vid] : [];
                $docs[] = $doc;
            }
            $pageTimes[] = (int)round((microtime(true) - $t0) * 1000);
            $page++;
        } while (count($docs) < $total && $page <= self::MAX_PAGES && !empty($result['documents']));

        $meta = [
            'erzeugt'          => date('c'),
            'anzahl'           => count($docs),
            'gesamtLautPlenty' => $total,
            'seiten'           => count($pageTimes),
            'dauerMs'          => (int)round((microtime(true) - $start) * 1000),
            'seitenMs'         => $pageTimes,
            'mitVerkauf'       => $this->zaehleVerkauf($docs),
        ];
        $body = json_encode(['_meta' => $meta, '_cfg' => $this->regeln($config), '_fw' => (object)($facetten['werte'] ?? []), 'docs' => $docs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $storage->uploadObject(self::PLUGIN, self::FILE_KEY, $body);

        $meta['bytes'] = strlen($body);
        $meta['ok'] = true;
        return $meta;
    }

    /**
     * Aktuelle Preise und Verfuegbarkeit fuer die angezeigten Treffer.
     * Liefert nur die noetigen Felder, damit die Antwort klein bleibt.
     */
    public function preise(Request $request, Response $response)
    {
        $start = microtime(true);
        $ids = [];
        foreach (explode(',', (string)$request->get('ids', '')) as $teil) {
            $id = (int)trim($teil);
            if ($id > 0 && !in_array($id, $ids, true) && count($ids) < self::MAX_IDS) {
                $ids[] = $id;
            }
        }
        $out = [];
        if (count($ids)) {
            /** @var ItemSearchService $searchService */
            $searchService = pluginApp(ItemSearchService::class);
            $factory = VariationList::getSearchFactory([
                'variationIds' => $ids,
                'itemsPerPage' => count($ids),
            ]);
            $result = $searchService->getResult($factory);
            foreach (($result['documents'] ?? []) as $document) {
                $d = $document['data'] ?? [];
                $id = $d['variation']['id'] ?? 0;
                $out[$id] = [
                    'p'   => $d['prices']['default']['price']['formatted'] ?? '',
                    'uvp' => $d['prices']['rrp']['price']['formatted'] ?? '',
                    'gp'  => $d['prices']['default']['basePrice'] ?? '',
                    'av'  => $d['variation']['availability']['names']['name'] ?? '',
                    'avId'=> (int)($d['variation']['availabilityId'] ?? 0),
                    'ok'  => !empty($d['filter']['isSalable']),
                ];
            }
        }
        $body = json_encode(['dauerMs' => (int)round((microtime(true) - $start) * 1000), 'preise' => (object)$out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $response->make($body, 200, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    /**
     * Verkaufszahlen je Variantennummer speichern: {"11202/1": 206.0, ...}
     * Wirkt beim naechsten Neuaufbau des Index.
     */
    public function verkauf(Request $request, ConfigRepository $config)
    {
        if (!$this->tokenOk($request, $config)) {
            return ['ok' => false, 'fehler' => 'Schlüssel fehlt oder falsch'];
        }
        $data = json_decode((string)$request->getContent(), true);
        if (!is_array($data) || !count($data)) {
            return ['ok' => false, 'fehler' => 'Keine Daten im Body'];
        }
        $clean = [];
        foreach ($data as $nr => $wert) {
            // PHP macht aus rein numerischen Schluesseln ("12261") Zahlen, deshalb zurueck in Text
            $nr = (string)$nr;
            if ($nr !== '' && strlen($nr) <= 40 && is_numeric($wert)) {
                $clean[$nr] = (float)$wert;
            }
        }
        /** @var StorageRepositoryContract $storage */
        $storage = pluginApp(StorageRepositoryContract::class);
        $storage->uploadObject(self::PLUGIN, self::SALES_KEY, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return ['ok' => true, 'anzahl' => count($clean), 'gespeichert' => date('c')];
    }

    /**
     * Facetten-Zuordnung speichern: {"werte":{"16":["Farbe","rot",0,1],...},"v":{"1083":[16,49],...}}
     * Wird von aussen erzeugt (Shop-Schnittstelle), wirkt beim naechsten Neuaufbau.
     */
    public function facetten(Request $request, ConfigRepository $config)
    {
        if (!$this->tokenOk($request, $config)) {
            return ['ok' => false, 'fehler' => 'Schlüssel fehlt oder falsch'];
        }
        $data = json_decode((string)$request->getContent(), true);
        if (!is_array($data) || !isset($data['werte']) || !isset($data['v']) || !is_array($data['werte']) || !is_array($data['v'])) {
            return ['ok' => false, 'fehler' => 'Format: werte und v erwartet'];
        }
        $werte = [];
        foreach ($data['werte'] as $id => $w) {
            if (is_array($w) && count($w) >= 2) {
                $werte[(string)$id] = [(string)$w[0], (string)$w[1], (int)($w[2] ?? 0), (int)($w[3] ?? 0)];
            }
        }
        $v = [];
        foreach ($data['v'] as $vid => $ids) {
            if (is_array($ids)) {
                $liste = [];
                foreach ($ids as $id) {
                    $liste[] = (int)$id;
                }
                $v[(string)$vid] = $liste;
            }
        }
        /** @var StorageRepositoryContract $storage */
        $storage = pluginApp(StorageRepositoryContract::class);
        $storage->uploadObject(self::PLUGIN, self::FACET_KEY, json_encode(['werte' => (object)$werte, 'v' => (object)$v], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return ['ok' => true, 'werte' => count($werte), 'varianten' => count($v), 'gespeichert' => date('c')];
    }

    private function tokenOk(Request $request, ConfigRepository $config): bool
    {
        $token = trim((string)$config->get(self::PLUGIN . '.rebuildToken'));
        return $token !== '' && (string)$request->get('token', '') === $token;
    }

    /** Suchregeln aus der Plugin-Konfiguration, wandern mit dem Index in den Browser */
    private function regeln(ConfigRepository $config): array
    {
        $syn = [];
        foreach ($this->liste($config, 'synonyme') as $paar) {
            $teile = explode('=', $paar, 2);
            if (count($teile) === 2 && trim($teile[0]) !== '' && trim($teile[1]) !== '') {
                $syn[trim($teile[0])] = trim($teile[1]);
            }
        }
        return [
            'synonyme'         => (object)$syn,
            'eigenmarken'      => $this->liste($config, 'eigenmarken'),
            'eigenmarkenBonus' => (int)$config->get(self::PLUGIN . '.eigenmarkenBonus', 25),
            'verkaufsBonus'    => (int)$config->get(self::PLUGIN . '.verkaufsBonus', 80),
            'abwerten'         => $this->liste($config, 'abwerten'),
            'abwertFaktor'     => (int)$config->get(self::PLUGIN . '.abwertFaktor', 30),
            'herkunft'         => $this->liste($config, 'herkunft'),
            'toleranz'         => (int)$config->get(self::PLUGIN . '.toleranz', 2),
        ];
    }

    private function liste(ConfigRepository $config, string $key): array
    {
        $out = [];
        foreach (explode(';', (string)$config->get(self::PLUGIN . '.' . $key, '')) as $teil) {
            $teil = trim($teil);
            if ($teil !== '') {
                $out[] = $teil;
            }
        }
        return $out;
    }

    /** Kategoriepfad "Weihnachten » Schwibbogen", Webshop-Daten liefern nur die ID */
    private function katPfad(int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        if (array_key_exists($id, $this->katCache)) {
            return $this->katCache[$id];
        }
        $this->katCache[$id] = '';
        $name = '';
        $parent = 0;
        try {
            /** @var CategoryRepositoryContract $repo */
            $repo = pluginApp(CategoryRepositoryContract::class);
            $cat = $repo->get($id, 'de');
            $arr = $cat ? $cat->toArray() : [];
            $name = (string)($arr['details'][0]['name'] ?? '');
            $parent = (int)($arr['parentCategoryId'] ?? 0);
        } catch (\Exception $e) {
            $name = '';
        }
        $pfad = $parent > 0 && $parent !== $id ? $this->katPfad($parent) : '';
        $this->katCache[$id] = $pfad !== '' ? $pfad . ' » ' . $name : $name;
        return $this->katCache[$id];
    }

    private function zaehleVerkauf(array $docs): int
    {
        $n = 0;
        foreach ($docs as $doc) {
            if (($doc['vk'] ?? 0) > 0) {
                $n++;
            }
        }
        return $n;
    }

    private function toDoc(array $d): array
    {
        $itemId = $d['item']['id'] ?? 0;
        $variationId = $d['variation']['id'] ?? 0;
        $urlPath = $d['texts']['urlPath'] ?? '';

        $attributes = [];
        $merkmale = [];
        foreach (($d['attributes'] ?? []) as $attribute) {
            $value = $attribute['value']['names']['name'] ?? '';
            $name = $attribute['attribute']['names']['name'] ?? '';
            if ($value !== '') {
                $attributes[] = $value;
                if ($name !== '') {
                    $merkmale[] = [$name, $value];
                }
            }
        }

        $image = $d['images']['variation'][0]['urlPreview'] ?? ($d['images']['all'][0]['urlPreview'] ?? '');

        return [
            'id'  => $variationId,
            'i'   => $itemId,
            'n'   => $d['texts']['name1'] ?? '',
            'v'   => $d['variation']['name'] ?? '',
            'a'   => implode(' ', $attributes),
            'nr'  => $d['variation']['number'] ?? '',
            'h'   => $d['item']['manufacturer']['externalName'] ?? ($d['item']['manufacturer']['name'] ?? ''),
            'k'   => $d['defaultCategories'][0]['id'] ?? 0,
            'u'   => '/' . $urlPath . '_' . $itemId . '_' . $variationId . '/',
            'b'   => $image,
            'p'   => $d['prices']['default']['price']['formatted'] ?? '',
            'av'  => (int)($d['variation']['availabilityId'] ?? 0),
            'ok'  => !empty($d['filter']['isSalable']),
            'at'  => $merkmale,
        ];
    }

    /** Version des Plugins, damit die feste Vorlage im Shop immer das aktuelle Skript laedt */
    public function version(Response $response)
    {
        return $response->make('{"v":"' . self::VERSION . '"}', 200, ['Content-Type' => 'application/json']);
    }
}
