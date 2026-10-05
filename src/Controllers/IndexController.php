<?php

namespace OSVSuche\Controllers;

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
        $token = trim((string)$config->get(self::PLUGIN . '.rebuildToken'));
        if ($token === '' || $request->get('token', '') !== $token) {
            return ['ok' => false, 'fehler' => 'Schlüssel fehlt oder falsch'];
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
                $docs[] = $this->toDoc($document['data'] ?? []);
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
        ];
        $body = json_encode(['_meta' => $meta, 'docs' => $docs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        /** @var StorageRepositoryContract $storage */
        $storage = pluginApp(StorageRepositoryContract::class);
        $storage->uploadObject(self::PLUGIN, self::FILE_KEY, $body);

        $meta['bytes'] = strlen($body);
        $meta['ok'] = true;
        return $meta;
    }

    private function toDoc(array $d): array
    {
        $itemId = $d['item']['id'] ?? 0;
        $variationId = $d['variation']['id'] ?? 0;
        $urlPath = $d['texts']['urlPath'] ?? '';

        $attributes = [];
        foreach (($d['attributes'] ?? []) as $attribute) {
            $value = $attribute['value']['names']['name'] ?? '';
            if ($value !== '') {
                $attributes[] = $value;
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
            'm'   => !empty($d['variation']['isMain']),
            'ok'  => ($d['variation']['availability']['mappedAvailability'] ?? '') === 'https://schema.org/InStock',
        ];
    }
}
